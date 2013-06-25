//===========================================================
// ===== SocketClient() =====================================
//===========================================================
function SocketClient(address, port)
{
	var self=this;
	
	// PUBLIC
	//==========================================================
	this.init = function()
	{
		this.server_address = address;
		this.server_port = port;

		this.cookie_name = "WSUID";
		this.debug_mode = false;
		this.events_listeners = new Array();
		this.first_connection = true;
		this.connections_attempts = 0;
		this.max_connections_attempts = 3;
		this.reconnection_delay = 5; //in seconds
		this.stay_closed = false; //true|false, défini si le chat tentera de se reconnecté après un event onclose
		this.socket;

		// attempt to close socket before pageunload
		//jQuery(window).on('beforeunload', function(){ self.close(); });
	}
	
	this.on = function(evt,fnc)
	{
		if(!this.events_listeners[evt]){this.events_listeners[evt]=new Array();}
		this.events_listeners[evt].push(fnc);
	}
		
	this.open = function(get_vars)
	{
		if(this.socket){this.socket.close();}
		this.last_opening_vars=get_vars;
		
		if(window.WebSocket&&this.connections_attempts<this.max_connections_attempts)
		{
			this.stay_closed=false;
			this.connections_attempts++;
			this.debug("Connection attempt...");
			
			var cookie = this.get_cookie(this.cookie_name);
			if(cookie){if(!get_vars){get_vars="";} get_vars+=(get_vars==""?"":"&")+"cookie="+cookie}
			
			this.socket=new WebSocket("ws://"+this.server_address+":"+this.server_port+(get_vars?"/?"+get_vars:""));
			
			//OPEN
			this.socket.onopen = function()
			{
				self.fire_event("open");
				self.debug("Socket opened.");
			}
			
			//MESSAGE
			this.socket.onmessage = function(e)
			{
				try
				{
				   var data = JSON.parse(e.data);
				}
				catch(err)
				{
					var data = e.data;
				}
				
				// normal data type
				if(data.action)
				{
					self.fire_event("message", data);
				}

				// system data type
				else if(data.sys)
				{
					switch(data.sys)
					{
						case "set_cookie": self.clientID=data.content; self.set_cookie(self.cookie_name,data.content,30); break;
						case "alert": self.sys_alert(data.content); break;
						case "reboot": setTimeout(function(){self.open(self.last_opening_vars);},3000); break;
						case "ping": self.fire_event("ping", (new Date().getTime()-self.ping_start)); self.ping_start=null; break;
					}
				}

				// raw data type
				else
				{
					self.fire_event("message", data);
				}
			}
			
			//CLOSE
			this.socket.onclose = function(e)
			{
				self.fire_event("close",e);
				self.debug("Socket closed.");
				if(!self.stay_closed)
				{
					if(self.connections_attempts<self.max_connections_attempts)
					{
						setTimeout(function(){self.open(self.last_opening_vars);},self.reconnection_delay*1000);
						self.debug("Will try again in "+self.reconnection_delay+" seconds...");
					}
					else{self.debug("Can't connect.");}
				}
			}
			
			//ERROR
			this.socket.onerror = function(e)
			{
				self.fire_event("error",e);
				self.debug("Socket error.");
			}
		}
		else{this.debug("Browser don't support websockets.");}
	}
	
	this.close = function()
	{
		if(this.sys_send("exit"))
		{
			this.stay_closed=true;
			setTimeout(function(){self.socket.close();},2000);
		}
	}
	
	this.send = function(action, content)
	{
		var o = {action: action, content: (content ? content : "")};
		return this.write(JSON.stringify(o));
	}

	this.raw_send = function(content)
	{
		return this.write(content);
	}
	
	this.sys_send = function(command, content)
	{
		var o = {sys: command, content: (content ? content : "")};
		return this.socket.send(JSON.stringify(o));
	}
	
	this.ping = function()
	{
		if(!this.ping_start)
		{
			this.ping_start = new Date().getTime();
			this.sys_send("ping");
		}
	}

	this.set_debug = function(bool)
	{
		this.debug_mode = bool;
	}

	// PRIVATE
	//==========================================================
	
	this.fire_event = function(evt,args)
	{
		if(this.events_listeners[evt])
		{
			for(var i in this.events_listeners[evt])
			{
				this.events_listeners[evt][i](args?args:"");
			}
		}
	}

	this.write = function(data)
	{
		if(this.socket.readyState==1)
		{
			this.socket.send(data);
			return true;
		}
		return false;
	}

	this.sys_alert = function(t)
	{
		self.fire_event("alert",t);
	}
	
	this.debug = function(t)
	{
		if(this.debug_mode == true)
		{
			console.log("DEBUG: " + t);
		}
	}

	this.get_cookie = function(c_name)
	{
		var i,x,y,ARRcookies=document.cookie.split(";");
		for (i=0;i<ARRcookies.length;i++)
		{
			x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
			y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
			x=x.replace(/^\s+|\s+$/g,"");
			if (x==c_name)
			{
				return unescape(y);
			}
		}
	}

	this.set_cookie = function(c_name,value,exdays)
	{
		var exdate=new Date();
		exdate.setDate(exdate.getDate() + exdays);
		var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString()+";path=/");
		document.cookie=c_name + "=" + c_value;
	}


	// initialization
	this.init();
}