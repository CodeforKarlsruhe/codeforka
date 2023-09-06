""" create teasers for main codefor.de site from projects """
from yaml import load, CLoader
import os
import requests
import sys
import json

from bs4 import BeautifulSoup
from markdown import markdown


LANGS = ["de","en"]
LABURL = "https://ok-lab-karlsruhe.de"
VERSION = 0.1
NAME = "Projectlist"

TESTLINKS = False #True

LABINFO = {
    "city": "Karlsruhe",
    "url": LABURL,
    "members" : ["Andreas Kugel",
                 "Martin Weis",
                 "Josef Attmann",
                 "Michael Riedmüller",
                 "Alexander Melde"
                 ],
    "socials" : {
        "github":"https://github.com/CodeforKarlsruhe",
        "gettogether":"https://gettogether.community/ok-lab-ka/"
    }
}

# categories: must be in sync with globals site
# currently we have Umwelt, Politik, Gesellschaft, Mobilität
# if no key present, default to all
# DEFAULT_CATEGORIES = ["environment","politics","society","mobility"]

DEFAULT_CATEGORIES = [0,1,2,3]

CATEGEORIES = {"de": ["Umwelt", "Politik", "Gesellschaft", "Mobilität"],
               "en": ["environment","politics","society","mobility"]}

# Status should be one of active, completed, archived. Default is completed
# localized status
STATUS = {
    "en":["active","completed","archived"],
    "de":["Laufend","Fertig","Abgeschlossen"]
}
DEFAULT_STATUS = 2


projects = []

for lang in LANGS:
    prjdir = f"../content/{lang}/projekte"
    print(prjdir)
    files = os.listdir(prjdir)
    for fl in files:
        if (not fl.endswith(".md")) or (fl == "_index.md"):
            continue
        print(fl)
        with open(os.sep.join([prjdir,fl])) as f:
            # split by "---" to get yaml header
            x = f.read().split("---")
            if len(x) != 3:
                continue
            y = x[1]
            md = x[2]
            print(md)
            yml = load(y,CLoader)
            #print(yml)
            # get project name and year from file name
            prjname = fl.split(".md")[0].split("-")
            prjyear = str(prjname[0])
            pm = str(prjname[1])
            pd = str(prjname[2])
            prjdate = "".join([prjyear,pm,pd])
            prjname = "-".join(prjname[3:])
            print(prjname)
            # get status or use default
            if yml.get("status") != None:
                prjstatus = yml["status"]
            else:
                prjstatus = STATUS[lang][DEFAULT_STATUS]
            # get categories or use default
            if yml.get("categories") != None:
                prjcats = yml["categories"]
            else:
                prjcats = [x for x in CATEGEORIES["en"]]
            # get image or use default
            prjimg = f"{LABURL}/img/CfKA%20Hexagon%203d.svg"
            if yml.get("imgname") != None:
                prjimg = f"{LABURL}/projects/{yml['imgname']}"
            # make project link, lang specific
            if lang == LANGS[0]:
                prjurl = f"{LABURL}/projekte/{prjname}"
            else:
                prjurl = f"{LABURL}/{lang}/projekte/{prjname}"
            # test url and image links
            if TESTLINKS:
                r = requests.get(prjurl)
                if r.status_code != 200:
                    print(f"Error on {prjurl}: {r.status_code}")
                    sys.exit()
                r = requests.get(prjimg)
                if r.status_code != 200:
                    print(f"Error on {prjimg}: {r.status_code}")
                    sys.exit()
            # make teaser
            print(md)
            html = markdown(md)
            text = ''.join(BeautifulSoup(html).findAll(text=True))

            if len(text) < 200:
                teaser = text
            else:
                teaser = text[:200] + " ...\n"
            # teaser = " ".join(md.split("\n")[:3]) + " ...\n"
            prj = {
                "lab": yml["lab"],
                "title": yml["title"],
                "year":prjyear,
                "date":prjdate,
                "categories":prjcats,
                "status":prjstatus,
                "link": prjurl,
                "img": prjimg,
                "teaser": teaser.strip(),
                "lang":lang
                }
            print(prj)
            projects.append(prj)
            

# sort by date, descending
projects = sorted(projects, key=lambda prj: prj["date"], reverse=True)


# create project list for export 
pl = {
    "name": NAME,
    "version": VERSION,
    "lab":LABINFO,
    "projects": projects
    }

with open("../static/projects/projectlist.json","w") as f:
    json.dump(pl,f)
    
