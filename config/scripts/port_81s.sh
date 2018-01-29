#!/bin/bash
source /opt/vyatta/etc/functions/script-template
echo "SessionID "$_  >/tmp/port_81s.log
echo "PPID "$PPID   >>/tmp/port_81s.log
[ ! "$PPID" ] && PPID=$_
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
eval $session_env
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
