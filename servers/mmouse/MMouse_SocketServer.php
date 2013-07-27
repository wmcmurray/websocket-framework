<?php
require_once("../core/index.php");

class MMouse_SocketServer extends SocketServer
{
	// SERVER EVENTS
	//===========================================================================
	protected function on_client_connect($client)
	{
		
	}
	
	protected function on_client_handshake($client,$headers,$vars)
	{
		if(isset($vars["url"]))
		{
			$client->join_group(urldecode($vars["url"]));
			$this->send_to_others_in_group($client,"login",array("id"=>$client->id));
		}
		else{$this->disconnect($client);}
	}
	
	protected function on_client_disconnect($client)
	{
		$this->send_to_others_in_group($client,"logout",$client->id);
	}
	
	// DATA HANDLING
	//===========================================================================
	protected function handle_list_clients($client,$data)
	{
		$this->send($client,"list_clients",$this->list_clients(array("x","y","cursor","username"),$this->get_clients_from_group($client->get_group())));
	}
	
	protected function handle_user_prefs($client,$data)
	{
		$prefs_to_send_back=array();
		if(isset($data->username)){$prefs_to_send_back[]="username"; $client->set("username",$data->username);}
		if(isset($data->cursor)){$prefs_to_send_back[]="cursor"; $client->set("cursor",$data->cursor);}
		$this->send_to_others_in_group($client,"user_prefs",array("id"=>$client->id,"prefs"=>$client->get($prefs_to_send_back)));
	}
	
	protected function handle_mouse_data($client,$data)
	{
		$content=array("id"=>$client->id);
		if(isset($data->x)){$client->set("x",$data->x); $content["x"]=$data->x;}
		if(isset($data->y)){$client->set("y",$data->y); $content["y"]=$data->y;}
		if(isset($data->md)){$content["md"]=$data->md;}
		if(isset($data->mu)){$content["mu"]=$data->mu;}
		if(isset($data->mc)){$content["mc"]=$data->mc;}
		
		$this->send_to_others_in_group($client,"mouse_data",$content);
	}
}
?>