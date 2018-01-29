#!/bin/vbash
session_env=`/bin/cli-shell-api getSessionEnv $PPID`
eval $session_env
cli-shell-api setupSession

WRAPPER=/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper

$WRAPPER begin
$WRAPPER set service gui http-port 81
$WRAPPER commit
$WRAPPER end
