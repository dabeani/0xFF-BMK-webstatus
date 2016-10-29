<?php
// scripts should terminated after 1 minute!
set_time_limit(60);

$APP = Array();

// issues: blanks in interface-desc eth_desc

# required: aptitude install traceroute snmp bind9-host dnsutils nginx php5-fpm php5-curl php5-snmp
# required: /etc/sudoers: www-data ALL=NOPASSWD: ALL

// define all possible management interfaces for status-output
$interface_1100_list='br0.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100,eth5.1100,br1.1100,br2.1100';

// URI in Variablen umwandeln!
parse_str(parse_url($_SERVER["REQUEST_URI"],PHP_URL_QUERY));

function getRoutes() {
    $routes_raw = explode("\n",trim(shell_exec("/sbin/ip route | awk '{print $3,$1}'")));
    foreach ($routes_raw as $getroute) {
        if(strpos($getroute,'default') !== false) {
            $APP["default_route"] = trim(substr($getroute,0,strpos($getroute," ")));
        }
        $route = explode(" ",$getroute);
        if(preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $route['0'], $ip_match)) {
            $APP["routes"][$route['0']][] = trim($route['1']);
            $APP["routes_".$route['0']] = count($APP["routes"][$route['0']]);
        }
    }
    
    $olsr_links_raw = explode("\n",trim(file_get_contents("http://127.0.0.1:2006/links")));
    if(isset($olsr_links_raw['0']) && isset($olsr_links_raw['1'])) {
        unset($olsr_links_raw['0']);
        unset($olsr_links_raw['1']);
    }
    
    echo "<table class=\"table table-hover table-bordered\"><thead><tr valign=top><td><b>Local IP</b></td><td><b>Remote IP</b></td><td><b>Hyst.</b></td><td><b>LQ</b></td><td><b>NLQ</b></td><td><b>Cost</b></td><td><b>route entries</b></td></tr></thead>\n";
    echo "<tbody>\n";
    foreach ($olsr_links_raw as $getlink) {
        $getlink = preg_replace('/\s+/',',',trim($getlink));
        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\,(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\,(.*)\,(.*)\,(.*)\,(.*)/', $getlink, $link);
        $tmp_output_route_text = "route";
        $tmp_defaultroute = "";
        if($APP["routes_".$link['2']] > 1) {
            $tmp_output_route_text = "routes";
        }
        if($link['2'] == $APP["default_route"]) {
           $tmp_defaultroute = " bgcolor=FFD700";
        }
        echo "<tr".$tmp_defaultroute."><td>".$link['1']."</td><td>".gethostbyaddr($link['2'])."</td><td>".$link['3']."</td><td>".$link['4']."</td><td>".$link['5']."</td><td>".$link['6']."</td><td>".$APP["routes_".$link['2']]." ".$tmp_output_route_text."</td></tr>";
    }
    echo "</tbody></table>\n";
    unset($routes_raw);
    unset($olsr_links_raw);
}
    
function parse_firmware($in) {
	//crop firmware version
	$fw = explode(".", $in); 
	$fwstring = $fw[2];
	for ($f = 3; $f < count($fw); $f++) {
		if ((strlen($fw[$f]) >= 5) && (strpos(strtolower($fw[$f]), 'beta')==false)) {break;}
		$fwstring.='.';
		$fwstring.=$fw[$f];
	}
	return $fwstring;
}

/*
* Diverse Daten auslesen um ScanTools zu supporten! (- andernfalls Statusseite ausgeben...)
*/

