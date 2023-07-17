""" create teasers for main codefor.de site from projects """
from yaml import load, CLoader
import os
import requests
import sys
import json

LANGS = ["de","en"]
LABURL = "https://ok-lab-karlsruhe.de"

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
            # make project link
            prjname = fl.split(".md")[0].split("-")
            prjname = "-".join(prjname[3:])
            print(prjname)
            # get image or use default
            prjimg = f"{LABURL}/img/CfKA%20Hexagon%203d.svg"
            if yml.get("imgname") != None:
                prjimg = f"{LABURL}/projects/{yml['imgname']}"
            # make lang specific link
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
                "link": prjurl,
                "img": prjimg,
                "teaser": teaser,
                "lang":lang
                }
            print(prj)
            projects.append(prj)
            

with open("../static/projects/projectlist.json","w") as f:
    json.dump(projects,f)
    
