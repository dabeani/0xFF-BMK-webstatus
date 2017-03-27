#!/bin/bash
#letsrenew.sh
#checks online state to continue
#checks for needed files to continue
#does nothing if no domain-registration is prepared from wizard

#feature request
#for renewal, custom lighttps must run on port 80
#if not, move ports, renew, and switch back to previous ports

# hosts to check for online status
v4iphost='8.8.8.8'
v4dnshost='www.google.com'
v6iphost='2001:4860:4860::8888'
v6dnshost='www.google.com'
# function to check if connectivity is given to download packages
onlinecheck () {
    ping="ping -c 1 -W 1 ";
    ping6="ping6 -c 1 -W 1 ";
    $ping $v4iphost > /dev/null
    if [[ $? == 0 ]]; then
        $ping6 $v6iphost > /dev/null
        if [[ $? == 0 ]]; then
            $ping6 $v6dnshost > /dev/null
            if [[ $? == 0 ]]; then
                return 0
            else
                return 1
            fi
        else
            $ping $v4dnshost > /dev/null
            if [[ $? == 0 ]]; then
                return 0
            else
                return 1
            fi
        fi
    else
        return 1
    fi
}

# needed files available for renewal or initial creation?
if [ $((onlinecheck)) != 0 ] ||
   [ ! -f "/config/letsencrypt/domain.csr" ] ||
   [ $(stat -c %s /config/letsencrypt/domain.csr) -eq 0 ] ||
   [ ! -f "/config/letsencrypt/domain.key" ] ||
   [ $(stat -c %s /config/letsencrypt/domain.key) -eq 0 ] ||
   [ ! -f "/config/letsencrypt/account.key" ] ||
   [ $(stat -c %s /config/letsencrypt/account.key) -eq 0 ] ||
   [ ! -d "/config/custom/www/.well-known/acme-challenge/" ]
then
  echo "error: offline, missing or empty files, or challenge-directory!"
else

# Opening up firewall on port 80
CHAIN=$( iptables -L | awk '/^Chain WAN/ && /LOCAL/ {print $2;}' )
iptables -I $CHAIN 1 -p tcp --dport 80 -j ACCEPT

## check if custom_lighttpd runns http:80, if not: make it to!
customhttpport=$(grep -i server.port /config/custom/lighttpd/*.conf /config/custom/lighttpd/conf-enabled/*.conf | awk -F'=' {'gsub(" ","",$2);print $2;'})
orighttpport=$(grep "http-port" /config/config.boot | awk {'print $2;'})
if [ "$orighttpport" == "80" ]; then
    #stop orig server
    sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid
    if [ -f "/var/run/lighttpd.pid" ]; then
        rm -f /var/run/lighttpd.pid
    fi
    #remember to restart!
    restart="A"
fi

if [ "$customhttpport" != "80" ]; then
    #stop custom server
    sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
    if [ -f "/var/run/lighttpd_custom.pid" ]; then
      rm -f /var/run/lighttpd_custom.pid
    fi
    #change port to 80
    customhttpportnew="80"
    echo "Current http settings located, changing ports in 10-ssl.conf" >> $log
    sed -i -r 's/server.port.{0,4}=.{0,4}'$customhttpport'/server.port = '$customhttpportnew'/' /config/custom/lighttpd/conf-enabled/10-ssl.conf
    sed -i -r 's/\]:'$customhttpport'"/\]:'$customhttpportnew'"/' /config/custom/lighttpd/conf-enabled/10-ssl.conf
    #start custom server
    sudo /sbin/start-stop-daemon --start --quiet \
          --pidfile /var/run/lighttpd_custom.pid \
          --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
fi
## Port preperation done

# Run renewal script
python /config/letsencrypt/acme_tiny.py --account-key /config/letsencrypt/account.key --csr /config/letsencrypt/domain.csr --acme-dir /config/custom/www/.well-known/acme-challenge/ > /config/letsencrypt/signed.crt

## Restore original port settings, remember restart-need
if [ "$customhttpport" != "80" ]; then
    #stop custom server
    sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
    if [ -f "/var/run/lighttpd_custom.pid" ]; then
      rm -f /var/run/lighttpd_custom.pid
    fi
    #change port back to original setting 80
    echo "Current http settings located, changing ports in 10-ssl.conf" >> $log
    sed -i -r 's/server.port.{0,4}=.{0,4}'$customhttpportnew'/server.port = '$customhttpport'/' /config/custom/lighttpd/conf-enabled/10-ssl.conf
    sed -i -r 's/\]:'$customhttpportnew'"/\]:'$customhttpport'"/' /config/custom/lighttpd/conf-enabled/10-ssl.conf
fi
## Restore done

# Removing firewall rule added earlier
iptables -D $CHAIN 1

# Copying files to lighttpd directory on success only (file domain.key is not empty)
if [ -f "/config/letsencrypt/signed.crt" ] && [ ! $(stat -c %s /config/letsencrypt/signed.crt) -eq 0 ] &&
   [ -f "/config/letsencrypt/domain.key" ] && [ ! $(stat -c %s /config/letsencrypt/domain.key) -eq 0 ]
then
  cat /config/letsencrypt/signed.crt | tee /etc/lighttpd/server.pem
  cat /config/letsencrypt/domain.key | tee -a /etc/lighttpd/server.pem

  # Restarting original lighttpd webserver for EdgeOS
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid
  if [ -f "/var/run/lighttpd.pid" ]; then
    rm -f /var/run/lighttpd.pid
  fi
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd.pid \
        --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf
  #orig server already started
  restart=""
  
  # Restart custom lighttpd webserver for custom scripts
  sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid
  if [ -f "/var/run/lighttpd_custom.pid" ]; then
    rm -f /var/run/lighttpd_custom.pid
  fi
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd_custom.pid \
        --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf
fi
## end if from "check needed files"
fi

## restart orig server after port-changes in case LE-registration failed
if [ "$restart" ]; then
  sudo /sbin/start-stop-daemon --start --quiet \
        --pidfile /var/run/lighttpd.pid \
        --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf
fi
