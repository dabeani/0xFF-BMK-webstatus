 <?php

function namevlan ($v) {
  switch ($v) {
    case 1001:
      return "Bullet2HPtoRR72";
    case 1002:
      return "AirGridM5xMZG32";
    case 1003:
      return "NanoBridgeM5xLUXI";
    case 1004:
      return "NanoStationM5xHOCHMAYER";
    case 1005:
      return "free";
    case 1006:
      return "free";
    case 1007:
      return "free";
    case 1008:
      return "sso2";
    case 1009:
      return "s2";
    case 1010:
      return "ssw2";
    case 1011:
      return "sw2";
    case 1012:
      return "wsw2";
    case 1013:
      return "w2";
    case 1014:
      return "wnw2";
    case 1015:
      return "nw2";
    case 1016:
      return "nnw2";
    case 1017:
      return "n5";
    case 1018:
      return "nno5";
    case 1019:
      return "no5";
    case 1020:
      return "ono5";
    case 1021:
      return "o5";
    case 1022:
      return "oso5";
    case 1023:
      return "so5";
    case 1024:
      return "sso5";
    case 1025:
      return "s5";
    case 1026:
      return "ssw5";
    case 1027:
      return "sw5";
    case 1028:
      return "wsw5";
    case 1029:
      return "w5";
    case 1030:
      return "wnw5";
    case 1031:
      return "nw5";
    case 1032:
      return "nnw5";
    case 1099;
      return "ToughSwitch 8-PRO";
    case 1100:
        return "mgmt";
    case 1101:
        return "gw1";
    case 1102:
        return "gw2";
    case 1103:
        return "gw3";
  }
}

if (isset($_REQUEST['source'])) {
        if($_REQUEST['source']) {
                show_source(__FILE__);
        }
}
?>

