Websocket Framework v0.0.1
======================================

This project provide a way to easily create websocket servers as well as clients. You won't have to deal with socket listening, handshacking, data unmasking and such, it's all done by the framework, all you have to do is create what you have in mind !

**Licence :** GNU General Public License, version 3.0 (GPLv3)

**Please note :** this is an early version so the API is likely to change a bit in future versions.


Features
-------------------------
* **Easy** to use API
* **Remote control** of servers via a JavaScript client (kick clients, shutdown/reboot server, etc.)
* **Multiple servers** configuration based on the same core
* **A groups system** enabling internal communications between group clients (like a chatrooms in a chat)
* **Connected clients limit** to prevent your servers from exploding

Included demos
-------------------------
* A simple **Chat server**
* A server admin **Control Panel**

How to create a server
-------------------------
1. Create a new class extending SocketServer and place it in "/my_servers"
2. Overwrite method called "init()" in your extended class
3. Instanciate this class and call method "run()"
4. In dir "/executables", create an executable file (".bat" in windows) to execute your script
5. Execute this executable file and voilà !


**Exemple :** extended class file "/my_servers/MyServer.php" :

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


**Exemple :** executable file "/executables/MyServer.bat" :

	php ../my_servers/MyServer.php -q

How to handle data received by server
-------------------------
Each time the server receive data from a client, it'll try to call a method
to handle it so you have to declare one in your extended class for every
actions you want your server to handle. Function name have to be like this :

handle_(ACTION_NAME_HERE)($client, $data);

**Exemple :** a server which can handle "helloworld" action :

	<?php
	require_once("../core/index.php");
	class HelloWorld_SocketServer extends SocketServer
	{
		protected function init()
		{
			// set the name of the server (displayed in command prompt)
			$this->set_server_name("Hello World Server");
		}
		
		protected function handle_helloworld($client, $data)
		{
			// will simply output data received in command prompt
			output($data);
			
			// and send back data to every connected clients
			$this->send_to_all("helloworld", $data);
		}
	}

	$server = new HelloWorld_SocketServer("127.0.0.1",8080);
	$server->run();
	?>


API Reference
======================================

Server events
-------------------------
You can declare theses methods in your extended class, theses will be executed when the event is fired on the server.

* on_client_connect();
* on_client_handshake();
* on_client_disconnect();
* on_client_kick();
* on_server_shutdown();
* on_server_reboot();


Server methods
-------------------------

Use theses to set some global variables that affect behavior of server. (call them preferably in the init() method.)

* set_admin_password();
* set_server_name();
* set_max_clients();


Use theses to set configs options specific to your server. You could just set properties in your classe but these methods provide a more centralized environement that is used, by exemple, to apply same config to a new server instance when it's rebooted.

* set_config();
* get_config();


Use theses to retrieve clients lists or infos about connected clients.

* list_clients();
* get_clients_count();
* get_clients();
* get_clients_from_group();

Use theses to send data to all clients or specific clients.

* send();
* send_to_all();
* send_to_others();
* send_to_others_in_group();
* send_to_group();

Use these to force disconnect a client.

* disconnect();


TODOs list
======================================
- [x] Clean this README.md file
- [x] Clean demos code and integrate twitter bootstrap
- [x] Create an Admin Control Panel demo
- [ ] Add more details about APIs methods in README.md file
- [/] Clean the core PHP code
- [ ] Add possibility to set different output modes in each server instances instead of common config file
- [ ] Add an admin command to buffer server output and retrieve it via client to remotely see PHP errors and such
- [ ] Find a way to redefine the server class when server is rebooted remotely by an admin
- [ ] Add a way to ban a client definitively with the Admin API

