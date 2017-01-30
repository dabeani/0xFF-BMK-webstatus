#!/bin/sh

if [ ! -d "/var/log/lighttpd_custom" ]
then
    mkdir /var/log/lighttpd_custom
    chown www-data:www-data /var/log/lighttpd_custom
fi

# start custom script webserver
sudo /sbin/start-stop-daemon --start --quiet \
	--pidfile /var/run/lighttpd_custom.pid \
	--exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf

# start original EsgeOS webserver
sudo /sbin/start-stop-daemon --start --quiet \
	--pidfile /var/run/lighttpd.pid \
	--exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf
