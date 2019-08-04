# Farther
Dabney House music- and video-playing queue

It's like [Nearer](https://github.com/ejaszewski/nearer-client), but worse

Farther (like Nearer) has three pieces, all of which are included in this repository:

 - A client, which is a Python script designed to run on a Raspberry Pi
 - A PHP interface for users to submit songs and view the queue
 - A Python socket server, which keeps track of the queue, communicating with
   both the PHP interface. Should be run on port 27036 of the same server as the
   PHP interface.
