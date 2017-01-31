# 0xFF-BMK-webstatus
0xFF-BMK-webstatus for EdgeRouters / EdgePoints
including Let's-Encrypt scripts to install and renew certificate

!!! since Version 3.0+ the default Webserver Ports (80 & 443) must be set to different ones, because that tool now uses it own WebServer to not touch the default Webserver-Configuration. Useful Ports (81 and 8443). we plan to use a separate wizard to modify the hardcoded ports with you own values. Please change your default WebServer PORTS FIRST. !!!

!!! since Version 4.0+ the detault Webserver Ports (80 & 443) will be automaticalle changed to 81 & 8443 !!!

Adds a Status-Page
- Node-Status with olsrd links, vlan1100 devices, traceroute to 0xFF-gateway
- Port-table with connected devices
- json status API

Possible GET-Params
- default: show olsr-status, 1100 devices, traceroute, port-table
- get=status --> json output with devices of vlan 1100
- get=phpinfo
- get=devices same like status, but as plain text

Let's Encrypt starting Version 4.0
- registeres a LetsEncrypt-Cerificate for the publix FQDN (active internet connection needed)
- monthly renewal cronjob (active internet connection needed)

Parameters directly in php source coude header:
- $interface_1100_list: names of management interfaces/vlans to scann devices for
- $get_nslookup_from_nodedb: lookup IPs from Funkfeuer Map data source (DNS, HNA, MID, node names)
- $show_link_to_adminlogin: add link to default EdgeMax login-page (including customized https-port!)
- $traceroute_to: IP-address destination for traceroute table

Build link: http://193.238.158.8/0xFF-BMK-webstatus/builds/
