<?php
require_once("Abstract_SocketServer.php");

/**
* Basic_SocketServer
* Contains basic server functionnality (no admin access)
*/
class Basic_SocketServer extends Abstract_SocketServer
{
	protected $server_name 	= "SERVER"; // default server name
	protected $max_clients 	= 1000;		// default connected clients limit
	protected $version 		= "0.0.4";	// server version
	protected $clients = array();
	protected $events_listeners = array();
	protected $ping_requests_queue = array();
	protected $config = array();
	protected $socket_select_timeout = 1;
	protected $socket_recv_len = 2048;
	protected $tick_interval = 1;
	protected $master_socket;
	protected $last_loop_time;
	protected $is_running;
	protected $reboot_on_shutdown;

	public function __construct($address = "127.0.0.1", $port = "8080")
	{
		$this->address = $address;
		$this->port = $port;
		$this->master_socket = socket_create((filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET), SOCK_STREAM, SOL_TCP);
		$this->set_socket_option(SO_REUSEADDR, 1);
		$this->init();
		socket_bind($this->master_socket, $this->address, $this->port) or die();
		socket_listen($this->master_socket, $this->max_clients);
	}
	
	protected function init()
	{

	}
	
	public function run()
	{
		output("Websocket Framework v" . $this->version . " " . SCRIPT_OPTIONS, true);
		output("========================================================", true);
		output($this->server_name . " STARTED RUNNING ON " . $this->address . ":" . $this->port, true);
		output("========================================================", true);
		
		$this->is_running = true;
		$this->last_tick_time = microtime(true);
		$this->tick_counter = 0;

		while($this->is_running)
		{
			$changed_sockets = $this->get_all_sockets();
			$write  = NULL;
			$except = NULL;
			@socket_select($changed_sockets, $write, $except, $this->socket_select_timeout);

			foreach($changed_sockets as $socket)
			{
				if($socket == $this->master_socket)
				{
					if(($accepted_socket = socket_accept($this->master_socket)))
					{
						$client = $this->connect($accepted_socket);
					}
					else
					{
						debug(socket_strerror(socket_last_error($accepted_socket)));
					}
				}
				else
				{
					$client = $this->get_client_by_socket($socket);
					if($client)
					{
						$bytes = socket_recv($socket, $data, $this->socket_recv_len, 0);
						if(!$bytes || $bytes === 0)
						{
							$this->disconnect($client);
						}
						else if(!$client->is_handshaked())
						{
							if(!$this->handshake($client, $data))
							{
								debug("Hanshaking FAILED.");
							}
						}
						else
						{
							$this->process_data($client, $data);
						}
					}
				}
			}

			if($this->tick_interval > 0)
			{
				$timebetween = microtime(true) - $this->last_tick_time;

				if($timebetween >= $this->tick_interval)
				{
					$this->exec_method("on_server_tick", array(++$this->tick_counter));
					$this->last_tick_time = microtime(true) - ($timebetween - $this->tick_interval);
				}
			}
		}

		output("SHUTDOWN.", true);
		$this->exec_method("on_server_shutdown");
		$this->disconnect_all();
		socket_close($this->master_socket);
		
		if($this->reboot_on_shutdown)
		{
			// this line tell the server wrapper to restart this script
			echo "\r\n-reboot_on_shutdown";
		}
	}


	// =================================================================================================================
	// SETTERS AND GETTERS
	// =================================================================================================================

	public function set_socket_option($optname, $optval = null)
	{
		socket_set_option($this->master_socket, SOL_SOCKET, $optname, $optval);
	}

	public function get_socket_option($optname)
	{
		socket_get_option($this->master_socket, SOL_SOCKET, $optname);
	}
	
	public function set_server_name($name = "")
	{
		$this->server_name = $name;
	}

	public function set_max_clients($nb)
	{
		$this->max_clients = $nb;
	}

	public function set_socket_select_timeout($nb)
	{
		$this->socket_select_timeout = $nb;
	}

	public function set_socket_recv_len($nb = 2048)
	{
		$this->socket_recv_len = $nb;
	}
	
	public function set_tick_interval($nb)
	{
		$this->tick_interval = $nb;
	}

	public function get_config($name = "")
	{
		return $name ? (isset($this->config[$name]) ? $this->config[$name] : false) : $this->config;
	}
	
