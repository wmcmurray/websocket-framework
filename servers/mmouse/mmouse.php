<?php
require_once("MMouse_SocketServer.php");

$server = new MMouse_SocketServer($CONFIG["default_ip"], 8080);
$server->set_server_name("MMouse Server");
$server->set_max_clients(50);
$server->run();
?>