#!/bin/bash

## make a nice list of connected devices
bridges=$(/usr/sbin/brctl show | awk '$0~/^br[0-9]/ {print $1}')
arplist=$(/usr/sbin/arp -e -n | awk '{if ($0!~/incomplete|HWaddress/) {print "   "$5"    \t"toupper($3)" \t"$1}}')
locals=$(/bin/ip l | grep ether | awk '!x[$2]++ {print toupper($2)}')
disc=$(/usr/sbin/ubnt-discover -d 500 -V -j | jq -M '.devices[]')
for portstring in $(/bin/ip addr show | grep -E "^[0-9]|link\/ether" | awk '{gsub("@"," ",$0); print $1" "$2}' | sed '$!N;s/\n\s*link\/ether//;P;D' | awk '($3~/:/ && $2~/eth/){gsub(":","",$2); print $2","toupper($3)}' | sort); do
  port=$(echo $portstring | cut -d "," -f1)
  portmac=$(echo $portstring | cut -d "," -f2)
  echo "Port "$port" "$(cat /opt/vyatta/config/active/interfaces/ethernet/$(echo $port | sed 's/\./\/vif\//')/description/node.val 2>/dev/null)
  for bridge in $bridges; do
    [ $(/usr/sbin/brctl show $bridge | grep -wce "$port\$") -eq 0 ] && continue
    bridgeport=$(/usr/sbin/brctl showstp $bridge | grep -E "^$port " | cut -d "(" -f2 | tr -d ")")
    neighmacs=$(/usr/sbin/brctl showmacs $bridge | awk '($1 == '$bridgeport') {print toupper($2)}')
    for neigh in $neighmacs; do
       [ $(echo "$locals" | grep -ic $neigh) -eq 0 ] || continue
       line=$(echo "$arplist" | grep -iE "^   "$bridge".*"$neigh | head -n1)
       ip=$(echo "$line" | awk '{print $3}')
       [ "$ip" ] && discline=$(echo "$disc" | jq -M 'select (.addresses[].ipv4=="'$ip'") | {hostname:.hostname,product}' | grep '"' | sed 'N;s/\n//g'  | awk -F'"' '{print $8" ("$4")"}' | head -n1) || discline=""
       [ "$line" ] || line="x  $bridge        $neigh "$(echo "$disc" | jq -M 'select ((.addresses[].hwaddr=="'$neigh'") or (.hwaddr=="'$neigh'")) | {hostname:.hostname,product}' | grep '"' | sed 'N;s/\n//g'  | awk -F'"' '{print $8" ("$4")"}' | head -n1)
       echo " .$line $discline"
    done
  done
  for line in $(echo "$arplist" | grep -iE "^   $port " | awk '{print $1","toupper($2)","$3}'); do
    ip=$(echo "$line" | awk -F, '{print $3}')
    [ "$ip" ] && discline=$(echo "$disc" | jq -M 'select (.addresses[].ipv4=="'$ip'") | {hostname:.hostname,product}' | grep '"' | sed 'N;s/\n//g'  | awk -F'"' '{print $8" ("$4")"}' | head -n1) || discline=""
    echo " ~ $line,$discline" | sed 's/,/\t/g'
  done
  echo "_"
done

exit 0