if(isset($get)) {
 if($get == "status") { 
	echo shell_exec("/usr/sbin/ubnt-discover -d150 -V -i \"".$interface_1100_list."\" -j");
 } elseif($get == "devices") {
	//echo shell_exec("/usr/sbin/ubnt-discover -d10 -ibr0.1100");
	echo shell_exec("/usr/sbin/ubnt-discover -d150 -i \"".$interface_1100_list."\"");
 } elseif($get == "phpinfo") {
	phpinfo();
 } elseif($get == "table") {
	$time = microtime();
	$time = explode(' ', $time);
	$time = $time[1] + $time[0];
	$start = $time;

	$APP["ip"] = $_SERVER["SERVER_NAME"];
	$APP["hostname"] = gethostbyaddr($APP["ip"]);

	$discover = json_decode(shell_exec("/usr/sbin/ubnt-discover -d150 -V -j"), TRUE);
 	// show interfaces ethernet detail | grep -E "^eth.|link/ether" | awk '{if ($1~/^eth./) {gsub(":","",$1); print $1;} else {print ",\""$2"\"";}}' | sed 'N;s/\n//'
	$eth_macs = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --intf-type=ethernet --action=show | grep -E \"^eth.|link/ether\" | awk '{if ($1~/^eth./) { print $1\",\";} else {print $2;}}' | sed 'N;s/\\n//'")));

	// show interfaces | grep -E "^eth" | awk '{print $1","$4;}'
	//$eth_desc = explode("\n",trim(shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E "^eth|^br" | awk \'{printf $1",";{for(i=4;i<=NF;++i) printf($i)} print ","$3","$2;}\'')));
	//$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{printf $1\",\"; printf($4); print \",\"$3\",\"$2;}'")));
	$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --action=show-brief | grep -E \"^eth|^br\" | awk '{print $1\",\"$2\",\"$3\",\"$4;}'")));
	// $eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{printf $1\",\"; {for(i=4;i<=NF;++i) printf($i)} print \",\"$3\",\"$2;}'")));
	//$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{print $1\",\"$4\",\"$3\",\"$2;}'")));
	// achtung: blanks im interface-namen irgendwie beachten!
	//echo "version 0";
						
	// show bridge br0 macs | awk '{print $3","$2","$1","$4}'
	// spaeter: $br0_macs = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show bridge br0 macs | awk '{print $3\",\"$2\",\"$1\",\"$4}'"))); 
	// skip 1st line?
	// achtung: auch br1 koennte interessant sein --> hat eigene mac/ids
									
	// show arp | awk '{if ($2!~/incomplete/) {print $3","$1","$5}}'
	$br0_ips =    explode("\n",trim(shell_exec("/usr/sbin/arp -e -n | awk '{if ($2!~/incomplete/) {print $3\",\"$1\",\"$5}}'")));
	// skip first line?
	
	//show interfaces ethernet physical
	// does not seem to work... 
	// works: $eth_speeds = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces ethernet poe"))); 
	$eth_speeds = array(); //explode("\n",trim(shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper physical | show interfaces ethernet'))); 
	//echo "0!\n";
	//print_r($eth_speeds);
	
	// echo shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces ethernet physical'); 
	
	// load POE configuration
	$eth_poe = explode("\n",trim(shell_exec("/usr/sbin/ubnt-ifctl show-poe-status | grep -E \"^eth\" | awk '{print $1\",\"$2\",\"$3\",\"$4}'")));
	
	// load eth assignments to bridges
	$br_eth = explode("\n",trim(shell_exec("/usr/sbin/brctl show | grep -v interfaces | awk '{print $1\",\"$4}'")));
	
	$eth_in_bridge=array();
	foreach ($br_eth as $key=>$value) {
		$int = explode(",", trim($value));
		if (strlen($int[1])>0) {
			$current_bridge=$int[0];
			$current_if = $int[1];
		} else {
			$current_if = $int[0];
		}
		unset($int);
		// example     eth2.1001   = br1
		// example     eth2        = br0
	$eth_in_bridge[$current_if]=$current_bridge;
	}
	unset($current_bridge);
	unset($current_if);
	
	function build_sorter($key) {
		return function ($a, $b) use ($key) {
			return strnatcmp($a[$key], $b[$key]);
 		};
	}
	
	// pysical ports and their mac address
	$interfaces=array();
	$vlan_list=array();
	foreach ($eth_macs as $key=>$value) {
		$int = explode(",", trim($value));
		$int[0]=str_replace(":","",$int[0]);
		$tmp  =explode("@",$int[0]);
		if ($tmp[1]) {
			// this is a vlan
			$v=explode(".",$tmp[0]); // extract vlan-id
			$vlans[$tmp[1]][$v[1]]['name']=$v[1];  // port eth0 has vlan 1001
			$vlans[$tmp[1]][$v[1]]['devices']=array();  // port eth0 has vlan 1001
			if (!in_array($v[1], $vlan_list)) {array_push($vlan_list, $v[1]);}
			unset($v);
			continue;
		};
		$interfaces[$tmp[0]]['port'] = $tmp[0];
		$interfaces[$tmp[0]]['mac'] = str_replace("\"","",$int[1]);
		$interfaces[$tmp[0]]['devices'] =array();
		if (isset($tmp[1])) {
			$interfaces[$tmp[0]]['vif'] = $tmp[1];
		}
	}
	
	// add port description, state, maybe own IP
	$bridges=array();
	foreach ($eth_desc as $key=>$value) {
		$int = explode(",", trim($value));
		$v=explode(".",$int[0]);
		if (substr($int[0],0,2)=="br") { 
			if (!isset($v[1])) {
				$bridges[$int[0]]['name']=$int[0];
				$bridges[$int[0]]['desc'] = trim($int[3]);
				$bridges[$int[0]]['state'] = $int[2];
				$bridges[$int[0]]['own_ip'] = $int[1];
			} else {
				unset($v);
			}
			continue;
		}		
		if ($v[1]) {
			// this is a vlan
			$vlans[$v[0]][$v[1]]['desc'] = trim($int[3]);
			$vlans[$v[0]][$v[1]]['own_ip']=$int[1];
			unset($v);
			continue;
		}
		$interfaces[$int[0]]['desc'] = trim($int[3]);
		$interfaces[$int[0]]['state'] = $int[2];
		$interfaces[$int[0]]['own_ip'] = $int[1];
	}
	
	foreach ($interfaces as $key=>$value) {
		$lines = explode("\n",trim(shell_exec("/sbin/ethtool ".$value['port']." | grep -iE \"speed|duplex|link detected\" | awk '{gsub(\" detected\",\"\",$0); print $1$2;}'"))); 
		foreach ($lines as $line) {
			$tmp = explode (":",$line);
			if (strtolower($tmp[0])=='speed')  { $interfaces[$key]['speed']=$tmp[1]; }
			if (strtolower($tmp[0])=='duplex') { $interfaces[$key]['duplex']=$tmp[1]; }
			if (strtolower($tmp[0])=='link')   { $interfaces[$key]['link_detected']=$tmp[1]; }
		}
	}
	
	// add poe setup
	foreach ($eth_poe as $key=>$value) {
		$int = explode(",", trim($value));
		$interfaces[$int[0]]['poe'] = $int[1];
		$interfaces[$int[0]]['poe_v'] = $int[2];
		$interfaces[$int[0]]['poe_wd'] = $int[3];
	}
	
 	// add port speed and link-state 
 	foreach ($interfaces as $ikey=>$ivalue) {
		$tracker = 0;
		foreach ($eth_speeds as $line) {
			if (strpos($line, ' '.$ikey.':')!==false) {
				$tracker++;
			}
 			if ($tracker>0) {
 				$info = explode (":", $line);
 				if (strpos($info[0], 'Speed')!==false) { $interfaces[$ikey]['speed']=trim($info[1]);}
 				if (strpos($info[0], 'Link')!==false)  { $interfaces[$ikey]['link']= trim($info[1]);}
 			}
 			if (strlen($line) < 2) {$tracker=0;}
 		}
 	}

	// NEW 5 loop all bridges br0,br1
 	$devices = array();
	foreach ($bridges as $br=>$brdata) {
	$br0_macs = explode("\n",trim(shell_exec("/usr/sbin/brctl showmacs ".$br." | awk '{print $3\",\"$2\",\"$1\",\"$4}'")));

	// 6 add correct port_id for ethX from mac table
 	foreach ($br0_macs as $key=>$value) {
 		$int = explode(",", trim($value));
 		if ($int[0] == "yes") {
 			foreach ($interfaces as $ikey=>$ivalue) {
				if ($interfaces[$ikey]['mac']==$int[1]) {
					$interfaces[$ikey]['port_id']=$int[2];
				}
			}
		}
	}

	// 7 add mac addresses to correct ethX port from arp table
 	foreach ($br0_macs as $key=>$value) {
		$int = explode(",", trim($value));
		if ($int[0] == "no") {
			foreach ($interfaces as $ikey=>$ivalue) {
				if ($interfaces[$ikey]['port_id']==$int[2]) {
					$interfaces[$ikey]['devices'][] =$int[1];
				}
			}
 			$devices[$int[1]]['mac']=$int[1];
 			$devices[$int[1]]['age']=$int[3];
 			$devices[$int[1]]['port_id']=$int[2];
 		}
 	}
 	foreach ($interfaces as $ikey=>$ivalue) { unset($interfaces[$ikey]['port_id']); }
 	} // end loop all bridges

 	// 8 add IP and bridge-name to devices
 	$bridge_names = array();
 	foreach ($devices as $dkey=>$dvalue) {
 		foreach ($br0_ips as $key=>$value) {
 			$ip = explode(",", trim($value));
 			$v= explode(".", $ip[2]); // 0=port/bridge, 1=vlan
			if (isset($vlans[$v[0]][$v[1]]['desc'])) {	
				// this is a vlan and not a bridge
				$devices[$ip[0]]['mac']=$ip[0];
				if (!in_array($ip[1], $devices[$ip[0]]['ips'][$ip[2]])) {$devices[$ip[0]]['ips'][$ip[2]][]=$ip[1];}
				$devices[$ip[0]]['vlan']=$v[1];
				if (!in_array($ip[0], $vlans[$v[0]][$v[1]]['devices'])) {$vlans[$v[0]][$v[1]]['devices'][]=$ip[0];}
	 			unset($v);
				continue;
			}
 			if ($ip[0]==$devices[$dkey]['mac']){
 		//	if (($ip[0]==$devices[$dkey]['mac']) && ($ip[2]==$devices[$dkey]['port'])) {
 				if (!in_array($ip[2], $bridge_names)) {array_push($bridge_names, $ip[2]);}
				$devices[$dkey]['ips'][$ip[2]][] = $ip[1];
			}
		}

	}

	sort($bridge_names);

	// find discover_id for additional information
	foreach ($devices as $dkey=>$dvalue) {
		foreach ($discover[devices] as $key=>$value) {
			if (strtolower($value['hwaddr'])==strtolower($dkey)) {
				$devices[$dkey]['discover_id']=$key;
				break;
			}
			foreach ($value['addresses'] as $akey=>$avalue) {
				if (strtolower($avalue['hwaddr'])==strtolower($dkey)) {
					$devices[$dkey]['discover_id']=$key;
					break;
				}
			}
		}
	}
	

 ?><!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!--meta name="viewport" content="width=device-width, initial-scale=1"-->
    <meta name="viewport" content="width=1000, initial-scale=0.5">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">

    <title><?php echo $APP["hostname"];?></title>

    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="/css/starter-template.css" rel="stylesheet">

  </head>

  <body>
