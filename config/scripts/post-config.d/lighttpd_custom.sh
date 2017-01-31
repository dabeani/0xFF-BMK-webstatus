#!/bin/sh

# create missing log-directory if needed
if [ ! -d "/var/log/lighttpd_custom" ]; then
  mkdir /var/log/lighttpd_custom
  chown www-data:www-data /var/log/lighttpd_custom
fi

# re-establish monthly renewal if needed
if [ ! -L /etc/cron.monthly/letsrenew.sh ]; then
  ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh
fi

# re-establish current certificate file, only of domain.key is not of zero file-size
# original server.pem contains "BEGIN PRIVATE KEY", whereas LE-signed server.pem includes "BEGIN RSA PRIVATE KEY".
# only renew server.pem file if needed and signature file is >0 bytes
if [ -f "/config/letsencrypt/signed.crt" ] && [ ! $(stat -c %s /config/letsencrypt/signed.crt) -eq 0 ] && [ $(grep "BEGIN RSA PRIVATE KEY" /etc/lighttpd/server.pem | wc -l) -eq 0 ]
  then
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
  
  # stop custom webserver if already running
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
  if [ -f "/var/run/lighttpd_custom.pid" ]; then
    rm /var/run/lighttpd_custom.pid
  fi
fi

# Start custom webserver
# CPO: improve: custom-server on port http-80, stock-server von https-443 should work as well!
if [ $(grep "https-port 443" /config/config.boot | wc -l) -eq 0 ] && [ $(grep "http-port 80" /config/config.boot | wc -l) -eq 0 ]
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd_custom.pid \
        --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
fi
