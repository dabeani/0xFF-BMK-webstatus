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

    # get local olsr infos
    import urllib2
    olsr_links = urllib2.urlopen("http://127.0.0.1:2006/links").read()

    # start to print content
    print("Content-Type: text/plain")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print "### UBNT-DISCOVER ###"
    print data
    print
    print 
    print "### OLSR-NEIGHBORS ###"
    print olsr_links

if (GET.get('get') == "status"):
    show_status()
else:
    show_html()

