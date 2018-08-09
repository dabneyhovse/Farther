from interval import *
from time import time,sleep
import os, glob
from collections import namedtuple

STATUS_URL = "http://localhost:5000/status"

dl_status = {}
# id => True if saved, False if failed
DownloadProgress = namedtuple('DownloadProgress', 'start_time pid')

current_song = None
current_song_start = None
player_pid = None

def download_song(id):
    # TODO: actually download

    dl_status[id] = DownloadProgress(time(), None) # TODO get a PID somehow? and still set to True only when exiting successfully
    sleep(15)
    with open("vids/{}.mkv", "w") as f:
        f.write(id)
    dl_status[id] = True # False if fail

def retry_download(id):
    for filename in glob.glob("vids/{}*".format(id)):
        os.remove(filename)
    download_song(id)

def check_song(id):
    status = dl_status.get(id)

    if status is True:
        return True # no action necessary, song is ready

    if not status:
        retry_download(id)
    if time() - status[0] > 69: # nice
        # TODO kill current thread
        retry_download(id)
    return False

def get_queue():
    pass

def play_song(id, start_time=0):
    assert current_song is None
    current_song = id
    current_song_start = time() - start_time
    print("playing {} (starting at {})".format(id, start_time))
    sleep(60)
    current_song = None

def get_time():
    if current_song_start:
        return time() - current_song_start

# placeholders
def start(vid, time):
    print('Now "playing" {} (starting at {})'.format(vid, time))
def stop(vid):
    print('Player stopped')