<div class="container">
	<div class="row">
		<!--<div class="col-md-6">-->
			<!-- Nav tabs --><div class="card">
				<ul class="nav nav-tabs" role="tablist">
					<li role="presentation"><a href="cgi-bin-status.php#main">&Uuml;bersicht</a></li>
					<li role="presentation"><a href="cgi-bin-status.php#status">Status</a></li>
					<li role="presentation" class="active"><a href="#table">Port-Table</a></li>
					<li role="presentation"><a href="cgi-bin-status.php#contact">Kontakt</a></li>
					<li role="presentation"><a href="#"><?php echo  $APP["ip"] ." - ".$APP["hostname"]; ?></a></li>
				</ul>
<!-- Tab panes -->
				<div class="tab-content"> 
					<div role="tabpanel" class="tab-pane active" id="table">
<br>
  <table class="table table-hover table-bordered"><tbody>
 <?php
 	echo "<tr valign=top><td><b>Ports</b></td>";           foreach ($interfaces as $key=>$value) { echo "<td>".$key."</td>"; } echo "</tr>";
	echo "<tr valign=top><td><b>Description</b></td>";     foreach ($interfaces as $key=>$value) { echo "<td>".$interfaces[$key]['desc']."</td>"; } echo "</tr>";

	echo "<tr valign=top><td><b>Port State/Link</b></td>";           foreach ($interfaces as $key=>$value) { 
 		$state = explode("/",$interfaces[$key]['state']);
 		echo "<td>";
 		if ($state[0]=="u") { echo "up"; }
 		if ($state[0]=="D") { echo "down"; }
 		if ($state[0]=="A") { echo "disabl"; }
 		echo "/";
 		if ($state[1]=="u") { echo "up"; }
 		if ($state[1]=="D") { echo "down"; }
 		if ($state[1]=="A") { echo "disabl"; }
 		echo "</td>"; 
 	} echo "</tr>";
 	
	echo "<tr valign=top><td><b>PoE setting</b></td>";     foreach ($interfaces as $key=>$value) { 
		echo "<td>";
		if ($interfaces[$key]['vif']) {
			echo "vif";
		} else {
			echo $interfaces[$key]['poe'];
 			echo "/";
			echo $interfaces[$key]['poe_v'];
 			echo "/";
			echo $interfaces[$key]['poe_wd'];
		}
		echo "</td>"; 
	} echo "</tr>";
	
	echo "<tr valign=top><td><b>Speed, Duplex</b></td>";           foreach ($interfaces as $key=>$value) { 
		if ($interfaces[$key]['link_detected']=="yes") { 
			echo "<td>".$interfaces[$key]['speed'].", ".$interfaces[$key]['duplex']."</td>"; }
		else { 
			echo "<td>no link</td>"; 
		}
	} echo "</tr>";

 	foreach ($bridge_names as $bridge) {
 		echo "<tr valign=top><td><b>Devices</b> ".$bridge."<br>";
 		echo $bridges[$bridge]['desc']."<br>";
 		echo $bridges[$bridge]['own_ip']."<br>";
 		echo "</td>";
		foreach ($interfaces as $key=>$value) {
 			echo "<td>";
			if (($eth_in_bridge[$key]!=$bridge)&&(isset($bridges[$bridge]['name']))) {
				// only native bridges
				echo $key." not member<br>"; 
			}
			foreach ($interfaces[$key]['devices'] as $d) {
 				foreach ($devices[$d]['ips'][$bridge] as $ip) {
 					if (substr($ip,0,8)=="192.168.") {
	 					if ($skip_this==1) { continue; }
 						echo '192.168.*.*';
 						$skip_this = 1;
 						continue;
 					}
 					echo $ip." (".$devices[$d]['age'].")<br>";
 					if ($devices[$d]['discover_id']) {
						$tmp=$discover['devices'][$devices[$d]['discover_id']];
 						if (isset($tmp['hostname'])) { echo "=".$tmp['hostname']."<br>"; }
 						if (isset($tmp['product'])) { echo "-".$tmp['product']."<br>"; }
 						if (isset($tmp['fwversion'])) { echo "::".parse_firmware($tmp['fwversion'])."<br>"; }
 						//if (isset($tmp['essid'])) { echo "@".$tmp['essid']."<br>"; }
						unset($tmp);
 					}
 				}
			}
			unset($skip_this);
 			echo "</td>"; 
 		} echo "</tr>";
	}
	
	foreach ($vlan_list as $vlan_id) {
		echo "<tr valign=top><td>VLAN ".$vlan_id."</td>";
		foreach ($interfaces as $port=>$value) { // alle eth-ports durchgehen
			echo "<td>";
			if (isset($vlans[$port][$vlan_id]['desc'])) { // vlan existiert an diesem ETH-port
				if (isset($eth_in_bridge[$port.".".$vlan_id])) {
					echo "part of ".$eth_in_bridge[$port.".".$vlan_id]."<br>"; 
				}
				echo "<b>".$vlans[$port][$vlan_id]['desc']."</b><br>";
				if (isset($vlans[$port][$vlan_id]['own_ip'])) { 
					echo "<b>".$vlans[$port][$vlan_id]['own_ip']."</b><br>"; 
				}
				foreach ($vlans[$port][$vlan_id]['devices'] as $d) {
					echo $d.":<br>";
					foreach ($devices[$d]['ips'][$port.".".$vlan_id] as $ip) {
						if (substr($ip,0,8)=="192.168.") {
							$ip='192.168.*.*';
						} 
						echo "=".$ip." <br>";
					}
					if ($devices[$d]['discover_id']) {
							$tmp=$discover['devices'][$devices[$d]['discover_id']];
							if (isset($tmp['hostname'])) { echo "=".$tmp['hostname']."<br>"; }
							if (isset($tmp['product'])) { echo "-".$tmp['product']."<br>"; }
							if (isset($tmp['fwversion'])) { echo "::".parse_firmware($tmp['fwversion'])."<br>"; }
							//if (isset($tmp['essid'])) { echo "@".$tmp['essid']."<br>"; }
							unset($tmp);
					}
				}
			}
			echo "&nbsp;</td>";
		}
		echo "</tr>";
	}
	
 	echo "</tbody></table>";
 	//echo "\n<pre>";
 	//print_r($bridges);
 	//print_r($vlans);
 	//print_r($interfaces);
 	//print_r($bridge_names);
 	//print_r($br0_ips);
 	//print_r($devices);
	//print_r($eth_speeds);
	//print_r($discover);
	echo "</pre>";
	
	?>
					</div>
				</div>
			</div>
		<!--</div>-->
	</div>
