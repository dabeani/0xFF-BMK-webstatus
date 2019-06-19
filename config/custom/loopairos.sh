#!/bin/bash
LOG=/var/log/0xffloopairos.log

INTERFACES=$(grep interface_1100_list /config/custom/www/settings.inc | awk -F "'" '{print $2}')

PORT=$(grep -i port= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$PORT" ] || PORT=22
USER=$(grep -i user= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$USER" ] || USER="ubnt"

## find correct *local* management vlan
SEARCH=$(ip -4 -o addr | grep -E ": "$(echo $INTERFACES | sed 's/,/\|/g'))
if [ $(echo "$SEARCH" | grep -coE "10\..{3,7}\.[12]0[012345]/.{1,2}") == "1" ]; then
  # easiest: theres only one single ip if range 10.*
  LANSEGM=$(echo "$SEARCH" | grep -oE "10\..{3,7}\.[12]0[012345]/.{1,2}" | head -n 1 | awk -F. {'print $1"."$2"."$3"."'})
elif (echo "$SEARCH" | grep -qE "192\.168\..{3,7}/.{1,2}"); then
  # easiest2: take first ip of range 192.168.*
  LANSEGM=$(echo "$SEARCH" | grep -oE "192\.168\..{3,7}/.{1,2}" | head -n 1 | awk -F. {'print $1"."$2"."$3"."'})
elif [ $(echo "$SEARCH" | grep -coE "10\..{3,7}\.[12]0[012345]/.{1,2}") \> 1 ]; then
  # there are several 10.* addresses, try to fetch nodeID from map database
  nodeid=$(curl -4s --connect-timeout 1 --speed-time 1 https://ff.cybercomm.at/mynodeid.php 2>/dev/null)
  if [ "$nodeid" ]; then
    # if found, use node-id
    t=$(printf "%0$((6-${#nodeid}))d%s" 0 $nodeid)
    LANSEGM="10."$((10#${t:1:3}))"."$((10#${t:4:6}))"."
  elif [ $(echo "$SEARCH" | grep -coE "10\..{3,7}\.100/.{1,2}") \> 0 ]; then
    # if not found, take lastes address in format 10.*.*.100
    LANSEGM=$(echo "$SEARCH" | grep -oE "10\..{3,7}\.100/.{1,2}" | tail -n 1 | awk -F. {'print $1"."$2"."$3"."'})
  else
    # if no 10.*.*.100 exist, take lastes 10.*.*.???
    LANSEGM=$(echo "$SEARCH" | grep -oE "10\..{3,7}\.[12]0[012345]/.{1,2}" | tail -n 1 | awk -F. {'print $1"."$2"."$3"."'})
  fi
fi
[ "$INTERFACES" ] &&
 ANTENNEN=$(/usr/sbin/ubnt-discover -i "$INTERFACES" | grep -E "P5B|NB5|N5B|P5C|N5C|N5N|AG5|B5N|LM5|L5C|LB5|R5C" | grep $LANSEGM) ||
 ANTENNEN=$(/usr/sbin/ubnt-discover                  | grep -E "P5B|NB5|N5B|P5C|N5C|N5N|AG5|B5N|LM5|L5C|LB5|R5C" | grep $LANSEGM)
echo "${ANTENNEN[@]}" >>$LOG

for IP in $(echo "${ANTENNEN[@]}" | awk {'print $3'}); do
  echo "Fetching status from "$IP"..." >>$LOG
  (grep -qi "user-"$IP"=" /config/letsencrypt/deploysetting.dat) && THISUSER=$(grep -i "user-"$IP"=" /config/letsencrypt/deploysetting.dat | cut -d"=" -f2) || THISUSER=$USER
  ssh -o "StrictHostKeyChecking=no" -o "UserKnownHostsFile=/dev/null" -o "PasswordAuthentication=no" -o "LogLevel=error" -p $PORT $THISUSER@$IP 'sh' </config/custom/getairos.sh 2>>$LOG >/tmp/$IP.tmp
  result=$?
  if [ $result -eq 0 ] && [ -s /tmp/$IP.tmp ]; then
    dos2unix /tmp/$IP.tmp
    sed -i 's/Content-Type: application\/json//g' /tmp/$IP.tmp
    sed -i '$!N;s/}\n,"connections":/},"connections":/;P;D' /tmp/$IP.tmp
    sed -i 's/},"connections":/,"connections":/' /tmp/$IP.tmp
    jq -M -r '.host.devmodel' /tmp/$IP.tmp >>$LOG 2>>$LOG
    mv /tmp/$IP.tmp /tmp/$IP.json 2>>$LOG
  else
    echo "error "$result": something went wrong" >>$LOG
  fi
done

## combine all antennas to a single json array (only responses from the last 3h)
LIST=$(find /tmp/10.*.json /tmp/192.168*.json -mmin -185 2>/dev/null)
t=$(echo "$LIST" | wc -l)
echo -n "{" >/tmp/10-all.json
for i in $LIST; do
  t=$(($t-1))
  IP=$(basename $i .json)
  echo '"'$IP'":' >>/tmp/10-all.json
  cat $i >>/tmp/10-all.json 2>>$LOG
  [ $t -eq 0 ] || echo -n "," >>/tmp/10-all.json
done
echo '}' >>/tmp/10-all.json

## validate (skip on mips64 due to jq bug)
[ $(uname -m) == "mips64" ] || jq '.' /tmp/10-all.json >/dev/null 2>>$LOG
