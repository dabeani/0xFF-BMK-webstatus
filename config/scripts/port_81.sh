#!/bin/vbash
    
source /opt/vyatta/etc/functions/script-template
    
configure
set service gui http-port 81
commit
save
exit
