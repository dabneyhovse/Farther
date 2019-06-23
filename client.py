#! /usr/local/bin/python3.6

from socketIO_client import SocketIO
from time import time
from interval import *
import player
import logging

logging.basicConfig(filename="/home/pi/farther.log", filemode='a',
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO)
logging.info("Farther client started")

player.Player.set_volume(1) # volume control is on amp itself

# The socket.on('connect') and .on('reconnect') handlers didn't work
# so this wraps all server-signal-handling methods in code to make sure
# we know that we're connected
connected = False
def indicates_connection(f):
    def _decorator(*args, **kwargs):
        global connected
        if not connected:
            connected = True
        return f(*args, **kwargs)
    return _decorator

@indicates_connection
def on_status(status):
    logging.debug('status JSON: %s', status)

@indicates_connection
def on_play(req):
    logging.info("Play requested for {} at {}".format( req["video"], req["start"] ))
    player.Player(req["video"], start_time=req["start"], done_callback=emit_done)

@indicates_connection
def on_pause(*args):
    if player.Player.status == player.PlayerStatus.PLAYING:
        t = player.Player.current_player.get_time()
    else:
        t = 0
    logging.info("Paused at {}".format(t))
    socket.emit('paused', t)
    player.Player.stop_current()

@indicates_connection
def on_skip(*args):
    logging.info("Received skip request")
    player.Player.stop_current()

def emit_done():
    socket.emit("done")

def on_disconnect():
    global connected
    connected = False
    logging.info('disconnected')

socket = SocketIO('dabney.caltech.edu', 27036)
socket.on('disconnect', on_disconnect)
socket.on('status', on_status)
socket.on('play', on_play)
socket.on('pause', on_pause)
socket.on('skip', on_skip)

logging.info("Handlers set up, SocketIO is now polling...")
socket.wait()