</div>
<?php
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_time = round(($finish - $start), 4);
?>
<footer class="footer">
  <div class="container">
    <p class="text-muted"><?php echo 'Page generated in '.$total_time.' seconds.'; ?></p>
  </div>
</footer>
    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="/js/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
  </body>
</html>
	<?php
	exit;
 } // end get=table
} else { // standard-action without "get"

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

$APP = Array();

$APP["ip"] = $_SERVER["SERVER_NAME"];
$APP["hostname"] = gethostbyaddr($APP["ip"]);
//$APP["host"] = substr(shell_exec("/usr/bin/host ".$APP["ip"]." | cut -d ' ' -f 5"),0,-2);

$APP["devices"] = explode("\n",shell_exec("/usr/sbin/ubnt-discover -d150 -i \"".$interface_1100_list."\""));
$APP["devices"][0]=str_replace("Local","Local  ",$APP["devices"][0]);
$APP["v4defaultrouteviaport"] = trim(shell_exec("ip r | grep default | awk {'sub(/^eth0./,\"\",$5);print $5'}"));
$APP["v4defaultrouteviaip"] = trim(shell_exec("ip r | grep default | awk {'print $3'}"));
$APP["v6defaultrouteviaport"] = trim(shell_exec("ip -f inet6 r | grep default | awk {'sub(/^eth0./,\"\",$5);print $5'}"));
$APP["v6defaultrouteviaip"] = trim(shell_exec("ip -f inet6 r | grep default | awk {'print $3'}"));

$APP["ipv6_status"] = trim(shell_exec("netstat -na | grep 2008"));
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!--meta name="viewport" content="width=device-width, initial-scale=1"-->
    <meta name="viewport" content="width=1000, initial-scale=0.5">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">

    <title><?php echo $APP["hostname"];?></title>

    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="/css/starter-template.css" rel="stylesheet">

  </head>

  <body>

<div class="container">
	<div class="row">
		<!--<div class="col-md-6">-->
			<!-- Nav tabs --><div class="card">
				<ul class="nav nav-tabs" role="tablist">
					<li role="presentation"><a href="#main" aria-controls="main" role="tab" data-toggle="tab">&Uuml;bersicht</a></li>
					<li role="presentation" class="active"><a href="#status" aria-controls="status" role="tab" data-toggle="tab">Status</a></li>
					<!--<li role="presentation"><a href="cgi-bin-status.php?get=table">Port-Table</a></li>-->
                    <li role="presentation"><a href="cgi-bin-status.php?ext=table#table">Port-Table</a></li>
					<li role="presentation"><a href="#contact" aria-controls="contact" role="tab" data-toggle="tab">Kontakt</a></li>
					<li role="presentation"><a href="#"><?php echo  $APP["ip"] ." - ".$APP["hostname"]; ?></a></li>
				</ul>

<!-- Tab panes -->
				<div class="tab-content"> 
				  <div role="tabpanel" class="tab-pane" id="main">
						<h1 align="center">Willkommen auf <?php echo $APP["hostname"] . '<br>' . $APP["ip"]; ?></h1>
						<b>WAS?</b><br>
						FunkFeuer ist ein freies, experimentelles Netzwerk in Wien, Graz, der Weststeiermark, in Teilen des Weinviertels (N&Ouml;) und in Bad Ischl. Es wird aufgebaut und betrieben von computerbegeisterten Menschen. Das Projekt verfolgt keine kommerziellen Interessen.<br><br>
						<b>FREI?</b><br>
						FunkFeuer ist offen f&uuml;r jeden und jede, der/die Interesse hat und bereit ist mitzuarbeiten. Es soll dabei ein nicht reguliertes Netzwerk entstehen, welches das Potential hat, den digitalen Graben zwischen den sozialen Schichten zu &Uuml;berbr&uuml;cken und so Infrastruktur und Wissen zur Verf&uuml;gung zu stellen. Teilnahme Zur Teilnahme an FunkFeuer braucht man einen WLAN Router (gibt's ab 60 Euro) oder einen PC, das OLSR Programm, eine IP Adresse von FunkFeuer, etwas Geduld und Motivation. Auf unserer Karte ist eingezeichnet, wo man FunkFeuer schon &Uuml;berall (ungef&auml;r) empfangen kann (bitte beachte, dass manchmal H&auml;user oder &Auml;hnliches im Weg sind, dann geht's nur &uuml;ber Umwege).
					</div>
					<div role="tabpanel" class="tab-pane active" id="status">
					
						<dl class="dl-horizontal">
						  <dt>System Uptime</dt><dd><?php echo shell_exec("uptime") ?></dd>
						  <dt>IPv4 Default-Route</dt><dd><?php echo "<a href=\"http://".$APP["v4defaultrouteviaip"]."/cgi-bin-status.html\">".$APP["hostname"]."(".$APP["v4defaultrouteviaip"].")</a> via ".$APP["v4defaultrouteviaport"]."<br>"; ?></dd>
						  <dt>Devices vlan 1100</dt><dd><pre><?php echo implode("\n", $APP["devices"]); ?></pre></dd>
						  <dt>IPv4 OLSR-Links</dt><dd><?php echo getRoutes(); ?></dd>
<?php
if(strlen($APP["ipv6_status"]) > 5) {?>
						  <dt>IPv6 Default-Route</dt><dd><?php echo "<a href=\"http://".$APP["v6defaultrouteviaip"]."/cgi-bin-status.html\">".$APP["v6defaultrouteviaip"]."</a> via ".$APP["v4defaultrouteviaport"]."<br>"; ?></dd>
						  <dt>IPv6 OLSR-Links</dt><dd><pre><?php echo trim(str_replace($APP["v6defaultrouteviaip"],"<mark><b>".$APP["v6defaultrouteviaip"]."</b></mark>",file_get_contents("http://[::1]:2008/links"))); ?></pre></dd>
<?php } else { echo "<dt>IPv6</dt><dd>disabled...</dd>"; }
?>
						  <dt>Trace to UPLINK</dt><dd><pre><?php echo trim(shell_exec("/usr/bin/traceroute -w 1 -q 1 78.41.115.228"));?></pre></dd>
						</dl>
					</div>
                    <div role="tabpanel" class="tab-pane" id="table">
<?
$discover = json_decode(shell_exec("/usr/sbin/ubnt-discover -d150 -V -j"), TRUE);
// show interfaces ethernet detail | grep -E "^eth.|link/ether" | awk '{if ($1~/^eth./) {gsub(":","",$1); print $1;} else {print ",\""$2"\"";}}' | sed 'N;s/\n//'
$eth_macs = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --intf-type=ethernet --action=show | grep -E \"^eth.|link/ether\" | awk '{if ($1~/^eth./) { print $1\",\";} else {print $2;}}' | sed 'N;s/\\n//'")));

