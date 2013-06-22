//===========================================================
// ===== Chat() ===========================================
//===========================================================

function Chat(address, port)
{
	var self=this;
	
	this.init = function()
	{
		this.me = new Chat_user("CHAT_PREFS");
		this.chatroom = "MAIN_CHATROOM";		// le nom de la salle de chat auquel on se connectera
		this.init_socket();
	}
	
	this.init_socket = function()
	{
		this.socket = new SocketClient(address,port);

		// eventListener de quand la socket est ouverte
		this.socket.on("open",function(){
			
			// demande au serveur la liste des clients connectées dans la chatroom
			setTimeout(function(){ self.socket.send("list_clients"); }, 100);
			
			// demande au serveur la liste des messages sauvegardés
			setTimeout(function(){ self.socket.send("list_messages"); }, 200);

			// affiche un msg dans la console JS
			self.socket.sys_alert("Socket opened.");
		});
		
		// eventListener de quand le serveur envoit un message
		this.socket.on("message", this.handle_messages);

		// eventListener de quand il se produit une erreur
		this.socket.on("error",function(e){alert(e.type);});

		// eventListener de quand la socket est fermée
		this.socket.on("close",function(){self.socket.sys_alert("Socket closed.");});

		// eventListener de quand le serveur envoie des messages d'alerte
		this.socket.on("alert",function(t){console.log("WS : "+t);});

		// eventListener de quand le serveur envoie le résultat d'une requête ping
		this.socket.on("ping",function(ms){alert("Your ping is "+Math.round(ms*1000)/1000)+" milliseconds";});

		// ouverture de la socket, il est possible de passé des variables comme ont le fait dans une URL
		this.socket.open("chatroom="+this.chatroom+"&username="+this.me.prefs.username);
	}

	// permet d'envoyer un message aux autres clients de la même salle de chat
	this.send_message = function(msg)
	{
		this.socket.send("send_message", msg);
	}

	// permet de changer son nom d'utilisateur
	this.change_username = function(new_username)
	{
		this.me.prefs.username = new_username;
		this.me.save_prefs();
		this.socket.send("change_username", new_username);
	}

	// méthode qui gère la réception de données en provenance du serveur
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			case "message" :
				// affiche le message reçu
				self.display_message(data.content.client, data.content.message);
			break;

			case "list_clients" :
				// affiche la liste des clients connectés
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
			break;

			case "list_messages" :
				// vide les message affichés
				jQuery("#messages").html("");

				// affiche la liste des anciens messages
				for(var i in data.content)
				{
					self.display_message(data.content[i].client, data.content[i].message);
				}
			break;
			
			case "login" :
				// update la liste des clients connectés
				self.socket.send("list_clients");
			break;
			
			case "logout" :
				// update la liste des clients connectés
				self.socket.send("list_clients");
			break;
		}
	}

	// affiche un message reçu
	this.display_message = function(client, message)
	{
		var div = document.createElement("div");
		jQuery(div).addClass("message")
		.html("<strong>" + client + " said :</strong> " + message)
		.css({opacity: 0})
		.animate({opacity: 1}, 500);
		jQuery("#messages").append(div);
	}
	
	this.init();
}