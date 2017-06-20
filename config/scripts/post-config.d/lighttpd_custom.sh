#!/bin/sh
#post-config.d/lighttpd_custom.sh
#re-establishes server.pem with certificate (if possible or needed)
#re-establishes renew-cronjob (if missing)
#starts custom lighttpd

# create missing log-directory if needed
if [ ! -d "/var/log/lighttpd_custom" ]; then
  mkdir /var/log/lighttpd_custom
  chown www-data:www-data /var/log/lighttpd_custom
fi

# re-establish monthly renewal if needed
if [ ! -L /etc/cron.monthly/letsrenew.sh ]; then
  ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh
fi

# re-establish daily restart script
if [ ! -L "/etc/cron.daily/restartservers.sh" ] && [ -f /config/letsencrypt/restartservers.sh ]; then
    ln -sf /config/letsencrypt/restartservers.sh /etc/cron.daily/restartservers.sh
fi

# 1.9.7aplha3 and later has no preinstalled php5 - remove php-setup from config
# this logic supports only oneway php->python, will not reestablish php-setup later on!
if [ ! -d /var/run/php5 ] && [ "$(grep -ni conf-enabled/15-fastcgi-php.conf /config/custom/lighttpd/lighttpd_custom.conf | wc -l)" == "1" ]; then
    linenumber=$(grep -ni "conf-enabled/15-fastcgi-php.conf" /config/custom/lighttpd/lighttpd_custom.conf | awk -F: {'print $1'})
    sed -i $linenumber'd' /config/custom/lighttpd/lighttpd_custom.conf
    #rm /config/custom/lighttpd/conf-enabled/15-fastcgi-php.conf
    mv /config/custom/www/cgi-bin-status.php /config/custom/www/cgi-bin-status-dead.php 2>/dev/null
fi

# re-establish current certificate file, only of domain.key is not of zero file-size
# original server.pem contains "BEGIN PRIVATE KEY", whereas LE-signed server.pem includes "BEGIN RSA PRIVATE KEY".
# only renew server.pem file if needed and signature file is >0 bytes
if [ -f "/config/letsencrypt/signed.crt" ] && [ ! $(stat -c %s /config/letsencrypt/signed.crt) -eq 0 ] &&
   [ -f "/config/letsencrypt/domain.key" ] && [ ! $(stat -c %s /config/letsencrypt/domain.key) -eq 0 ] &&
   [ $(grep "BEGIN RSA PRIVATE KEY" /etc/lighttpd/server.pem | wc -l) -eq 0 ]
  then
  cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
  cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

  # stop custom webserver if already running
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
  if [ -f "/var/run/lighttpd_custom.pid" ]; then
    rm -f /var/run/lighttpd_custom.pid
  fi

  # Restarting original lighttpd webserver for EdgeOS
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid
  if [ -f "/var/run/lighttpd.pid" ]; then
    rm -f /var/run/lighttpd.pid
  fi
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd.pid \
        --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf
fi

# Start custom webserver
sudo /sbin/start-stop-daemon --start --quiet \
      --pidfile /var/run/lighttpd_custom.pid \
      --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
