#!/bin/bash

# Opening up firewall on port 80
CHAIN=$( iptables -L | awk '/^Chain WAN/ && /LOCAL/ {print $2;}' )
iptables -I $CHAIN 1 -p tcp --dport 80 -j ACCEPT

# Run renewal script
python /config/letsencrypt/acme_tiny.py --account-key /config/letsencrypt/account.key --csr /config/letsencrypt/domain.csr --acme-dir /config/custom/www/.well-known/acme-challenge/ > /config/letsencrypt/signed.crt

# Removing firewall rule added earlier
iptables -D $CHAIN 1

# Copying files to lighttpd directory on success
if [ -s "/config/letsencrypt/domain.key" ]
  then
  cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
  cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

  # Restarting original lighttpd webserver for EdgeOS
  ps aux | grep lighttpd.conf | awk '{print $2;}' | head -n1 | xargs kill
  /usr/sbin/lighttpd -f /etc/lighttpd/lighttpd.conf
  # Restart custom lighttpd webserver for custom scripts
  ps aux | grep lighttpd_custom.conf | awk '{print $2;}' | head -n1 | xargs kill
  /usr/sbin/lighttpd -f /config/custom/lighttpd/lighttpd_custom.conf
fi
