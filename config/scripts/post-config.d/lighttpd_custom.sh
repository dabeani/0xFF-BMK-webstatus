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

# re-establish renewal if needed
if [ ! -f /etc/cron.daily/letsrenew ] && [ -f /config/letsencrypt/letsrenew.sh ]; then
    echo "#!/bin/bash" >/etc/cron.daily/letsrenew
    echo "/config/letsencrypt/letsrenew.sh" >>/etc/cron.daily/letsrenew
fi
chmod 755 /etc/cron.daily/letsrenew

# re-establish daily restart script
if [ ! -f /etc/cron.daily/restartservers ] && [ -f /config/letsencrypt/restartservers.sh ]; then
    echo "#!/bin/bash" >/etc/cron.daily/restartservers
    echo "/config/letsencrypt/restartservers.sh" >>/etc/cron.daily/restartservers
fi
chmod 755 /etc/cron.daily/restartservers

# re-establish hourly AirOS fetching
if [ ! -L "/etc/cron.hourly/loopairos" ] && [ -f /config/custom/loopairos.sh ]; then
    ln -sf /config/custom/loopairos.sh /etc/cron.hourly/loopairos
fi
chmod 755 /etc/cron.hourly/loopairos

# 1.9.7aplha3 and later has no preinstalled php5 - remove php-setup from config
# this logic supports only oneway php->python, will not reestablish php-setup later on!
if [ ! -d /var/run/php5 ] && [ "$(grep -ni conf-enabled/15-fastcgi-php.conf /config/custom/lighttpd/lighttpd_custom.conf | wc -l)" == "1" ]; then
    linenumber=$(grep -ni "conf-enabled/15-fastcgi-php.conf" /config/custom/lighttpd/lighttpd_custom.conf | awk -F: {'print $1'})
    sed -i $linenumber'd' /config/custom/lighttpd/lighttpd_custom.conf
    #rm /config/custom/lighttpd/conf-enabled/15-fastcgi-php.conf
    mv /config/custom/www/cgi-bin-status.php /config/custom/www/cgi-bin-status-dead.php 2>/dev/null
fi

#add ca-file to config (also after FW-update)
if [ -f /config/letsencrypt/chain.pem ]; then
  for configfile in $(echo "/config/custom/lighttpd/conf-enabled/10-ssl.conf /etc/lighttpd/conf-enabled/10-ssl.conf"); do
    if [ $(grep -c "ssl\.ca-file" $configfile) -ne 2 ]; then
      sed -i '/ssl.ca-file/d' $configfile
      for linenumber in $(grep -n server.pem $configfile | cut -d":" -f1 | sort -r); do
        sed -i $(($linenumber +1))'i\
        ssl.ca-file = "/config/letsencrypt/chain.pem"' $configfile
      done
    fi
  done
fi

# re-establish current certificate file, only if domain.key is not of zero file-size
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

# after FW-update, ssh keys for root and for users are gone
# if exists, copy the keys back to users directories
# key to root user as well (needed for wizard)
if [ ! -d /root/.ssh ]; then
  mkdir /root/.ssh
fi
if [ ! -f /root/.ssh/id_rsa ] && [ ! -f /root/.ssh/id_rsa.pub ]; then
  cp /config/letsencrypt/id_rsa /root/.ssh/id_rsa
  cp /config/letsencrypt/id_rsa.pub /root/.ssh/id_rsa.pub
fi
# fuer alle admin-user bereitstellen
userlist=$(grep -E "^        user |^            level " /config/config.boot | sed 'N;s/\n/ /' | grep "level admin" | awk {'print $2'})
#userlist="ubnt"
for user in $userlist; do
  if [ -d /home/$user ] && [ ! -f /home/$user/.ssh/id_rsa ] && [ ! -f /home/$user/.ssh/id_rsa.pub ]; then
    if [ ! -d /home/$user/.ssh ]; then
      mkdir /home/$user/.ssh
    fi
    cp /config/letsencrypt/id_rsa /home/$user/.ssh/id_rsa
    cp /config/letsencrypt/id_rsa.pub /home/$user/.ssh/id_rsa.pub
    chown $user:users /home/$user/.ssh/id_rsa
    chown $user:users /home/$user/.ssh/id_rsa.pub
  fi
  [ $(grep -c "alias ll=" /home/$user/.profile) -eq 0 ] && echo "alias ll=\"ls -al\"" >>/home/$user/.profile
done
[ $(grep -c "alias ll=" /root/.profile) -eq 0 ] && echo 'echo "alias ll=\"ls -al\"" >>/root/.profile' | sudo at now
