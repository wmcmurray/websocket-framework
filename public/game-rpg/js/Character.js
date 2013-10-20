//===========================================================
// ===== Character() ========================================
//===========================================================

function Character(username, parent, props)
{
	EventsDispatcher.prototype.init.call(this);

	this.username = username;
	this.parent = parent;
	this.props = props ? props : {};
	this.anim_frame = 1;
	this.is_jumping = false;
	this.anim_interval;
	this.refresh_interval;
	this.refresh_interval_time = 1000;

	this.init();
}

Character.prototype = new EventsDispatcher();

Character.prototype.init = function()
{
	this.view = document.createElement("div");
	this.view.className = "character";

	this.view.shadow = document.createElement("span");
	this.view.shadow.className = "shadow";

	this.view.sprite = document.createElement("div");
	this.view.sprite.className = "sprite";
	
	this.view.username = document.createElement("div");
	this.view.username.className = "username";
	this.view.username.innerHTML = this.username ? this.username : "Anonymous";

	this.view.health = document.createElement("div");
	this.view.health.className = "ui health";

	this.view.healthbar = document.createElement("div");
	this.view.healthbar.className = "bar";

	this.view.sprite.appendChild(this.view.username);
	this.view.health.appendChild(this.view.healthbar);
	this.view.sprite.appendChild(this.view.health);
	this.view.appendChild(this.view.shadow);
	this.view.appendChild(this.view.sprite);

	if(this.props.skin)
	{
		this.change_skin(this.props.skin);
	}
	
	this.appear();
	
	return this;
}

Character.prototype.sync = function(props)
{
	// sync received props
	for(var i in props)
	{
		this.state_change_msg(i, Math.round(props[i] - this.props[i]));
		this.props[i] = props[i];
	}

	// health bar
	if(props["health"] || props["max_health"])
	{
		this.view.healthbar.style.width = (this.props["health"] * 100) / this.props["max_health"] + "%";
	}

	// mouvements
	if(props["x"] || props["y"] || props["speed"] || props["direction"])
	{
		// reset estimated position
		this.estimatedX = this.props.x;
		this.estimatedY = this.props.y;

		// refresh
		if(this.refresh_interval)
		{
			clearInterval(this.refresh_interval);
		}
		
		// anim
		if(this.anim_interval)
		{
			clearInterval(this.anim_interval);
		}

		if(this.props.direction[0] != 0 || this.props.direction[1] != 0)
		{
			this.refresh_interval = setInterval(jQuery.proxy(this.refresh, this), this.refresh_interval_time);
			this.anim_interval = setInterval(jQuery.proxy(this.animate, this), 1000 / (this.props.speed / 20));
			this.animate();
		}

		this.refresh();
	}
	
	return this;
}

Character.prototype.apply_keyevent = function(type, key)
{
	switch(type)
	{
		case "keydown" :
			switch(key)
			{
				case "w" : break;
				case "a" : break;
				case "s" : break;
				case "d" : break;
				case "space" : this.jump(); break;
			}
		break;
	}

	return this;
}

Character.prototype.refresh = function()
{
	// update estimated position
	var divider = this.props.direction[0] && this.props.direction[1] ? 0.75 : 1;
	this.estimatedX += (this.props.direction[0] * divider) * this.props.speed;
	this.estimatedY += (this.props.direction[1] * divider) * this.props.speed;
	this.place(this.estimatedX, this.estimatedY, true);

	this.trigger("refresh", this.refresh_interval_time);
	
	return this;
}

Character.prototype.place = function(x, y, animated)
{
	var props = {left: (x - (this.width * 0.5))+ "px", top: (y - this.height) + "px"};

	if(animated)
	{
		jQuery(this.view).stop().animate(props,
		{
			duration: this.refresh_interval_time,
			easing: "linear",
			progress: jQuery.proxy(this.update_depth, this)
		});
	}
	else
	{
		jQuery(this.view).stop().css(props);
		this.estimatedX = x;
		this.estimatedY = y;
	}

	return this;
}

Character.prototype.jump = function()
{
	if(!this.is_jumping)
	{
		this.is_jumping = true;
		this.anim_frame = Math.random() < 0.5 ? 2 : 4;

		// anim shadow
		jQuery(this.view.shadow).stop().animate({opacity: 0.25}, 500, function()
		{
			jQuery(this).animate({opacity: 1}, 400);
		});

		// anim sprite
		jQuery(this.view.sprite).stop().animate({top: -this.height + "px"}, 500,
		jQuery.proxy(function()
		{
			jQuery(this.view.sprite).animate({top: "0px"}, 400, jQuery.proxy(function()
			{
				this.is_jumping = false;
				this.animate();
			}, this));
		}, this));
	}

	return this;
}

