#!/bin/bash
# put version info to better to support status.py

#get autoupdate-settings
[ -L /etc/cron.daily/autoupdatewizards ] && auon="yes" || auon="no"
[ $(grep "wizard-autoupdate=yes" /config/user-data/autoupdate.dat 2>/dev/null | wc -l) == 1 ] && aa="on"    || aa="off"
[ $(grep "wizard-olsrd_v1=yes"   /config/user-data/autoupdate.dat 2>/dev/null | wc -l) == 1 ] && aa1="on"   || aa1="off"
[ $(grep "wizard-olsrd_v2=yes"   /config/user-data/autoupdate.dat 2>/dev/null | wc -l) == 1 ] && aa2="on"   || aa2="off"
[ $(grep "wizard-0xffwsle=yes"   /config/user-data/autoupdate.dat 2>/dev/null | wc -l) == 1 ] && aale="on"  || aale="off"
[ $(grep "wizard-ebtables=yes"   /config/user-data/autoupdate.dat 2>/dev/null | wc -l) == 1 ] && aaebt="on" || aaebt="off"

#get wizard versions
for i in $(ls /config/wizard/feature/*/wizard-run 2>/dev/null); do
    vers=$(head -n 10 $i | grep -ioE -m 1 'version.*' | awk -F' ' '{print $2;}' | tr -d "[]() ")
    [ $(head -n 10 $i | grep -l 'OLSRd_V1' | wc -l) == 1 ] && olsrv1=$vers && continue
    [ $(head -n 10 $i | grep -l 'OLSRd_V2' | wc -l) == 1 ] && olsrv2=$vers && continue
    [ $(head -n 10 $i | grep -l '0xFF-BMK-Webstatus-LetsEncrypt' | wc -l) == 1 ] && wsle=$vers && continue
    [ $(head -n 10 $i | grep -l 'ER-wizard-ebtables' | wc -l) == 1 ] && ebtables=$vers && continue
    [ $(head -n 10 $i | grep -l 'ER-wizard-AutoUpdate' | wc -l) == 1 ] && autoupdate=$vers && continue
done
[ ! "$olsrv1" ] && olsrv1="n/a" && aa1="n/a"
[ ! "$olsrv2" ] && olsrv2="n/a" && aa2="n/a"
[ ! "$wsle" ] && wsle="n/a" && aale="n/a"
[ ! "$autoupdate" ] && autoupdate="n/a" && aa="n/a"
[ ! "$ebtables" ] && ebtables="n/a" && aaebt="n/a"
bmkwebstatus=$(head -n 12 /config/custom/www/cgi-bin-status*.php 2>/dev/null | grep -m1 version= | cut -d'"' -f2)
[ ! "$bmkwebstatus" ] && bmkwebstatus="n/a"

#get olsrd4-watchdog setting
if [ $(grep -c LoadPlugin.*olsrd_watchdog /config/user-data/olsrd4.conf) \> 0 ] && [[ $(grep LoadPlugin.*olsrd_watchdog /config/user-data/olsrd4.conf) != \#* ]]; then
    olsrd4watchdog="on"
else
    olsrd4watchdog="off"
fi

#get local ips
ints=$(sed -e 's/[ \t]*#.*$//' -e '/^[ ]*$/d' /config/user-data/olsrd4.conf | grep -iw interface | tr -d '"' | tr '[:upper:]' '[:lower:]' | awk '{print $2}'; awk -F= '/MESH_IF=/ { print $2 }' /config/user-data/olsrd.default 2>/dev/null | tr -d \")
v4=$(ip -4 -o addr show | grep -wE "$ints" | awk {'print $4'} | awk -F/ {'print $1'})
orig=$(if [ $(ps ax | grep olsrd2.conf | grep -v grep | awk {'print $7'} | wc -l) == "1" ]; then curl -s --connect-timeout 1 http://127.0.0.1:8000/telnet/olsrv2info%20originator 2>/dev/null | grep : | head -n 1; else echo "n/a"; fi)
[ ! "$orig" ] && orig="n/a"
[ ! "$v4" ] && v4="n/a"
v6=$(ip -6 addr show lo | grep global | awk {'print $2'} | awk -F/ {'print $1'} | grep -iv $orig)
[ ! "$v6" ] && v6="n/a"

#get link local addresses and S/N
output='"serial":"'$(ip -6 link show eth0 | grep link/ether | awk {'gsub(":","",$2); print toupper($2)'})'"'
for line in $(ip -6 -h -br a | grep -oE "^.{0,15}|fe80::.{10,25}\/64"); do
  if [ $(echo $line | grep -ic fe80) -eq 0 ]; then
    interface=$(echo $line | cut -d"@" -f1)
    linklocal=""
    continue
  else
    [ "$linklocal" ] && linklocal=$linklocal","
    linklocal=$linklocal$line
  fi
  output=$output",\"$interface\":\"$linklocal\""
done

echo -n '{'
echo -n '"wizards":{"olsrv1":"'$olsrv1'","olsrv2":"'$olsrv2'","0xffwsle":"'$wsle'","bmk-webstatus":"'$bmkwebstatus'","ebtables":"'$ebtables'"},'
echo -n '"local_ips":{"ipv4":"'$v4'","ipv6":"'$v6'","originator":"'$orig'"},'
echo -n '"autoupdate":{"installed":"'$autoupdate'","enabled":"'$auon'","aa":"'$aa'","olsrv1":"'$aa1'","olsrv2":"'$aa2'","0xffwsle":"'$aale'","ebtables":"'$aaebt'"},'
echo -n '"olsrd4watchdog":{"state":"'$olsrd4watchdog'"},'
echo -n '"linklocals":{'$output'},'
echo -n '"bootimage":{"md5":"'$(/usr/bin/md5sum /dev/mtdblock2 | cut -f1 -d" ")'"}'
echo    '}'

exit 0
