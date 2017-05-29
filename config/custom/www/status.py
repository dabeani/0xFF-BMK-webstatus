import shlex, subprocess
exec_command="/usr/sbin/ubnt-discover -d150 -V -i br0.1100,br1 -j"
args = shlex.split(exec_command)
print subprocess.check_output(args)
