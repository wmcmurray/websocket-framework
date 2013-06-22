websocket-framework
===================

This project provide a way to easily create websocket servers as well as client. You won't have to deal with socket listening, handshacking, data unmasking and such, it's all done by the framework, all you have to do is create what you have in mind !


ABOUT
===========================================================================
Version : 0.0.1
Author  : William Mcmurray
Licence : GNU General Public License, version 3.0 (GPLv3)


HOW TO USE ?
===========================================================================
1. Create a new class extending SocketServer and place it in "/my_servers"
2. Overwrite method called "init()" in your extended class
3. Instanciate this class and call method "run()"
4. Execute this script from a command prompt and voilà !


Exemple file :

	<?php
	require_once("../core/index.php");
	class MyServer_SocketServer extends SocketServer
	{
		protected function init()
		{
			//...
		}
	}

	$server = new MyServer_SocketServer("127.0.0.1",8080);
	$server->run();
	?>


SERVER EVENTS
===========================================================================
You can declare these methods in your extended class :

- on_client_login();
- on_client_handshake();
- on_client_logout();
- on_server_shutdown();


HANDLING OF RECEIVED DATA
===========================================================================
Each time the server receive data from a client, it'll try to call a method
to handle it so you have to declare one in your extended class for every
actions you want your server to handle. Function name have to be like this :

- handle_[ACTION_NAME_HERE]($client, $data);


METHODS YOU CAN USE IN EXTENDED CLASS
===========================================================================
- set_config();
- get_config();
- set_admin_password();
- set_server_name();
- set_max_clients();

- list_clients();
- get_clients_count();
- get_clients();
- get_clients_from_group();

- send();
- send_to_all();
- send_to_others();
- send_to_others_in_group();
- send_to_group();

- disconnect();