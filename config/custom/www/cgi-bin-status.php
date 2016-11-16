<?php
// scripts should terminated after 1 minute!
set_time_limit(60);

$APP = Array();
$IP_RANGE=Array();

# required: aptitude install traceroute snmp bind9-host dnsutils nginx php5-fpm php5-curl php5-snmp
# required: /etc/sudoers: www-data ALL=NOPASSWD: ALL

// define all possible management interfaces for status-output
$interface_1100_list='br0.1100,eth0.1100,eth1.1100,eth2.1100,eth3.1100,eth4.1100,eth5.1100,br1.1100,br2.1100';
$get_nslookup_from_nodedb=1;       // enables lookup of IPs from cached node database (originally taken from map meta data at map.funkfeuer.at/wien
$show_link_to_adminlogin=0;        // enables Link to Routerlogin page (with https-port from config-file)
$traceroute_to='78.41.115.228';    // defines destination for traceroute -> should be internet gateway, tunnelserver.funkfeuer.at

$IP_RANGE["78er_range_low"]  = ip2long("78.41.112.1");
$IP_RANGE["78er_range_high"] = ip2long("78.41.119.254");
$IP_RANGE["193er_range_low"] = ip2long("193.238.156.1");
$IP_RANGE["193er_range_high"]= ip2long("193.238.159.254");

// URI in Variablen umwandeln!
parse_str(parse_url($_SERVER["REQUEST_URI"],PHP_URL_QUERY));

function printLoadingText($text) {
    ?>
    <script>
    document.getElementById("overlay").innerHTML = "<h2><span class='glyphicon glyphicon-hourglass' aria-hidden='true'></span> <?php echo $text; ?></h2>";
    </script>
    <?  flush();
}

function getHostnameFromDB($ip) {
	if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
		// $ip is not a valid IP address
		return(array('ip'=>$ip));
	}
	global $IP_RANGE;
	global $node_dns;
	global $get_nslookup_from_nodedb;
	// IP-Check... funkfeuer ip-adresses are useable...
	$ip_long = ip2long($ip);
	if (($ip_long >= $IP_RANGE["78er_range_low"] && $ip_long <= $IP_RANGE["78er_range_high"]) or ($ip_long >= $IP_RANGE["193er_range_low"] && $ip_long <= $IP_RANGE["193er_range_high"])) {
		// $ip is withing the range of 0xFF IP address space
		// if not yet done, load ip whois data from http://ff.cybercomm.at/node_db.json
		if (!isset($node_dns)) {
			$node_dns = json_decode(trim(shell_exec("curl --connect-timeout 1 http://ff.cybercomm.at/node_db.json")), true);
		}
		if (count($node_dns) <= 1) {
			// json not available, stop lookup
			$node_dns=array('0');
			$get_nslookup_from_nodedb=0;
			return(array('ip'=>$ip));
		}
		// {"193.238.159.58":{"n":"1230bfs256","i":"2182","d":"natrouter"}
		if (isset($node_dns[$ip])) {
			$result = $node_dns[$ip]['d'] .".".$node_dns[$ip]['n'] /*.".wien.funkfeuer.at" */;
			foreach (array('m'=>'MID of', 'h'=>'HNA at') as $key=>$text) {
				if (isset($node_dns[$ip][$key])) {
					$result .= " (".$text." ";
					if (isset($node_dns[$node_dns[$ip][$key]]['n'])) {
						$result .= $node_dns[$ip][$key] ."=". $node_dns[$node_dns[$ip][$key]]['d'].".".$node_dns[$node_dns[$ip][$key]]['n'] /*.".wien.funkfeuer.at"*/;
					} else {
						$result .= $node_dns[$ip][$key] ."=unknown device/node";
					}
					$result .= ")";
			}
			}
			$return_arr=$node_dns[$ip];
			$return_arr['ip']=$ip;
			$return_arr['string']=$result;
			return($return_arr);
		}
		return(array('ip'=>$ip));
	} else {
		// $ip is not an 0xFF ip
		return(array('ip'=>$ip));
	}
}
    
