from socketIO_client import SocketIO
from time import time
from interval import *

socket = SocketIO('localhost', 5000)

def on_connect():
    print('connected to server')
socket.on('connect', on_connect)

def on_disconnect():
    print('disconnected')
socket.on('disconnect', on_disconnect)

def on_reconnect():
    print('reconnected')
socket.on('reconnect', on_reconnect)

def on_confirm_connect():
    print('server confirmed connection')
socket.on('server_connect', on_confirm_connect)

def on_status(status):
    print('status:', status)
socket.on('status', on_status)

pingTimes = []
pingSent = 0
def ping():
    pingSent = time()
    socket.emit('cl_ping')
def pong():
    latency = time() - pingSent;
    pingTimes.append(latency);
    pingTimes = pingTimes[-30:]
    avg = sum(pingTimes)/len(pingTimes)

    print("Average ping:", round(avg,1))
socket.on('sv_pong', pong)
set_interval(ping, 10)

def on_play(req):
    player.play(req.video, req.start)
socket.on('play', on_play)

def on_pause():
    socket.emit('paused', player.get_time())
    player.stop()
socket.on('pause', on_pause)

def on_skip():
    player.stop()
socket.on('skip', on_skip)


# TODO
# when you lose connection:
#   reconnection: true,
#  reconnectionDelay: 500,
#  reconnectionAttempts: 10,
#  socket.disconnect();
# player.stop();
# socket.connect();
# on exit
  # // Emitted when the window is closed.
  # mainWindow.on('closed', () => {
  #   // Stop pinging boi.
  #   clearInterval(pingInterval);
  #
  #   // Stop the player and close the socket.
  #   closed = true;
  #   player.stop();
  #   socket.disconnect();
  #
  #   // Dereference window object.
  #   mainWindow = null;
  #
  #   // Quit the app.
  #   app.quit();
