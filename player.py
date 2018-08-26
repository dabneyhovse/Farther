import pafy
from omxplayer.player import OMXPlayer

VIDEO = False # TODO: auto-detect if an HDMI is plugged in

player = None
stop_time = 0

def play(id, start_time=0):
    print("play requested for {}, starting at {}".format(id, start_time))

    video = pafy.new("https://youtube.com/watch?v=" + id)
    if VIDEO:
    	target = video.getbest()
    else:
    	target = video.getbestaudio()

    args = ["-o", "both"] if VIDEO else ["-o", "local"]

    global player
    if player is not None:
        player = OMXPlayer(target.url, args=args)

def stop():
    global player
    global stop_time

    if player is not None:
        try:
            stop_time = player.position()
        except omxplayer.player.OMXPlayerDeadError:
            stop_time = 0
        
        player.quit()
        player = None

def stop_if_done():
    if player is None or player._process is None:
        return False
    elif player._process.poll() is not None:
        # TODO: this seems to be the how OMXPlayer internally detects whether a
        # player is done, but a try-catech may work better
        stop()
        return True
    return False

def get_time():
    if player is None:
        return stop_time
    else:
        return player.position()
