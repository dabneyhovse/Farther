#! /bin/sh

cd /home/pi/farther-client
omxplayer -o both startup.mp3
sudo -u pi python3 client.py
