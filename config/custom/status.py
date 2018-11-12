#!/usr/bin/env python
#version=4.7

#set defaults
ipranges=[
  ['193.238.156.0','193.238.159.255'],
  ['78.41.112.0','78.41.119.255'],
  ['185.194.20.0','185.194.23.255']
]
ipaddresses=[]
allowiphones=1
interface_list='br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100'
get_nslookup_from_nodedb=1
show_link_to_adminlogin=0
traceroute_to='78.41.115.36'
traceroute6_to='2a02:60:35:492:1:ffff::1'
ubntdiscovertime='150'
#load settings
for line in open("/config/custom/www/settings.inc"):
    if (line.find("=")==-1): continue
    dat=line.split("=")
    if (line.find("interface_1100_list")>0): interface_list=dat[1].strip(";'\n ")
    if (line.find("get_nslookup_from_nodedb")>0): get_nslookup_from_nodedb=dat[1].strip(";'\n ")
    if (line.find("show_link_to_adminlogin")>0): show_link_to_adminlogin=dat[1].strip(";'\n ")
    if (line.find("traceroute_to")>0): traceroute_to=dat[1].strip(";'\n ")
    if (line.find("traceroute6_to")>0): traceroute6_to=dat[1].strip(";'\n ")
    if (line.find("allowiphones")>0): allowiphones=dat[1].strip(";'\n ")
    if (line.find("ubntdiscovertime")>0): ubntdiscovertime=dat[1].strip(";'\n ")
    if (line.find("ipranges")>0): 
        ipranges=[]
        list=dat[1].strip(";'\n ").split(",")
        for pairs in list:
            #verify if valid ipv4!
            if (pairs.find("-")>0): 
                ipranges.append(pairs.split("-"))
    if (line.find("ipaddresses")>0): 
        #verify if valid ipv4!
        if (len(dat[1].strip(";'\n "))>0): ipaddresses=dat[1].strip(";'\n ").split(",")
        else: ipaddresses=[]

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

def convert_ipv4(ip):
    return tuple(int(n) for n in ip.split('.'))

def check_ipv4_in(addr, start, end):
    return convert_ipv4(start) < convert_ipv4(addr) < convert_ipv4(end)

# check if client is out of defined ip ranges
try:
    clientip=os.environ["REMOTE_ADDR"]
    try:
        #check if client is IPv4
        socket.inet_aton(clientip)
        authorized=False
        for ip in ipranges:
            authorized=check_ipv4_in(clientip, ip[0], ip[1])
            if (authorized): break
        if (authorized==False):
            for ip in ipaddresses:
                authorized=check_ipv4_in(clientip, ip, ip)
                if (authorized): break
    
    except socket.error:
        #client is not IPv4, maybe IPv6
        authorized=True

except KeyError:
    # allow if unknown ip or local runenvironment
    authorized=True
    clientip="unknown"

authorized_ip=authorized

if (authorized==False and str(allowiphones)=="1"):
    try:
        agent=os.environ["HTTP_USER_AGENT"]
        import re
        iphone=r'.*iPhone.*OS (.{3,8}) like'
        uamatch = re.match( iphone, agent, re.M|re.I)
        if uamatch:
           #print "iPhone - iOS version "+ uamatch.group(1).replace("_",".")
           authorized=True
    except:
        agent=""
        uamatch=""

else:
    uamatch=""

def show_test():
    print("Content-Type: text/plain")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers

    print str(authorized)+" ("+clientip+") "
    if (uamatch):
        print "iOS:"+uamatch.group(1).replace("_",".")

def show_ipv4():
    print("Content-Type: text/plain")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    exec_command="/sbin/ip -4 a"
    data=subprocess.check_output(exec_command, shell=True)
    print data
    print
    exec_command="/sbin/ip -4 neigh"
    data=subprocess.check_output(exec_command, shell=True)
    print data

def show_ipv6():
    print("Content-Type: text/plain")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    exec_command="/sbin/ip -6 a"
    data=subprocess.check_output(exec_command, shell=True)
    print data
    print
    exec_command="/sbin/ip -6 neigh"
    data=subprocess.check_output(exec_command, shell=True)
    print data

