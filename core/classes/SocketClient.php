<?php
class SocketClient
{
	public $id;
	public $socket;
	
	protected $props = array();
	
	private $cookie_name = "WSUID";
	private $is_handshaked = false;
	private $is_admin = false;
	private $groups = array();
	
	public function __construct($socket = NULL)
	{
		if($socket)
		{
			$this->socket = $socket;
		}
		else
		{
			$this->id = uniqid();
		}
	}
	
	public function set($prop = "", $value = "")
	{
		if(is_array($prop))
		{
			foreach($prop as $k => $v)
			{
				$this->props[$k] = $v;
			}
		}
		else
		{
			$this->props[$prop] = $value;
		}

		return $this;
	}
	
	public function get($prop = null)
	{
		if(is_null($prop))
		{
			return $this->props;
		}
		else if(is_array($prop))
		{
			$a = array();
			foreach($prop as $k => $v)
			{
				$a[$v] = $this->get($v);
			}
			return $a;
		}
		return isset($this->props[$prop]) ? $this->props[$prop] : NULL;
	}
	
	public function get_profile()
	{
		return "#" . $this->id . " : " . ($this->is_admin() ? "ADMIN" : "user") . " " . ($this->is_handshaked() ? "handshaked" : "");
	}
	
	public function is_admin()
	{
		return $this->is_admin;
	}
	
	public function is_handshaked()
	{
		return $this->is_handshaked;
	}

	public function is_npc()
	{
		return is_null($this->socket) ? true : false;
	}

	public function handshake()
	{
		$this->is_handshaked = true;

		return $this;
	}
	
	public function grant_admin()
	{
		$this->is_admin = true;
		output("#" . $this->id . " became admin.");

		return $this;
	}
	
	public function revoke_admin()
	{
		$this->is_admin = false;
		output("#" . $this->id . " is no longer admin.");

		return $this;
	}
	
	public function set_group($name = "")
	{
		$this->groups = is_array($name) ? $name : array($name);

		return $this;
	}
	
	public function join_group($name = "")
	{
		$this->groups[] = $name;

		return $this;
	}
	
	public function quit_group($name = "")
	{
		$a = array();
		foreach($this->groups as $k => $v)
		{
			if($v != $name)
			{
				$a[] = $v;
			}
		}
		$this->groups = $a;

		return $this;
	}
	
	public function get_group()
	{
		return count($this->groups) > 1 ? $this->groups : (isset($this->groups[0]) ? $this->groups[0] : "");
	}
	
	public function in_group($groups = "")
	{
		if(!is_array($groups))
		{
			$groups = array($groups);
		}
		foreach($this->groups as $k => $v)
		{
			if(in_array($v, $groups))
			{
				return true;
			}
		}
		return false;
	}
}
?>