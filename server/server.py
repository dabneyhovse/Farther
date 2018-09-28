#! /usr/bin/python36

from queue import Queue
from flask import Flask, request, abort
from flask_socketio import SocketIO, emit
from functools import wraps
from threading import Lock
import json
import re

app = Flask(__name__)
app.config['SECRET_KEY'] = 'secret!'
socketio = SocketIO(app, async_mode=None)

# Localhost Only
def check_auth():
    return True

def local_only(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if not request.remote_addr == '127.0.0.1':
            abort(403) # Forbidden
        return f(*args, **kwargs)
    return decorated

# Play Queue State Variables
queue = Queue()
playing = None
running = True
playtime = 0
client_playing = False
client_connected = False

thread = None
thread_lock = Lock()

# IP limiting regexes
caltech = re.compile('131.215.[0-9]{1,3}.[0-9]{1,3}')

@app.before_request
def limit_remote_addr():
    if caltech.fullmatch(request.remote_addr) == None and \
       request.remote_addr != '127.0.0.1':
        abort(403)  # Forbidden
        print(request.remote_addr)

def playNext():
    global thread
    global playing
    global playtime

    if running and not queue.empty():
        playing = queue.get(True)
        print(playing)
        playtime = 0

        socketio.emit('status', getStatus())
        emitPlay()

        return True
    else:
        socketio.emit('status', getStatus())
        return False

def emitPlay(data=None):
    if data == None:
        global playing
        global playtime
        data = dict({ 'video': playing, 'start': playtime })
    if data['video'] != None:
        print('Emitting play request:', data)
        socketio.emit('play', data)

def getStatus():
    status = ''
    if running and playing:
        status = 'Playing'
    elif running:
        status = 'Queue Empty'
    else:
        status = 'Paused'

    return dict({
        'queue': list(queue.queue),
        'current': playing,
        'status': status
    })

@app.route('/status')
def queueStatus():
    status = getStatus()
    status.update({'client_connected': client_connected})
    return json.dumps(status)

@app.route('/add')
@local_only
def addToQueue():
    vid = request.args.get('vid', '')
    if vid == '':
        abort(400)
    else:
        queue.put(vid, True)
        if queue.qsize() == 1 and playing == None:
            playNext()
        return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@app.route('/pause')
def pauseQueue():
    global running
    print('Pause requested.')
    running = False;

    socketio.emit('pause');

    return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@socketio.on('paused')
def paused(timestamp):
    global playtime
    try:
        playtime = int(timestamp)
    except (ValueError, TypeError):
        playtime = 0
        print('Invalid timestamp', timestamp)
    socketio.emit('status', getStatus())

@app.route('/resume')
def resumeQueue():
    global running
    print('Resume requested.')
    if not running:
        running = True
        emitPlay()
    socketio.emit('status', getStatus())

    return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@app.route('/skip')
def skip():
    print('Skip requested.')
    socketio.emit('skip')
    return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@socketio.on('done')
def done():
    print('Client done playing.')
    if running:
        global playing
        playing = None
    socketio.emit('status', getStatus())
    playNext()

@socketio.on('cl_ping')
def pong():
    emit('sv_pong')

@socketio.on('connect_event')
def connection(msg):
    print('Client response:', msg)

@socketio.on('connect')
def connect():
    print('Client connected.')
    global client_connected
    client_connected = True

    emit('server_connect')
    emit('status', getStatus())

    if playing or not queue.empty():
        emitPlay()

@socketio.on('disconnect')
def disconnect():
    print('Client disconnected.')
    global client_connected
    client_connected = False

if __name__ == '__main__':
    socketio.run(app, host='0.0.0.0', port=27036)
