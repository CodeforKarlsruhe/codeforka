# codeforka
## New (2020) website OK Lab Karlsruhe

Live version, still work in progress [here](https://ok-lab-karlsruhe.de)


This is a modified copy of the projected [new codefor.de website](https://dev.codefor.de) which is online, but still under development. I tried to keep the same look-and-feel.

Is using hugo, a stitc site generator. I have modivied the config to support 2 languages (de and en). Translations are there (tx to Google) but need to be improved. Also, some content relates only to the main codefor.de site ans has to be removed or modfied for the OK lab KA.

It supports to load the event schedule from a json file from github at runtime, so we don't need to recompile just to update events.

For developing you need to get the **extended** hugo site generator from [here](https://github.com/gohugoio/hugo/releases/), make sure to use version >= 0.64

Then go to the base directory (the one with the config.yaml file) and run

hugo server -D

to start development.

To deploy, run hugo -D and copy the resulting "public" directory to your server.

The -D tells hugo to include "draft" content (see front matter). You migh change this once the site is stable.

## Newsletter 
Approach: allow user to provide email address, using double opt-in. 

PHP / Mysql backend is installed and works. Addresses are encrypted with the PHP "builtin" OpenSSL public key, as not all providers have GPG available. A standalone php download and decoder program is in the "php" folder. 

The python based newsletter compiler is now in the tools directory. It reads the newsletter content from the news.json file (static/news) and creates a template, which is converted to html using [mjml](https://mjml.io/).
This html template should be inspected before the newsletter is actually sent. Note: it will still contain the templated unsuscribe link at the bottom.

Newsletter images should go into static/news and not into static/img to keep static and dynamic data apart.

Subsequently, all subscribed addresses are loaded from the server. For all addresses the html template is updated with the individual unsubscribe links and a multipart email message with html and plaintext version is generated and saved to the "out" directory. If "sendMails" is enabled, the newsletter is sent via SMTP, using a delay of 1s. For large amounts of addresses the delay should be adjusted according to the specs of the provider (maybe it can be removed for few mails).

The news page on the server is created from the same news.json file via javascript, so they are already in sync. Note, the server doesn't show news older than 2 weeks (adjust in themes/config-hugo/static/js/news.js)

All sensitive information like smtp user data is stored in the file "news.ini" which should reside in a location where it cannot be reached by the webserver, only by PHP.

On the server we need the smtp information for the double opt-in mails. For encryption we need only the certificate file with the public key and the download password for verification. Note, the remove links are encrypted as well for download, so in case someone gets download access he still receives fully encrypted data only.
On the client, we need the private key file and the key password as well.

PHP versions >= 7.2, Python >= 3.6