function getOLSRLinks() {
	global $get_nslookup_from_nodedb;
    printLoadingText("Loading Status-TAB (generating link-table)...");
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
    
    echo "<table class=\"table table-hover table-bordered table-condensed\"><thead style=\"background-color:#f5f5f5;\"><tr valign=top><td><b>Local IP</b></td><td><b>Remote IP</b></td><td><b>Remote Hostname</b></td><td><b>Hyst.</b></td><td><b>LQ</b></td><td><b>NLQ</b></td><td><b>Cost</b></td><td><b>routes</b></td><td><b>nodes</b></td></tr></thead>\n";
    echo "<tbody>\n";
    foreach ($olsr_links_raw as $getlink) {
        $getlink = preg_replace('/\s+/',',',trim($getlink));
        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\,(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\,(.*)\,(.*)\,(.*)\,(.*)/', $getlink, $link);
        $tmp_output_route_text = "route";
        $tmp_defaultroute = "";
        // prepare the text of route or routes..
        if(!isset($APP["routes_".$link['2']])) {
            $tmp_output_route_text = "no routes";
            $APP["routes_".$link['2']]=0;
        }
        if($APP["routes_".$link['2']] > 1) {
            $tmp_output_route_text = "routes";
        }
        // if we know the default-route, set a colored background of that column
        if($link['2'] == $APP["default_route"]) {
           $tmp_defaultroute = " bgcolor=FFD700";
        }
        $neighbor = gethostbyaddr($link['2']); // do this request only one time...
		$nodes_at_this_route=array();
        ?>
        <!-- Modal -->
<div class="modal fade" id="myModal<?= str_replace('.','',$link['2']); ?>" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="myModalLabel"><?= $APP["routes_".$link['2']];?> <?= $tmp_output_route_text; ?> from <b><?= $neighbor; ?></b></h4>
      </div>
      <div class="modal-body"><?
        if ($APP["routes_".$link['2']]>0) { 
			foreach ($APP["routes"][$link['2']] as $listroutes) {
				echo $listroutes;
				if ((isset($get_nslookup_from_nodedb)) && ($get_nslookup_from_nodedb==1)) {
					$lookup=getHostnameFromDB($listroutes);
					if (isset($lookup['n'])) {
						echo " - ";
						echo $lookup['string'];
						if (!in_array(strtolower($lookup['n']), $nodes_at_this_route )) {
							array_push($nodes_at_this_route, strtolower($lookup['n']));
						}
					}
				}
				echo "<br>";
			}
        }
        ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
        <? if (count($nodes_at_this_route)>0) {
		?>
        <!-- Modal -->
<div class="modal fade" id="myModal<?= str_replace('.','',$link['2']); ?>_nodes" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="myModalLabel"><?= count($nodes_at_this_route);?> nodes from <b><?= $neighbor; ?></b></h4>
      </div>
      <div class="modal-body"><?
			foreach ($nodes_at_this_route as $node) {
				echo "- ".$node;
				echo "<br>";
			}
        ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
        <?
		}
        echo "<tr".$tmp_defaultroute."><td>".$link['1']."</td><td><a href=https://".$link['2']." target=_blank>".$link['2']."</a></td><td><a href=https://".$neighbor." target=_blank>".$neighbor."</a></td><td>".$link['3']."</td><td>".$link['4']."</td><td>".$link['5']."</td><td>".$link['6']."</td>";
		echo "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal".str_replace('.','',$link['2'])."\">".$APP["routes_".$link['2']]."</button></td>";
		echo "<td align=right><button type=\"button\" class=\"btn btn-primary btn-xs\" data-toggle=\"modal\" data-target=\"#myModal".str_replace('.','',$link['2'])."_nodes\">". count($nodes_at_this_route) ."</button></td>";
		echo "</tr>";
		flush();
    }
    echo "</tbody></table>\n";
    unset($routes_raw);
    unset($olsr_links_raw);
    unset($nodes_at_this_route);
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

function format_wmode($in) {
	if ($in == -1) { return "CABLE"; }
	if ($in ==  3) { return "AP"; }
	if ($in ==  2) { return "STA"; }
	return $in;
}

function format_duration($in) {
	if ($in  <= 120)  return $in."sec";    // 0-120 sek
	if ($in  <= 120*60)  return round($in/60,0)."min"; // 2-120min
	if ($in  <= 50*60*60)  return round($in/60/60,0)."h"; // 2-50h
	return round($in/60/60/24,0)."d"; // 2+ tage
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
 }
} else { // standard-action without "get"

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

$APP = Array();

$APP["ip"] = $_SERVER["SERVER_ADDR"]; // $_SERVER["SERVER_NAME"];
$APP["hostname"] = gethostbyaddr($APP["ip"]);
    
    
// if dns resolver is not working / too slow - get IP-Adress!
if (($APP["hostname"]==$APP["ip"]) || (strlen($APP["hostname"]) < 2)) {
    $APP["hostname"] = $_SERVER["SERVER_NAME"]; //$APP["ip"];
}

$APP["v4defaultrouteviaip"] = trim(shell_exec("ip r | grep default | awk {'print $3'}"));
$APP["v4defaultrouteviadns"] = gethostbyaddr($APP["v4defaultrouteviaip"]);
flush();
//$APP["host"] = substr(shell_exec("/usr/bin/host ".$APP["ip"]." | cut -d ' ' -f 5"),0,-2);
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
	<? /* favicons on iPhone/iPad only work, if FQDN is used as url; wont work over IP - might need a fix for correct PORT in URI */ ?>
	<link rel="shortcut icon" type="image/ico" href="https://<?= $APP["hostname"]; ?>/css/favicon.ico">
	<link rel="apple-touch-icon" sizes="114x114" href="https://<?= $APP["hostname"]; ?>/css/apple-touch-icon.png">
	<link rel="icon" type="image/png" href="https://<?= $APP["hostname"]; ?>/css/favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="https://<?= $APP["hostname"]; ?>/css/favicon-16x16.png" sizes="16x16">
	<link rel="icon" type="image/x-icon" href="https://<?= $APP["hostname"]; ?>/css/favicon.ico">

    <title><?php echo $APP["hostname"];?></title>

    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

  </head>
  <body>

<style>
    #overlay {
        background: #ffffff;
        color: #666666;
        position: fixed;
        height: 100%;
        width: 100%;
        z-index: 5000;
        opacity: 0.5;
        top: 0;
        left: 0;
        float: left;
        text-align: center;
        padding-top: 25%;
    }
</style>
<div id="overlay">
    <h2><span class="glyphicon glyphicon-hourglass" aria-hidden="true"></span> Loading...</h2>
</div><?php flush(); ?>

<div class="container">
	<div class="row">
		<!--<div class="col-md-6">-->
			<!-- Nav tabs --><div class="card">
				<ul class="nav nav-tabs" role="tablist">
					<li role="presentation"><a href="#main" aria-controls="main" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> &Uuml;bersicht</a></li>
					<li role="presentation" class="active"><a href="#status" aria-controls="status" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> Status</a></li>
                    <li role="presentation"><a href="#table" aria-controls="table" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-list" aria-hidden="true"></span> Port-Table</a></li>
					<li role="presentation"><a href="#contact" aria-controls="contact" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Kontakt</a></li>
					<li role="presentation"><a href="#"><?php echo  $APP["ip"] ." - ".$APP["hostname"]; ?></a></li>
				<?	if ((isset($show_link_to_adminlogin)) && ($show_link_to_adminlogin==1)) {
						$port_array_raw=explode("\n",trim(shell_exec("grep -E \"http.{0,1}-port\" /config/config.boot")));
						foreach ($port_array_raw as $line) {
							$tmp=explode(" ", trim($line));
							$port_array[$tmp[0]]=$tmp[1];
						}
						unset($tmp); 
						unset($port_array_raw);
						unset($line);
						?><li role="presentation"><a href="<? echo "https://".$_SERVER['SERVER_NAME'].":".$port_array['https-port']."/";   ?>"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Login</a></li>
						<?
					}
				?>
				</ul><br>
<?
//$APP["devices"] = explode("\n",shell_exec("/usr/sbin/ubnt-discover -d150 -i \"".$interface_1100_list."\""));
//$APP["devices"][0]=str_replace("Local","Local  ",$APP["devices"][0]);
$APP["v4defaultrouteviaport"] = trim(shell_exec("ip r | grep default | awk {'sub(/^eth0./,\"\",$5);print $5'}"));
$APP["v6defaultrouteviaport"] = trim(shell_exec("ip -f inet6 r | grep default | awk {'sub(/^eth0./,\"\",$5);print $5'}"));
$APP["ipv6_status"] = trim(shell_exec("netstat -na | grep 2008"));
?>
<!-- Tab panes -->
				<div class="tab-content">
<!-- Main TAB -->
                    <div role="tabpanel" class="tab-pane" id="main">
                        <div class="page-header">
                            <h1>Willkommen auf <?php echo $APP["hostname"] . ' <small>' . $APP["ip"]; ?></small></h1>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-body"><b>WAS?</b></div>
                            <div class="panel-footer">FunkFeuer ist ein freies, experimentelles Netzwerk in Wien, Graz, der Weststeiermark, in Teilen des Weinviertels (N&Ouml;) und in Bad Ischl. Es wird aufgebaut und betrieben von computerbegeisterten Menschen. Das Projekt verfolgt keine kommerziellen Interessen.</div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-body"><b>FREI?</b></div>
                            <div class="panel-footer">FunkFeuer ist offen f&uuml;r jeden und jede, der/die Interesse hat und bereit ist mitzuarbeiten. Es soll dabei ein nicht reguliertes Netzwerk entstehen, welches das Potential hat, den digitalen Graben zwischen den sozialen Schichten zu &Uuml;berbr&uuml;cken und so Infrastruktur und Wissen zur Verf&uuml;gung zu stellen. Teilnahme Zur Teilnahme an FunkFeuer braucht man einen WLAN Router (gibt's ab 60 Euro) oder einen PC, das OLSR Programm, eine IP Adresse von FunkFeuer, etwas Geduld und Motivation. Auf unserer Karte ist eingezeichnet, wo man FunkFeuer schon &Uuml;berall (ungef&auml;r) empfangen kann (bitte beachte, dass manchmal H&auml;user oder &Auml;hnliches im Weg sind, dann geht's nur &uuml;ber Umwege).</div>
                        </div>
					</div>
<!-- Status TAB -->
<?php printLoadingText("Loading Status TAB..."); ?>
                    <div role="tabpanel" class="tab-pane active" id="status">
						<dl class="dl-horizontal">
						  <dt>System Uptime <span class="glyphicon glyphicon-time" aria-hidden="true"></span></dt><dd><?php echo shell_exec("uptime") ?></dd>
						  <dt>IPv4 Default-Route <span class="glyphicon glyphicon-transfer" aria-hidden="true"></span></dt><dd><?php echo "<a href=\"https://".$APP["v4defaultrouteviadns"]."\">".$APP["v4defaultrouteviadns"]." (".$APP["v4defaultrouteviaip"].")</a> via ".$APP["v4defaultrouteviaport"]."<br>"; ?></dd>
						  <dt>mgmt Devices <span class="glyphicon glyphicon-signal" aria-hidden="true"></span></dt><dd>
						  <?
						  $APP["devices_list"]=json_decode(shell_exec("/usr/sbin/ubnt-discover -d150 -V -i \"".$interface_1100_list."\" -j"),true);
						  if (count($APP["devices_list"]>0)) {
							echo "<table class=\"table table-hover table-bordered table-condensed\"><thead style=\"background-color:#f5f5f5;\"><tr valign=top><td><b>HW Address</b></td><td><b>Local IP</b></td><td><b>Hostname</b></td>";
							echo "<td><b>Product</b></td><td><b>Uptime</b></td><td><b>WMODE</b></td><td><b>ESSID</b></td><td><b>Firmware</b></td></tr></thead>\n";
							echo "<tbody>\n";
							foreach ($APP["devices_list"] as $device) {
								foreach ($device as $d) {
									echo "<tr>";
									echo "<td>".$d['hwaddr']."</td>";
									echo "<td>".$d['ipv4']."</td>";
									echo "<td>".$d['hostname']."</td>";
									echo "<td>".$d['product']."</td>";
									echo "<td>".format_duration($d['uptime'])."</td>";
									echo "<td>".format_wmode($d['wmode'])."</td>";
									echo "<td>".$d['essid']."</td>";
									echo "<td>".parse_firmware($d['fwversion'])."</td>";
									echo "</tr>\n";
									flush();
								}
							}
							echo "</tbody></table>";
						  } else {
							echo "No devices discovered";
						  }
						  ?>
						  </dd>
						  <dt>IPv4 OLSR-Links <span class="glyphicon glyphicon-link" aria-hidden="true"></span></dt><dd><?php echo getOLSRLinks(); ?></dd>
<?php
if(strlen($APP["ipv6_status"]) > 5) {?>
						  <dt>IPv6 Default-Route <span class="glyphicon glyphicon-transfer" aria-hidden="true"></span></dt><dd><?php echo "<a href=\"http://".$APP["v6defaultrouteviaip"]."/cgi-bin-status.html\">".$APP["v6defaultrouteviaip"]."</a> via ".$APP["v4defaultrouteviaport"]."<br>"; ?></dd>
						  <dt>IPv6 OLSR-Links <span class="glyphicon glyphicon-link" aria-hidden="true"></span></dt><dd><pre><?php echo trim(str_replace($APP["v6defaultrouteviaip"],"<mark><b>".$APP["v6defaultrouteviaip"]."</b></mark>",file_get_contents("http://[::1]:2008/links"))); ?></pre></dd>
<?php } else { echo "<dt>IPv6</dt><dd><span class=\"glyphicon glyphicon-remove-sign\" aria-hidden=\"true\"></span> disabled...</dd>"; }
printLoadingText("Loading Status-TAB (do traceroute)...");
?>
						  <dt>Trace to UPLINK <span class="glyphicon glyphicon-stats" aria-hidden="true"></span></dt><dd>
						  <?php
							echo "<table class=\"table table-hover table-bordered table-condensed\"><thead style=\"background-color:#f5f5f5;\"><tr valign=top><td><b>#</b></td><td><b>Hostname</b></td><td><b>IP Address</b></td>";
							echo "<td><b>Ping</b></td></tr></thead>\n";
							echo "<tbody>\n";
							$tracelines=explode("\n",trim(shell_exec("/usr/bin/traceroute -w 1 -q 1 ".$traceroute_to)));
							array_shift($tracelines); // remove headline
							foreach ($tracelines as $line) {
								$line=str_replace('     ',' ',$line); 
								$line=str_replace('    ',' ',$line); 
								$line=str_replace('   ',' ',$line); 
								$line=str_replace('  ',' ',$line); 
								$hop = explode(" ", trim($line));
								//  1  router.luxi122home.wien.funkfeuer.at (78.41.113.155)  5.307 ms
								echo "<tr>";
								echo "<td>".$hop[0]."</td>"; // hop number
								echo "<td>";
								if (strlen($hop[1])>=5) { echo "<a href=\"http://".$hop[1]."\" target=\"".$hop[1]."\">"; }
								if (strstr($hop[1], 'wien.funkfeuer.at')==TRUE) {
									$hostname=explode(".",$hop[1]);
									$smoke_image_url="http://smokeping.funkfeuer.at/smokeping/freenet/img/".$hostname[1]."/".$hostname[0]."_mini.png";
									$hostname[1]="<b>".$hostname[1]."</b>";
									echo implode(".",$hostname); // hostname with nodename highlighted
								} else {
									echo $hop[1]; // hostname as is
									unset($smoke_image_url);
								}
								if (strlen($hop[1])>=5) { echo "</a>"; }
								if ((isset($smoke_image_url)) && (1==2)) {   // no not execute - future feature
									$headers = get_headers($smoke_image_url);
									$code = $headers[0];
									$code_0=explode(" ", $code);
									if ($code_0[1]=="200") {
										echo "<br><img src=\"".$smoke_image_url."\" BORDER=\"0\" WIDTH=\"697\" HEIGHT=\"137\">";
									} else {
										echo "<!-- smokeping image load error: ~".$code."~ -->";
									}
								}
								echo "</td>";
								echo "<td>";
								$hop[2]=trim($hop[2]," ()[]");
								if (strlen($hop[2])>=5) { echo "<a href=\"http://".$hop[2]."\" target=\"".$hop[2]."\">"; }
								echo $hop[2]; // ip address
								if (strlen($hop[2])>=5) { echo "</a>"; }
								echo "</td>";
								echo "<td align=right>".number_format($hop[3],2)." ".$hop[4]."</td>"; // ping-time
								echo "</tr>\n";
								flush();
							}
							echo "</tbody></table>";
						  ?> 
						  </dd>
						</dl>
					</div>
<!-- Port-Status TAB -->
<script type="text/javascript">
document.getElementById('overlay').style.height='60px';
document.getElementById('overlay').style.top ='0px';
document.getElementById('overlay').style.float='none';
document.getElementById('overlay').style.padding='0';
</script>
<?php printLoadingText("Loading Port-Table TAB..."); ?>
                    <div role="tabpanel" class="tab-pane" id="table">
					<?
					$discover = json_decode(shell_exec("/usr/sbin/ubnt-discover -d150 -V -j"), TRUE);
					// show interfaces ethernet detail | grep -E "^eth.|link/ether" | awk '{if ($1~/^eth./) {gsub(":","",$1); print $1;} else {print ",\""$2"\"";}}' | sed 'N;s/\n//'
					$eth_macs = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --intf-type=ethernet --action=show | grep -E \"^eth.|link/ether\" | awk '{if ($1~/^eth./) { print $1\",\";} else {print $2;}}' | sed 'N;s/\\n//'")));
					
					// show interfaces | grep -E "^eth" | awk '{print $1","$4;}'
					// looses desc after first blank: $eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --action=show-brief | grep -E \"^eth|^br\" | awk '{print $1\",\"$2\",\"$3\",\"$4;}'")));
					$eth_desc = explode("\n",trim(shell_exec("/opt/vyatta/bin/vyatta-show-interfaces.pl --action=show-brief | grep -E \"^eth|^br\"")));
					
					// show arp | awk '{if ($2!~/incomplete/) {print $3","$1","$5}}'
					$br0_ips =    explode("\n",trim(shell_exec("/usr/sbin/arp -e -n | awk '{if ($2!~/incomplete/) {print $3\",\"$1\",\"$5}}'")));
					// skip first line?
					
					// show interfaces ethernet physical
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
					    if (isset(($tmp[1]))) {
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
						// get rid of fixed column layout, leave only one blank as seperator
						$value=str_replace('       ',' ',$value); 
						$value=str_replace('      ',' ',$value); 
						$value=str_replace('     ',' ',$value); 
						$value=str_replace('    ',' ',$value); 
						$value=str_replace('   ',' ',$value); 
						$value=str_replace('  ',' ',$value); 
					    $int = explode(" ", trim($value));
						$int_name=array_shift($int); // retrieve interface name: br0, eth1.101 (was:0)
						$int_ip=array_shift($int); // retrieve interface IP address (was:1)
						$int_state=array_shift($int); // retrieve status: u/u (was:2)
						$int_desc = implode(" ", $int); // rest of array is interface description (was:3)
						$int_desc=trim(str_replace($int_name,'',$int_desc), ' -:()'); // remove self-naming, trim
					    $v=explode(".",$int_name);
					    if (substr($int_name,0,2)=="br") {
					        if (!isset($v[1])) {
					            $bridges[$int_name]['name']=$int_name;
					            $bridges[$int_name]['desc'] = $int_desc;
					            $bridges[$int_name]['state'] = $int_state;
					            $bridges[$int_name]['own_ip'] = $int_ip;
					        } else {
					            unset($v);
					        }
					        continue;
					    }
					    if (isset($v[1])) {
					        // this is a vlan
					        $vlans[$v[0]][$v[1]]['desc'] = $int_desc;
					        $vlans[$v[0]][$v[1]]['own_ip']=$int_ip;
					        unset($v);
					        continue;
					    }
					    $interfaces[$int_name]['desc'] = $int_desc;
					    $interfaces[$int_name]['state'] = $int_state;
					    $interfaces[$int_name]['own_ip'] = $int_ip;
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
					                if(isset($interfaces[$ikey]['port_id'])) {
						                if ($interfaces[$ikey]['port_id']==$int[2]) {
						                    $interfaces[$ikey]['devices'][] =$int[1];
						                }
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
					        if(isset($v[0]) && isset($v[1])) {
						        if (isset($vlans[$v[0]][$v[1]]['desc'])) {
						            // this is a vlan and not a bridge
						            $devices[$ip[0]]['mac']=$ip[0];
						            if ((isset($devices[$ip[0]]['ips'][$ip[2]]))&&(!in_array($ip[1], $devices[$ip[0]]['ips'][$ip[2]]))) {$devices[$ip[0]]['ips'][$ip[2]][]=$ip[1];}
						            $devices[$ip[0]]['vlan']=$v[1];
						            if (!in_array($ip[0], $vlans[$v[0]][$v[1]]['devices'])) {$vlans[$v[0]][$v[1]]['devices'][]=$ip[0];}
						            unset($v);
						            continue;
						        }
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
					    foreach ($discover['devices'] as $key=>$value) {
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
					echo "<table class=\"table table-hover table-bordered table-condensed\"><tbody>";
					    //if($ext == "table") {
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
					        if (isset($interfaces[$key]['vif'])) {
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
					        if (isset($bridges[$bridge]['desc'])) { echo $bridges[$bridge]['desc']."<br>"; }
					        if (isset($bridges[$bridge]['own_ip'])) { echo $bridges[$bridge]['own_ip']."<br>"; }
					        echo "</td>";
					        foreach ($interfaces as $key=>$value) {
					            echo "<td>";
					            if ((isset($eth_in_bridge[$key]))&&($eth_in_bridge[$key]!=$bridge)&&(isset($bridges[$bridge]['name']))) {
					                // only native bridges
					                echo $key." not member<br>";
					            }
					            foreach ($interfaces[$key]['devices'] as $d) {
					                if (isset($devices[$d]['ips'][$bridge])) { foreach ($devices[$d]['ips'][$bridge] as $ip) {
					                    if (substr($ip,0,8)=="192.168.") {
					                        if (isset($skip_this)) { continue; }
					                        echo '192.168.*.*';
					                        $skip_this = 1;
					                        continue;
					                    }
					                    echo $ip." (".$devices[$d]['age'].")<br>";
					                    if (isset($devices[$d]['discover_id'])) {
					                        $tmp=$discover['devices'][$devices[$d]['discover_id']];
					                        if (isset($tmp['hostname'])) { echo "=".$tmp['hostname']."<br>"; }
					                        if (isset($tmp['product'])) { echo "-".$tmp['product']."<br>"; }
					                        if (isset($tmp['fwversion'])) { echo "::".parse_firmware($tmp['fwversion'])."<br>"; }
					                        //if (isset($tmp['essid'])) { echo "@".$tmp['essid']."<br>"; }
					                        unset($tmp);
					                    }
					                }}
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
					                    if (isset($devices[$d]['ips'][$port.".".$vlan_id])) { foreach ($devices[$d]['ips'][$port.".".$vlan_id] as $ip) {
					                        if (substr($ip,0,8)=="192.168.") {
					                            $ip='192.168.*.*';
					                        }
					                        echo "=".$ip." <br>";
					                    }}
					                    if (isset($devices[$d]['discover_id'])) {
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
					    //echo "</pre>";
					    //}
					    echo "</tbody></table>";
					    ?>
                    </div>
<!-- Contact TAB -->
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
    
    <script>
	    jQuery(window).load(function(){
	    jQuery('#overlay').fadeOut();
	    });
	</script>
  </body>
</html>
<?php
}
unset($APP);
unset($node_dns);
?>
