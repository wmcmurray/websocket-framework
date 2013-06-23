//===========================================================
// ===== AdminCP() ==========================================
//===========================================================

function AdminCP(address, port)
{
	var self=this;
	
	this.init = function()
	{
		this.init_socket();
	}
	
	this.init_socket = function()
	{
		// instanciate the SocketClient class
		this.socket = new SocketClient(address, port);

		// eventListener executed when socket is opened
		this.socket.on("open", function(){ self.display_log("Socket opened."); });
		
		// eventListener executed when receiving a message from server
		this.socket.on("message", this.handle_messages);

		// eventListener executed when an error occurs
		this.socket.on("error", function(e){ self.display_log("An error occured."); });

		// eventListener executed when socket is closed
		this.socket.on("close", function(e){ self.display_log("Socket closed with code : " + e.code); });

		// eventListener executed when server send alerts
		this.socket.on("alert", function(t){ self.display_log(t); });

		// eventListener executed when server return a ping request
		this.socket.on("ping", function(ms){ self.display_log("Your ping is "+ ms +" milliseconds"); });

		// opening of the socket
		self.socket.sys_alert("Trying to open the socket...");
		this.socket.open();
	}

	// this method handle all incoming data from the server
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			// when a client connect to the server
			case "connect" :
				self.display_log("A user is connecting to the server...");
			break;
			
			// when a client sucessfully handshake the server
			case "handshake" :
				self.display_log("User #" + data.content + " handshaked sucessfully.");
			break;

			// when a client disconnect
			case "disconnect" :
				self.display_log("User #" + data.content + " disconnected.");
			break;

			// when kicked
			case "kicked" :
				self.display_log("You have been kicked by and administrator.");
			break;

			// when server is shutdown
			case "shutdown" :
				self.display_log("SHUTDOWN.");
			break;

			// when server is rebooted
			case "reboot" :
				self.display_log("REBOOT...");
			break;
		}
	}

	// display an entry in console
	this.display_log = function(t)
	{
		var div = document.createElement("div");

		jQuery(div)
		.html("> " + t)
		.addClass("entry")
		.css({opacity: 0, position: "relative", left: "-200px"})
		.animate({opacity: 1, left: "0px"}, 150);


		jQuery("#admin-cp-console")
		.append(div)
		.stop()
		.animate({"scrollTop" : jQuery("#admin-cp-console")[0].scrollHeight}, 500);
	}
	
	// initialization
	this.init();
}