def show_airos():
    # return output
    print("Content-Type: text/json")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    if (authorized_ip):
        try: 
            f = open('/tmp/10-all.json', 'r')
            data = json.loads(f.read())
            print json.dumps(data)
            f.close()
        except IOError as (errno, strerror):
            string= "I/O error({0}): {1}".format(errno, strerror)
            print '{"return":"'+string+'"}'
        except ValueError:
            print '{"return":"ValueError"}'
        except:
            string="Unexpected error:", sys.exc_info()[0]
            print '{"return":"'+string+'"}'
    
    else:
        print '{"return":"not-authorized","ip":"'+clientip+'"}'

def show_status():
    # get ubnt-discover
    # first: make sure all interfaces exist
    exec_command="cat /proc/net/dev | awk '/:/ {print $1}' | tr -d ':'"
    existinginterfaces=subprocess.check_output(exec_command, shell=True).strip("\n ").split("\n")
    search4interfaces=interface_list.split(",")
    s2 = set(search4interfaces)
    b3 = [val for val in existinginterfaces if val in s2]
    interface_list_ok = ",".join(b3)

    exec_command="/usr/sbin/ubnt-discover -d"+ubntdiscovertime+" -V -i "+interface_list_ok+" -j"
    args = shlex.split(exec_command)
    data = json.loads(subprocess.check_output(args))

    # get versions of wizards and IP-addresses from seperate shellscript
    versions = json.loads(subprocess.check_output("/config/custom/versions.sh"))
    # merge it to data array
    data["wizards"]=versions["wizards"]
    data["local_ips"]=versions["local_ips"]
    data["autoupdate"]=versions["autoupdate"]
    data["linklocals"]=versions["linklocals"]
    data["olsrd4watchdog"]=versions["olsrd4watchdog"]

    #allow airos-data for monitoring (for autorized IPs only)
    if (authorized_ip):
        try: 
            f = open('/tmp/10-all.json', 'r')
            airosdata = json.loads(f.read())
            f.close()
        except:
            airosdata={}
    
    else:
        airosdata={}

    data["airosdata"]=airosdata

    # return json output
    print("Content-Type: text/json")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print json.dumps(data)

