from interval import *
from time import time,sleep
import os, glob
from collections import namedtuple
from subprocess import Popen

STATUS_URL = "http://localhost:5000/status"

dl_statuses = {}
# id => True if saved, DownloadProgress tuple otherwise
DownloadProgress = namedtuple('DownloadProgress', 'start_time download_proc')
DOWNLOAD_TIMEOUT = 420 # blaze it

log_file = open("output.log", "w+")

current_song = None
current_song_start = None
player_proc = None

def download_song(id):
    print("Starting download of {}".format(id))

    # TODO: actually download
    proc = Popen(["./fake_downloader.sh", id], stdout=log_file, stderr=log_file)

    dl_statuses[id] = DownloadProgress(time(), proc)
def retry_download(id):
    print("Retrying download of {}".format(id))
    for filename in glob.glob("vids/{}*".format(id)):
        os.remove(filename)
    if isinstance(dl_statuses.get(id), DownloadProgress):
        dl_statuses[id].download_proc.kill()

    download_song(id)
def check_downloads():
    for id in dl_statuses:
        status = dl_statuses[id]
        if status is True:
            continue
        status.download_proc.poll()
        return_code = status.download_proc.returncode
        if return_code == 0:
            print("Completed download of {}".format(id))
            dl_statuses[id] = True # successfully downloaded
        elif (return_code is None and time() - status.start_time > DOWNLOAD_TIMEOUT) or (isinstance(return_code, int) and return_code > 0):
            retry_download(id)
            # taking too long or download failed (returned nonzero without being killed)

def is_song_ready(id):
    return (dl_statuses.get(id) is True)

def dl_queue():
    pass
set_interval(dl_queue, 5000)

def start(id, start_time=0):
    assert current_song is None
    current_song = id
    current_song_start = time() - start_time
    stopped_at = None

    command = ["./fake_player.sh", "vids/{}.mkv".format(id)]

    if start_time != 0:
        seconds = start_time
        hours = seconds // 3600
        seconds -= 3600 * hours
        minutes = seconds // 60
        seconds -= 60 * minutes
        timestamp = "{}:{}:{}".format(hours, minutes, seconds)

        command.insert(1, timestamp)
        command.insert(1, "--pos")
        # syntax: ./fake_player.sh --pos {timestamp} vids/{id}.mkv

    player_proc = Popen(command, stdout=log_file, stderr=log_file)

def stop():
    player_proc.kill()
    player_proc = None
    current_song = None
    stopped_at = get_time()

def stop_if_done():
    if player_proc:
        player_proc.poll()
        if player_proc.returncode is not None:
            stop()
set_interval(stop_if_done, 1000)

def get_time():
    return stopped_at or (time() - current_song_start)
    # references stopped_at when it is defined (between songs)
