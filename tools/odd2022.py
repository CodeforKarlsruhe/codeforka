#!/usr/bin/python3
""" send news emails directly from address array, no unsibscribe link """

import sys
import os
import time 
####
import urllib.request
import json
import pystache
####
import configparser
#### 
from pathlib import Path, PurePath # builtin
import shutil # builtin
import smtplib # builtin
import quopri  # builtin
# email from: pip install simplemail
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formatdate
from email import charset
import html2text # pip install html2text

#################

sendMails = True

#################
# config parser
# https://docs.python.org/3.6/library/configparser.html
cfgFile = "/home/kugel/daten/work/projekte/okLabs/okLab/repo/code4ka/php/news.ini"
config = configparser.ConfigParser()
config.read(cfgFile)

################
# create local new data

data = {
  "de": {
    "date": "01.03.2022",
    "title": "Open Data Day 2022",
    "item": {
        "headline": "Open Data Day Zugangsinformation",
        "imglink": "https://ok-lab-karlsruhe.de/projects/karlsruhe/ODDlogo-ka.svg",
        "imgtitle": "ODD",
        "teaser": """Vielen Dank für Ihr Interesse am OpenDataDay 2022.<br>
            Die Veranstaltung findet am 5.3.2022 von 14:00 Uhr bis 17:00 Uhr statt.<br>
            Bitte wählen Sie sich mit Ihrem Webbrowser unter
            <a href="https://meet.google.com/gdx-xvda-yea">diesem Link</a> oder mit der
            Telefonnummer 08081618117, PIN: 441529540 ein.<br>
            Alle weiteren Informationen finden Sie auf unserer Webseite.<br>
            Gerne können Sie diese Mail an Bekannte oder Freunde weiterleiten.<br>
            Wir freuen uns auf Ihre Teilnahme.""",
        "more": "https://ok-lab-karlsruhe.de/projekte/odd22/"
      }
  }
}


print(data["de"]["date"])


hdr = """
<mjml>
  <mj-head>
    <mj-title>OK Lab Karlsruhe Newsletter</mj-title>
    <mj-attributes>
      <mj-all font-family="Montserrat, Helvetica, Arial, sans-serif" padding="0"></mj-all>
      <mj-section padding="20px 20px 0 20px"></mj-section>
      <!--
      <mj-text font-weight="400" font-size="16px" color="#363636" line-height="24px" align="justify"></mj-text>
    -->
      <mj-text></mj-text>
      <mj-class name="title" font-size="32px" line-height="36px" font-weight="800"></mj-class>
      <mj-class name="headline" font-size="24px" line-height="30px" font-weight="600"></mj-class>
      <mj-class name="text" padding="10px 10px 10px 10px" font-weight="400" font-size="16px" color="#363636" line-height="24px" align="justify"></mj-class>
      <mj-class name="seperator" padding="10px 0 10px 0"></mj-class>
      <mj-class name="webview" font-size="11px" color="#363636" line-height="20px" padding="5px 0 5px 0"></mj-class>
      <mj-class name="more" padding="0 10px 10px 10px" font-size="16px" line-height="20px" align="left" color="#01aefd" </mj-class>
    </mj-attributes>
  </mj-head>
  <mj-body background-color="#ffffff">

    <!-- webview link -->
    <mj-section mj-class="webview">
      <mj-column>
        <mj-text  mj-class="webview" align="center" color="#999999">
        </mj-text>
      </mj-column>
    </mj-section>

	<!-- blue separator -->
	<mj-section mj-class="seperator" background-color="#01aefd">
	</mj-section>


    <!-- logo -->
    <mj-section background-color="#FFFFFF">
      <mj-column>
        <mj-text align="center" mj-class="title">OK Lab Karlsruhe</mj-text>
        <mj-text align="center" mj-class="headline">Newsletter</mj-text>
        <mj-image href="https://ok-lab-karlsruhe.de" src="https://ok-lab-karlsruhe.de/img/CfKA%20Hexagon%203d.svg" title="OK Lab Karlsruhe" align="center" border="none" padding="10px" width="200px"></mj-image>
      </mj-column>
    </mj-section>

"""

intro_t = """
    <!-- intro -->
    <mj-section background-color="#FFFFFF">
      <mj-column>
        <mj-text align="center" mj-class="headline" padding="30px 40px 10px 40px">
          {{{title}}}
        </mj-text>
      </mj-column>
    </mj-section>
    <!-- separator -->
    <mj-section mj-class="seperator" background-color="#444F60">
    </mj-section>
"""

even_t = """
    <!-- news item even -->
    <mj-section background-color="#FFFFFF">
      <mj-column>
        <mj-text align="center" mj-class="headline" padding="30px 40px 10px 40px">
          {{{headline}}}
        </mj-text>
        <mj-image  src="{{{imglink}}}" title="{{{imgtitle}}}" align="center" border="none" padding="10px" width="250px"></mj-image>
        <mj-text mj-class="text">
          {{{teaser}}}
        </mj-text>
        <mj-text mj-class="text more">
          <a href="{{{more}}}" style="text-decoration:none" title="Weiter" >Weiter</a>
        </mj-text>
      </mj-column>
    </mj-section>
    <!-- separator -->
    <mj-section mj-class="seperator" background-color="#444F60">
    </mj-section>
"""


