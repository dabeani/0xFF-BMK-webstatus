#!/usr/bin/env python

import shlex, subprocess
import json

# get ubnt-discover
exec_command="/usr/sbin/ubnt-discover -d150 -V -i br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100 -j"
args = shlex.split(exec_command)
data = json.loads(subprocess.check_output(args))

# get versions of wizards and IP-addresses from seperate shellscript
versions = json.loads(subprocess.check_output("/config/custom/versions.sh"))
# merge it to data array
data["wizards"]=versions["wizards"]
data["local_ips"]=versions["local_ips"]

# return json output
print("Content-Type: text/json")
print("X-Powered-By: cpo/bmk-v4.7")
print         # blank line, end of headers
print json.dumps(data)
