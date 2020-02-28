""" create event in ics format (vcal), print as string an db64 """
import datetime
import base64
import urllib.request
import json
import pystache

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

################################
    
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

# insert base64 later
href = "<a href=\"data:text/calendar;base64,{{{ics}}}\">ICS</a>"

# get the original json
url = "https://raw.githubusercontent.com/CodeforKarlsruhe/labSchedule/master/karlsruhe.json"

try:
    req = urllib.request.Request(url)
    with urllib.request.urlopen(req) as response:
       data = response.read()
except urllib.error.HTTPError as err:
    if err.code == 404 or err.code == 500 :
        print("URL not found: ",url)
        sys.exit(0)
    else:
        raise
        sys.exit(0)

data = json.loads(data)
for e in data["de"]:
    if not "ics" in e:
        event = Event()
        event.add("summary",e["title"])
        start = e["date"]
        try:
            s = datetime.datetime.strptime(start, "%d.%m.%Y %H:%M")
        except ValueError:
            start += " 19:00"
            pass
        event.add('start',start) #e["date"])
        #event.add('end', end)
        event.add('location', "Karlsruhe Digital Lab")
        context = {"ics":base64.b64encode(event.get().encode()).decode("utf-8")}
        link = pystache.render(href,context)
        print(link)

