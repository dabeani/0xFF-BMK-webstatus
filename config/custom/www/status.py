#!/usr/bin/env python

import shlex, subprocess
exec_command="/usr/sbin/ubnt-discover -d150 -V -i br0.1100,br1,br1.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100 -j"
args = shlex.split(exec_command)

print
print subprocess.check_output(args)
