# 0xFF-BMK-webstatus
0xFF-BMK-webstatus for EdgeRouters / EdgePoints

!!! since Version 3.0+ the default Webserver Ports (80 & 443) must be set to different ones, because that tool now uses it own WebServer to not touch the default Webserver-Configuration. useful Ports (81 and 8443). we plan to use a separate wizard to modify the hardcoded ports with you own values. Please change your default WebServer PORTS FIRST. !!!

Adds a Link to Login-Screen to show
- Node-Status with olsrd links, vlan1100 devices, traceroute to 0xFF-gateway
- Port-table with connected devices
- json status API

Possible GET-Params
- default: show olsr-status, 1100 devices, traceroute, port table
- get=status --> json output with devices of vlan 1100
- get=phpinfo
- get=devices same like status, but as plain text

Build link: http://193.238.158.8/0xFF-BMK-webstatus/builds/
