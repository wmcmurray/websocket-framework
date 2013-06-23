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

Demos included
-------------------------
* An "HelloWorld" server
* A simple **chat server**
* An **admin control panel**


Instructions
======================================

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

	$server = new HelloWorld_SocketServer("127.0.0.1", 8080);
	$server->run();
	?>
	
How to setup a JavaScript client
-------------------------
Until this doc is completed, please look at the demos !



API Reference
======================================

## Back-end

### Server events
You can declare theses methods in your extended class, theses will be executed when the event is fired on the server.

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>on_client_connect()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Executed when a client is trying to connect to the server (before handshake is done).</td>
	</tr>
	<tr>
		<td><strong>on_client_handshake()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
				<li>headers&nbsp;-&nbsp;array()</li>
				<li>vars&nbsp;-&nbsp;array()</li>
			</ul>
		</td>
		<td>Executed when a client is sucessfully handshaked. You got access to an array containing headers sent along with the client request and an array containing variables passed in URL.</td>
	</tr>
	<tr>
		<td><strong>on_client_disconnect()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Executed when a client socket is closed.</td>
	</tr>
	<tr>
		<td><strong>on_client_kick()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Executed when a client is kicked from the server.</td>
	</tr>
	<tr>
		<td><strong>on_server_shutdown()</strong></td>
		<td></td>
		<td>Executed when the server is shuting down.</td>
	</tr>
	<tr>
		<td><strong>on_server_reboot()</strong></td>
		<td></td>
		<td>Executed just before the server reboots.</td>
	</tr>
</table>



### Server methods



#### Global settings
Use theses to set some global variables that affect behavior of server. (call them preferably in the init() method.)

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>set_admin_password()</strong></td>
		<td>
			<ul>
				<li>newPassword&nbsp;-&nbsp;string</li>
			</ul>
		</td>
		<td>Used to set the password that will be required to be granted by admin priviledges via a JavaScript client.</td>
	</tr>
	<tr>
		<td><strong>set_server_name()</strong></td>
		<td>
			<ul>
				<li>serverName&nbsp;-&nbsp;string</li>
			</ul>
		</td>
		<td>Set the name of the server. Used almost only for display in command prompt.</td>
	</tr>
	<tr>
		<td><strong>set_max_clients()</strong></td>
		<td>
			<ul>
				<li>maxClient&nbsp;-&nbsp;int</li>
			</ul>
		</td>
		<td>Set the limit of accepted clients connections on the server. If this limit is reached, new connections will be rejected until connected clients disconnects.</td>
	</tr>
</table>



#### Configuration
Use theses to set configs options specific to your server. You could just set properties in your classe but these methods provide a more centralized environement that is used, by exemple, to apply same config to a new server instance when it's rebooted.

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>set_config()</strong></td>
		<td>
			<ul>
				<li>properties&nbsp;-&nbsp;array(key=>value)</li>
			</ul>
		</td>
		<td>Set config properties on server by passing an array of key and values pairs.</td>
	</tr>
	<tr>
		<td><strong>get_config()</strong></td>
		<td>
			<ul>
				<li>property&nbsp;-&nbsp;string</li>
			</ul>
		</td>
		<td>Retreive the value of a config property by passing its name.</td>
	</tr>
</table>



#### Filtering clients
Use theses to retrieve clients lists or infos about connected clients.

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>list_clients()</strong></td>
		<td>
			<ul>
				<li>properties&nbsp;-&nbsp;array()</li>
				<li>clients&nbsp;-&nbsp;array()</li>
			</ul>
		</td>
		<td>Return an array of clients each containing an array of desired properties.</td>
	</tr>
	<tr>
		<td><strong>get_clients_count()</strong></td>
		<td></td>
		<td>Return connected clients count (handshaked or not).</td>
	</tr>
	<tr>
		<td><strong>get_clients()</strong></td>
		<td>
			<ul>
				<li>exceptions&nbsp;-&nbsp;array()&nbsp;||&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Return an array of all clients except those passed in parameter.</td>
	</tr>
	<tr>
		<td><strong>get_clients_from_group()</strong></td>
		<td>
			<ul>
				<li>group&nbsp;-&nbsp;SocketClient()->get_group()</li>
				<li>exceptions&nbsp;-&nbsp;array()&nbsp;||&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Return an array of all clients in the same group as the group passed in parameter.</td>
	</tr>
</table>



#### Sending data to clients
Use theses to send data to all clients or specific clients. All data are separated in two parts : the action name and the content. The content will be converted in JSON automatically before being sent.

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>send()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;array()&nbsp;||&nbsp;SocketClient()</li>
				<li>action&nbsp;-&nbsp;string</li>
				<li>content&nbsp;-&nbsp;array()&nbsp;||&nbsp;string</li>
			</ul>
		</td>
		<td>Send data to a client or an array of clients.</td>
	</tr>
	<tr>
		<td><strong>send_to_all()</strong></td>
		<td>
			<ul>
				<li>action&nbsp;-&nbsp;string</li>
				<li>content&nbsp;-&nbsp;array()&nbsp;||&nbsp;string</li>
			</ul>
		</td>
		<td>Send data to all clients.</td>
	</tr>
	<tr>
		<td><strong>send_to_others()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;array()&nbsp;||&nbsp;SocketClient()</li>
				<li>action&nbsp;-&nbsp;string</li>
				<li>content&nbsp;-&nbsp;array()&nbsp;||&nbsp;string</li>
			</ul>
		</td>
		<td>Send data to all clients except those passed in parameter.</td>
	</tr>
	<tr>
		<td><strong>send_to_others_in_group()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
				<li>action&nbsp;-&nbsp;string</li>
				<li>content&nbsp;-&nbsp;array()&nbsp;||&nbsp;string</li>
			</ul>
		</td>
		<td>Send data to other clients in the same group as the group on the client passed in parameter.</td>
	</tr>
	<tr>
		<td><strong>send_to_group()</strong></td>
		<td>
			<ul>
				<li>group&nbsp;-&nbsp;SocketClient()->get_group()</li>
				<li>action&nbsp;-&nbsp;string</li>
				<li>content&nbsp;-&nbsp;array()&nbsp;||&nbsp;string</li>
			</ul>
		</td>
		<td>Send data to every clients of the same group.</td>
	</tr>
</table>



#### Other methods

<table>
	<tr>
		<th>Method</th>
		<th>Parameters</th>
		<th>Description</th>
	</tr>
	<tr>
		<td><strong>disconnect()</strong></td>
		<td>
			<ul>
				<li>client&nbsp;-&nbsp;SocketClient()</li>
			</ul>
		</td>
		<td>Close the connection between a client and the server.</td>
	</tr>
</table>


## Front-end

Until this doc is completed, please look at the demos !


TODOs list
======================================
- [x] Clean this README.md file
- [x] Clean demos code and integrate twitter bootstrap
- [x] Create an Admin Control Panel demo
- [x] Create an Hello World demo
- [ ] Create a simple game demo
- [/] Add more details about APIs methods in README.md file
- [/] Clean the core PHP code
- [ ] Add possibility to set different output modes in each server instances instead of common config file
- [ ] Add an admin command to buffer server output and retrieve it via client to remotely see PHP errors
- [ ] Find a way to redefine the server class when server is rebooted remotely by an admin
- [ ] Add a way to ban a client definitively with the Admin API

