#! /bin/sh

cd /home/pi/farther-client
python3.6 clean_vids.py > farther.log
python3.6 client.py > farther.log 2>&1
