#!/bin/bash
source /opt/vyatta/etc/functions/script-template
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
session_env=$(echo $session_env | sed -e 's/active declare/active; declare/')
echo $PPID >/tmp/port_81s.log
eval $session_env >>/tmp/port_81s.log
cli-shell-api setupSession

cmd="/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper"
tfile=$(mktemp)
(
$cmd begin
ret=0
if [ $ret == 0 ]; then
$cmd set service gui http-port 81 || ret=1
fi
if [ $ret == 0 ]; then
$cmd commit || ret=1
fi
if [ $ret == 0 ]; then
$cmd save || ret=1
fi
$cmd end
exit $ret
) >$tfile 2>&1
ret=$?
output=$(cat $tfile)
rm -f $tfile
echo $output
echo $output >>/tmp/port_81s.log

exit 0
