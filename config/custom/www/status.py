#!/usr/bin/env python
#version=4.7

#load settings
interface_list='br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100'
get_nslookup_from_nodedb=1
show_link_to_adminlogin=0
traceroute_to='subway.funkfeuer.at'
traceroute6_to='subway.funkfeuer.at'
for line in open("/config/custom/www/settings.inc"):
    if (line.find("=")==-1): continue
    dat=line.split("=")
    if (line.find("interface_1100_list")>0): interface_list=dat[1].strip(";'\n ")
    if (line.find("get_nslookup_from_nodedb")>0): get_nslookup_from_nodedb=dat[1].strip(";'\n ")
    if (line.find("show_link_to_adminlogin")>0): show_link_to_adminlogin=dat[1].strip(";'\n ")
    if (line.find("traceroute_to")>0): traceroute_to=dat[1].strip(";'\n ")
    if (line.find("traceroute6_to")>0): traceroute6_to=dat[1].strip(";'\n ")

import shlex, subprocess
import json, os, socket

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

def show_test():
    exec_command="/usr/bin/traceroute -4 -w 1 -q 1 subway.funkfeuer.at"
    args = shlex.split(exec_command)
    data = subprocess.check_output(args)
    lines=data.split('\n')
    for key,line in enumerate(lines):
        if (key == 0): continue
        if (len(line) == 0): continue
        print key, ": ", line,
        traceline=line.split()
        print traceline[0] #HOP
        print traceline[1] #HOST
        print traceline[2].strip("()") #IP
        print traceline[3],"ms" #PING

def show_status():
    # get ubnt-discover
    exec_command="/usr/sbin/ubnt-discover -d150 -V -i "+interface_list+" -j"
    args = shlex.split(exec_command)
    data = json.loads(subprocess.check_output(args))

    # get versions of wizards and IP-addresses from seperate shellscript
    versions = json.loads(subprocess.check_output("/config/custom/versions.sh"))
    # merge it to data array
    data["wizards"]=versions["wizards"]
    data["local_ips"]=versions["local_ips"]
    data["autoupdate"]=versions["autoupdate"]

    # return json output
    print("Content-Type: text/json")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print json.dumps(data)

def parse_firmware(me):
    fw=me.split(".")
    out=str(fw[2])
    for f in range(3,len(fw)-1):
        c=str(fw[f])
        if (f>4 and len(c)>4 and c.find('alpha')==-1 and c.find('beta')==-1 and c.find('rc')==-1): break
        out=out+"."+c
    
    return out

def format_wmode(me):
    if (me ==  3): return "AP"
    if (me ==  2): return "STA"
    if (me == -1): return "CABLE"
    return str(me)

def format_duration(me):
    if (me <=120):      return str(me)+"sek"     # 0-120sek
    if (me <=120*60):   return str(me/60)+"min"  # 2-120min
    if (me <=50*60*60): return str(me/60/60)+"h" # 2-50h
    return str(me/60/60/24)+"d"                  # 2+ tage

def format_hostname(me):
    dn=me.split(".")
    if (me.find("wien.funkfeuer.at")>=0 and len(dn)==5):
        dn=me.split(".")
        return dn[0]+".<b>"+dn[1]+"</b>.wien.funkfeuer.at"
    else: return str(me)

def ip4_to_integer(s):
    #transform IP to interger
    i=s['ipv4'].split(".")
    l=i[0].rjust(3,'0')+i[1].rjust(3,'0')+i[2].rjust(3,'0')+i[3].rjust(3,'0')
    return int(l)

