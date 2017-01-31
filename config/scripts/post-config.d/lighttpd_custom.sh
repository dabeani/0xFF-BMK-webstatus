#!/bin/sh

# create missing log-directory if needed
if [ ! -d "/var/log/lighttpd_custom" ]; then
  mkdir /var/log/lighttpd_custom
  chown www-data:www-data /var/log/lighttpd_custom
fi

# re-establish monthly renewal
ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh

# re-establish current certificate file, only of domain.key is not of zero file-size
if [ -f "/config/letsencrypt/domain.key" ] && [ ! $(stat -c %s /config/letsencrypt/domain.key) -eq 0 ]
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
  
  # Restart custom lighttpd webserver for custom scripts
  # only if default webserver is not configured to run on port 80/443...
  # CPO: improve: custom-server on port http-80, stock-server von https-443 should work as well!
  if [ $(grep "https-port 443" /config/config.boot | wc -l) -eq 0 ] && [ $(grep "http-port 80" /config/config.boot | wc -l) -eq 0 ]
    sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
    if [ -f "/var/run/lighttpd_custom.pid" ]; then
      rm /var/run/lighttpd_custom.pid
    fi
    sudo /sbin/start-stop-daemon --start --quiet \
          --pidfile /var/run/lighttpd_custom.pid \
          --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
  fi
fi
