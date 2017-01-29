#!/bin/bash

# re-establish monthly renewal
ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh

# re-establish current certificate file
cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

# restart both lighttpd instances
ps -e | grep lighttpd | awk '{print $1;}' | xargs kill
/usr/sbin/lighttpd -f /etc/lighttpd/lighttpd.conf
/usr/sbin/lighttpd -f /config/custom/lighttpd/lighttpd_custom.conf
