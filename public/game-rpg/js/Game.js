//===========================================================
// ===== Game() =============================================
//===========================================================

function Game(address, port)
{
	this.init = function()
	{
		this.scene = document.getElementById("scene");
		this.map = document.getElementById("map");
		this.inventory = document.getElementById("inventory");
		this.players = {};
		this.objects = Array();
		this.keypressed = {};
		this.fps = 0;

		// instanciate the SocketClient class and add eventsListeners
		this.socket = new SocketClient(address, port);
		this.socket.on("open", this.socket.proxy(this.handle_socket_open, this));
		this.socket.on("close", this.socket.proxy(this.handle_socket_close, this));
		this.socket.on("message", this.socket.proxy(this.handle_messages, this));

		// opening of the socket
		this.display_log("Trying to open the socket...");
		this.socket.open();
	}

	// the main game loop (characters mouvements are not part of this loop)
	this.loop = function()
	{
		// main game loop... unused for now
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

		clearInterval(this.loop_interval);

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
			// triggered when player first sync
			case "initial_sync" :
				this.hero
				.sync(data.content)
				.change_skin(data.content.skin)
				.place(data.content.x, data.content.y);

				this.update_camera(0);

				if(this.loop_interval)
				{
					clearInterval(this.loop_interval);
				}

				if(this.fps > 0)
				{
					this.loop_interval = setInterval(this.socket.proxy(this.loop, this), 1000 / this.fps);
					this.loop();
				}

				if(data.content.area)
				{
					areasButtons.parent().find("a[data-area='" + data.content.area + "']").trigger("click");
				}

				if(data.content.inventory)
				{
					this.update_inventory(data.content.inventory);
				}
			break;

			// triggered when server send our real state to syncronize the browser
			case "sync" :
				this.hero.sync(data.content);

				if(data.content.inventory)
				{
					this.update_inventory(data.content.inventory);
				}
			break;

			// when we receives a list of players
			case "players_list" :
				// delete old ones
				for(var id in this.players)
				{
					this.remove_player(id);
				}

				// add new players
				for(var id in data.content)
				{
					this.insert_player(id, data.content[id]);
				}
			break;

			// when we receives a list of objects
			case "objects_list" :
				// delete old ones
				for(var i in this.objects)
				{
					this.objects[i].remove();
					delete this.objects[i];
				}

				// add new objects
				for(var i in data.content)
				{
					this.insert_object(data.content[i]);
				}
			break;

			// triggered when we teleport
			case "teleport" :
				this.hero.teleport(data.content.x, data.content.y);
				this.update_camera(0);
				this.hero.sync(data.content);
			break;

			// triggered when a new player join the game
			case "new_player" :
				this.insert_player(data.content.id, data.content.props);
			break;

			// triggered when a new object get dropped in the game
			case "new_object" :
				this.insert_object(data.content);
			break;

			// triggered when an other player position change
			case "player_update" :
				if(this.players[data.content.id])
				{
					this.players[data.content.id].sync(data.content.props);
				}
			break;

			// triggered when an other player perform an action that we should know about
			case "player_action" :
				if(this.players[data.content.id])
				{
					switch(data.content.action)
					{
						case "jump" :
							this.players[data.content.id].jump();
						break;

						case "talk" :
							this.players[data.content.id].talk(data.content.msg);
						break;
					}
				}
			break;

			// triggered when an other player teleport
			case "player_teleport" :
				if(this.players[data.content.id])
				{
					this.players[data.content.id].teleport(data.content.props.x, data.content.props.y);
					this.players[data.content.id].sync(data.content.props);
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
		this.hero = new Character(prompt("Enter a player name :", "guest"), this.map);
		this.hero.change_displayed_username("<strong>ME</strong>");
		this.hero.on("refresh", jQuery.proxy(this.update_camera, this))

		jQuery(document)
		.keydown(jQuery.proxy(this.on_keyevent, this))
		.keyup(jQuery.proxy(this.on_keyevent, this));

		jQuery(this.map)
		.mousedown(jQuery.proxy(this.on_mouseevent, this));

		jQuery(window).blur(function()
		{
			var a = Array(87, 65, 83, 68);
			for(var i in a)
			{
			    jQuery(document).trigger(jQuery.Event('keyup', {which: a[i]}));
			}
		})
		.resize(jQuery.proxy(this.update_camera, this, 0));
	}

	this.on_mouseevent = function(e)
	{
		e.preventDefault();
		var x = e.pageX - jQuery(e.currentTarget).offset().left;
		var y = e.pageY - jQuery(e.currentTarget).offset().top;
		this.socket.send("mouseevent", {type: e.type, x: Math.round(x), y: Math.round(y)});
	}

	// handler executed when player press or release a key on his keyboard
	this.on_keyevent = function(e)
	{
		var key = this.convert_keycode(e.which);
		if(key)
		{
			if(e.type == "keydown")
			{
				if(key == "t")
				{
					var msg = prompt("Enter your message here :");
					this.socket.send("talk", msg);
					this.hero.talk(msg);
					return;
				}
				else if(!this.keypressed[key] || this.keypressed[key] == false)
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
				if(this.keypressed[key])
				{
					this.keypressed[key] = false;
				}
				else
				{
					return;
				}
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
			case 16 : return "shift"; break;
			case 69 : return "e"; break;
			case 81 : return "q"; break;
			case 84 : return "t"; break;
			default : return false; break;
		}
	}

	// create a new player instance in the game
	this.insert_player = function(id, props)
	{
		if(this.players[id])
		{
			this.remove_player(id);
		}

		this.players[id] = new Character(props.username, this.map, props);
		this.players[id].sync(props).place(props.x, props.y).update_depth();
	}

	// remove a player instance in the game
	this.remove_player = function(id)
	{
		this.players[id].disappear();
		delete this.players[id];
	}

	// create a new object instance in the game
	this.insert_object = function(props)
	{
		this.objects.push(new Game_Object(props.name, this.map, props.pos.x, props.pos.y));
	}

	// update the player inventory in UI
	this.update_inventory = function(objects)
	{
		console.log(objects);
		this.inventory.innerHTML = "";

		for(var i in objects)
		{
			new Game_Object(objects[i], this.inventory, 0, 0);
			//this.inventory.innerHTML += objects[i] + ", ";
		}
	}

	// move the whole scene to always see player, this simulate a camera effect
	this.update_camera = function(speed, easing)
	{
		var sceneW = jQuery(this.scene).width();
		var sceneH = jQuery(this.scene).height();
		var mapW = jQuery(this.map).width();
		var mapH = jQuery(this.map).height();
		var heroW = this.hero ? jQuery(this.hero.view).width() : 0;
		var heroH = this.hero ? jQuery(this.hero.view).height() : 0;
		var heroL = this.hero ? this.hero.estimatedX : 0;
		var heroT = this.hero ? this.hero.estimatedY : 0;
		var mapTopMax = -(mapH - sceneH);
		var mapLeftMax = -(mapW - sceneW);
		var left = 0;
		var top = 0;
		
		if(mapW < sceneW)
		{
			left = (sceneW - mapW) * 0.5;
		}
		else
		{
			left = -heroL + (sceneW * 0.5);

			if(left > 0)
			{
				left = 0;
			}
			else if(left < mapLeftMax)
			{
				left = mapLeftMax;
			}
		}

		
		if(mapH < sceneH)
		{
			top = (sceneH - mapH) * 0.5;
		}
		else
		{
			top = -heroT + (sceneH * 0.5) + (heroH * 0.5);

			if(top > 0)
			{
				top = 0;
			}
			else if(top < mapTopMax)
			{
				top = mapTopMax;
			}
		}
		
		if(!speed)
		{
			jQuery(this.map).stop().css({left: left + "px", top: top + "px"});
		}
		else
		{
			jQuery(this.map).stop().animate({left: left + "px", top: top + "px"}, speed, easing ? easing : "linear");
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