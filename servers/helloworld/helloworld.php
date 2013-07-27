<?php
require_once("HelloWorld_SocketServer.php");

$server = new HelloWorld_SocketServer($CONFIG["default_ip"], 8080);
$server->set_server_name("Hello World Server");
$server->run();
?>