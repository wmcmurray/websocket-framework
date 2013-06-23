<?php

$SocketServer_new_instance;
class SocketServer
{
	private	$server_name="SERVER";
	private $config=array();
	private	$master_socket;
	private	$clients=array();
	private	$events_listeners=array();
	private	$is_running;
	private	$reboot_on_shutdown;
	private	$max_clients=1000;
	
	// PUBLIC
	//==========================================================
	public function __construct($address="127.0.0.1", $port="8080")
	{
		$this->address=$address;
		$this->port=$port;
		$this->set_config(array("admin_password"=>DEFAULT_ADMIN_PASSWORD));
		$this->init();
		$this->init_socket();
	}
	
	public function init_socket()
	{
		$this->master_socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
		socket_set_option($this->master_socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($this->master_socket,$this->address,$this->port) or die("This port is already used.\n");
		socket_listen($this->master_socket,$this->max_clients);
	}
	
	public function run()
	{
		output("========================================================",true);
		output($this->server_name." STARTED RUNNING ON ".$this->address.":".$this->port,true);
		output("========================================================",true);
		
		$this->is_running=true;
		while($this->is_running)
		{
			$changed_sockets = $this->get_all_sockets();
			@socket_select($changed_sockets,$write=NULL,$except=NULL,1);
			
			foreach($changed_sockets as $socket)
			{
				if($socket==$this->master_socket)
				{
					if(($accepted_socket=socket_accept($this->master_socket))){$client=$this->connect($accepted_socket);}
					else{debug(socket_strerror(socket_last_error($accepted_socket)));}
				}
				else
				{
					$client = $this->get_client_by_socket($socket);
					if($client)
					{
						$bytes = @socket_recv($socket,$data,2048,0);
						if(!$bytes||$bytes===0){$this->disconnect($client);}
						else if(!$client->is_handshaked()){if($this->handshake($client,$data)){}else{debug("Hanshaking FAILED.");}}
						else{$this->handle_data($client,$data);}
					}
				}
			}
		}
		socket_close($this->master_socket);
		output("SHUTDOWN.",true);
		$this->exec_method("on_server_shutdown");
		
		if($this->reboot_on_shutdown)
		{
			output("REBOOT...",true);
			$r = new ReflectionClass(get_class($this));
   			$SocketServer_new_instance = $r->newInstanceArgs(array($this->address,$this->port));
			$SocketServer_new_instance->set_config($this->get_config());
			$SocketServer_new_instance->run();
		}
		unset($this);
	}
	
	public function get_config($name=""){ return $name ? (isset($this->config[$name]) ? $this->config[$name] : false) : $this->config; }
	
	public function set_config($params){foreach($params as $k=>$v){$this->config[$k]=$v;}}
	public function set_admin_password($password=""){$this->set_config(array("admin_password"=>$password));}
	public function set_server_name($name=""){$this->server_name=$name;}
	public function set_max_clients($nb){$this->max_clients=$nb;}
	
	// PROTECTED
	//==========================================================
	protected function init(){}
	protected function list_clients($prop=array(),$clients=array()){$a=$clients?$clients:$this->clients; $b=array(); foreach($a as $k=>$v){ $prop2=$prop; $b[$v->id]=array(); foreach($prop as $k2=>$v2){$b[$v->id][$v2]=$v->get($v2);} } return $b;}
	protected function get_clients_count(){return count($this->get_clients());}
	
	protected function get_clients($except=NULL){if($except){if(!is_array($except)){$except=array($except);} $o=array(); foreach($this->clients as $k=>$v){if(!in_array($v,$except)){$o[]=$v;}}}else{$o=$this->clients;} return $o;}
	protected function get_clients_from_group($group,$except=NULL){$a=$this->get_clients($except); $b=array(); foreach($a as $c){if($c->in_group($group)){$b[]=$c;}} return $b;}
	
	protected function send($client,$action="",$content=""){if(is_array($client)){foreach($client as $a){$this->send($a,$action,$content);}}else{$this->write($client->socket,array("action"=>$action,"content"=>$content));}}
	protected function send_to_all($action="",$content=""){$this->send($this->get_clients(),$action,$content);}
	protected function send_to_others($client,$action="",$content=""){$this->send($this->get_clients($client),$action,$content);}
	protected function send_to_others_in_group($client,$action="",$content=""){$this->send($this->get_clients_from_group($client->get_group(),$client),$action,$content);}
	protected function send_to_group($group,$action="",$content=""){if(is_array($group)){foreach($group as $k=>$v){$this->send($this->get_clients_from_group($v),$action,$content);}}else{$this->send($this->get_clients_from_group($group),$action,$content);}}
	
	protected function disconnect($client)
	{
		output("disconnected : ".$client->id);
		@socket_shutdown($client->socket,2);
		@socket_close($client->socket);
		$this->remove_client($client);
		$this->exec_method("on_client_disconnect",array($client));
	}
	
	// PRIVATE
	//==========================================================
	private function write($socket,$data){$data=$this->encode($data); if(!@socket_write($socket,$data,strlen($data))){$this->disconnect($this->get_client_by_socket($socket));}}
	private function add_client($client){$this->clients[]=$client;}
	private function remove_client($client){$i=array_search($client,$this->clients); if($i>=0){array_splice($this->clients,$i,1); return true;}else{return false;}}
	private function get_all_sockets(){$a=array($this->master_socket); $clients=$this->get_clients(); foreach($clients as $c){$a[]=$c->socket;} return $a;}
	private function get_client_by_socket($socket){$clients=$this->get_clients(); foreach($clients as $c){if($c->socket===$socket){return $c;}} return false;}
	private function get_client_by_id($id){$clients=$this->get_clients(); foreach($clients as $c){if($c->id===$id){return $c;}} return false;}
	
	private function sys_send($client,$cmd,$content=""){$this->write($client->socket,array("sys"=>$cmd,"content"=>$content));}
	private function sys_send_to_all($cmd,$content=""){$clients=$this->get_clients(); foreach($clients as $a){$this->sys_send($a,$cmd,$content);}}
	private function sys_disconnect_all(){$clients=$this->get_clients(); foreach($clients as $a){$this->disconnect($a);}}
	
	private function do_if_admin($client){if(REMOTE_ADMIN_ACCESS){if($client->is_admin()){return true;}else{$this->sys_send($client,"alert","Admin privilege required.");}} return false;}
	
	private function handle_data($client,$data)
	{
		$data=$this->unmask($data);
		if(!$data){return false;}
		$json=json_decode($data);
		if(!$json){return false;}
		
		if(isset($json->action))
		{
			debug('Handling action "'.$json->action.'"...');
			$this->exec_method("handle_".$json->action,array($client,$json->content));
		}
		
		else if(isset($json->sys))
		{
			debug('Handling system command "'.$json->sys.'"...');
			switch($json->sys)
			{
				case "login" : if(REMOTE_ADMIN_ACCESS){if($client->is_admin()){$this->sys_send($client,"alert","You are already admin.");}else if($this->get_config("admin_password")===$json->content){$client->grant_admin(); $this->sys_send($client,"alert","You are now admin.");}else{ $this->sys_send($client,"alert","Access denied.");}} break;
				case "logout" : if(REMOTE_ADMIN_ACCESS){if($client->is_admin()){$client->revoke_admin(); $this->sys_send($client,"alert","Your admin privilege have been revoked.");}else{$this->sys_send($client,"alert","Your where already loged out.");}} break;
				case "kick" : if($this->do_if_admin($client)){$c=$this->get_client_by_id($json->content); if($c&&$client->id!==$json->content){$this->sys_send($c,"alert","Kicked by an admin."); $this->disconnect($c);}} break;
				case "shutdown" : if($this->do_if_admin($client)){$this->sys_send_to_all("alert","The server have been shutdown by an admin."); $this->is_running=false;} break;
				case "reboot" : if($this->do_if_admin($client)){$this->sys_send_to_all("reboot"); $this->sys_disconnect_all(); $this->reboot_on_shutdown=true; $this->is_running=false;} break;
				case "sleep" : if($this->do_if_admin($client)){sleep(intval($json->content));} break;
				case "clients_count" : if($this->do_if_admin($client)){$this->sys_send($client,"alert",$this->get_clients_count()." clients connected.");} break;
				case "clients_list" : if($this->do_if_admin($client)){ $output = ""; foreach($this->clients as $k=>$v){ $output .= "\n[".$k."] = ".$v->get_profile(); } $this->sys_send($client,"alert",$output); } break;
				
				case "exit" : $this->disconnect($client); break;
				case "ping" : $this->sys_send($client,"ping",microtime(true)); break;
				default : $this->exec_method("sys_handle_".$json->sys,array($client,$json->content)); break;
			}
		}
	}
	
	private function unmask($payload)
	{
		$length = ord($payload[1]) & 127;
		if($length == 126){$masks = substr($payload, 4, 4); $data = substr($payload, 8);}
		elseif($length == 127) {$masks = substr($payload, 10, 4); $data = substr($payload, 14);}
		else {$masks = substr($payload, 2, 4); $data = substr($payload, 6);}
		
		$data_length=strlen($data);
		$text = ''; for($i = 0; $i < $data_length; ++$i) { $text .= $data[$i] ^ $masks[$i%4];}
		return $text;
	}
	
	private function encode($text)
	{
		$text=json_encode($text);
		//$text = base64_encode($text);
		$b1 = 0x80 | (0x1 & 0x0f);// 0x1 text frame (FIN + opcode)
		$length = strlen($text);
		if($length <= 125){$header = pack('CC', $b1, $length);}
		elseif($length > 125 && $length < 65536){$header = pack('CCn', $b1, 126, $length);}
		elseif($length >= 65536){$header = pack('CCN', $b1, 127, $length);}
		return $header.$text;
	}
	
	private function handshake($client,$raw_headers)
	{
		//debug("Start handshaking.");
		//debug($raw_headers);
		
		$headers=array();
		$t=explode("\r\n",$raw_headers);
		foreach($t as $k=>$v)
		{
			$p=strpos($v,":");
			$h=substr($v,0,$p);
			if($h){$headers[$h]=trim(substr($v,$p+1));}
		}
		if(preg_match("/GET (.*) HTTP/",$raw_headers,$match)){$uri=$match[1];}else{$uri="";}
		
		if(isset($headers["Sec-WebSocket-Version"])&&isset($headers["Sec-WebSocket-Key"]))
		{
			//debug("WebSocket-Version=".$headers["Sec-WebSocket-Version"]);
			
			if($headers["Sec-WebSocket-Version"] == 13)
			{
				if(preg_match("/GET \/\?(.*) HTTP/", $raw_headers, $match)){$vars_parts = explode("&",$match[1]); $vars=array(); foreach($vars_parts as $k=>$v){$t=explode("=",$v); $vars[$t[0]]=$t[1];}}
				else{$vars=array();}
				//if(preg_match("/Host: (.*)\r\n/", $headers, $match)){$host = $match[1];}
				//if(preg_match("/Origin: (.*)\r\n/", $headers, $match)){$client->referer = $match[1];}
				//if(preg_match("/User-Agent: (.*)\r\n/", $headers, $match)){$client->user_agent = $match[1];}
				//if(preg_match("/Accept-Language: (.*)\r\n/", $headers, $match)){$client->lang = $match[1];}//COULD BE BETTER FILTERED
				//if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match)){$key = $match[1];}
		 		
				//output($uri);
				
				$acceptKey = $headers["Sec-WebSocket-Key"].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
				$acceptKey = base64_encode(sha1($acceptKey, true));
				$upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptKey\r\n\r\n";
				socket_write($client->socket,$upgrade,strlen($upgrade));
				$client->is_handshaked=true;
				$client->id=isset($vars["cookie"])?$vars["cookie"]:uniqid();
				$this->sys_send($client,"set_cookie",$client->id);
				$this->exec_method("on_client_handshake",array($client,$headers,$vars));
				output("handshaked : ".$client->id);
				//debug("Done handshaking.");
				return true;
			}
		}
		debug("This browser use an old versions of websocket that is not supported by this server.");
		return false;
	}
	
	private function connect($socket)
	{
		if($this->get_clients_count()<$this->max_clients||$this->max_clients===0)
		{
			$client=new SocketClient($socket);
			$this->add_client($client);
			$this->exec_method("on_client_connect",array($client));
			output("A client is connecting...");
			return $client;
		}
		else
		{
			debug("Maximum clients limit reached.");
			return false;
		}
	}
	
	private function exec_method($name="",$args=array())
	{
		if(method_exists($this,$name)){call_user_func_array(array($this,$name),$args);}
	}
}
?>