# 0xFF-BMK-webstatus
0xFF-BMK-webstatus for EdgeRouters / EdgePoints
including Let's-Encrypt scripts to install and renew certificate

!!! since Version 3.0+ the default Webserver Ports (80 & 443) must be set to different ones, because that tool now uses it own WebServer to not touch the default Webserver-Configuration. Useful Ports (81 and 8443). we plan to use a separate wizard to modify the hardcoded ports with you own values. Please change your default WebServer PORTS FIRST. !!!

configure
set system gui http-port 81
set system gui https-port 8443
commit
save

Adds a Link to Login-Screen to show
- Node-Status with olsrd links, vlan1100 devices, traceroute to 0xFF-gateway
- Port-table with connected devices
- json status API

Possible GET-Params
- default: show olsr-status, 1100 devices, traceroute, port table
- get=status --> json output with devices of vlan 1100
- get=phpinfo
- get=devices same like status, but as plain text

Let's Encrypt prepared starting Version 4.0
- in CLI run "sudo /config/letsencrypt/install_once.sh" to initialize, enter your FQDN
- registration and monthly renewal needs active internet connection
- registration-fail might destroy server.pem, use "sudo /config/letsencrypt/repair.sh" 
  to reset to default server.pem and re-launch both lighttpd instances

Build link: http://193.238.158.8/0xFF-BMK-webstatus/builds/