def show_html():
    # local hostname
    try: 
        # could be IP or hostname
        hostname=os.environ['SERVER_NAME']
    except KeyError: 
        hostname=socket.gethostname()
        ## locally assign name, not fqdn


    # host,aliaslist,ipaddrlist=socket.gethostbyattr('78.41.113.155')
    # host=socket.getfqdn('78.41.113.155')

    # get ubnt-discover
    exec_command="/usr/sbin/ubnt-discover -d150 -V -i "+interface_list+" -j"
    args = shlex.split(exec_command)
    data = json.loads(subprocess.check_output(args))
    #need sorting by IP last octet: 100..255, 1...99
    
    # get local olsr infos
    import urllib2
    try: olsr_links = urllib2.urlopen("http://127.0.0.1:2006/links", timeout = 1).read().strip("\n ")
    except urllib2.URLError as e:
        print type(e)    #not catch
    except socket.timeout as e:
        print type(e)    #catched
        
    # get node-db info
    global get_nslookup_from_nodedb
    try: nodedb_raw=urllib2.urlopen("http://ff.cybercomm.at/node_db.json", timeout = 1)
    except urllib2.URLError:
        get_nslookup_from_nodedb=0
    except socket.timeout:
        get_nslookup_from_nodedb=0

    if (str(get_nslookup_from_nodedb)=="1"):
        node_dns=json.loads(nodedb_raw.read())
    else: node_dns={}

    # get routing table
    exec_command="/sbin/ip -4 r | grep -v scope | awk '{print $3,$1,$5}'"
    routinglist=subprocess.check_output(exec_command, shell=True).strip("\n ").split("\n")

    gatewaylist={}
    nodelist={}
    for route in routinglist:
        line=route.split()
        if (line[1] == 'default'):
            defaultv4ip=line[0]
            defaultv4dev=line[2]
            defaultv4host=socket.getfqdn(defaultv4ip)
            continue
        try: gatewaylist[line[0]].extend([str(line[1])])
        except KeyError: gatewaylist[line[0]]=[str(line[1])]
        try: tmp=len(nodelist[line[0]])
        except KeyError: nodelist[line[0]]=[]
        try: 
            n=node_dns[line[1]]['n']
            if (n not in nodelist[line[0]]): nodelist[line[0]].extend([str(n)])
        except KeyError: n=""
    
    # get uptime
    uptime = subprocess.check_output("uptime").strip("\n ")

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
    <title>"""
    print hostname
    print """</title>
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
                <li role="presentation"><a href="#">"""
    print hostname
    print """</a></li>
            </ul><br>
            <div class="tab-content">
<!-- Main TAB -->
                <div role="tabpanel" class="tab-pane" id="main">
                    <div class="page-header">
                        <h1>Willkommen auf """
    print hostname
    print """</small></h1>
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
                      <dt>System Uptime <span class="glyphicon glyphicon-time" aria-hidden="true"></span></dt><dd>"""
    print uptime
    print """</dd>
                      <dt>mgmt Devices <span class="glyphicon glyphicon-signal" aria-hidden="true"></span></dt><dd>
                        <table class="table table-hover table-bordered table-condensed"><thead style="background-color:#f5f5f5;"><tr valign=top><td><b>HW Address</b></td><td><b>Local IP</b></td><td><b>Hostname</b></td>
                        <td><b>Product</b></td><td><b>Uptime</b></td><td><b>WMODE</b></td><td><b>ESSID</b></td><td><b>Firmware</b></td></tr></thead><tbody>"""
    for key,device in enumerate(sorted(data['devices'], key=ip4_to_integer)):
        print "<tr>"
        print "<td>"+device['hwaddr']+"</td>"
        print "<td>"+device['ipv4']+"</td>"
        print "<td>"+device['hostname']+"</td>"
        print "<td>"+device['product']+"</td>"
        print "<td>"+format_duration(device['uptime'])+"</td>"
        print "<td>"+format_wmode(device['wmode'])+"</td>"
        print "<td>"+device['essid']+"</td>"
        print "<td>"+parse_firmware(device['fwversion'])+"</td>"
        print "</tr>"

    print """</tbody></table></dd>
                      <dt>IPv4 Default-Route <span class="glyphicon glyphicon-transfer" aria-hidden="true"></span></dt><dd>"""
    print "<a href=\"https://"+defaultv4host+"\" target=_blank>"+format_hostname(defaultv4host)+"</a> "
    print "(<a href=\"https://"+defaultv4ip+"\" target=_blank>"+defaultv4ip+"</a>) via "+defaultv4dev
    print """</dd>
                      <dt>IPv4 OLSR-Links <span class="glyphicon glyphicon-link" aria-hidden="true"></span></dt><dd>"""
    #insert olsr-route-layover
    for key,destinationlist in gatewaylist.items():
        print """<!-- Modal -->