Character.prototype.talk = function(msg, speed)
{
	var bubble = document.createElement("div");
	bubble.className = "bubble";
	bubble.innerHTML = msg;
	this.view.appendChild(bubble);

	jQuery(bubble).animate({opacity: 0, bottom: "250%"}, speed ? speed : 5000, function(){
		this.parentNode.removeChild(this);
	});

	return this;
}

Character.prototype.teleport = function(x, y)
{
	this.props.x = x;
	this.props.y = y;
	this.place(x, y);

	return this;
}

Character.prototype.animate = function()
{
	if(!this.is_jumping)
	{
		if(this.props.direction[0] != 0 || this.props.direction[1] != 0)
		{
			if(this.anim_frame >= 4)
			{
				this.anim_frame = 1;
			}
			else
			{
				this.anim_frame++;
			}

			// add footprint
			var footprint = document.createElement("img");
			footprint.className = "footprint";
			footprint.src = "images/footprint.png";
			footprint.style.left = Number(this.view.style.left.replace("px", "")) + (this.width * 0.5) + (this.anim_frame%2 && this.props.direction[1] ? -10 : 0) + "px";
			footprint.style.top = Number(this.view.style.top.replace("px", "")) + (this.height) + (this.anim_frame%2 && this.props.direction[0] ? (this.props.direction[1] == this.props.direction[0] ? 5 : -5) : 0) -5 + "px";
			this.parent.appendChild(footprint);

			setTimeout(function(){
				jQuery(footprint).animate({opacity: 0}, 1000, function(){
					this.parentNode.removeChild(this);
				});
			}, 10000)
			
		}
		else
		{
			//this.anim_frame = 1;
		}
	}

	// update sprite anim
	if(this.props.direction[1] > 0)
	{
		this.update_sprite(this.anim_frame, 1);
	}
	else if(this.props.direction[1] < 0)
	{
		this.update_sprite(this.anim_frame, 4);
	}

	if(this.props.direction[0] > 0)
	{
		this.update_sprite(this.anim_frame, 3);
	}
	else if(this.props.direction[0] < 0)
	{
		this.update_sprite(this.anim_frame, 2);
	}
	
	return this;
}

Character.prototype.update_depth = function()
{
	this.view.style.zIndex = Math.round(Number(this.view.style.top.replace("px", "")) + this.height);

	return this;
}

Character.prototype.update_sprite = function(x, y)
{
	jQuery(this.view.sprite).css({backgroundPosition : -((x-1) * this.width) + "px " + -((y-1) * this.height) + "px"});

	return this;
}

Character.prototype.appear = function()
{
	this.place(this.props.x, this.props.y);
	jQuery(this.view).stop().css({opacity: 1}).animate({opacity: 1}, 1000);
	this.parent.appendChild(this.view);
	
	return this;
}

Character.prototype.disappear = function(instantly)
{
	if(this.refresh_interval)
	{
		clearInterval(this.refresh_interval);
	}

	if(this.anim_interval)
	{
		clearInterval(this.anim_interval);
	}

	if(instantly)
	{
		this.parent.removeChild(this.view);
	}
	else
	{
		jQuery(this.view).stop().animate({opacity: 0}, 1000, function(){ if(this.parentNode){this.parentNode.removeChild(this);} });
	}
	
	return this;
}

Character.prototype.change_displayed_username = function(username)
{
	this.view.username.innerHTML = username;

	return this;
}

Character.prototype.change_skin = function(skin)
{
	jQuery(this.view).removeClass(this.props.skin).addClass(skin);
	this.view.sprite.style.backgroundImage = "url(images/" + skin + ".png)";
	this.props.skin = skin;

	switch(this.props.skin)
	{
		case "npc1" :
			this.width = 32;
			this.height = 32;
		break;

		default :
			this.width = 32;
			this.height = 48;
		break;
	}

	this.view.style.width = this.width + "px";
	this.view.style.height = this.height + "px";
	
	return this;
}

Character.prototype.state_change_msg = function(prop, amount)
{
	switch(prop)
	{
		case "health" :
			var color = amount < 0 ? "#f00" : "#0f0";
		break;

		case "exp" :
			var color = "#f0f";
		break;
	}

	if(!color || !amount)
	{
		return false;
	}

	this.talk('<span style="color:' + color + '; font-size:' + (10 + ((amount < 0 ? -amount : amount) * 0.3)) + 'px;">' + (amount > 0 ? '+' : '') + amount + '</span>', 1000);

	return this;
}