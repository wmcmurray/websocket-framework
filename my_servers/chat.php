<?php
require_once("../core/index.php"); //mettre ce chemin en absolut si le script est exécuté à partir d'un autre endroit que le dossion "executables"

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
			"messages_kept" => 10 // le nombre de messages conservés en mémoire
		));

		$this->messages = array(); // array contenant les derniers messages
	}
	
	// SERVER EVENTS
	//===========================================================================
	// quand un client se connecte au serveur
	protected function on_client_login($client)
	{
		
	}
	
	// quand le serveur "sert la main" au client
	protected function on_client_handshake($client,$headers,$vars)
	{
		if(isset($vars["chatroom"]))
		{
			// join le client à la chatroom passé dans l'url
			$client->join_group($vars["chatroom"]);

			// si son username est passé dans l'url, set son username sur le serveur
			if(isset($vars["username"]))
			{
				$client->set("username", $vars["username"]);
			}

			// signale aux autres clients de la même chatroom que ce client viens de se connecté
			$this->send_to_others_in_group($client,"login", $client->get("username"));
		}
		else
		{
			$this->disconnect($client);
		}
	}
	
	// quand un client se déconnecte
	protected function on_client_logout($client)
	{
		$this->send_to_others_in_group($client,"logout", $client->get("username"));
	}

	// quand le serveur se ferme
	protected function on_server_shutdown()
	{
		output("Goodbye !");
	}

	
	// RECEIVED DATA HANDLING
	//===========================================================================
	protected function handle_send_message($client,$data)
	{
		$content = array("client" => $client->get("username"), "message" => $data);

		// efface le plus ancien message si la limite est atteinte
		if(count($this->messages) >= $this->get_config("messages_kept"))
		{
			array_shift($this->messages);
		}

		// sauvegarde le message en mémoire
		array_push($this->messages, $content);

		// réenvoie le message à tout les clients du même groupe
		$this->send_to_group($client->get_group(), "message", $content);
	}

	protected function handle_list_clients($client,$data)
	{
		// renvoie la liste de tout les clients au client qui l'a demandé
		$this->send($client, "list_clients", $this->list_clients(array("username"), $this->get_clients_from_group($client->get_group())));
	}

	protected function handle_list_messages($client,$data)
	{
		// renvoie la liste de tout les messages envoyés sur le serveur
		$this->send($client, "list_messages", $this->messages);
	}
	
	protected function handle_change_username($client,$data)
	{
		// change le username du client
		$client->set("username", $data);

		// renvoie la liste des clients à tous ceux qui sont dans le même groupe
		$this->send_to_group($client->get_group(), "list_clients", $this->list_clients(array("username"), $this->get_clients_from_group($client->get_group())));		
	}
}

// crée et lance le serveur sur l'addresse et le port passé en paramètre
$server = new Chat_SocketServer("127.0.0.1",8080);
$server->run();
?>