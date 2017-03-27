# 0xFF-BMK-webstatus
0xFF-BMK-webstatus for EdgeRouters / EdgePoints
including Let's-Encrypt scripts to install and renew certificate

Version 4.5 installs without the need to change Original-Server http/https-ports. You can adopt all 4 port settings from within the wizard afterwards. Anyway, on initial install, http:80 will be moved to http:81 to better support cgi-bin-status API.

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
