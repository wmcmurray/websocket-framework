<?php
ob_start();

// get config file
$CONFIG = file_exists("config.ini") ? @parse_ini_file("config.ini") : false;
if(!$CONFIG)
{
	$CONFIG = parse_ini_file("config.template.ini");
}

// get options
$options = "";
foreach($argv as $k => $v)
{
	if($k != 0)
	{
		$options .= ($options == "" ? "" : " ") . $v;
	}
}

define("SERVER_ROOT", dirname(realpath($argv[0])) . "/");					// path of the server root
define("SCRIPT_OPTIONS", $options); 										// list of all options passed to the script
define("REMOTE_ADMIN_ACCESS", in_array("-admin", $argv) ? true : false);	// (true|false), define if the server is controlable from a javascript client
define("VERBOSE_MODE", in_array("-verbose", $argv) ? true : false);			// (true|false), define if the server will output what is happening
define("DEBUG_MODE", in_array("-debug", $argv) ? true : false);				// (true|false), define if the server will output debuging infos
define("WARNING_MODE", in_array("-warn", $argv) ? true : false);			// (true|false), define if the server will output warnings


error_reporting(DEBUG_MODE ? E_ALL : 0);
date_default_timezone_set($CONFIG["date.timezone"] ? $CONFIG["date.timezone"] : (ini_get('date.timezone') ? ini_get('date.timezone') : 'America/Montreal'));
set_time_limit(0);
require_once("fonctions/main.php");
require_once("classes/SocketClient.php");
require_once("classes/SocketServer.php");

ob_end_clean();
echo "\r\n";
?>