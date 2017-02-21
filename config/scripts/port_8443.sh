#!/bin/vbash
    
source /opt/vyatta/etc/functions/script-template
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
eval $session_env
cli-shell-api setupSession

configure
set service gui https-port 8443
commit
save
exit
