#! /bin/sh

cd /home/pi/farther-client
python3.6 clean_vids.py >> farther.log
omxplayer -o both startup.mp3
python3.6 client.py >> farther.log 2>&1
