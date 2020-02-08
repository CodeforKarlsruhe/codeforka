# codeforka
New (2020) website OK Lab Karlsruhe

Live version, still work in progress [here](https://ok-lab-karlsruhe.de)


This is a modified copy of the projected [new codefor.de website](https://dev.codefor.de)

Is is using hugo, as a stitc site generator. I have modivied the config to support 2 languageed (de and en) but the translations are not there yet.

It support to load the event schedule from a json file from github at runtime, so we don't need to recompile just to update events.

For developing you need to get the **extended** hugo site generator from [here](https://github.com/gohugoio/hugo/releases/)

Then go to the base directory (the one with the config.yaml file) and run

hugo server -D

to start development.

To deploy, run hugo -D and copy the resulting "public" directory to your server.

The -D tells hugo to include "draft" content (see front matter). You migh change this once the site is stable.


