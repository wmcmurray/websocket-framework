<?php
define("SERVER_PATH", $argv[0]);											// path of the server php file
define("REMOTE_ADMIN_ACCESS", in_array("-admin", $argv) ? true : false);	// (true|false), define if the server is controlable from a javascript client
define("VERBOSE_MODE", in_array("-verbose", $argv) ? true : false);			// (true|false), define if the server will output what is happening
define("DEBUG_MODE", in_array("-debug", $argv) ? true : false);				// (true|false), define if the server will output debuging infos
define("WARNING_MODE", in_array("-warn", $argv) ? true : false);			// (true|false), define if the server will output warnings

ob_start();
error_reporting(DEBUG_MODE ? E_ALL : 0);
date_default_timezone_set(ini_get('date.timezone') ? ini_get('date.timezone') : 'America/Montreal');
set_time_limit(0);
require_once("fonctions/main.php");
require_once("classes/SocketClient.php");
require_once("classes/SocketServer.php");
ob_end_clean();
echo "\r\n";

$options = "";
foreach($argv as $k => $v)
{
	if($k != 0)
	{
		$options .= ($options == "" ? "" : " ") . $v;
	}
	
}

define("SCRIPT_OPTIONS", $options); // list of all options passed to the script

if($options)
{
	output($options, true);
}
?>