<?php

class SocketServer
{
	private $server_name 	= "SERVER"; // default server name
	private $admin_password = "root";	// default admin password
	private $max_clients 	= 1000;		// default connected clients limit
	private $version 		= "0.0.4";	// server version
	private $socket_select_timeout = 1;
	private $socket_recv_len = 2048;
	private $tick_interval = 1;
	private $config = array();
	private $clients = array();
	private $events_listeners = array();
	private $ping_requests_queue = array();
	private $last_loop_time;
	private $master_socket;
	private $is_running;
	private $reboot_on_shutdown;

	private $cmds_requiring_admin_privileges = array("kick", "shutdown", "reboot", "sleep", "clients_count", "clients_list", "ping_client", "last_error", "options");
	
	// PUBLIC
	//==========================================================
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

	public function set_socket_option($optname, $optval = null)
	{
		socket_set_option($this->master_socket, SOL_SOCKET, $optname, $optval);
	}

	public function get_socket_option($optname)
	{
		socket_get_option($this->master_socket, SOL_SOCKET, $optname);
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
							$this->handle_data($client, $data);
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
		$this->sys_disconnect_all();
		socket_close($this->master_socket);
		
		if($this->reboot_on_shutdown)
		{
			// this line tell the server wrapper loop to restart this script
			echo "\r\n-reboot_on_shutdown";
		}
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
	public function set_admin_password($password = "")
	{
		$this->admin_password = $password;
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

	public function register_admin_command($cmd)
	{
		if(is_array($cmd))
		{
			foreach($cmd as $k => $v)
			{
				$this->register_admin_command($v);
			}
		}
		else
		{
			$this->cmds_requiring_admin_privileges[] = $cmd;
		}
	}

	public function create_client($socket = NULL)
	{
		$client = new SocketClient($socket);
		$this->add_client($client);
		return $client;
	}
	
	// PROTECTED
	//==========================================================
	protected function init()
	{

	}

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

	protected function get_clients_count()
	{
		return count($this->get_clients());
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
	
	// PRIVATE
	//==========================================================
	private function write($socket, $data)
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

	private function add_client($client)
	{
		$this->clients[] = $client;
	}

	private function remove_client($client)
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

	private function get_all_sockets()
	{
		$a = array($this->master_socket);
		$clients = $this->get_clients();
		foreach($clients as $c)
		{
			$a[] = $c->socket;
		}
		return $a;
	}

	private function get_client_by_socket($socket)
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

	private function get_client_by_id($id)
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
	
	private function sys_send($client, $cmd, $content = "")
	{
		$this->write($client->socket, array(
			"sys" => $cmd,
			"content" => $content
		));
	}

	private function sys_send_to_all($cmd, $content = "")
	{
		$clients = $this->get_clients();
		foreach($clients as $a)
		{
			$this->sys_send($a, $cmd, $content);
		}
	}

	private function sys_disconnect_all()
	{
		$clients = $this->get_clients();
		foreach($clients as $a)
		{
			$this->disconnect($a);
		}
	}
	
	private function do_if_admin($client)
	{
		if(REMOTE_ADMIN_ACCESS)
		{
			if($client->is_admin())
			{
				return true;
			}
			else
			{
				$this->sys_send($client, "alert", "Admin privilege required.");
			}
		}
		return false;
	}
	
	private function handle_data($client, $data)
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
					debug('Handling action "' . $json->action . '"...');
					$this->exec_method("handle_" . $json->action, array($client, $json->content));
				}
				
				else if(isset($json->sys))
				{
					$access_required = in_array($json->sys, array("login", "logout"));
					$privileges_required = in_array($json->sys, $this->cmds_requiring_admin_privileges);

					if($access_required && !REMOTE_ADMIN_ACCESS)
					{
						debug("Remote admin access isn't enabled. (-admin option)");
						return false;
					}

					if(($privileges_required && $this->do_if_admin($client)) || !$privileges_required)
					{
						debug('Handling system command "' . $json->sys . '"...');
					}
					else
					{
						warning('client #' . $client->id . ' tried to execute "' . $json->sys . '".');
						return false;
					}
					
					switch($json->sys)
					{
						// ----------------------- public commands
						case "exit":
							$this->disconnect($client);
						break;
						case "ping_request":
							$this->sys_send($client, "ping_response");
						break;
						case "ping_response":
							if(isset($this->ping_requests_queue[$client->id]) && $json->content)
							{
								$ping = round((microtime(true) - $json->content) * 1000);

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
						break;

						// ----------------------- admin commands
						case "login":
							if($client->is_admin())
							{
								$this->sys_send($client, "alert", "You are already admin.");
							}
							else if($this->admin_password === $json->content)
							{
								$client->grant_admin();
								$this->sys_send($client, "alert", "You are now admin.");
							}
							else
							{
								warning('client #' . $client->id . ' entered a wrong password in admin login.');
								$this->sys_send($client, "alert", "Access denied.");
							}
						break;
						case "logout":
							if($client->is_admin())
							{
								$client->revoke_admin();
								$this->sys_send($client, "alert", "Your admin privilege have been revoked.");
							}
							else
							{
								$this->sys_send($client, "alert", "You can't log out if you are not logged in.");
							}
						break;
						case "kick":
							$c = $this->get_client_by_id($json->content);
							if($c)
							{
								if($client->id !== $json->content)
								{
									$this->exec_method("on_client_kick", $c);
									$this->disconnect($c);
								}
								else
								{
									$this->sys_send($client, "alert", "Kicking yourself is unfortunately impossible. (and stupid)");
								}
							}
						break;
						case "shutdown":
							$this->is_running = false;
						break;
						case "reboot":
							$this->sys_send_to_all("reboot");
							$this->exec_method("on_server_reboot");
							$this->reboot_on_shutdown = true;
							$this->is_running = false;
						break;
						case "sleep":
							$time = intval($json->content);
							if($time > 0)
							{
								output("Zzzzzz for " . $time . " seconds...");
								sleep($time);
								output("Zzzzzz is over !");
							}
						break;
						case "clients_count":
							$this->sys_send($client, "alert", $this->get_clients_count() . " clients connected.");
						break;
						case "clients_list":
							$output = "Clients list :";
							foreach($this->clients as $k => $v)
							{
								$output .= "\n[" . $k . "] = " . $v->get_profile();
							}
							$this->sys_send($client, "alert", $output);
						break;
						case "ping_client":
							$pinged = $this->get_client_by_id($json->content);
							if($pinged)
							{
								if(!isset($this->ping_requests_queue[$pinged->id]))
								{
									$this->ping_requests_queue[$pinged->id] = array();
									$this->sys_send($pinged, "ping_request", microtime(true));
								}
								$this->ping_requests_queue[$pinged->id][] = $client->id;
							}
						break;
						case "last_error":
							ob_start();
							print_r(error_get_last());
							$err = ob_get_clean();

							$this->sys_send($client, "alert", $err != "" ? $err : "No last error.");
						break;
						case "options":
							$this->sys_send($client, "alert", SCRIPT_OPTIONS);
						break;

						// ----------------------- custom commands
						default:
							$this->exec_method("sys_handle_" . $json->sys, array($client, $json->content));
						break;
					}
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
	
	private function decode($payload)
	{
		$output = array();
		$done = false;

		if(isset($this->incomplete_message))
		{
			$payload = $this->incomplete_message . $payload;
			unset($this->incomplete_message);
		}

		do
		{
			$length = ord($payload[1]) & 127;

			if($length == 126)
			{
				$mask = substr($payload, 4, 4);
				$data = substr($payload, 8);
				$length = $this->get_playload_length($payload, 3);
			}
			else if($length == 127)
			{
				$mask = substr($payload, 10, 4);
				$data = substr($payload, 14);
				$length = $this->get_playload_length($payload, 9);
			}
			else
			{
				$mask = substr($payload, 2, 4);
				$data = substr($payload, 6);
			}
			
			$data_length = strlen($data);
			$unmasked = $this->unmask($data, $length, $mask);
			$text = $unmasked[0];
			$overflow = $unmasked[1];

			if($data_length > $length && $length != 0)
			{
				$payload = $overflow;
			}
			else
			{
				$done = true;
			}

			$output[] = $text;
		}
		while(!$done);

		$last_message_len = strlen($output[count($output) -1]);

		if($length > $last_message_len)
		{
			$this->incomplete_message = $payload;
			array_pop($output);
		}

		return $output;
	}

	private function unmask($data, $length, $mask)
	{
		$data_length = strlen($data);
		$text = '';
		$overflow = '';

		for($i = 0; $i < $data_length; ++$i)
		{
			if($i < $length)
			{
				$text .= $data[$i] ^ $mask[$i % 4];
			}
			else
			{
				$overflow .= $data[$i];
			}
		}

		return array($text, $overflow);
	}

	private function get_playload_length($payload, $end)
	{
		$start = 2;
		$length = 0;

        for ($i = $start; $i <= $end; $i++)
        {
            $length <<= 8;
            $length += ord($payload[$i]);
        }

        return $length;
	}
	
	private function encode($text)
	{
		if(is_array($text))
		{
			$text = json_encode($text);
		}
		
		//$text = base64_encode($text);
		$b1 = 0x80 | (0x1 & 0x0f); // 0x1 text frame (FIN + opcode)
		$length = strlen($text);
		if($length <= 125)
		{
			$header = pack('CC', $b1, $length);
		}
		else if($length > 125 && $length < 65536)
		{
			$header = pack('CCn', $b1, 126, $length);
		}
		else if($length >= 65536)
		{
			$header = pack('CCN', $b1, 127, $length);
		}
		return $header . $text;
	}
	
	private function handshake($client, $raw_headers)
	{
		//debug($raw_headers);
		
		$headers = array();
		$t = explode("\r\n", $raw_headers);
		foreach($t as $k => $v)
		{
			$p = strpos($v, ":");
			$h = substr($v, 0, $p);
			if($h)
			{
				$headers[$h] = trim(substr($v, $p + 1));
			}
		}
		if(preg_match("/GET (.*) HTTP/", $raw_headers, $match))
		{
			$uri = $match[1];
		}
		else
		{
			$uri = "";
		}
		
		if(isset($headers["Sec-WebSocket-Version"]) && isset($headers["Sec-WebSocket-Key"]))
		{
			if($headers["Sec-WebSocket-Version"] == 13)
			{
				if(preg_match("/GET \/\?(.*) HTTP/", $raw_headers, $match))
				{
					$vars_parts = explode("&", $match[1]);
					$vars = array();
					foreach($vars_parts as $k => $v)
					{
						$t = explode("=", $v);
						$vars[$t[0]] = $t[1];
					}
				}
				else
				{
					$vars = array();
				}

				//if(preg_match("/Host: (.*)\r\n/", $headers, $match)){$host = $match[1];}
				//if(preg_match("/Origin: (.*)\r\n/", $headers, $match)){$client->referer = $match[1];}
				//if(preg_match("/User-Agent: (.*)\r\n/", $headers, $match)){$client->user_agent = $match[1];}
				//if(preg_match("/Accept-Language: (.*)\r\n/", $headers, $match)){$client->lang = $match[1];}//COULD BE BETTER FILTERED
				//if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match)){$key = $match[1];}
				
				//output($uri);
				
				$acceptKey = $headers["Sec-WebSocket-Key"] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
				$acceptKey = base64_encode(sha1($acceptKey, true));
				$upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptKey\r\n\r\n";
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
	
	private function connect($socket)
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
	
	private function exec_method($name = "", $args = array())
	{
		if(method_exists($this, $name))
		{
			if(!is_array($args))
			{
				$args = array($args);
			}

			call_user_func_array(array($this, $name), $args);
		}
	}
}
?>