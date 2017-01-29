#!/bin/bash
# execute on edgerouter this command:
# ATTENTION: run only once, second execution will kill server.pem and lighttpd
# use repair.sh in such case to restore original server.pem file and start over

# Input regarding FQDN which will be used
read -p "Enter your full FQDN:" fqdn

# correctoin permissions, create cron-job
chmod 755 /config/letsencrypt/acme_tiny.py
chmod 755 /config/letsencrypt/letsrenew.sh
ln -sf /config/letsencrypt/letsrenew.sh /etc/cron.monthly/letsrenew.sh
chmod 755 /config/scripts/post-config.d/install_letsencrypt.sh

# Generate certifications which will be used
openssl genrsa 4096 | tee /config/letsencrypt/account.key
openssl genrsa 4096 | tee /config/letsencrypt/domain.key
openssl req -new -sha256 -key /config/letsencrypt/domain.key -subj "/CN=$fqdn" | tee /config/letsencrypt/domain.csr

# save original server.pem
cp /etc/lighttpd/server.pem /config/lighttpd/server.pem.bak

# Run letsrenew.sh file for initial connect and/or renewal, doesn't matter
bash /config/letsencrypt/letsrenew.sh