def show_connections():
    # get ports,bridges,vlans from connected/discovered devices
    data = subprocess.check_output("/config/custom/connections.sh")

    # return output
    print("Content-Type: text/plain")
    print("X-Powered-By: cpo/bmk-v4.7")
    print         # blank line, end of headers
    print data

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
        hostname=os.environ['SERVER_NAME']
        try:
            hostname,list,ip=socket.gethostbyaddr(hostname)
            ip=ip[0]
        except socket.error:
            hostname=hostname
            ip=""
    
    except KeyError: 
        hostname=socket.gethostname()
        ip=""

    # host,aliaslist,ipaddrlist=socket.gethostbyattr('78.41.113.155')
    # host=socket.getfqdn('78.41.113.155')

    # get ubnt-discover
    exec_command="cat /proc/net/dev | awk '/:/ {print $1}' | tr -d ':'"
    existinginterfaces=subprocess.check_output(exec_command, shell=True).strip("\n ").split("\n")
    search4interfaces=interface_list.split(",")
    s2 = set(search4interfaces)
    b3 = [val for val in existinginterfaces if val in s2]
    interface_list_ok = ",".join(b3)
    exec_command="/usr/sbin/ubnt-discover -d"+ubntdiscovertime+" -V -i "+interface_list_ok+" -j"
    args = shlex.split(exec_command)
    data = json.loads(subprocess.check_output(args))
    #need sorting by IP last octet: 100..255, 1...99
    
    # get local olsr infos
    import urllib2
    try: olsr_links = urllib2.urlopen("http://127.0.0.1:2006/lin", timeout = 1).read().strip("\n ")
    except:
        #use httpinfo plugin if available
        try:
            urllib2.urlopen("http://127.0.0.1:8080/about", timeout = 1).read()
            exec_command="echo -e 'T L\\nh e a d l i n e' && /usr/bin/curl -s 127.0.0.1:8080/nodes | sed -n \"/^<h2>Links/,/^<h2>Neighbors/p\" | sed -e 's/[\\/)( <>]/#/g' | awk -F'#' '/all/ {printf \"%-17s%-17s%5s%7s%7s%7s\\n\", $11,$25,$33,$39,$40,$42}'"
            olsr_links=subprocess.check_output(exec_command, shell=True).strip("\n ")
        except:
            #work around: get neighbors from routing table
            exec_command="echo -e 'T L\\nh e a d l i n e' && /sbin/ip -4 route | grep onlink | awk '!x[$3]++' | awk '{print \"- \"$3\" 0.000 0.000 0.000 0.000\"}'"
            try: olsr_links=subprocess.check_output(exec_command, shell=True).strip("\n ")
            except:
                olsr_links={}
    
    # get node-db info
    global get_nslookup_from_nodedb
    try: useragent=os.environ["HTTP_USER_AGENT"]
    except: useragent='status.py-unknown-user-agent'
    try:
        #nodedb_raw=urllib2.urlopen("https://ff.cybercomm.at/node_db.json", timeout = 1)
        req = urllib2.Request("https://ff.cybercomm.at/node_db.json", headers={'User-Agent': useragent+' OriginIP/'+clientip})
        nodedb_raw=urllib2.urlopen(req, timeout = 1)
    except urllib2.URLError:
        get_nslookup_from_nodedb=0
    except socket.timeout:
        get_nslookup_from_nodedb=0
    except:
        get_nslookup_from_nodedb=0

    if (str(get_nslookup_from_nodedb)=="1"):
        try: node_dns=json.loads(nodedb_raw.read())
        except:
            node_dns={}
            get_nslookup_from_nodedb=0
    
    else: node_dns={}

    # get default route
    exec_command="/sbin/ip -4 r get 8.8.8.8 | head -1 | awk '{print $3,$5}'"
    defroute=subprocess.check_output(exec_command, shell=True).strip("\n ").split("\n")
    defr=defroute[0].split()
    defaultv4ip=defr[0]
    defaultv4dev=defr[1]
    defaultv4host=socket.getfqdn(defaultv4ip)

    # get routing table
    exec_command="/sbin/ip -4 r | grep -vE 'scope|default' | awk '{print $3,$1,$5}'"
    routinglist=subprocess.check_output(exec_command, shell=True).strip("\n ").split("\n")

    gatewaylist={}
    nodelist={}
    for route in routinglist:
        line=route.split()
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
    
    # get AirOS-Data
    band_outdoor={"5490":0,"5500":0,"5510":0,"5520":0,"5530":0,"5540":0,"5550":0,"5560":0,"5570":0,"5580":0,"5590":0,"5600":0,"5610":0,"5620":0,"5630":0,"5640":0,"5650":0,"5660":0,"5670":0,"5680":0,"5690":0,"5700":0,"5710":0}
    try: 
        f=open('/tmp/10-all.json', 'r')
        airos=json.loads(f.read())
        f.close()
    except:
        airos={}

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
    <meta name="author" content="">"""
    print '    <title>'+hostname+'</title>'
    print '    <link rel="shortcut icon" type="image/ico" href="https://'+hostname+'/css/favicon.ico">'
    print '    <link rel="apple-touch-icon" sizes="114x114" href="https://'+hostname+'/css/apple-touch-icon.png">'
    print '    <link rel="icon" type="image/png" href="https://'+hostname+'/css/favicon-32x32.png" sizes="32x32">'
    print '    <link rel="icon" type="image/png" href="https://'+hostname+'/css/favicon-16x16.png" sizes="16x16">'
    print '    <link rel="icon" type="image/x-icon" href="https://'+hostname+'/css/favicon.ico">'
    print """    <link href="/css/bootstrap.min.css" rel="stylesheet">
    </head>
  <body>
<div class="container">
    <div class="row">
<!-- Nav tabs -->
        <div class="card">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation"><a href="#main" aria-controls="main" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> &Uuml;bersicht</a></li>
                <li role="presentation" class="active"><a href="#status" aria-controls="status" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> Status</a></li>
                <li role="presentation"><a href="#contact" aria-controls="contact" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Kontakt</a></li>"""
    print '                <li role="presentation"><a href="#">'+ip+" - "+hostname+'</a></li>'
    if (str(show_link_to_adminlogin)=="1"):
        try:
            exec_command="grep -E \"https-port\" /config/config.boot"
            args = shlex.split(exec_command)
            port = subprocess.check_output(args)
            port = port.strip(" \n").split(" ")[1]
            print '                <li role="presentation"><a href="https://'+hostname+':'+str(port)+'/"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Login</a></li>'
        except:
            print ""
    
    print """            </ul><br>
            <div class="tab-content">
