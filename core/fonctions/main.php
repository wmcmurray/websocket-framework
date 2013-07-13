<?php
function output($m,$f=false){if(VERBOSE_MODE||$f){global $CONFIG; echo date($CONFIG["date.format"])." >> ".$m."\n";}}
function debug($m){if(DEBUG_MODE){output("DEBUG: " . $m,true);}}
function warning($m){if(WARNING_MODE){output("WARNING: " . $m,true);}}
function cookie($name,$value="",$days=1){setcookie($name,$value,time()+60*60*24*$days,"/");/*,DOMAIN,isset($_SERVER["HTTPS"])?$_SERVER["HTTPS"]:false*/}
function delete_cookie($name){cookie($name,"",-1);}
?>