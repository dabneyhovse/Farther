#! /usr/local/bin/python3.6

from socketIO_client import SocketIO
from time import time
from interval import *
import player

def on_status(status):
    print('status:', status)

pingTimes = []
pingSent = 0
def ping():
    global pingSent
    pingSent = time()
    socket.emit('cl_ping')
def pong(*args):
    global pingTimes
    latency = time() - pingSent;
    pingTimes.append(latency);
    pingTimes = pingTimes[-30:]
    avg = sum(pingTimes)/len(pingTimes)

    print("Average ping:", round(avg,1))
set_interval(ping, 10)

def on_play(req):
    print("Pley requested for {} at {}".format( req["video"], req["start"] ))
    player.play(req["video"], req["start"])

def on_pause(*args):
    print("Paused at {}".format(player.get_time()))
    socket.emit('paused', player.get_time())
    player.stop()

def on_skip(*args):
    print("Received skip request")
    player.stop()
    socket.emit("done")

def check_done(*args):
    if player.stop_if_done():
        socket.emit("done")
set_interval(check_done, 1)

# The socket.on('connect') and .on('reconnect') handlers didn't work
# so this wraps all server-signal-handling methods in code to make sure
# we know that we're connected
connected = False
def connect_augment(f):
    def callback(*args, **kwargs):
        global connected
        if not connected:
            print('connected to server')
            connected = True
        f(*args, **kwargs)
    return callback
def on_disconnect():
    global connected
    connected = False
    print('disconnected')

socket = SocketIO('dabney.caltech.edu', 27036)
socket.on('disconnect', on_disconnect)
socket.on('status', connect_augment(on_status))
socket.on('sv_pong', connect_augment(pong))
socket.on('play', connect_augment(on_play))
socket.on('pause', connect_augment(on_pause))
socket.on('skip', connect_augment(on_skip))

socket.wait()
