#!/bin/bash
/usr/www/status.cgi | sed 's/^M//g'
echo ',"connections":'
/usr/sbin/wstalist
echo '}'
