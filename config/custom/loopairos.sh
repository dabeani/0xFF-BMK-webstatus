#!/bin/bash
PORT=$(grep -i port= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$PORT" ] || PORT=22
USER=$(grep -i user= /config/letsencrypt/deploysetting.dat 2>/dev/null | cut -d= -f2)
[ "$USER" ] || USER="ubnt"
LANSEGM=$(ip -4 addr | grep -oE "10\..{3,7}\.[12]0[012345]/.{1,2}" | head -n 1 | awk -F. {'print $1"."$2"."$3"."'})
ANTENNEN=$(/usr/sbin/ubnt-discover | grep -E "P5B|NB5|N5B|P5C|N5C|N5N|AG5|B5N|LM5|L5C" | grep $LANSEGM)
echo "${ANTENNEN[@]}"

for IP in $(echo "${ANTENNEN[@]}" | awk {'print $3'}); do
  echo "Fetching status from "$IP"..."
  ssh -o "StrictHostKeyChecking=no" -o "UserKnownHostsFile=/dev/null" -o "PasswordAuthentication=no" -p $PORT $USER@$IP 'sh' </config/custom/getairos.sh 2>/dev/null >/tmp/$IP.tmp
  result=$?
  echo "Closing connection   "$IP"..."
  echo "Result-Code: "$result
  if [ $result -eq 0 ] && [ -s /tmp/$IP.tmp ]; then
    dos2unix /tmp/$IP.tmp
    sed -i 's/Content-Type: application\/json//g' /tmp/$IP.tmp
    sed -i '$!N;s/}\n,"connections":/},"connections":/;P;D' /tmp/$IP.tmp
    sed -i 's/},"connections":/,"connections":/' /tmp/$IP.tmp
    jq -M -r '.host.devmodel' /tmp/$IP.tmp
    mv /tmp/$IP.tmp /tmp/$IP.json
  elif
    echo "error: something went wrong"
  fi
  echo ""
done
