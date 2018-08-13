#! /usr/local/bin/python3.6

from socketIO_client import SocketIO
from time import time
from interval import *
import player

# import logging
# logging.getLogger('socketIO-client').setLevel(logging.DEBUG)
# logging.basicConfig()

def on_connect():
    print('connected to server')

def on_disconnect():
    print('disconnected')

def on_reconnect():
    print('reconnected')

def on_confirm_connect():
    print('server confirmed connection')

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

def check_done(*args):
    if player.stop_if_done():
        socket.emit("done")
set_interval(check_done, 5)

socket = SocketIO('dabney.caltech.edu', 27036)
socket.on('connect', on_connect)
socket.on('disconnect', on_disconnect)
socket.on('reconnect', on_reconnect)
socket.on('server_connect', on_confirm_connect)
socket.on('status', on_status)
socket.on('sv_pong', pong)
socket.on('play', on_play)
socket.on('pause', on_pause)
socket.on('skip', on_skip)

socket.wait()
