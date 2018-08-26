import pafy
from omxplayer.player import OMXPlayer, OMXPlayerDeadError

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
    if start_time != 0:
        seconds = start_time
        hours = seconds // 3600
        seconds -= 3600 * hours
        minutes = seconds // 60
        seconds -= 60 * minutes
        timestamp = "{}:{}:{}".format(hours, minutes, seconds)
        args += ["--pos", timestamp]

    global player
    if player is None:
        player = OMXPlayer(target.url, args=args)

def stop():
    global player
    global stop_time

    if player is not None:
        try:
            stop_time = player.position()
        except OMXPlayerDeadError:
            stop_time = 0

        tmp = player
        player = None
        tmp.quit()

def stop_if_done():
    if player is None:
        return False
    elif player._process is None or player._process.poll() is not None:
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
