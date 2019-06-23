#! /usr/local/bin/python3.6

from socketIO_client import SocketIO
from time import time
from interval import *
import player

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
    print('status:', status)

@indicates_connection
def on_play(req):
    print("Play requested for {} at {}".format( req["video"], req["start"] ))
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
    print('disconnected')

socket = SocketIO('dabney.caltech.edu', 27036)
socket.on('disconnect', on_disconnect)
socket.on('status', on_status)
socket.on('play', on_play)
socket.on('pause', on_pause)
socket.on('skip', on_skip)

socket.wait()
