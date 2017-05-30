#!/bin/bash
# put version info to getter to support status.py

olsrv1=$([ $(grep -l 'OLSRd_V1'  /config/wizard/feature/*/wizard-run | wc -l) == 1 ] && head -n 10 $(grep -l 'OLSRd_V1'  /config/wizard/feature/*/wizard-run) | grep -ioE -m 1 'version.*' | awk -F' ' {'print $2;'} || echo 'not installed')
olsrv2=$([ $(grep -l 'OLSRd_V2'  /config/wizard/feature/*/wizard-run | wc -l) == 1 ] && head -n 10 $(grep -l 'OLSRd_V2'  /config/wizard/feature/*/wizard-run) | grep -ioE -m 1 'version.*' | awk -F' ' {'print $2;'} || echo 'not installed')
wsle=$([ $(grep -l '0xFF-WSLE' /config/wizard/feature/*/wizard-run | wc -l) == 1 ] && head -n  8 $(grep -l '0xFF-WSLE' /config/wizard/feature/*/wizard-run) | grep -ioE -m 1 'version.*' | awk -F' ' {'print $2;'} || echo 'not installed')
bmkwebstatus=$(head -n 12 /config/custom/www/cgi-bin-status.php 2>/dev/null | grep version= | cut -d'"' -f2 | head -n 1)

v4=$(ip -4 addr show $(awk -F= '/MESH_IF=/ { print $2 }' /config/user-data/olsrd.default | tr -d \") | grep inet | awk {'print $2'} | awk -F/ {'print $1'})
orig=$(if [ $(ps ax | grep olsrd2.conf | grep -v grep | awk {'print $7'} | wc -l) == "1" ]; then curl -s --connect-timeout 1 http://127.0.0.1:8000/telnet/olsrv2info%20originator 2>/dev/null | grep : | head -n 1; else echo "n/a"; fi)
[ ! "$orig" ] && orig="n/a"
v6=$(ip -6 addr show lo | grep global | awk {'print $2'} | awk -F/ {'print $1'} | grep -iv $orig)

echo -n '{"wizards":{"olsrv1":"'$olsrv1'","olsrv2":"'$olsrv2'","0xffwsle":"'$wsle'","bmk-webstatus":"'$bmkwebstatus'"},'
echo    '"local_ips":{"ipv4":"'$v4'","ipv6":"'$v6'","originator":"'$orig'"}}'

exit 0
