#! /bin/sh

cd /home/pi/farther-client
rm -f vids/*.part
# TODO: check if disk is almost full and run a "keep only queued" script

python3.6 client.py > farther.log 2>&1
