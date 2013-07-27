<?php
echo "Initialisation of the server wrapper...\n";

$options = "";
$CONFIG = array();

function get_config()
{
	global $CONFIG;
	$CONFIG = @parse_ini_file("config.ini");

	if(!$CONFIG)
	{
		$CONFIG = parse_ini_file("config.template.ini");
	}
}

foreach($argv as $k => $v)
{
	// first option is the script path
	if($k == 0)
	{
		
	}
	else if(preg_match("/^-server:/", $v))
	{
		$server = str_replace("-server:", "", $v);
	}
	else
	{
		$options .= ($options == "" ? "" : " ") . $v;
	}
}

if(isset($server))
{
	get_config();

	if($CONFIG)
	{
		while(system($CONFIG["php_path"] . " ". $CONFIG["servers_path"] . $server . "/" . $server . ".php " . $options) == "-reboot_on_shutdown")
		{
			get_config();
		}
	}
	else
	{
		echo "Configuration file missing !\n";
	}
}
else
{
	echo "ERROR: Server not specified.\nUse the -server:MY_SERVER_NAME_HERE option with the name of the server's php file.\n";
}

?>