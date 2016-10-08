# 0xFF-BMK-webstatus
0xFF-BMK-webstatus for EdgeRouters / EdgePoints

Adds a Link to Login-Screen to show
- Node-Status with olsrd links, vlan1100 devices, traceroute to 0xFF-gateway
- Port-table with connected devices
- json status API

Possible GET-Params
- default: show olsr-status, 1100 devices, traceroute
- get=table: show port table, bridges, connected devices per interface
- get=status --> json output with devices of vlan 1100
- get=phpinfo
- get=devices same like status, but as plain text

Build link: http://193.238.158.8/0xFF-BMK-webstatus/builds/
