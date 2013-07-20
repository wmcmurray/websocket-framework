//===========================================================
// ===== SocketClient() =====================================
//===========================================================

function SocketClient(address, port)
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
    this.reconnect_timeout;
    this.socket;
}

// PUBLIC
//==========================================================
SocketClient.prototype.on = function (evt, fnc)
{
    if(!this.events_listeners[evt])
    {
        this.events_listeners[evt] = new Array();
    }
    this.events_listeners[evt].push(fnc);
}

SocketClient.prototype.open = function (get_vars)
{
    if(window.WebSocket && this.connections_attempts < this.max_connections_attempts)
    {
    	if(this.reconnect_timeout)
		{
			clearTimeout(this.reconnect_timeout);
		}

		if(this.socket_ready())
		{
			return false;
		}

    	this.debug("Connection attempt...");
        this.stay_closed = false;
        this.connections_attempts++;
	    this.last_opening_vars = get_vars;
        
        var cookie = this.get_cookie(this.cookie_name);
        if(cookie)
        {
            if(!get_vars)
            {
                get_vars = "";
            }
            get_vars += (get_vars == "" ? "" : "&") + "cookie=" + cookie
        }

        this.socket = new WebSocket("ws://" + this.server_address + ":" + this.server_port + (get_vars ? "/?" + get_vars : ""));
        this.socket.onopen = this.proxy(this.handle_onopen, this);
        this.socket.onmessage = this.proxy(this.handle_onmessage, this);
        this.socket.onclose = this.proxy(this.handle_onclose, this);
        this.socket.onerror = this.proxy(this.handle_onerror, this);
    }
    else
    {
        this.debug("Browser don't support websockets.");
    }
}

SocketClient.prototype.close = function ( reconnectAfter )
{
	if(this.socket_ready())
	{
		this.stay_closed = reconnectAfter ? false : true;

	    // tell the server to close socket
	    if(this.sys_send("exit"))
	    {
	    	// some time after close client socket
	        setTimeout(this.proxy(function(){this.socket.close();}, this), 250);
	    }
	}
}

SocketClient.prototype.send = function (action, content)
{
    var o = {action: action, content: (content ? content : "")};
    return this.write(JSON.stringify(o));
}

SocketClient.prototype.sys_send = function (command, content)
{
    var o = {sys: command, content: (content ? content : "")};
    return this.write(JSON.stringify(o));
}

SocketClient.prototype.raw_send = function (content)
{
    return this.write(content);
}

SocketClient.prototype.ping = function ()
{
    if(!this.ping_start && this.socket_ready())
    {
        this.ping_start = new Date().getTime();
        this.sys_send("ping_request");
    }
}

SocketClient.prototype.set_debug = function (bool)
{
    this.debug_mode = bool;
}

// PRIVATE
//==========================================================

//ON OPEN
// ---------------------------------------------
SocketClient.prototype.handle_onopen = function ()
{
	this.connections_attempts = 0;
    this.trigger("open");
    this.debug("Socket opened.");
}

//ON MESSAGE
// ---------------------------------------------
SocketClient.prototype.handle_onmessage = function (e)
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
        this.trigger("message", data);
    }

    // system data type
    else if(data.sys)
    {
        switch(data.sys)
        {
	        case "set_cookie":
	            this.clientID = data.content;
	            this.set_cookie(this.cookie_name, data.content, 30);
	        break;
	        case "alert":
	            this.alert(data.content);
	        break;
	        case "reboot":
	        	this.socket.close();
	        break;
	        case "ping_response":
	            if(this.ping_start)
	            {
	                this.trigger("ping", (new Date().getTime() - this.ping_start));
	                this.ping_start = null;
	            }
	        break;
	        case "ping_request":
	            this.sys_send("ping_response", data.content);
	        break;
        }
    }

    // raw data type
    else
    {
        this.trigger("message", data);
    }
}

//ON CLOSE
// ---------------------------------------------
SocketClient.prototype.handle_onclose = function (e)
{
    this.trigger("close", e);
    this.debug("Socket closed.");
	
    if(this.ping_start)
    {
    	this.ping_start = null;
    }

    if(this.stay_closed == false)
    {
        if(this.connections_attempts < this.max_connections_attempts)
        {
        	this.set_reconnect_timeout((this.reconnection_delay * 1000));
            this.debug("Will try again in " + this.reconnection_delay + " seconds...");
        }
        else
        {
            this.debug("Can't connect.");
        }
    }
}

//ON ERROR
// ---------------------------------------------
SocketClient.prototype.handle_onerror = function (e)
{
    this.trigger("error", e);
    this.debug("Socket error.");
}

// ---------------------------------------------

SocketClient.prototype.socket_ready = function ()
{
	return this.socket && this.socket.readyState == 1 ? true : false;
}

SocketClient.prototype.set_reconnect_timeout = function ( delay )
{
	if(this.reconnect_timeout)
	{
		clearTimeout(this.reconnect_timeout);
	}

	this.reconnect_timeout = setTimeout(this.proxy(this.open, this, this.last_opening_vars), delay);
}

SocketClient.prototype.trigger = function (evt, args)
{
    if(this.events_listeners[evt])
    {
        for(var i in this.events_listeners[evt])
        {
            this.events_listeners[evt][i](args ? args : "");
        }
    }
}

SocketClient.prototype.write = function (data)
{
    if(this.socket_ready())
    {
        this.socket.send(data);
        return true;
    }
    return false;
}

SocketClient.prototype.alert = function (t)
{
    this.trigger("alert", t);
}

SocketClient.prototype.debug = function (t)
{
    if(this.debug_mode == true)
    {
        console.log("DEBUG: " + t);
    }
}

SocketClient.prototype.proxy = function (func, scope)
{
    return function()
    {
        return func.apply(scope, arguments);
    }
}


// ---------------------------------------------

SocketClient.prototype.get_cookie = function (c_name)
{
    var i, x, y, ARRcookies = document.cookie.split(";");
    for(i = 0; i < ARRcookies.length; i++)
    {
        x = ARRcookies[i].substr(0, ARRcookies[i].indexOf("="));
        y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
        x = x.replace(/^\s+|\s+$/g, "");
        if(x == c_name)
        {
            return unescape(y);
        }
    }
}

SocketClient.prototype.set_cookie = function (c_name, value, exdays)
{
    var exdate = new Date();
    exdate.setDate(exdate.getDate() + exdays);
    var c_value = escape(value) + ((exdays == null) ? "" : "; expires=" + exdate.toUTCString() + ";path=/");
    document.cookie = c_name + "=" + c_value;
}