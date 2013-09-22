<?php
require_once("../../core/index.php");

class AdminCP_SocketServer extends SocketServer
{
	// SERVER EVENTS
	//===========================================================================
	// when a client connects to the server
	protected function on_client_connect($client)
	{
		$this->send_to_others($client, "connect");
	}
	
	// when a client has sucessfully done the handshake with the server
	protected function on_client_handshake($client, $headers, $vars)
	{
		$this->send_to_others($client, "handshake", $client->id);
	}
	
	// when a client is disconnected
	protected function on_client_disconnect($client)
	{
		$this->send_to_others($client, "disconnect", $client->id);
	}

	// when a client is kicked
	protected function on_client_kick($client)
	{
		$this->send($client, "kicked");
	}						

	// when the server is closing
	protected function on_server_shutdown()
	{
		$this->send_to_all("shutdown");
	}

	// when the server is rebooting
	protected function on_server_reboot()
	{
		$this->send_to_all("reboot");
	}
}
?>