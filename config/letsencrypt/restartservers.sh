#!/bin/bash
# just restart the lighttpd daemons in a clean way to fix php/shell_exec issues
echo "$(date +%Y-%m-%d/%H:%M:%S.%N) Restarting daemons" >>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd_custom.pid >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --stop --pidfile /var/run/lighttpd.pid >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sleep 1
sudo /sbin/start-stop-daemon --start --quiet --pidfile /var/run/lighttpd.pid --exec /usr/sbin/lighttpd -- -f /etc/lighttpd/lighttpd.conf >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log
sudo /sbin/start-stop-daemon --start --quiet --pidfile /var/run/lighttpd_custom.pid --exec /usr/sbin/lighttpd -- -f /config/custom/lighttpd/lighttpd_custom.conf >>/var/log/0xwslerestart.log 2>>/var/log/0xwslerestart.log

echo 'homes="'$(for user in $(ls /home); do echo $user:$(echo $(sudo grep "ssh-" /home/$user/.ssh/authorized_keys 2>/dev/null | awk '{print $3}')|sed -e 's/ /,/g'); done|sed -e 's/:$//g')'"'|sed -e 's/ /","/g' >/tmp/versions.dat
echo 'md5='$(sudo /usr/bin/md5sum /dev/mtdblock2 | cut -f1 -d" ") >>/tmp/versions.dat
exit 0