foot_t = """
  <!-- blue separator -->
  <mj-section mj-class="seperator" background-color="#01aefd">
  </mj-section>

    <!-- footer -->
    <!-- use a wrapper ! -->
    <mj-wrapper padding="0">
      <mj-section background-color="#444F60">
        <mj-column>
          <mj-text align="center" mj-class="headline" color="#ffffff">OK Lab Karlsruhe</mj-text>
        </mj-column>
      </mj-section>

      <mj-section background-color="#444F60">
        <mj-column width="40%" background-color="#ffffff">
          <mj-image href="https://okfn.de" src="https://ok-lab-karlsruhe.de/img/okf.svg" title="OK Lab Karlsruhe" align="left" border="none" padding="10px"></mj-image>
        </mj-column>
        <mj-column width="40%">
          <mj-text align="center" color="#ffffff" mj-class="text" padding="0 10px 0 10px">
            <a href="https://ok-lab-karlsruhe.de/impressum/"
            style="color: #98a9c3; text-decoration:none;" Title="Impressum">Impressum</a>
          </mj-text>
          <mj-text align="center" color="#ffffff" mj-class="text">
            <a href="mailto:info@ok-lab-karlsruhe.de"
            style="color: #98a9c3; text-decoration:none;" Title="EMail">Email</a>
          </mj-text>
          <mj-social  padding="0 10px 0 10px">
            <mj-social-element  name="github" background-color="#444F60" href="https://github.com/codeforkarlsruhe"/>
            <mj-social-element  name="web" background-color="#444F60" href="https://ok-lab-karlsruhe.de"/>
          </mj-social>
        </mj-column>
      </mj-section>
    <mj-section background-color="#444F60">
      <mj-column>
        <mj-text mj-class="text" color="#ffffff">
          Dies ist ein einmaliger Mailversandt. Sie müssen sich nicht abmelden.
        </mj-text>
      </mj-column>
    </mj-section>
  </mj-wrapper>


  <!-- white separator -->
  <mj-section mj-class="seperator" background-color="#ffffff">
  </mj-section>

  </mj-body>
</mjml>
"""

# init with hdr
news = hdr

# we should know what the template code is ...
context = {"title":data["de"]["title"]}
intro = pystache.render(intro_t,context)
#print(intro)

# append intro
news += intro

item = data["de"]["item"]

context = {"headline": item["headline"],
           "imglink":item["imglink"],
           "imgtitle":item["imgtitle"],
           "teaser":item["teaser"],
           "more":item["more"]}
#append items
news += pystache.render(even_t,context)
     

#print(news)

# append footer
news += foot_t

# the remove link is still open!
# update when sending mails

# create mjml source
fn = "news"
with open(fn + ".mjml","w") as f:
    f.write(news)

# generate html template
cmd = "mjml " + fn + ".mjml -o " + fn + ".html"
os.system(cmd)

# specify addresses
addr = [
    {"email":"ak@akugel.de"},
]

# process addresses
# create out dir
shutil.rmtree(Path("out"), ignore_errors=True)
time.sleep(1)
os.mkdir(Path("out"))

# open server connection if we send the mails here
if sendMails:
    server = smtplib.SMTP(config["mail"]["smtphost"], config["mail"]["smtpport"])
    server.ehlo()
    server.starttls()
    rc = server.login(config["mail"]["smtpuser"],config["mail"]["smtppass"])
    print("RC:",rc) # expect 235
    if not rc[0] == 235:
        print("Connecting to server failed")
        sys.exit()

else:
    server = None
    print("Dry run: not sending emails")




# read template
with open(fn + ".html") as f:
    html = f.read()
    
mails = 0
for a in addr:
    # create text only version with html2text
    hp = html2text.HTML2Text()
    ## we don't remoe the alt text here ...
    ##    parser.wrap_links = False
    ##    parser.skip_internal_links = True
    ##    parser.inline_links = True
    ##    parser.ignore_anchors = True
    hp.ignore_emphasis = True
    hp.inline_links = False
    #images: ignore or alt
    hp.ignore_images = True
    #hp.images_to_alt = True
    ptext = hp.handle(html)

    #encode quoted printable
    newsQpBytes = quopri.encodestring(bytes("\n" + ptext + "\n",'utf-8'))
    #make sure to utf-8 encode string
    newsQp = str(newsQpBytes,'utf-8')

    # create email
    # Create message container - the correct MIME type is multipart/alternative.
    msg = MIMEMultipart('alternative')
    msg['Subject'] = "Newsletter OK Lab Karlsruhe"
    msg['From'] = config["mail"]["smtpname"] +" <" + config["mail"]["smtpuser"] + ">"
    msg['To'] = a['email']
    msg['Date'] = formatdate()  # rfc2822 compatible

    # Record the MIME types of both parts - text/plain and text/html.
    part1 = MIMEText(newsQp, 'plain')
    part1.set_charset('utf-8')
    part1.replace_header('content-transfer-encoding', 'quoted-printable')

    part2 = MIMEText(html, 'html')

    # Attach parts into message container.
    # According to RFC 2046, the last part of a multipart message, in this case
    # the HTML message, is best and preferred.
    msg.attach(part1)
    msg.attach(part2)

    # write mail to putput dir
    mf = open(Path("out/" + a['email'] + ".txt"),'w')
    mf.write(msg.as_string())
    mf.close()

    if sendMails:
        print("Sending from:",msg['From'],", To:", msg['To'])
        try:
            server.sendmail(msg['From'], [msg['To']], msg.as_string())
            print ('email sent')
        except:
            print ('error sending mail')
            break

        print("Delay on sending mails ...")
        time.sleep(1)



    mails += 1

if None != server:
    server.close()

print("Created: ",mails, " mails")


