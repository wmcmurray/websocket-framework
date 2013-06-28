//===========================================================
// ===== Chat() =============================================
//===========================================================

function Chat(address, port)
{
	var self=this;
	
	this.init = function()
	{
		this.me = new Chat_user("CHAT_PREFS");
		this.chatroom = "MAIN_CHATROOM"; // the name of the chatroom clients gonna join
		this.init_socket();
	}
	
	this.init_socket = function()
	{
		// instanciate the SocketClient class
		this.socket = new SocketClient(address, port);

		// eventListener executed when socket is opened
		this.socket.on("open", function(){
			
			// ask the server for the list of connected users
			setTimeout(function(){ self.socket.send("list_clients"); }, 100);
			
			// ask the server for the list of last saved messages
			setTimeout(function(){ self.socket.send("list_messages"); }, 200);

			self.socket.alert("Socket opened.");
		});
		
		// eventListener executed when receiving a message from server
		this.socket.on("message", this.handle_messages);

		// eventListener executed when an error occurs
		this.socket.on("error", function(e){ self.socket.alert("An error occured."); });

		// eventListener executed when socket is closed
		this.socket.on("close", function(e){ self.socket.alert("Socket closed with code : " + e.code); });

		// eventListener executed when server send alerts
		this.socket.on("alert", function(t){ document.getElementById("console").innerHTML = t; });

		// opening of the socket
		self.socket.alert("Trying to open the socket...");
		this.socket.open("chatroom="+this.chatroom+"&username="+this.me.prefs.username);
	}

	// send a message to the server
	this.send_message = function(msg)
	{
		this.socket.send("send_message", msg);
	}

	// change our username and send it to the server
	this.change_username = function(new_username)
	{
		this.me.prefs.username = new_username;
		this.me.save_prefs();
		this.socket.send("change_username", new_username);
	}

	// this method handle all incoming data from the server
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			// display a message when we receive one
			case "message" :
				self.display_message(data.content.client, data.content.message);
			break;

			// when receiving a list of connected clients from server, we display it in the DOM
			case "list_clients" :
				
				var div = jQuery("#clients_list").html("")[0];
				for(var i in data.content)
				{
					var username = data.content[i].username;

					if(i==self.socket.clientID)
					{
						username = '<span style="color: #00f;">' + username + '</span>';
					}

					div.innerHTML = div.innerHTML + (div.innerHTML == "" ? "" : ", ") + username;
				}

				jQuery(div).stop().css({opacity: 0}).animate({opacity: 1}, 500);
			break;

			case "list_messages" :
				// empty messages div
				jQuery("#messages").html("");

				// display last saved messages
				for(var i in data.content)
				{
					self.display_message(data.content[i].client, data.content[i].message);
				}
			break;
			
			// when a client sucessfully connected and handshaked the server, we ask for a fresh list of connected users
			case "handshake" :
				self.socket.send("list_clients");
			break;
			
			// when a client disconnect, we ask the server for a fresh list of connected users
			case "disconnect" :
				self.socket.send("list_clients");
			break;
		}
	}

	// display a received message and scroll down to it
	this.display_message = function(client, message)
	{
		var div = document.createElement("div");
		jQuery(div).addClass("message")
		.html("<strong>" + client + " said :</strong> " + message)
		.css({opacity: 0})
		.animate({opacity: 1}, 500);
		
		jQuery("#messages")
		.append(div)
		.stop()
		.animate({"scrollTop" : jQuery("#messages")[0].scrollHeight}, 500);
	}
	
	// initialization
	this.init();
}