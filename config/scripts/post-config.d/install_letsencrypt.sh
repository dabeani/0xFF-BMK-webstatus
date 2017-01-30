#!/bin/bash

# re-establish monthly renewal
ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh

# re-establish current certificate file
cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

# Restarting original lighttpd webserver for EdgeOS
ps aux | grep lighttpd.conf | awk '{print $2;}' | head -n1 | xargs kill
/usr/sbin/lighttpd -f /etc/lighttpd/lighttpd.conf
# Restart custom lighttpd webserver for custom scripts
ps aux | grep lighttpd_custom.conf | awk '{print $2;}' | head -n1 | xargs kill
/usr/sbin/lighttpd -f /config/custom/lighttpd/lighttpd_custom.con
