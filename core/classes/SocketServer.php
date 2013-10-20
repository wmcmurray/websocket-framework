<?php
require_once("Basic_SocketServer.php");

/**
* SocketServer
* Extends Basic_SocketServer  and contains aditionnal features like:
* - Remote admin access + commands
*/
class SocketServer extends Basic_SocketServer
{
	private $admin_password = "root";	// default admin password
	private $cmds_requiring_admin_privileges = array("kick", "shutdown", "reboot", "sleep", "clients_count", "clients_list", "ping_client", "last_error", "options");
	
	public function __construct($address = "127.0.0.1", $port = "8080")
	{
		parent::__construct($address, $port);
	}


	// =================================================================================================================
	// SETTERS AND GETTERS
	// =================================================================================================================
	
	public function set_admin_password($password = "")
	{
		$this->admin_password = $password;
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


	// =================================================================================================================
	// INCOMING DATA PROCESSING
	// =================================================================================================================

	protected function process_system_data($client, $json)
	{
		// Admin access verifications
		$access_required = in_array($json->sys, array("login", "logout"));
		$privileges_required = in_array($json->sys, $this->cmds_requiring_admin_privileges);

		if($access_required && !REMOTE_ADMIN_ACCESS)
		{
			debug("Remote admin access isn't enabled. (-admin option)");
			return false;
		}

		if(($privileges_required && $client->is_admin()) || !$privileges_required)
		{
			// continue with basic handling
			parent::process_system_data($client, $json);
			return true;
		}
		else
		{
			warning('client #' . $client->id . ' tried to execute "' . $json->sys . '".');
			$this->sys_send($client, "alert", "Admin privilege required.");
			return false;
		}
	}

	protected function sys_handle_login($client, $content)
	{
		if($client->is_admin())
		{
			$this->sys_send($client, "alert", "You are already admin.");
		}
		else if($this->admin_password === $content)
		{
			$client->grant_admin();
			$this->sys_send($client, "alert", "You are now admin.");
		}
		else
		{
			warning('client #' . $client->id . ' entered a wrong password in admin login.');
			$this->sys_send($client, "alert", "Access denied.");
		}
	}

	protected function sys_handle_logout($client, $content)
	{
		if($client->is_admin())
		{
			$client->revoke_admin();
			$this->sys_send($client, "alert", "Your admin privilege have been revoked.");
		}
		else
		{
			$this->sys_send($client, "alert", "You can't log out if you are not logged in.");
		}
	}

	protected function sys_handle_kick($client, $content)
	{
		$c = $this->get_client_by_id($content);
		if($c)
		{
			if($client->id !== $content)
			{
				$this->exec_method("on_client_kick", $c);
				$this->disconnect($c);
			}
			else
			{
				$this->sys_send($client, "alert", "Kicking yourself is unfortunately impossible. (and stupid)");
			}
		}
	}

	protected function sys_handle_shutdown($client, $content)
	{
		$this->is_running = false;
	}

	protected function sys_handle_reboot($client, $content)
	{
		$this->sys_send_to_all("reboot");
		$this->exec_method("on_server_reboot");
		$this->reboot_on_shutdown = true;
		$this->is_running = false;
	}

	protected function sys_handle_sleep($client, $content)
	{
		$time = intval($content);
		if($time > 0)
		{
			output("Zzzzzz for " . $time . " seconds...");
			sleep($time);
			output("Zzzzzz is over !");
		}
	}

	protected function sys_handle_clients_count($client, $content)
	{
		$this->sys_send($client, "alert", $this->get_clients_count() . " clients connected.");
	}

	protected function sys_handle_clients_list($client, $content)
	{
		$output = "Clients list :";
		foreach($this->clients as $k => $v)
		{
			$output .= "\n[" . $k . "] = " . $v->get_profile();
		}
		$this->sys_send($client, "alert", $output);
	}

	protected function sys_handle_ping_client($client, $content)
	{
		$pinged = $this->get_client_by_id($content);
		if($pinged)
		{
			if(!isset($this->ping_requests_queue[$pinged->id]))
			{
				$this->ping_requests_queue[$pinged->id] = array();
				$this->sys_send($pinged, "ping_request", microtime(true));
			}
			$this->ping_requests_queue[$pinged->id][] = $client->id;
		}
	}

	protected function sys_handle_last_error($client, $content)
	{
		ob_start();
		print_r(error_get_last());
		$err = ob_get_clean();

		$this->sys_send($client, "alert", $err != "" ? $err : "No last error.");
	}

	protected function sys_handle_options($client, $content)
	{
		$this->sys_send($client, "alert", SCRIPT_OPTIONS);
	}
}
?>