// show interfaces | grep -E "^eth" | awk '{print $1","$4;}'
//$eth_desc = explode("\n",trim(shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E "^eth|^br" | awk \'{printf $1",";{for(i=4;i<=NF;++i) printf($i)} print ","$3","$2;}\'')));
//$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{printf $1\",\"; printf($4); print \",\"$3\",\"$2;}'")));
$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --action=show-brief | grep -E \"^eth|^br\" | awk '{print $1\",\"$2\",\"$3\",\"$4;}'")));
// $eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{printf $1\",\"; {for(i=4;i<=NF;++i) printf($i)} print \",\"$3\",\"$2;}'")));
//$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces | grep -E \"^eth|^br\" | awk '{print $1\",\"$4\",\"$3\",\"$2;}'")));
// achtung: blanks im interface-namen irgendwie beachten!
//echo "version 0";

// show bridge br0 macs | awk '{print $3","$2","$1","$4}'
// spaeter: $br0_macs = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show bridge br0 macs | awk '{print $3\",\"$2\",\"$1\",\"$4}'")));
// skip 1st line?
// achtung: auch br1 koennte interessant sein --> hat eigene mac/ids

// show arp | awk '{if ($2!~/incomplete/) {print $3","$1","$5}}'
$br0_ips =    explode("\n",trim(shell_exec("/usr/sbin/arp -e -n | awk '{if ($2!~/incomplete/) {print $3\",\"$1\",\"$5}}'")));
// skip first line?

