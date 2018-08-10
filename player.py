from interval import *
from time import time,sleep
import os, glob
from collections import namedtuple
from subprocess import Popen
import urllib.request
import json

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
    proc = Popen(["./fake_downloader.sh", id])

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
set_interval(check_downloads, 5)

def dl_queue():
    f = urllib.request.urlopen(STATUS_URL)
    data = json.load(f)
    to_download = set(data["queue"])
    to_download.add(data["current"])
    for id in to_download:
        if not dl_statuses.get(id): # if status is true or DownloadProgress tuple, no action required
            download_song(id)
set_interval(dl_queue, 5)

def play(id, start_time=0):
    if dl_statuses.get(id) is None:
        download_song(id)
    while dl_statuses.get(id) is not True:
        continue # wait for song to download

    assert current_song is None
    current_song = id
    current_song_start = time() - start_time

    command = ["./fake_player.sh", "vids/{}.mkv".format(id)]

    # if start_time != 0:
    #     seconds = start_time
    #     hours = seconds // 3600
    #     seconds -= 3600 * hours
    #     minutes = seconds // 60
    #     seconds -= 60 * minutes
    #     timestamp = "{}:{}:{}".format(hours, minutes, seconds)
    #
    #     command.insert(1, timestamp)
    #     command.insert(1, "--pos")
        # syntax: ./fake_player.sh --pos {timestamp} vids/{id}.mkv

    player_proc = Popen(command)

def stop():
    player_proc.kill()
    player_proc = None
    current_song = None

def stop_if_done():
    if player_proc:
        player_proc.poll()
        if player_proc.returncode is not None:
            stop()
set_interval(stop_if_done, 1)

def get_time():
    return time() - current_song_start