	public function set_config($params)
	{
		foreach($params as $k => $v)
		{
			$this->config[$k] = $v;
		}
	}

	protected function get_all_sockets()
	{
		$a = array($this->master_socket);
		$clients = $this->get_clients();
		foreach($clients as $c)
		{
			$a[] = $c->socket;
		}
		return $a;
	}
	
	protected function get_clients($except = NULL)
	{
		if($except)
		{
			if(!is_array($except))
			{
				$except = array($except);
			}
			$o = array();
			foreach($this->clients as $k => $v)
			{
				if(!in_array($v, $except))
				{
					$o[] = $v;
				}
			}
		}
		else
		{
			$o = $this->clients;
		}
		return $o;
	}

	protected function get_client_by_socket($socket)
	{
		$clients = $this->get_clients();
		foreach($clients as $c)
		{
			if($c->socket === $socket)
			{
				return $c;
			}
		}
		return false;
	}

	protected function get_client_by_id($id)
	{
		$clients = $this->get_clients();
		foreach($clients as $c)
		{
			if($c->id === $id)
			{
				return $c;
			}
		}
		return false;
	}

	protected function get_clients_from_group($group, $except = NULL)
	{
		$a = $this->get_clients($except);
		$b = array();
		foreach($a as $c)
		{
			if($c->in_group($group))
			{
				$b[] = $c;
			}
		}
		return $b;
	}

	protected function get_clients_count()
	{
		return count($this->get_clients());
	}

    // return an array of all NPCs (clients without socket)
    protected function get_npcs()
    {
        $clients = $this->get_clients();
        $npcs = array();

        foreach($clients as $client)
        {
            if($client->is_npc())
            {
            	$npcs[] = $client;
            }
        }

        return $npcs;
    }


	// =================================================================================================================
	// CLIENTS HANDLING
	// =================================================================================================================
	
	protected function handshake($client, $raw_headers)
	{
		$headers = $this->headers_to_array($raw_headers);
		$vars = $this->extract_uri($raw_headers, true);
		
		if(isset($headers["Sec-WebSocket-Version"]) && isset($headers["Sec-WebSocket-Key"]))
		{
			if($headers["Sec-WebSocket-Version"] == 13)
			{
				$upgrade = $this->make_upgrade($headers["Sec-WebSocket-Key"]);
				socket_write($client->socket, $upgrade, strlen($upgrade));
				$client->handshake();
				$client->id = isset($vars["cookie"]) ? $vars["cookie"] : uniqid();
				$this->sys_send($client, "set_cookie", $client->id);
				$this->exec_method("on_client_handshake", array($client, $headers, $vars));
				output("handshaked client #" . $client->id);
				return true;
			}
		}
		debug("This browser use a version of websocket which is not supported by this server.");
		return false;
	}

	public function create_client($socket = NULL)
	{
		$client = new SocketClient($socket);
		$this->add_client($client);
		return $client;
	}

	protected function add_client($client)
	{
		$this->clients[] = $client;
	}

	protected function remove_client($client)
	{
		$i = array_search($client, $this->clients);
		if($i >= 0)
		{
			array_splice($this->clients, $i, 1);
			return true;
		}
		else
		{
			return false;
		}
	}
	
	protected function connect($socket)
	{
		if($this->get_clients_count() < $this->max_clients || $this->max_clients === 0)
		{
			$client = $this->create_client($socket);
			$this->exec_method("on_client_connect", array($client));
			output("A client is connecting...");
			return $client;
		}
		else
		{
			debug("Maximum clients limit of " . $this->max_clients . " reached.");
			return false;
		}
	}
	
	protected function disconnect($client)
	{
		$this->exec_method("on_client_disconnect", array($client));
		if(!$client->is_npc())
		{
			output("disconnected client #" . $client->id);
			@socket_shutdown($client->socket, 2);
			@socket_close($client->socket);
		}
		$this->remove_client($client);
	}

	protected function disconnect_all()
	{
		$clients = $this->get_clients();
		foreach($clients as $a)
		{
			$this->disconnect($a);
		}
	}


	// =================================================================================================================
	// INCOMING DATA PROCESSING
	// =================================================================================================================
	
