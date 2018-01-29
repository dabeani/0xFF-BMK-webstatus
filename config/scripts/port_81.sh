#!/bin/vbash
source /opt/vyatta/etc/functions/script-template
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
session_env=$(echo $session_env | sed -e 's/active declare/active; declare/')
eval $session_env
cli-shell-api setupSession

configure
set service gui http-port 81
commit
save
exit