<!-- Main TAB -->
                <div role="tabpanel" class="tab-pane" id="main">
                    <div class="page-header">
                        <h1>Willkommen auf """
    print hostname+" <small>"+ip
    print """</small></h1>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-body"><b>WAS?</b></div>
                        <div class="panel-footer">FunkFeuer ist ein freies, experimentelles Netzwerk in Wien, Graz, der Weststeiermark, in Teilen des Weinviertels (N&Ouml;) und in Bad Ischl. Es wird aufgebaut und betrieben von computerbegeisterten Menschen. Das Projekt verfolgt keine kommerziellen Interessen.</div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-body"><b>FREI?</b></div>
                        <div class="panel-footer">FunkFeuer ist offen f&uuml;r jeden und jede, der/die Interesse hat und bereit ist mitzuarbeiten. Es soll dabei ein nicht reguliertes Netzwerk entstehen, welches das Potential hat, den digitalen Graben zwischen den sozialen Schichten zu &Uuml;berbr&uuml;cken und so Infrastruktur und Wissen zur Verf&uuml;gung zu stellen. Zur Teilnahme an FunkFeuer braucht man einen WLAN Router (gibt's ab 60 Euro) oder einen PC, das OLSR Programm, eine IP Adresse von FunkFeuer, etwas Geduld und Motivation. Auf unserer Karte ist eingezeichnet, wo man FunkFeuer schon &Uuml;berall (ungef&auml;r) empfangen kann (bitte beachte, dass manchmal H&auml;user oder &Auml;hnliches im Weg sind, dann geht's nur &uuml;ber Umwege).</div>
                    </div>
                </div>
