#!/usr/bin/env python

import shlex, subprocess
import json, os

# get http-get variables
GET={}
if (os.getenv("QUERY_STRING") is None):
    GET["get"]="default"
else:
    args=os.getenv("QUERY_STRING").split('&')
    for arg in args:
        t=arg.split('=')
        if len(t)>1: k,v=arg.split('='); GET[k]=v

if (GET.get('get') is None):
    GET["get"]="default"

if (GET.get('get') == ""):
    GET["get"]="default"

def show_status():
    # get ubnt-discover
    exec_command="/usr/sbin/ubnt-discover -d150 -V -i br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100 -j"
    args = shlex.split(exec_command)
    data = json.loads(subprocess.check_output(args))

    # get versions of wizards and IP-addresses from seperate shellscript
    versions = json.loads(subprocess.check_output("/config/custom/versions.sh"))
    # merge it to data array
    data["wizards"]=versions["wizards"]
    data["local_ips"]=versions["local_ips"]

    # return json output
    print("Content-Type: text/json")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print json.dumps(data)

def show_html():
    # get ubnt-discover
    exec_command="/usr/sbin/ubnt-discover -d150 -i br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100"
    args = shlex.split(exec_command)
    data = subprocess.check_output(args)

    # get uptime
    uptime = subprocess.check_output("uptime")

    # get local olsr infos
    import urllib2
    olsr_links = urllib2.urlopen("http://127.0.0.1:2006/links").read()

    # start to print content
    print("Content-Type: text/html")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print """<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=1000, initial-scale=0.5">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Status/OLSR EdgeOS</title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
<div class="container">
    <div class="row">
<!-- Nav tabs -->
        <div class="card">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation"><a href="#main" aria-controls="main" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> &Uuml;bersicht</a></li>
                <li role="presentation" class="active"><a href="#status" aria-controls="status" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> Status</a></li>
                <li role="presentation"><a href="#contact" aria-controls="contact" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Kontakt</a></li>
            </ul><br>
            <div class="tab-content">
<!-- Main TAB -->
                <div role="tabpanel" class="tab-pane" id="main">
                    <div class="page-header">
                        <h1>Willkommen</small></h1>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-body"><b>WAS?</b></div>
                        <div class="panel-footer">FunkFeuer ist ein freies, experimentelles Netzwerk in Wien, Graz, der Weststeiermark, in Teilen des Weinviertels (N&Ouml;) und in Bad Ischl. Es wird aufgebaut und betrieben von computerbegeisterten Menschen. Das Projekt verfolgt keine kommerziellen Interessen.</div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-body"><b>FREI?</b></div>
                        <div class="panel-footer">FunkFeuer ist offen f&uuml;r jeden und jede, der/die Interesse hat und bereit ist mitzuarbeiten. Es soll dabei ein nicht reguliertes Netzwerk entstehen, welches das Potential hat, den digitalen Graben zwischen den sozialen Schichten zu &Uuml;berbr&uuml;cken und so Infrastruktur und Wissen zur Verf&uuml;gung zu stellen. Teilnahme Zur Teilnahme an FunkFeuer braucht man einen WLAN Router (gibt's ab 60 Euro) oder einen PC, das OLSR Programm, eine IP Adresse von FunkFeuer, etwas Geduld und Motivation. Auf unserer Karte ist eingezeichnet, wo man FunkFeuer schon &Uuml;berall (ungef&auml;r) empfangen kann (bitte beachte, dass manchmal H&auml;user oder &Auml;hnliches im Weg sind, dann geht's nur &uuml;ber Umwege).</div>
                    </div>
                </div>
<!-- Status TAB -->
                <div role="tabpanel" class="tab-pane active" id="status">
                    <dl class="dl-horizontal">
                      <dt>System Uptime <span class="glyphicon glyphicon-time" aria-hidden="true"></span></dt><dd><pre>"""
    print uptime
    print """</pre></dd>
                      <dt>mgmt Devices <span class="glyphicon glyphicon-signal" aria-hidden="true"></span></dt><dd><pre>"""
    print data
    print "</pre></dd>"
    print """                      <dt>IPv4 Default-Route <span class="glyphicon glyphicon-transfer" aria-hidden="true"></span></dt><dd><pre>...</pre></dd>
                      <dt>IPv4 OLSR-Links <span class="glyphicon glyphicon-link" aria-hidden="true"></span></dt><dd><pre>"""
    print olsr_links
    print "</pre></dd>"
    print """                      <dt>Trace to UPLINK <span class="glyphicon glyphicon-stats" aria-hidden="true"></span></dt><dd><pre>...</pre></dd>
                    </dl>
                </div>
<!-- Contact TAB -->
                <div role="tabpanel" class="tab-pane" id="contact">
                    in Arbeit :D...
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="footer">
  <div class="container">
    <p class="text-muted">Page generated with Python.</p>
  </div>
</footer>
    <script src="/js/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
    <script>
      jQuery(window).load(function(){
        jQuery('#overlay').fadeOut();
      });
    </script>
  </body>
</html>
"""

if (GET.get('get') == "status"):
    show_status()
else:
    show_html()

