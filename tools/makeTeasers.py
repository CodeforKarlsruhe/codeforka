""" create teasers for main codefor.de site from projects """
from yaml import load, CLoader
import os
import requests
import sys
import json

LANGS = ["de","en"]
LABURL = "https://ok-lab-karlsruhe.de"
VERSION = 0.1
NAME = "Projectlist"

# categories: must be in sync with globals site
# currently we have umwelt, politik, gesellschaft, mobilität
# if no key present, default to all
DEFAULT_CATEGORIES = ["environment","politics","society","mobility"]

# Status should be one of active, completed, archived. Default is completed
DEFAULT_STATUS = "completed"

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
            prjname = "-".join(prjname[3:])
            print(prjname)
            # get status or use default
            if yml.get("status") != None:
                prjstatus = yml["status"]
            else:
                prjstatus = DEFAULT_STATUS
            # get categories or use default
            if yml.get("categories") != None:
                prjcats = yml["categories"]
            else:
                prjcats = DEFAULT_CATEGORIES
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
            r = requests.get(prjurl)
            if r.status_code != 200:
                print(f"Error on {prjurl}: {r.status_code}")
                sys.exit()
            r = requests.get(prjimg)
            if r.status_code != 200:
                print(f"Error on {prjimg}: {r.status_code}")
                sys.exit()
            # make teaser
            teaser = " ".join(md.split("\n")[:3]) + " ...\n"
            prj = {
                "lab": yml["lab"],
                "title": yml["title"],
                "year":prjyear,
                "categories":prjcats,
                "status":prjstatus,
                "link": prjurl,
                "img": prjimg,
                "teaser": teaser.strip(),
                "lang":lang
                }
            print(prj)
            projects.append(prj)
            

# create project list for export 
pl = {
    "name": NAME,
    "version": VERSION,
    "projects": projects
    }

with open("../static/projects/projectlist.json","w") as f:
    json.dump(pl,f)
    