//show interfaces ethernet physical
// does not seem to work...
// works: $eth_speeds = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces ethernet poe")));
$eth_speeds = array(); //explode("\n",trim(shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper physical | show interfaces ethernet')));
//echo "0!\n";
//print_r($eth_speeds);

// echo shell_exec('/opt/vyatta/bin/vyatta-op-cmd-wrapper show interfaces ethernet physical');

// load POE configuration
$eth_poe = explode("\n",trim(shell_exec("/usr/sbin/ubnt-ifctl show-poe-status | grep -E \"^eth\" | awk '{print $1\",\"$2\",\"$3\",\"$4}'")));

// load eth assignments to bridges
$br_eth = explode("\n",trim(shell_exec("/usr/sbin/brctl show | grep -v interfaces | awk '{print $1\",\"$4}'")));

$eth_in_bridge=array();
foreach ($br_eth as $key=>$value) {
    $int = explode(",", trim($value));
    if (strlen($int[1])>0) {
        $current_bridge=$int[0];
        $current_if = $int[1];
    } else {
        $current_if = $int[0];
    }
    unset($int);
    // example     eth2.1001   = br1
    // example     eth2        = br0
    $eth_in_bridge[$current_if]=$current_bridge;
}
unset($current_bridge);
unset($current_if);

function build_sorter($key) {
    return function ($a, $b) use ($key) {
        return strnatcmp($a[$key], $b[$key]);
    };
}

// pysical ports and their mac address
$interfaces=array();
$vlan_list=array();
foreach ($eth_macs as $key=>$value) {
    $int = explode(",", trim($value));
    $int[0]=str_replace(":","",$int[0]);
    $tmp  =explode("@",$int[0]);
    if ($tmp[1]) {
        // this is a vlan
        $v=explode(".",$tmp[0]); // extract vlan-id
        $vlans[$tmp[1]][$v[1]]['name']=$v[1];  // port eth0 has vlan 1001
        $vlans[$tmp[1]][$v[1]]['devices']=array();  // port eth0 has vlan 1001
        if (!in_array($v[1], $vlan_list)) {array_push($vlan_list, $v[1]);}
        unset($v);
        continue;
    };
    $interfaces[$tmp[0]]['port'] = $tmp[0];
    $interfaces[$tmp[0]]['mac'] = str_replace("\"","",$int[1]);
    $interfaces[$tmp[0]]['devices'] =array();
    if (isset($tmp[1])) {
        $interfaces[$tmp[0]]['vif'] = $tmp[1];
    }
}

// add port description, state, maybe own IP
$bridges=array();
foreach ($eth_desc as $key=>$value) {
    $int = explode(",", trim($value));
    $v=explode(".",$int[0]);
    if (substr($int[0],0,2)=="br") {
        if (!isset($v[1])) {
            $bridges[$int[0]]['name']=$int[0];
            $bridges[$int[0]]['desc'] = trim($int[3]);
            $bridges[$int[0]]['state'] = $int[2];
            $bridges[$int[0]]['own_ip'] = $int[1];
        } else {
            unset($v);
        }
        continue;
    }
    if ($v[1]) {
        // this is a vlan
        $vlans[$v[0]][$v[1]]['desc'] = trim($int[3]);
        $vlans[$v[0]][$v[1]]['own_ip']=$int[1];
        unset($v);
        continue;
    }
    $interfaces[$int[0]]['desc'] = trim($int[3]);
    $interfaces[$int[0]]['state'] = $int[2];
    $interfaces[$int[0]]['own_ip'] = $int[1];
}