	protected function process_data($client, $data)
	{
		$data = $this->decode($data);

		if(!$data)
		{
			return false;
		}

		foreach($data as $k => $frame)
		{
			$json = json_decode($frame);

			if($json && !is_int($json) && count((array)$json) == 2 && isset($json->content))
			{
				if(isset($json->action))
				{
					$this->process_normal_data($client, $json);
				}
				
				else if(isset($json->sys))
				{
					$this->process_system_data($client, $json);
				}
			}
			else
			{
				$len = strlen($frame);

				// detect the connection close bytes
				if($len == 2)
				{
					$data_bytes = "";
					for($i = 0; $i < $len; $i++)
					{
					   $data_bytes += ord($frame[$i]);
					}
					
					if($data_bytes == 236)
					{
						$this->disconnect($client);
						return true;
					}
				}
				
				// if not the closing bytes, handle data as raw data
				$this->exec_method("raw_handle", array($client, $frame));
				return true;
			}
		}
	}

	protected function process_normal_data($client, $json)
	{
		debug('Handling action "' . $json->action . '"...');
		$this->exec_method("handle_" . $json->action, array($client, $json->content));
	}

	protected function process_system_data($client, $json)
	{
		debug('Handling system command "' . $json->sys . '"...');
		$this->exec_method("sys_handle_" . $json->sys, array($client, $json->content));
	}

	protected function sys_handle_exit($client, $content)
	{
		$this->disconnect($client);
	}

	protected function sys_handle_ping_request($client, $content)
	{
		$this->sys_send($client, "ping_response");
	}

	protected function sys_handle_ping_response($client, $content)
	{
		if(isset($this->ping_requests_queue[$client->id]) && $content)
		{
			$ping = round((microtime(true) - $content) * 1000);

			foreach($this->ping_requests_queue[$client->id] as $k)
			{
				$asker = $this->get_client_by_id($k);
				if($asker)
				{
					$this->sys_send($asker, "alert", "Client #" . $client->id . " ping is " . $ping . " milliseconds.");
				}
			}

			unset($this->ping_requests_queue[$client->id]);
		}
	}
	

	// =================================================================================================================
	// OUTGOING DATA PROCESSING
	// =================================================================================================================

	protected function write($socket, $data)
	{
		if(!is_null($socket))
		{
			$data = $this->encode($data);
			if(!@socket_write($socket, $data, strlen($data)))
			{
				$this->disconnect($this->get_client_by_socket($socket));
			}
		}
	}

	protected function send($client, $action = "", $content = NULL)
	{
		// dispatch raw data normally
		if($action && is_null($content))
		{
			$content = $action;
			$action = NULL;
		}

		if(is_array($client))
		{
			foreach($client as $a)
			{
				$this->send($a, $action, $content);
			}
		}
		else
		{
			$this->write($client->socket, !is_null($action) ? array("action" => $action, "content" => $content) : $content);
		}
	}

	protected function send_to_all($action = "", $content = NULL)
	{
		$this->send($this->get_clients(), $action, $content);
	}

	protected function send_to_others($client, $action = "", $content = NULL)
	{
		$this->send($this->get_clients($client), $action, $content);
	}

	protected function send_to_others_in_group($client, $action = "", $content = NULL)
	{
		$this->send($this->get_clients_from_group($client->get_group(), $client), $action, $content);
	}

	protected function send_to_group($group, $action = "", $content = NULL)
	{
		if(is_array($group))
		{
			foreach($group as $k => $v)
			{
				$this->send($this->get_clients_from_group($v), $action, $content);
			}
		}
		else
		{
			$this->send($this->get_clients_from_group($group), $action, $content);
		}
	}
	
	protected function sys_send($client, $cmd, $content = "")
	{
		$this->write($client->socket, array(
			"sys" => $cmd,
			"content" => $content
		));
	}

	protected function sys_send_to_all($cmd, $content = "")
	{
		$clients = $this->get_clients();
		foreach($clients as $a)
		{
			$this->sys_send($a, $cmd, $content);
		}
	}


	// =================================================================================================================
	// OTHERS
	// =================================================================================================================

	protected function list_clients($prop = array(), $clients = NULL)
	{
		if(!is_array($prop))
		{
			$prop = array($prop);
		}
		$a = !is_null($clients) ? $clients : $this->clients;
		$b = array();
		foreach($a as $k => $v)
		{
			$prop2 = $prop;
			$b[$v->id] = array();
			foreach($prop as $k2 => $v2)
			{
				$b[$v->id][$v2] = $v->get($v2);
			}
		}
		return $b;
	}
}

?>