<?php

$options = "";
foreach($argv as $k => $v)
{
	// first is the script path
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
	$config = @parse_ini_file("config.ini");

	if(!$config)
	{
		$config = parse_ini_file("config.template.ini");
	}

	if($config)
	{
		while(system($config["php_path"] . " ". $config["servers_path"] . $server . ".php " . $options) == "-reboot_on_shutdown")
		{

		}
	}
	else
	{
		echo "Configuration file missing !";
	}
}

?>