<div class="modal fade" id="myModal"""+key.replace(".","")+"""" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="myModalLabel">"""+str(len(destinationlist))+""" routes via <b>"""+key+"""</b></h4>
      </div>
      <div class="modal-body">"""
        for dest in destinationlist:
            print dest, 
            try: 
                n=node_dns[dest]['n']
                print n, 
            except KeyError: n=""
            try: 
                d=node_dns[dest]['d']
                print d, 
            except KeyError: d=""

            print "<br>"

        print """</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
"""
    #insert olsr-node-layover
    for key,destinationlist in nodelist.items():
        print """<!-- Modal -->
<div class="modal fade" id="myModal"""+key.replace(".","")+"""_nodes" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="myModalLabel">"""+str(len(destinationlist))+""" nodes via <b>"""+key+"""</b></h4>
      </div>
      <div class="modal-body">"""
        for dest in destinationlist:
            print dest, 
            print "<br>"

        print """</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
"""
    print """                        <table class="table table-hover table-bordered table-condensed"><thead style="background-color:#f5f5f5;">
                        <tr valign=top><td><b>Local IP</b></td><td><b>Remote IP</b></td><td><b>Remote Hostname</b></td><td><b>Hyst.</b></td><td><b>LQ</b></td><td><b>NLQ</b></td><td><b>Cost</b></td><td><b>routes</b></td><td><b>nodes</b></td></tr></thead><tbody>
"""
    lines=olsr_links.split('\n')
    for key,line in enumerate(lines):
        if (key <= 1): continue
        if (len(line) == 0): continue
        link=line.split()
        print "<tr"
        if (link[1] == defaultv4ip): print " bgcolor=FFD700"
        host=socket.getfqdn(link[1])
        print "><td>"+link[0]+"</td><td><a href=\"https://"+link[1]+"\" target=_blank>"+link[1]+"</a></td>" #link-ip
        print "<td><a href=https://"+host+" target=_blank>"+format_hostname(host)+"</a></td>" #link-hostname
        print "<td>"+link[2]+"</td><td>"+link[3]+"</td>" #hyst, lq
        print "<td>"+link[4]+"</td><td>"+link[5]+"</td>" #nlq, cost
        print "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal"+link[1].replace(".","")+"\">"+str(len(gatewaylist[link[1]]))+"</button></td>"
        print "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal"+link[1].replace(".","")+"_nodes\">"+str(len(nodelist[link[1]]))+"</button></td>"
        print "</tr>"

    print """</tbody></table></dd>
                      <dt>Trace to UPLINK <span class="glyphicon glyphicon-stats" aria-hidden="true"></span></dt><dd>
                        <table class="table table-hover table-bordered table-condensed"><thead style="background-color:#f5f5f5;"><tr valign=top><td><b>#</b></td><td><b>Hostname</b></td><td><b>IP Address</b></td>
                        <td><b>Ping</b></td></tr></thead><tbody>
"""
    exec_command="/usr/bin/traceroute -4 -w 1 -q 1 subway.funkfeuer.at"
    args = shlex.split(exec_command)
    data = subprocess.check_output(args).strip("\n ")
    lines=data.split('\n')
    for key,line in enumerate(lines):
        if (key == 0): continue
        if (len(line) == 0): continue
        traceline=line.split()
        print "<tr><td>"+traceline[0]+"</td>", #HOP
        print "<td><a href=\"https://"+traceline[1]+"\" target=_blank>"+format_hostname(traceline[1])+"</a></td>", #HOST
        print "<td><a href=\"https://"+traceline[2].strip("()")+"\" target=_blank>"+traceline[2].strip("()")+"</a></td>", #IP
        print "<td>"+traceline[3]+"ms</td>", #PING
        print "</tr>"
    
    print """</tbody></table></dd>
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
elif (GET.get('get') == "test"):
    show_test()
else:
    show_html()
