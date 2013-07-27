<?php
require_once("Chat_SocketServer.php");

$server = new Chat_SocketServer($CONFIG["default_ip"], 8082);
$server->set_server_name("Chat Server");
$server->set_max_clients(50);
$server->run();
?>