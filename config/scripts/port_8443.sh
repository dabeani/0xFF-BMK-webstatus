#!/bin/vbash
    
source /opt/vyatta/etc/functions/script-template
    
configure
set service gui https-port 8443
commit
save
exit
