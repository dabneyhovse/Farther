#! /usr/local/bin/python3.6

import os, glob
import urllib.request
import json

STATUS_URL = "http://dabney.caltech.edu:27036/status"

f = urllib.request.urlopen(STATUS_URL)
data = json.load(f)
should_have = set(data["queue"])
if data.get("current") is not None:
    should_have.add(data["current"])

do_have = glob.glob("vids/*")

for fname in do_have:
    # if we don't find a reason to have this file, delete it
    if all([ fname.find(id) == -1 for id in should_have ]):
        os.remove(fname)
