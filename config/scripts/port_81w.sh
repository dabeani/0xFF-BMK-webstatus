#!/bin/vbash
eval $(echo $(/bin/cli-shell-api getSessionEnv $PPID) | sed -e 's/active declare/active; declare/')
cli-shell-api setupSession

/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper begin
/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper set service gui http-port 81
/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper commit
/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper end