<!-- Status TAB -->
                <div role="tabpanel" class="tab-pane active" id="status">
                    <dl class="dl-horizontal">
                      <dt>System Uptime <span class="glyphicon glyphicon-time" aria-hidden="true"></span></dt><dd>"""
    print uptime
    print """</dd>
                      <dt>mgmt Devices <span class="glyphicon glyphicon-signal" aria-hidden="true"></span></dt><dd>
                        <table class="table table-hover table-bordered table-condensed"><thead style="background-color:#f5f5f5;"><tr valign=top>
                        <!--td><b>HW Address</b></td--><td><b>Local IP</b></td><td><b>Hostname</b></td>
                        <td><b>Product</b></td><td><b>Uptime</b></td><td><b>Mode</b></td><td><b>ESSID</b></td><td><b>Firmware</b></td><td><b>Wireless</b></td></tr></thead><tbody>"""
    warn_frequency=0
    for key,device in enumerate(sorted(data['devices'], key=ip4_to_integer)):
        try:
            stationcount=str(airos[device['ipv4']]['wireless']['count'])
            frequency=str(airos[device['ipv4']]['wireless']['frequency'])
            frequency=frequency.replace("MHz","")
            frequency=frequency.strip(" ")
            frequency=int(frequency)
            chanbw=airos[device['ipv4']]['wireless']['chanbw']
            try:
                #works for AirOS8
                center1=airos[device['ipv4']]['wireless']['center1_freq']
                freq_start=center1-(chanbw/2)
                freq_end=center1+(chanbw/2)
                opmode=airos[device['ipv4']]['wireless']['mode']
                opmode.replace("sta-","")
                opmode.replace("ap-","")
                mixed=airos[device['ipv4']]['wireless']['compat_11n']
                if (mixed == 1): opmode=opmode+"-mixed"
                if (mixed == 0): opmode=opmode+"-ac"
            
            except: 
                #works for AirOS5,6
                opmode=airos[device['ipv4']]['wireless']['opmode']
                chwidth=airos[device['ipv4']]['wireless']['chwidth']
                opmode=opmode.lower()
                #chanbw=10,20,40  60,80?
                #chwidth=20
                #opmode=11NAHT20 11NAHT40PLUS 11NAHT40MINUS 11A
                freq_start=frequency-(chanbw/2)
                freq_end=frequency+(chanbw/2)
                if (opmode.find('plus') >0) : 
                    freq_end=freq_end+((chanbw-chwidth)/2)
                    freq_start=freq_start+((chanbw-chwidth)/2)
                
                if (opmode.find('minus') >0): 
                    freq_end=freq_end-((chanbw-chwidth)/2)
                    freq_start=freq_start-((chanbw-chwidth)/2)
            
            #add info to frequency-list
            for mhz,used in band_outdoor.items():
                # do not mind "head-on-head" channels
                if (freq_start+1 < int(mhz)  and int(mhz) < freq_end-1):
                    band_outdoor[mhz]=band_outdoor[mhz]+1
                
                if (band_outdoor[mhz]>1): warn_frequency=1
            
            if (stationcount>="1"):
                stationtext=""
                for stationnr,station in enumerate(airos[device['ipv4']]['connections']):
                    try:
                        hostmac=station['mac']
                    except:
                        hostmac="?"
                    
                    try:
                        hostname=station['name']
                        if (hostname==""): hostname=hostmac
                    except:
                        hostname=hostmac
                    
                    try:
                        hostremote=station['remote']['hostname']
                        if (hostremote==""): hostremote=hostmac
                    except:
                        hostremote=hostname
                    
                    try:
                        tx=str(int(station['tx']))
                        rx=str(int(station['rx']))
                        signal=str(station['signal'])
                    except:
                        tx="0"
                        rx="0"
                        signal="0"
                    
                    stationtext=stationtext+hostremote+ ": "+tx+"/"+rx+" ("+signal+")<br>"
            
            else:
                stationtext="no wifi connections"
            
            wirelessdata=stationtext
            if (authorized): wirelessdata=str(freq_start)+"-"+str(freq_end)+" ("+str(chanbw)+") "+opmode+"<br>"+wirelessdata

            try: 
                temperature=str(airos[device['ipv4']]['host']['temperature'])
                if (temperature=="0"): temperature=""
                else: temperature=' <span class="glyphicon glyphicon-dashboard" aria-hidden="true" style=\"font-size:80%\"></span>'+temperature+'&#176;'
            except: temperature=""
            
        except: 
            temperature=""
            if (device['wmode']==2) or (device['wmode']==3):
                wirelessdata='<span class="glyphicon glyphicon-question-sign" aria-hidden="true">'
            else:
                wirelessdata=''

        print "<tr>"
        #print "<!--td>"+device['hwaddr']+"</td-->"
        #print "<td title=\""+device['hwaddr']+"\">"+device['ipv4']+"</td>"
        print "<td style=\"font-size:60%\">"+device['ipv4']+"<br>"+device['hwaddr']+"</td>"
        print "<td>"+device['hostname']+"</td>"
        print "<td>"+device['product']+"</td>"
        print "<td>"+format_duration(device['uptime'])+"</td>"
        print "<td>"+format_wmode(device['wmode'])+"</td>"
        print "<td>"+device['essid']+"</td>"
        print "<td>"+parse_firmware(device['fwversion'])+temperature+"</td>"
        print "<td style=\"font-size:60%\">"+wirelessdata+"</td>"
        print "</tr>"

    
    print """</tbody></table>"""
    
    if (authorized and warn_frequency>0):
        print "<span style='color:red;'><b>Overlapping frequency!</b> Check "
        for mhz,used in band_outdoor.items():
            if (used > 1): print "<u>"+str(mhz)+"</u>MHz="+str(used)+" "
        
        print "</span><br><br>"    
    
    print """</dd>
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
        try: 
            g=gatewaylist[link[1]]
            g=str(len(g))
        except KeyError: g="0"
        print "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal"+link[1].replace(".","")+"\">"+g+"</button></td>"
        try: 
            l=nodelist[link[1]]
            l=str(len(l))
        except KeyError: l="0"
        print "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal"+link[1].replace(".","")+"_nodes\">"+l+"</button></td>"
        print "</tr>"

    print """</tbody></table></dd>
                      <dt>Trace to UPLINK <span class="glyphicon glyphicon-stats" aria-hidden="true"></span></dt><dd>
                        <table class="table table-hover table-bordered table-condensed"><thead style="background-color:#f5f5f5;"><tr valign=top><td><b>#</b></td><td><b>Hostname</b></td><td><b>IP Address</b></td>
                        <td><b>Ping</b></td></tr></thead><tbody>
"""
    exec_command="/usr/bin/traceroute -4 -w 1 -q 1 "+traceroute_to
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
elif (GET.get('get') == "connections"):
    show_connections()
elif (GET.get('get') == "airos"):
    show_airos()
elif (GET.get('get') == "ipv6"):
    show_ipv6()
elif (GET.get('get') == "ipv4"):
    show_ipv4()
elif (GET.get('get') == "test"):
    show_test()
else:
    show_html()
