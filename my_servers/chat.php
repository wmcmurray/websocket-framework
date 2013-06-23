<?php
require_once("../core/index.php"); // NOTE: make this path absolute if script is executed outside "/executables" dir

class Chat_SocketServer extends SocketServer
{
	// SERVER INIT
	//===========================================================================
	protected function init()
	{
		$this->set_server_name("Chat Server");
		$this->set_admin_password("caca");
		$this->set_max_clients(50);
		$this->set_config(array(
			"messages_kept" => 10 // quantity of saved last messages
		));

		$this->messages = array(); // array containing last messages
	}
	
	// SERVER EVENTS
	//===========================================================================
	// when a client connects to the server
	protected function on_client_connect($client)
	{
		
	}
	
	// when a client has sucessfully done the handshake with the server
	protected function on_client_handshake($client, $headers, $vars)
	{
		if(isset($vars["chatroom"]))
		{
			// join client in the chatroom passed in URL
			$client->join_group($vars["chatroom"]);

			// if username is passed too, set it now
			if(isset($vars["username"]))
			{
				$client->set("username", $vars["username"]);
			}

			// tell other clients of this chatroom that a new user just connected
			$this->send_to_others_in_group($client, "handshake", $client->get("username"));
		}
		else
		{
			$this->disconnect($client);
		}
	}
	
	// when a client is disconnected
	protected function on_client_disconnect($client)
	{
		$this->send_to_others_in_group($client, "disconnect", $client->get("username"));
	}

	// when the server is closing
	protected function on_server_shutdown()
	{
		output("Goodbye !");
	}

	
	// RECEIVED DATA HANDLING
	//===========================================================================
	protected function handle_send_message($client, $data)
	{
		$content = array("client" => $client->get("username"), "message" => $data);

		// erase older messages if limit is reached
		if(count($this->messages) >= $this->get_config("messages_kept"))
		{
			array_shift($this->messages);
		}

		// keep this current message in memory
		array_push($this->messages, $content);

		// broadcast this current message to every clients in the current client group
		$this->send_to_group($client->get_group(), "message", $content);
	}

	protected function handle_list_clients($client, $data)
	{
		// send back the list of all connected clients
		$this->send($client, "list_clients", $this->list_clients(array("username"), $this->get_clients_from_group($client->get_group())));
	}

	protected function handle_list_messages($client, $data)
	{
		// send back the list of last saved messages
		$this->send($client, "list_messages", $this->messages);
	}
	
	protected function handle_change_username($client, $data)
	{
		// change username of client
		$client->set("username", $data);

		// send back the list of all clients to every clients in the current client group
		$this->send_to_group($client->get_group(), "list_clients", $this->list_clients(array("username"), $this->get_clients_from_group($client->get_group())));		
	}
}

// create and start the server
$server = new Chat_SocketServer("127.0.0.1", 8082);
$server->run();
?>