<?php
require_once("../core/index.php"); // NOTE: make this path absolute if script is executed outside "/executables" dir

class HelloWorld_SocketServer extends SocketServer
{
    protected function init()
    {
        $this->set_server_name("Hello World Server");
    }

    // executed when server receive data
    protected function handle_helloworld($client, $data)
    {
        // display data in console
        output("Data received : " . $data);

        // broadcast data to every connected clients
        $this->send_to_all("helloworld", $data);
    }
}

$server = new HelloWorld_SocketServer("127.0.0.1", 8080);
$server->run();
?>