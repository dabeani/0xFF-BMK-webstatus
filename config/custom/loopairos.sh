#!/bin/bash
LOG=/var/log/0xffloopairos.log

PORT=$(grep -i port= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$PORT" ] || PORT=22
USER=$(grep -i user= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$USER" ] || USER="ubnt"
LANSEGM=$(ip -4 addr | grep -oE "10\..{3,7}\.[12]0[012345]/.{1,2}" | head -n 1 | awk -F. {'print $1"."$2"."$3"."'})
ANTENNEN=$(/usr/sbin/ubnt-discover | grep -E "P5B|NB5|N5B|P5C|N5C|N5N|AG5|B5N|LM5|L5C" | grep $LANSEGM)
echo "${ANTENNEN[@]}" >>$LOG

for IP in $(echo "${ANTENNEN[@]}" | awk {'print $3'}); do
  echo "Fetching status from "$IP"..." >>$LOG
  ssh -o "StrictHostKeyChecking=no" -o "UserKnownHostsFile=/dev/null" -o "PasswordAuthentication=no" -o "LogLevel=error" -p $PORT $USER@$IP 'sh' </config/custom/getairos.sh 2>>$LOG >/tmp/$IP.tmp
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

## combine all antennas to a single json array
LIST=$(find /tmp/10.*.json)
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

## validate
jq '.' /tmp/10-all.json >/dev/null 2>>$LOG
