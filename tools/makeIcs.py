""" create event in ics format (vcal), print as string an db64 """
import datetime
import base64

# see also https://www.kanzaki.com/docs/ical/dateTime.html

class Event():
    def __init__(self):
        self._createWrapper()

    def _createWrapper(self):
        self.head = ""
        self.foot = ""
        self.event = ""
        self.head += u"BEGIN:VCALENDAR\n"
        self.head += u"VERSION:2.0\n"
        self.head += u"CALSCALE:GREGORIAN\n"
        self.head += u"PRODID:OK Lab Karlsruhe\n"
        self.head += u"BEGIN:VEVENT\n"

        self.foot += u"END:VEVENT\n"
        self.foot += u"END:VCALENDAR\n"

#;TZID=America/New_York:20200228T200000

    def add(self,a,b):
        print("a:",a,", b:",b)
        swin = {
        0: "summary",
        1: "description",
        2: "start",
        3: "end",
        4: "location"
        }
        swout = [u"SUMMARY:",
                 u"DESCRIPTION:",
                 u"DTSTART;TZID=Europe/Berlin:",
                 u"DTEND;TZID=Europe/Berlin:",
                 u"LOCATION:"]
        try:
            self.event += swout[list(swin.values()).index(a)]
            if a == swin[2] or a == swin[3]:
                d = datetime.datetime.strptime(b, "%d.%m.%Y %H:%M")
                self.event += d.isoformat(timespec='seconds').replace("-","").replace(":","")
            else:
                self.event += b
            self.event += u"\n"
            
        except ValueError:
            print("Invalid item: ",a)
            raise
            
    def get(self):
        return self.head + self.event + self.foot
        
event = Event()

event.add("summary","Lab Meeting")
start = "02.03.2020 13:15"
end = "02.03.2020 15:30"
event.add('start',start)
event.add('end', end)
event.add('description', "Very nice socializing event")
event.add('location', "Karlsruhe Digital Lab")

print(event.get())
print(base64.b64encode(event.get().encode()).decode("utf-8"))


