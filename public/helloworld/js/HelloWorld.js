//===========================================================
// ===== HelloWorld() =======================================
//===========================================================

function HelloWorld(address, port)
{
	var self=this;
	
	this.init = function()
	{
		// instanciate the SocketClient class
		this.socket = new SocketClient(address, port);

		// eventListener executed when socket is opened
		this.socket.on("open", function(){ self.display_log("Socket opened."); });
		
		// eventListener executed when receiving a message from server
		this.socket.on("message", this.handle_messages);

		// eventListener executed when an error occurs
		this.socket.on("error", function(e){ self.display_log("An error occured."); });

		// opening of the socket
		self.display_log("Trying to open the socket...");
		this.socket.open();
	}

	// this method handle all incoming data from the server
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			// when data is received from server
			case "helloworld" :
				self.display_log("Data received : " + data.content);
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


		jQuery("#helloworld-console")
		.append(div)
		.stop()
		.animate({"scrollTop" : jQuery("#helloworld-console")[0].scrollHeight}, 500);
	}
	
	// initialization
	this.init();
}