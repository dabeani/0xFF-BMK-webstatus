#!/bin/vbash
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
session_env=$(echo $session_env | sed -e 's/active declare/active; declare/')
eval $session_env
cli-shell-api setupSession

WRAPPER=/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper

$WRAPPER begin
$WRAPPER set service gui http-port 81
$WRAPPER commit
$WRAPPER end

