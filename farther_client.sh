#! /bin/sh

cd /home/pi/farther-client
omxplayer -o both startup.mp3
python3 client.py >> farther.log 2>&1
