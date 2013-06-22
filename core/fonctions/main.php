<?php
function output($m,$f=false){if(VERBOSE_MODE||$f){echo now()." >> ".$m."\n";}}
function debug($m){if(DEBUG_MODE){output($m,true);}}
function now(){return date("Y-m-d H:i:s");}
function cookie($name,$value="",$days=1){setcookie($name,$value,time()+60*60*24*$days,"/");/*,DOMAIN,isset($_SERVER["HTTPS"])?$_SERVER["HTTPS"]:false*/}
function delete_cookie($name){cookie($name,"",-1);}
?>