foreach ($interfaces as $key=>$value) {
    $lines = explode("\n",trim(shell_exec("/sbin/ethtool ".$value['port']." | grep -iE \"speed|duplex|link detected\" | awk '{gsub(\" detected\",\"\",$0); print $1$2;}'")));
    foreach ($lines as $line) {
        $tmp = explode (":",$line);
        if (strtolower($tmp[0])=='speed')  { $interfaces[$key]['speed']=$tmp[1]; }
        if (strtolower($tmp[0])=='duplex') { $interfaces[$key]['duplex']=$tmp[1]; }
        if (strtolower($tmp[0])=='link')   { $interfaces[$key]['link_detected']=$tmp[1]; }
    }
}

// add poe setup
foreach ($eth_poe as $key=>$value) {
    $int = explode(",", trim($value));
    $interfaces[$int[0]]['poe'] = $int[1];
    $interfaces[$int[0]]['poe_v'] = $int[2];
    $interfaces[$int[0]]['poe_wd'] = $int[3];
}

// add port speed and link-state
foreach ($interfaces as $ikey=>$ivalue) {
    $tracker = 0;
    foreach ($eth_speeds as $line) {
        if (strpos($line, ' '.$ikey.':')!==false) {
            $tracker++;
        }
        if ($tracker>0) {
            $info = explode (":", $line);
            if (strpos($info[0], 'Speed')!==false) { $interfaces[$ikey]['speed']=trim($info[1]);}
            if (strpos($info[0], 'Link')!==false)  { $interfaces[$ikey]['link']= trim($info[1]);}
        }
        if (strlen($line) < 2) {$tracker=0;}
    }
}

// NEW 5 loop all bridges br0,br1
$devices = array();
foreach ($bridges as $br=>$brdata) {
    $br0_macs = explode("\n",trim(shell_exec("/usr/sbin/brctl showmacs ".$br." | awk '{print $3\",\"$2\",\"$1\",\"$4}'")));
    
    // 6 add correct port_id for ethX from mac table
    foreach ($br0_macs as $key=>$value) {
        $int = explode(",", trim($value));
        if ($int[0] == "yes") {
            foreach ($interfaces as $ikey=>$ivalue) {
                if ($interfaces[$ikey]['mac']==$int[1]) {
                    $interfaces[$ikey]['port_id']=$int[2];
                }
            }
        }
    }
    
    // 7 add mac addresses to correct ethX port from arp table
    foreach ($br0_macs as $key=>$value) {
        $int = explode(",", trim($value));
        if ($int[0] == "no") {
            foreach ($interfaces as $ikey=>$ivalue) {
                if ($interfaces[$ikey]['port_id']==$int[2]) {
                    $interfaces[$ikey]['devices'][] =$int[1];
                }
            }
            $devices[$int[1]]['mac']=$int[1];
            $devices[$int[1]]['age']=$int[3];
            $devices[$int[1]]['port_id']=$int[2];
        }
    }
    foreach ($interfaces as $ikey=>$ivalue) { unset($interfaces[$ikey]['port_id']); }
} // end loop all bridges

// 8 add IP and bridge-name to devices
$bridge_names = array();
foreach ($devices as $dkey=>$dvalue) {
    foreach ($br0_ips as $key=>$value) {
        $ip = explode(",", trim($value));
        $v= explode(".", $ip[2]); // 0=port/bridge, 1=vlan
        if (isset($vlans[$v[0]][$v[1]]['desc'])) {
            // this is a vlan and not a bridge
            $devices[$ip[0]]['mac']=$ip[0];
            if (!in_array($ip[1], $devices[$ip[0]]['ips'][$ip[2]])) {$devices[$ip[0]]['ips'][$ip[2]][]=$ip[1];}
            $devices[$ip[0]]['vlan']=$v[1];
            if (!in_array($ip[0], $vlans[$v[0]][$v[1]]['devices'])) {$vlans[$v[0]][$v[1]]['devices'][]=$ip[0];}
            unset($v);
            continue;
        }
        if ($ip[0]==$devices[$dkey]['mac']){
            //	if (($ip[0]==$devices[$dkey]['mac']) && ($ip[2]==$devices[$dkey]['port'])) {
            if (!in_array($ip[2], $bridge_names)) {array_push($bridge_names, $ip[2]);}
            $devices[$dkey]['ips'][$ip[2]][] = $ip[1];
        }
    }
    
}

sort($bridge_names);

