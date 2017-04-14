#!/bin/bash
# just restart the lighttpd daemons in a clean way to fix php/shell_exec issues
echo "$(date +%H:%M:%S.%N) Restarting daemons" >>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sleep 1
sudo /sbin/start-stop-daemon --start --quiet --pidfile /var/run/lighttpd.pid --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --start --quiet --pidfile /var/run/lighttpd_custom.pid --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
exit 0
