<?php
require_once("RPG_SocketServer.php");

$server = new RPG_SocketServer($CONFIG["default_ip"], 8083);
$server->set_server_name("RPG - Server");
$server->run();
?>