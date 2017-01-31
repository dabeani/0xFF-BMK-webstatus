#!/bin/bash

# Opening up firewall on port 80
CHAIN=$( iptables -L | awk '/^Chain WAN/ && /LOCAL/ {print $2;}' )
iptables -I $CHAIN 1 -p tcp --dport 80 -j ACCEPT

# Run renewal script
python /config/letsencrypt/acme_tiny.py --account-key /config/letsencrypt/account.key --csr /config/letsencrypt/domain.csr --acme-dir /config/custom/www/.well-known/acme-challenge/ > /config/letsencrypt/signed.crt

# Removing firewall rule added earlier
iptables -D $CHAIN 1

# Copying files to lighttpd directory on success only (file domain.key is not empty)
if [ -f "/config/letsencrypt/signed.crt" ] && [ ! $(stat -c %s /config/letsencrypt/signed.crt) -eq 0 ]; then
  cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
  cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

  # Restarting original lighttpd webserver for EdgeOS
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid
  if [ -f "/var/run/lighttpd.pid" ]; then
    rm /var/run/lighttpd.pid
  fi
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd.pid \
        --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf
  
  # Restart custom lighttpd webserver for custom scripts
  # only if default webserver is not configured to run on port 80/443...
  # CPO: improve: custom-server on port http-80, stock-server von https-443 should work as well!
  if [ $(grep "https-port 443" /config/config.boot | wc -l) -eq 0 ] && [ $(grep "http-port 80" /config/config.boot | wc -l) -eq 0 ]; then
    sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
    if [ -f "/var/run/lighttpd_custom.pid" ]; then
      rm /var/run/lighttpd_custom.pid
    fi
    sudo /sbin/start-stop-daemon --start --quiet \
          --pidfile /var/run/lighttpd_custom.pid \
          --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
  fi
fi
