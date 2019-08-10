#! /usr/bin/python36

from queue import Queue
from flask import Flask, request, abort
from flask_socketio import SocketIO, emit
from functools import wraps
from threading import Lock
import json
import re
import os
from datetime import datetime

app = Flask(__name__)
app.config['SECRET_KEY'] = 'secret!'
socketio = SocketIO(app, async_mode=None)

# Localhost Only
def local_only(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if not request.remote_addr == '127.0.0.1':
            abort(403) # Forbidden
        return f(*args, **kwargs)
    return decorated

OUT_FILE_ROOT = "/Users/Nicholas/Desktop/wtf/"

# Play Queue State Variables
queue = Queue()
try:
    qd = open(OUT_FILE_ROOT + 'queuedump.txt', 'r')
    for song in qd.readlines():
        queue.put(song.rstrip())
    os.remove(OUT_FILE_ROOT + 'queuedump.txt')
except:
    pass

history = Queue()
histlog = open(OUT_FILE_ROOT + 'history.log', 'a')

playing = None
if not queue.empty():
    playing = queue.get()
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

    if running and playing:
        socketio.emit('status', getStatus())
        emitPlay()

        return True
    elif running and not playing and not queue.empty():
        playing = queue.get(True)
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
        data = dict({ 'video': playing['vid'], 'start': playtime })
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
        'history': list(history.queue),
        'current': playing,
        'status': status
    })

@app.route('/status')
def queueStatus():
    status = getStatus()
    status.update({'client_connected': client_connected})
    return json.dumps(status)

def getAction(action_name, user, note):
    return {
        'action': action_name,
        # TODO what should be anonymous and what shouldn't?
        'user': user if action_name == "Enqueued" else None,
        'note': note,
        'time': datetime.now().strftime('%a, %b %d %Y %H:%M:%S')
    }

@app.route('/add')
@local_only
def addToQueue():
    vid = request.args.get('vid', '')
    user = request.args.get('user', '')
    note = request.args.get('note', '')
    if vid == '':
        abort(400)
    elif user == '':
        abort(401)
    else:
        data = {
            'vid': vid,
            'actions': [getAction("Enqueued", user, note)]
        }
        print({'action': 'play', **data}, file=histlog)
        queue.put(data, True)
        if playing == None:
            playNext()
        return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@app.route('/pause')
@local_only
def pauseQueue():
    print('Pause requested.')
    if playing:
        user = request.args.get('user', '')
        note = request.args.get('note', '')
        playing["actions"].append(getAction("Paused", user, note))

    global running
    running = False
    socketio.emit('pause')

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
@local_only
def resumeQueue():
    global running
    print('Resume requested.')
    if playing:
        user = request.args.get('user', '')
        note = request.args.get('note', '')
        playing["actions"].append(getAction("Resumed", user, note))

    if not running:
        running = True
        playNext()
    socketio.emit('status', getStatus())

    return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@app.route('/skip')
@local_only
def skip():
    print('Skip requested.')
    if playing:
        user = request.args.get('user', '')
        note = request.args.get('note', '')
        playing["actions"].append(getAction("Skipped", user, note))

    socketio.emit('skip')
    return json.dumps({ "message": "Success!", "queue": list(queue.queue)})

@socketio.on('done')
def done():
    print('Client done playing.')
    if running:
        global playing
        history.put(playing)
        if history.qsize() > 20:
            history.get()
        playing = None
    playNext()
    socketio.emit('status', getStatus())

@socketio.on('cl_ping')
def pong():
    emit('sv_pong')

@socketio.on('connect_event')
def connection(msg):
    print('Client response:', msg)

@socketio.on('connect')
def connect():
    # TODO require connections from client ip
    #print('Client connected.')
    global client_connected
    client_connected = True

    emit('server_connect')
    emit('status', getStatus())

    if playing or not queue.empty():
        playNext()

@socketio.on('disconnect')
def disconnect():
    #print('Client disconnected.')
    global client_connected
    client_connected = False

if __name__ == '__main__':
    socketio.run(app, host='0.0.0.0', port=27036)
