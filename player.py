from interval import *
from time import time,sleep
import os, glob
from collections import namedtuple
from subprocess import Popen
import urllib.request
import json

STATUS_URL = "http://dabney.caltech.edu:27036/status"

VIDEO = False # TODO: auto-detect if an HDMI is plugged in

dl_statuses = {}
for filename in glob.glob("vids/*"):
    if (VIDEO and filename.endswith("m4a")): # audio files aren't any good in video mode
        continue
    elif filename.endswith("part"): # only partially downloaded
        continue
    else:
        dl_statuses[filename[5:filename.find(".")]] = filename[filename.find(".")+1:]
    # if a file exists already, it's a safe bet that it's downloaded
print(dl_statuses)

# id => file extension if saved, DownloadProgress tuple if download in progress
DownloadProgress = namedtuple('DownloadProgress', 'start_time download_proc')
DOWNLOAD_TIMEOUT = 69 # nice

#log_file = open("output.log", "w+")
# TODO: log youtube-dl and omxplayer separately

current_song = None
current_song_start = None
player_proc = None

def download_song(id):
    print("Starting download of {}".format(id))

    if VIDEO:
        proc = Popen(["youtube-dl", "https://youtube.com/watch?v=" + id, "-o", "vids/" + id, "-f", "worstvideo[ext=mp4]+bestaudio[ext=m4a]/mp4" ])
    else:
        proc = Popen(["youtube-dl", "https://youtube.com/watch?v=" + id, "-o", "vids/" + id, "-f", "bestaudio[ext=m4a]" ])

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
        if isinstance(status, str):
            continue
        status.download_proc.poll()
        return_code = status.download_proc.returncode
        downloaded_files = glob.glob("vids/{}.*".format(id))
        if return_code == 0 and len(downloaded_files) > 0:
            print("Completed download of {}".format(id))
            dl_statuses[id] = downloaded_files[0][downloaded_files[0].find(".")+1:] # successfully downloaded, get format
        elif (return_code is None and time() - status.start_time > DOWNLOAD_TIMEOUT) or (isinstance(return_code, int) and return_code > 0):
            retry_download(id)
            # TODO better recovery from issues here: don't just keep trying to play a bad format, etc.
            # taking too long or download failed (returned nonzero without being killed)
set_interval(check_downloads, 5)

def dl_queue():
    f = urllib.request.urlopen(STATUS_URL)
    data = json.load(f)
    to_download = set(data["queue"])
    if data.get("current") is not None:
        to_download.add(data["current"])
    for id in to_download:
        if not dl_statuses.get(id): # if status is a format or DownloadProgress tuple, no action required
            download_song(id)
set_interval(dl_queue, 10)

def play(id, start_time=0):
    print("play requested for {}, starting at {}, {}".format(id, start_time, dl_statuses.get(id)))
    while not isinstance(dl_statuses.get(id), str):
        sleep(1) # wait for song to download by the queue checker

    global current_song
    global current_song_start
    global player_proc

    if current_song is not None:
        return # another thread beat us to playing; avoid overlap
    current_song = id
    vid_format = dl_statuses[id]
    current_song_start = time() - start_time

    command = ["omxplayer", "-o", ("both" if VIDEO else "local"), "vids/{}.{}".format(id, vid_format)]

    if start_time != 0:
        seconds = start_time
        hours = seconds // 3600
        seconds -= 3600 * hours
        minutes = seconds // 60
        seconds -= 60 * minutes
        timestamp = "{}:{}:{}".format(hours, minutes, seconds)

        command.insert(1, timestamp)
        command.insert(1, "--pos")

    player_proc = Popen(command)

def stop():
    global current_song
    global player_proc

    if player_proc:
        Popen(["killall", "omxplayer.bin"])
    player_proc = None
    current_song = None

def stop_if_done():
    global player_proc

    if player_proc:
        player_proc.poll()
        if player_proc.returncode is not None:
            stop()
            return True
    return False

def get_time():
    global current_song
    global current_song_start

    if current_song:
        return time() - current_song_start
    else:
        return 0
