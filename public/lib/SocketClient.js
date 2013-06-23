function t(a){console.log(a);}
function tt(a){for(var i in a){t(i+" = "+a[i]);}}

function getCookie(c_name)
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

function setCookie(c_name,value,exdays)
{
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString()+";path=/");
	document.cookie=c_name + "=" + c_value;
}


//===========================================================
// ===== SocketClient() =====================================
//===========================================================
function SocketClient(address,port)
{
	var self=this;
	
	this.init = function()
	{
		this.debug_mode=false;
		this.cookie_name="WSUID";
		this.socket;
		this.server_address=address;
		this.server_port=port;
		this.events_listeners=new Array();
		
		this.first_connection=true;
		this.connections_attempts=0;
		this.max_connections_attempts=3;
		this.reconnection_delay=5;//en secondes
		
		this.stay_closed=false;//true|false, défini si le chat tentera de se reconnecté après un event onclose
		this.debug("Socket initalised.");
	}
	
	this.on = function(evt,fnc)
	{
		if(!this.events_listeners[evt]){this.events_listeners[evt]=new Array();}
		this.events_listeners[evt].push(fnc);
	}
	
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
		
	this.open = function(get_vars)
	{
		if(this.socket){this.socket.close();}
		this.last_opening_vars=get_vars;
		
		if(window.WebSocket&&this.connections_attempts<this.max_connections_attempts)
		{
			this.stay_closed=false;
			this.connections_attempts++;
			this.debug("Connection attempt...");
			
			var cookie=getCookie(this.cookie_name);
			if(cookie){if(!get_vars){get_vars="";} get_vars+=(get_vars==""?"":"&")+"cookie="+cookie}
			
			this.socket=new WebSocket("ws://"+this.server_address+":"+this.server_port+(get_vars?"/?"+get_vars:""));
			
			//OPEN
			this.socket.onopen = function()
			{
				self.fire_event("open");
			}
			
			//MESSAGE
			this.socket.onmessage = function(e)
			{
				var data=JSON.parse(e.data);
				
				if(data.action)
				{
					self.fire_event("message",data);
				}
				else if(data.sys)
				{
					switch(data.sys)
					{
						case "set_cookie": self.clientID=data.content; setCookie(self.cookie_name,data.content,30); break;
						case "alert": self.sys_alert(data.content); break;
						case "reboot": self.sys_alert("The server will reboot."); setTimeout(function(){self.open(self.last_opening_vars);},3000); break;
						case "ping": self.fire_event("ping",(data.content-self.ping_start)); self.ping_start=null; break;
					}
				}
			}
			
			//CLOSE
			this.socket.onclose = function(e)
			{
				self.fire_event("close",e);
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
	
	this.send = function(action,content)
	{
		if(this.socket.readyState==1)
		{
			var o={action:action,content:(content?content:"")};
			this.socket.send(JSON.stringify(o));
			return true;
		}
		return false;
	}
	
	this.sys_send = function(command,content)
	{
		if(this.socket.readyState==1)
		{
			var o={sys:command,content:(content?content:"")};
			this.socket.send(JSON.stringify(o));
			return true;
		}
		return false;
	}
	
	this.sys_alert = function(t)
	{
		self.fire_event("alert",t);
	}
	
	this.ping = function()
	{
		if(!this.ping_start)
		{
			this.ping_start=parseInt(new Date().getTime()/1000);
			this.sys_send("ping");
		}
	}
	
	this.debug = function(t)
	{
		if(this.debug_mode)
		{
			console.log(t);
		}
	}
	
	this.init();
}