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
			// triggered when server send our real state to syncronize the browser
			case "sync" :
				if(data.content.initial_sync)
				{
					this.hero.sync(data.content.initial_sync.props).change_skin(data.content.initial_sync.skin).place();

					for(var id in data.content.initial_sync.players_list)
					{
						this.insert_player(id, data.content.initial_sync.players_list[id]);
					}
				}
				else
				{
					this.hero.sync(data.content);
				}
			break;

			// triggered when a new player join the game
			case "new_player" :
				this.insert_player(data.content.id, data.content.props);
			break;

			// triggered when an other player position change
			case "player_update" :
				this.players[data.content.id].sync(data.content.props);
			break;

			// triggered when an other player perform an action that we should know about
			case "player_action" :
				switch(data.content.action)
				{
					case "jump" :
						this.players[data.content.id].jump();
					break;
				}
			break;

			// triggered when a player quit the game
			case "player_quit" :
				this.remove_player(data.content);
			break;
		}
	}

	// create our character and set all events listeners needed for interactions
	this.init_hero = function()
	{
		this.hero = new Character(prompt("Enter a player name :"), this.scene);
		this.hero.change_displayed_username("<strong>ME</strong>");

		jQuery(document)
		.keydown(jQuery.proxy(this.on_keyevent, this))
		.keyup(jQuery.proxy(this.on_keyevent, this));
	}

	// create a new player instance in the game
	this.insert_player = function(id, props)
	{
		if(this.players[id])
		{
			this.remove_player(id);
		}

		this.players[id] = new Character(props.username, this.scene, props);
		this.players[id].sync(props).place().animate();
	}

	// remove a player instance in the game
	this.remove_player = function(id)
	{
		this.players[id].disappear();
		delete this.players[id];
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