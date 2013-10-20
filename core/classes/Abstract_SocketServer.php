<?php

/**
* Abstract_SocketServer
* Contains generals methods related to websocket protocol
*/
class Abstract_SocketServer
{
	/**
	* decode
	* Decode a data playload received by a client
	*/
	protected function decode($payload)
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

	/**
	* unmask
	* Unmask data received by a client
	*/
	protected function unmask($data, $length, $mask)
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

	/**
	* get_playload_length
	* Returns a reveived data playload length
	*/
	protected function get_playload_length($payload, $end)
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
	
	/**
	* encode
	* Prepare data to be sended to a client
	*/
	protected function encode($text)
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

	/**
	* headers_to_array
	* Slice request headers into array
	*/
	protected function headers_to_array($raw_headers)
	{
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

		return $headers;
	}

	/**
	* extract_uri
	* Extract URI vars from request headers
	*/
	protected function extract_uri($raw_headers, $array_output = false)
	{
		$uri = "";

		if(preg_match("/GET (.*) HTTP/", $raw_headers, $match))
		{
			$uri = $match[1];
		}

		if($array_output)
		{
			$vars = array();

			if(preg_match("/\/\?(.*)/", $uri, $match))
			{
				$vars_parts = explode("&", $match[1]);
				foreach($vars_parts as $k => $v)
				{
					$t = explode("=", $v);
					$vars[$t[0]] = $t[1];
				}
			}

			return $vars;
		}
		else
		{
			return $uri;
		}
	}

	/**
	* make_upgrade
	* Returns the upgrade response needed to establish a websocket connection
	*/
	protected function make_upgrade($key)
	{
		$acceptKey = $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
		$acceptKey = base64_encode(sha1($acceptKey, true));
		return "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptKey\r\n\r\n";
	}
	
	/**
	* exec_method
	* Execute a method inside of this class, if this method exists
	*/
	protected function exec_method($name = "", $args = array())
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