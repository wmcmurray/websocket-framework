<?php
require_once("../../core/index.php");

class HelloWorld_SocketServer extends Basic_SocketServer
{
    // executed when server receive data
    protected function handle_helloworld($client, $data)
    {
        // display data in console
        output("Data received : " . $data);

        // broadcast data to every connected clients
        $this->send_to_all("helloworld", $data);
    }
}
?>