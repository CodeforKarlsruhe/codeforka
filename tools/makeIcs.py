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

    def add(self,a,b):
        #print("a:",a,", b:",b)
        # map plaintext description to VCAL items
        swin = ("summary",
                "description",
                "start", #dd.mm.yyyy
                "end", #dd.mm.yyyy
                "duration",  #hh:mm
                "location"
        )
        swout = [u"SUMMARY:",
                 u"DESCRIPTION:",
                 u"DTSTART;TZID=Europe/Berlin:",
                 u"DTEND;TZID=Europe/Berlin:",
                 u"DURATION:",  # as period, like PT2H0M0S for 2 hours
                 u"LOCATION:"]
        try:
            item = swin.index(a) # throws on wrong items
            self.event += swout[item] # add property
            if a == "start" or a == "end":
                d = datetime.datetime.strptime(b, "%d.%m.%Y %H:%M")
                self.event += d.isoformat(timespec='seconds').replace("-","").replace(":","")
            elif a == "duration":
                self.event += "PT" + b.split(":")[0] + "H" + b.split(":")[1] + "M00S"
            else:
                self.event += b
            self.event += u"\n"
            
        except ValueError:
            print("Invalid date item: ",a)
            raise
            
    def get(self):
        return self.head + self.event + self.foot

################################
    
event = Event()

### simple test
##event.add("summary","Lab Meeting")
##start = "02.03.2020 13:15"
##end = "02.03.2020 15:30"
##event.add('start',start)
##event.add('end', end)
##event.add('description', "Very nice socializing event")
##event.add('location', "Karlsruhe Digital Lab")
##
##print(event.get())
##print(base64.b64encode(event.get().encode()).decode("utf-8"))

# link template
href = "<a href=\"data:text/calendar;headers=filename%3Doklab{{{date}}}.ics;base64,{{{ics}}}\" download=\"oklab{{{date}}}.ics\">ICS</a>"

# data url to get the original json
url = "https://raw.githubusercontent.com/CodeforKarlsruhe/labSchedule/master/karlsruhe.json"

# use the simple local version from the codeforka repo
# for use with running hugo server
#url = "https://raw.githubusercontent.com/CodeforKarlsruhe/codeforka/master/static/schedule/schedule.json"
url = "http://localhost:1313/schedule/schedule.json"

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
for dd in enumerate(data): # all languages
    lidx = dd[0]
    lang = dd[1]
    for ee in enumerate(data[lang]):
        idx = ee[0]
        e = ee[1]
        if  not "ics" in e:
            event = Event()
            event.add("summary",e["title"])
            start = e["date"]
            try:
                s = datetime.datetime.strptime(start, "%d.%m.%Y %H:%M")
            except ValueError:
                start += " 19:00"
                pass
            print("start: ",start)
            event.add('start',start) 

            if not "duration" in e:
                e["duration"] = "02:00" # default 2 hours
            try:
                event.add("duration",e["duration"])
            except ValueError:
                print ("invalid duration")

            #event.add('end', end)
            if not "location" in e:
                if lang == "en":
                    e["location"] = "Town hall, Digital Lab"
                else:
                    e["location"] = "Rathaus, Digital Labor"
                
            event.add('location', e["location"])
            print("event: ",event.get())
            context = {"ics":base64.b64encode(event.get().encode()).decode("utf-8"),
                       "date":e["date"].split(" ")[0].replace(".","")}
            link = pystache.render(href,context)
            data[lang][idx]["ics"] = link

with open("out.json","w") as f:
    f.write(json.dumps(data))

print("new json generated: out.json");
print("Simplest next step: update github file at \n\
      https://raw.githubusercontent.com/CodeforKarlsruhe/labSchedule/master/karlsruhe.json\n\
      with this content")