// find discover_id for additional information
foreach ($devices as $dkey=>$dvalue) {
    foreach ($discover[devices] as $key=>$value) {
        if (strtolower($value['hwaddr'])==strtolower($dkey)) {
            $devices[$dkey]['discover_id']=$key;
            break;
        }
        foreach ($value['addresses'] as $akey=>$avalue) {
            if (strtolower($avalue['hwaddr'])==strtolower($dkey)) {
                $devices[$dkey]['discover_id']=$key;
                break;
            }
        }
    }
}?>
<table class="table table-hover table-bordered"><tbody>
<?php
    echo "<tr valign=top><td><b>Ports</b></td>";           foreach ($interfaces as $key=>$value) { echo "<td>".$key."</td>"; } echo "</tr>";
    echo "<tr valign=top><td><b>Description</b></td>";     foreach ($interfaces as $key=>$value) { echo "<td>".$interfaces[$key]['desc']."</td>"; } echo "</tr>";
    
    echo "<tr valign=top><td><b>Port State/Link</b></td>";           foreach ($interfaces as $key=>$value) {
        $state = explode("/",$interfaces[$key]['state']);
        echo "<td>";
        if ($state[0]=="u") { echo "up"; }
        if ($state[0]=="D") { echo "down"; }
        if ($state[0]=="A") { echo "disabl"; }
        echo "/";
        if ($state[1]=="u") { echo "up"; }
        if ($state[1]=="D") { echo "down"; }
        if ($state[1]=="A") { echo "disabl"; }
        echo "</td>";
    } echo "</tr>";
    
    echo "<tr valign=top><td><b>PoE setting</b></td>";     foreach ($interfaces as $key=>$value) {
        echo "<td>";
        if ($interfaces[$key]['vif']) {
            echo "vif";
        } else {
            echo $interfaces[$key]['poe'];
            echo "/";
            echo $interfaces[$key]['poe_v'];
            echo "/";
            echo $interfaces[$key]['poe_wd'];
        }
        echo "</td>";
    } echo "</tr>";
    
    echo "<tr valign=top><td><b>Speed, Duplex</b></td>";           foreach ($interfaces as $key=>$value) {
        if ($interfaces[$key]['link_detected']=="yes") {
            echo "<td>".$interfaces[$key]['speed'].", ".$interfaces[$key]['duplex']."</td>"; }
        else {
            echo "<td>no link</td>";
        }
    } echo "</tr>";
    
    foreach ($bridge_names as $bridge) {
        echo "<tr valign=top><td><b>Devices</b> ".$bridge."<br>";
        echo $bridges[$bridge]['desc']."<br>";
        echo $bridges[$bridge]['own_ip']."<br>";
        echo "</td>";
        foreach ($interfaces as $key=>$value) {
            echo "<td>";
            if (($eth_in_bridge[$key]!=$bridge)&&(isset($bridges[$bridge]['name']))) {
                // only native bridges
                echo $key." not member<br>";
            }
            foreach ($interfaces[$key]['devices'] as $d) {
                foreach ($devices[$d]['ips'][$bridge] as $ip) {
                    if (substr($ip,0,8)=="192.168.") {
                        if ($skip_this==1) { continue; }
                        echo '192.168.*.*';
                        $skip_this = 1;
                        continue;
                    }
                    echo $ip." (".$devices[$d]['age'].")<br>";
                    if ($devices[$d]['discover_id']) {
                        $tmp=$discover['devices'][$devices[$d]['discover_id']];
                        if (isset($tmp['hostname'])) { echo "=".$tmp['hostname']."<br>"; }
                        if (isset($tmp['product'])) { echo "-".$tmp['product']."<br>"; }
                        if (isset($tmp['fwversion'])) { echo "::".parse_firmware($tmp['fwversion'])."<br>"; }
                        //if (isset($tmp['essid'])) { echo "@".$tmp['essid']."<br>"; }
                        unset($tmp);
                    }
                }
            }
            unset($skip_this);
            echo "</td>";
        } echo "</tr>";
    }
    
    foreach ($vlan_list as $vlan_id) {
        echo "<tr valign=top><td>VLAN ".$vlan_id."</td>";
        foreach ($interfaces as $port=>$value) { // alle eth-ports durchgehen
            echo "<td>";
            if (isset($vlans[$port][$vlan_id]['desc'])) { // vlan existiert an diesem ETH-port
                if (isset($eth_in_bridge[$port.".".$vlan_id])) {
                    echo "part of ".$eth_in_bridge[$port.".".$vlan_id]."<br>";
                }
                echo "<b>".$vlans[$port][$vlan_id]['desc']."</b><br>";
                if (isset($vlans[$port][$vlan_id]['own_ip'])) {
                    echo "<b>".$vlans[$port][$vlan_id]['own_ip']."</b><br>";
                }
                foreach ($vlans[$port][$vlan_id]['devices'] as $d) {
                    echo $d.":<br>";
                    foreach ($devices[$d]['ips'][$port.".".$vlan_id] as $ip) {
                        if (substr($ip,0,8)=="192.168.") {
                            $ip='192.168.*.*';
                        }
                        echo "=".$ip." <br>";
                    }
                    if ($devices[$d]['discover_id']) {
                        $tmp=$discover['devices'][$devices[$d]['discover_id']];
                        if (isset($tmp['hostname'])) { echo "=".$tmp['hostname']."<br>"; }
                        if (isset($tmp['product'])) { echo "-".$tmp['product']."<br>"; }
                        if (isset($tmp['fwversion'])) { echo "::".parse_firmware($tmp['fwversion'])."<br>"; }
                        //if (isset($tmp['essid'])) { echo "@".$tmp['essid']."<br>"; }
                        unset($tmp);
                    }
                }
            }
            echo "&nbsp;</td>";
        }
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    //echo "\n<pre>";
    //print_r($bridges);
    //print_r($vlans);
    //print_r($interfaces);
    //print_r($bridge_names);
    //print_r($br0_ips);
    //print_r($devices);
    //print_r($eth_speeds);
    //print_r($discover);
    echo "</pre>";?>
                    </div>
					<div role="tabpanel" class="tab-pane" id="contact">
						in Arbeit :D...
					</div>
				</div>
			</div>
		<!--</div>-->
	</div>
</div>

<?php
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_time = round(($finish - $start), 4);
?>
<footer class="footer">
  <div class="container">
    <p class="text-muted"><?php echo 'Page generated in '.$total_time.' seconds.'; ?></p>
  </div>
</footer>
    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="/js/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
  </body>
</html>
<?php
}
unset($APP);
?>
