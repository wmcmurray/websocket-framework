<?php
ob_start();
error_reporting(E_ALL);
set_time_limit(0);
require_once("config.php");
require_once("fonctions/main.php");
require_once("classes/SocketClient.php");
require_once("classes/SocketServer.php");
ob_end_clean();
echo "\r\n";
?>