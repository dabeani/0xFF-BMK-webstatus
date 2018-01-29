#!/bin/vbash
source /opt/vyatta/etc/functions/script-template
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
eval $session_env
cli-shell-api setupSession

configure
set service gui http-port 81
commit
save
exit
