import pafy
import youtube_dl
from omxplayer.player import OMXPlayer, OMXPlayerDeadError
from interval import *
import threading
import requests
import time
import datetime
import math
import logging
import enum

STATUS_URL = "http://dabney.caltech.edu:27036/status"
DOWNLOAD_DIR = "/home/pi/farther_downloads/"

# helper functions for volume, time
def linear_to_mbels(val):
    return 2000 * math.log(val, 10)
def get_timestamp(seconds):
    hours = seconds // 3600
    seconds -= 3600 * hours
    minutes = seconds // 60
    seconds -= 60 * minutes
    return "{:02}:{:02}:{:02}".format(hours, minutes, seconds)

class PlayerStatus(enum.Enum):
    """
    Enum for possible player states. Note that pauses are not represented:
    the player is simply re-created at the proper time when resuming
    """
    STOPPED = 0
    LOADING_DATA = 1
    DOWNLOADING = 2
    PLAYING = 3
class Player:
    current_player = None # avoid overlapping songs
    status = PlayerStatus.STOPPED

    def __init__(self, id, start_time=0, done_callback=None):
        """
        make a Player (and start playing it)
        """
        Player.status = PlayerStatus.LOADING_DATA

        self.start_time = start_time
        self.done = done_callback
        self.vid_data = VideoData(id, ready_callback=self.play)

        if self.vid_data.unavailable:
            logging.info("{} seems to be unavailable".format(id))
            done_callback()
        elif not self.vid_data.streamable:
            Player.status = PlayerStatus.DOWNLOADING
            # TODO race condition: player could be set to "downloading"
            # after it's already downloaded and playing

    def play(self):
        t = threading.Timer(1.0, self.__play)
        # TODO sketchy solution to the fact that the VideoData can't be accessed
        # by its callback until returns and sets the relevant player variables
        t.start()

    def __play(self):
        logging.info("Starting OMXPlayer for {} at {}".format(self.vid_data.id, self.start_time))

        args = ["-o", "local"]
        if self.start_time != 0:
            args += ["--pos", get_timestamp(self.start_time)]
        if Player.current_volume != 1 and Player.current_volume != 0:
            args += ["--vol", str(linear_to_mbels(Player.current_volume))]

        if Player.current_player is not None:
            Player.current_player.stop()
        Player.current_player = self

        Player.status = PlayerStatus.PLAYING
        self.omx = OMXPlayer(self.vid_data.url, args=args)
        self.omx.exitEvent += lambda p, code: self.stop()

        if self.done:
            self.omx.exitEvent += lambda p, code: self.done()

        self.start_timestamp = time.time() - self.start_time

        if Player.current_volume == 0:
            self.omx.mute()

        logging.info("OMXPlayer for {} has started".format(self.vid_data.id))

    def stop(self):
        if Player.status == PlayerStatus.PLAYING:
            self.omx.quit() # mark player as dead before we block on quitting it
        elif Player.status in (PlayerStatus.DOWNLOADING, PlayerStatus.LOADING_DATA):
            self.vid_data.remove_ready_callback()

        Player.current_player = None
        Player.status = PlayerStatus.STOPPED

    @classmethod
    def stop_current(self):
        if Player.current_player:
            Player.current_player.stop()

    def get_time(self):
        if Player.status == PlayerStatus.PLAYING:
            return time.time() - self.start_timestamp
        else:
            return 0
        # TODO: using player.position() seems cleaner but resulted in resumes
        # ~10 seconds off from the pauses

    current_volume = None
    @classmethod
    def set_volume(cls, vol):
        if Player.current_volume == None or vol != Player.current_volume:
            Player.current_volume = vol

        if Player.current_player and Player.current_player.status == PlayerStatus.PLAYING:
            if vol == 0:
                Player.current_player.omx.mute()
            else:
                Player.current_player.omx.unmute()
                Player.current_player.omx.set_volume(vol)

class YoutubeDownloader(threading.Thread):
    def __init__(self, id, callback):
       threading.Thread.__init__(self)
       self.setDaemon(True)
       self.id = id
       self.callback = callback
    def run(self):
        youtube_dl.YoutubeDL(params={
            "format": "worstaudio",
            "outtmpl": DOWNLOAD_DIR + "%(id)s.%(ext)s",
            "progress_hooks": [self.on_download_progress],
            "quiet": True}).download([self.id])

    def on_download_progress(self, params):
        if params["status"] == "finished":
            self.callback(params["filename"])

class VideoData:
    cache = {}
    data_loading_lock = threading.Lock()

    def load_data(self, id):
        VideoData.data_loading_lock.acquire()

        logging.info("refreshing video data for {}".format(id))
        self.id = id
        try:
            video = pafy.new(id) # TODO experiement with other formats to guarantee streaming works

            self.title = video.title
            self.duration = video.duration
            self.thumbnail = video.bigthumb
            self.last_updated = datetime.datetime.now()
            self.unavailable = False

            streamable = list(filter(lambda s: s.extension == "webm", video.audiostreams))
            if len(streamable) > 0 and self.duration[:4] != "00:0": # sketchy way of checking if >= 10 mins
                self.url = streamable[0].url
                self.streamable = True
                self.ready_callback()
            else:
                self.streamable = False
                self.downloaded = False # TODO check if already downloaded? or does YouTubeDL do that?
                dl_thread = YoutubeDownloader(self.id, self.download_callback)
                dl_thread.start()

        except (OSError, ValueError) as e:
            self.unavailable = True
            logging.info("%s unavailable because of error: %s", id, str(e))
            # indicates that video is UNAVAILABLE (premium only, copyright blocked, etc)

        VideoData.cache[id] = self

        VideoData.data_loading_lock.release()

    def download_callback(self, url):
        self.url = url
        self.downloaded = True
        self.ready_callback()

    def set_ready_callback(self, new_callback):
        if new_callback is None:
            new_callback = lambda: None

        if self.streamable or self.downloaded:
            new_callback()
        self.ready_callback = new_callback

    def remove_ready_callback(self):
        self.set_ready_callback(lambda: None)

    @classmethod
    def cache_valid(cls, id):
        return \
            (id in VideoData.cache) and \
            (not VideoData.cache[id].unavailable) and \
            (datetime.datetime.now() - VideoData.cache[id].last_updated < datetime.timedelta(hours=6))

    # reduce between-song latency by loading the player URL or downloading the video ahead of time
    @classmethod
    def prep_queue(cls):
        f = requests.get(STATUS_URL)
        data = f.json()
        to_download = { item["vid"] for item in data["queue"] }

        for id in set(to_download):
            VideoData(id)

    def __init__(self, id, ready_callback=None):
        self.streamable = False
        self.downloaded = False

        if VideoData.cache_valid(id):
            self.__dict__.update(VideoData.cache[id].__dict__)
            self.set_ready_callback(ready_callback)
            # copy from cached vid
        else:
            self.set_ready_callback(ready_callback)
            self.load_data(id)



queue_loader = SetInterval(VideoData.prep_queue, 30)
