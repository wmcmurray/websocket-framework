//===========================================================
// ===== Game() =============================================
//===========================================================

function Game(address, port)
{
	this.init = function()
	{
		this.scene = document.getElementById("scene");
		this.players = {};
		this.keypressed = {};

		// instanciate the SocketClient class and add eventsListeners
		this.socket = new SocketClient(address, port);
		this.socket.on("open", this.socket.proxy(this.handle_socket_open, this));
		this.socket.on("close", this.socket.proxy(this.handle_socket_close, this));
		this.socket.on("message", this.socket.proxy(this.handle_messages, this));

		// opening of the socket
		this.display_log("Trying to open the socket...");
		this.socket.open();
	}

	// handler executed when socket is opened
	this.handle_socket_open = function()
	{
		if(!this.hero)
		{
			this.init_hero();
		}

		this.socket.send("player_join", this.hero.username);
		this.display_log("Socket opened.");
	}

	// handler executed when socket is closed
	this.handle_socket_close = function(e)
	{
		this.display_log("Socket closed with code : " + e.code);

		for(var i in this.players)
		{
			this.players[i].disappear();
		}
	}

	// handler executed when receiving data from server
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			case "sync" :
				this.hero.sync(data.content);
				if(data.content.skin)
				{
					this.hero.change_skin(data.content.skin);
				}
			break;

			case "players_list" :
				for(var id in data.content)
				{
					//if(!this.players[id])
					//{
						this.players[id] = new Character(data.content[id].username, this.scene);
						this.players[id].change_skin(data.content[id].skin).sync(data.content[id]).place().animate();
					//}
				}
			break;

			case "new_player" :
				//if(!this.players[data.content.id])
				//{
					this.players[data.content.id] = new Character(data.content.username, this.scene);
					this.players[data.content.id].change_skin(data.content.props.skin).sync(data.content.props).place().animate();
				//}
			break;

			case "player_update" :
				this.players[data.content.id].sync(data.content.props);
				//this.display_log("Data received : " + data.content);
			break;

			case "player_jump" :
				this.players[data.content.id].jump();
			break;

			case "player_quit" :
				this.players[data.content].disappear();
			break;
		}
	}

	// create our character and set all events listeners needed for interactions
	this.init_hero = function()
	{
		this.hero = new Character(prompt("Enter a player name :"), this.scene);
		this.hero.change_username("<strong>You</strong>");
		//this.hero = new Character("npc1", this.scene);
		//this.hero = new Character("enemy" + (1 + Math.round(Math.random()*2)), this.scene);

		jQuery(document)
		.keydown(jQuery.proxy(this.on_keyevent, this))
		.keyup(jQuery.proxy(this.on_keyevent, this));
	}

	// handler executed when player press or release a key on his keyboard
	this.on_keyevent = function(e)
	{
		var key = this.convert_keycode(e.which);
		if(key)
		{
			if(e.type == "keydown")
			{
				if(!this.keypressed[key] || this.keypressed[key] == false)
				{
					this.keypressed[key] = true;
				}
				else
				{
					return;
				}
			}

			else if(e.type == "keyup")
			{
				this.keypressed[key] = false;
			}

			e.preventDefault();
			this.hero.apply_keyevent(e.type, key);
			this.socket.send("keyevent", {type: e.type, key: key});
		}
	}

	// transfer a keycode into a string, easily understandable (converts only keys we want to track in our game)
	this.convert_keycode = function(keycode)
	{
		switch(keycode)
		{
			case 87 : case 38 : return "w"; break;
			case 65 : case 37 : return "a"; break;
			case 83 : case 40 : return "s"; break;
			case 68 : case 39 : return "d"; break;
			case 32 : return "space"; break;
			case 13 : return "enter"; break;
			case 69 : return "e"; break;
			default : return false; break;
		}
	}

	// display info text somewhere
	this.display_log = function(t)
	{
		document.getElementById("console").innerHTML = t;
	}
	
	// initialization
	this.init();
}