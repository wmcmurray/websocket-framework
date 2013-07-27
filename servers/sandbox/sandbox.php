<?php
require_once("Sandbox_SocketServer.php");

$server = new Sandbox_SocketServer($CONFIG["default_ip"], 8079);
$server->set_server_name("Sandbox");
$server->register_admin_command("destroy");
$server->run();
?>