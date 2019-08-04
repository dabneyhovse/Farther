import time, threading

# source: https://stackoverflow.com/questions/2697039/python-equivalent-of-setinterval/48709380#48709380
class SetInterval:
    def __init__(self,action,interval,daemon=True,wait=True) :
        self.interval=interval
        self.action=action
        self.daemon=daemon
        self.stopEvent=threading.Event()
        self.restart(wait=wait)

    def __setInterval(self) :
        nextTime=time.time()+self.interval
        while not self.stopEvent.wait(nextTime-time.time()) :
            nextTime+=self.interval
            self.action()

    def cancel(self) :
        self.stopEvent.set()

    def restart(self, wait=True):
        if not wait:
            self.action()
        thread=threading.Thread(target=self.__setInterval)
        self.stopEvent.clear()
        thread.setDaemon(self.daemon)
        thread.start()
