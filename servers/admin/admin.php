<?php
require_once("AdminCP_SocketServer.php");

$server = new AdminCP_SocketServer($CONFIG["default_ip"], 8081);
$server->set_server_name("Admin Control Panel");
$server->set_admin_password("admin");
$server->set_max_clients(10);
$server->run();
?>