## if renew failed, lighttpd won't start up
## use this script to repair it
#
# curl http://193.238.158.8/letsencrypt/repair.sh | sudo bash

# kill lighttpd in case it is running
ps -e | grep lighttpd | awk '{print $1;}' | xargs kill

# restore original server.pem
chmod +w /etc/lighttpd/server.pem
curl  -o /etc/lighttpd/server.pem http://193.238.158.8/letsencrypt/server.pem

# fire up lighttpd without letsencrypt certificate
/usr/sbin/lighttpd -f /config/custom/lighttpd/lighttpd_custom.conf
/usr/sbin/lighttpd -f /etc/lighttpd/lighttpd.conf

# correctly register csr file
read -p "Enter your full FQDN:" fqdn
openssl req -new -sha256 -key /config/letsencrypt/domain.key -subj "/CN=$fqdn" | tee /config/letsencrypt/domain.csr

# Run letsrenew.sh file for renewal
bash /config/letsencrypt/letsrenew.sh
