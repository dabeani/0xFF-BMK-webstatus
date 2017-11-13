<?php

// define all possible management interfaces for status-output
$interface_1100_list='br0.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100,eth5.1100,br1.1100,br2.1100';

// enables lookup of IPs from cached node database (originally taken from map meta data at map.funkfeuer.at/wien)
$get_nslookup_from_nodedb=1;

// enables Link to Routerlogin page (with https-port from config-file)
$show_link_to_adminlogin=0;

// defines destination for traceroute: should be internet gateway, tunnelserver.funkfeuer.at
$traceroute_to='78.41.115.36';

// defines destination for traceroute6: should be internet gateway IPv6
$traceroute6_to='78.41.115.36';

// define ip-ranges to display management data in status.py: IP-IP[,IP-IP] default=3 0xFF-IP-ranges
$ipranges='193.238.156.0-193.238.159.255,78.41.112.0-78.41.119.255,185.194.20.0-185.194.23.255'; 

// define ip-addresses to display management data in status.py: IP[,IP] default none
$ipaddresses=''; 

$allowiphones=1; 

$ubntdiscovertime=150;

?>
