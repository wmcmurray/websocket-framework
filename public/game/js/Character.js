//===========================================================
// ===== Character() ========================================
//===========================================================

function Character(username, parent, props)
{
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

	this.view.coord = document.createElement("div");
	this.view.coord.className = "coord";

	this.view.sprite.appendChild(this.view.username);
	this.view.appendChild(this.view.shadow);
	this.view.appendChild(this.view.coord);
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
	for(var i in props)
	{
		this.props[i] = props[i];
	}

	if(this.props.direction[0] != 0 || this.props.direction[1] != 0)
	{
		// refresh
		if(this.refresh_interval)
		{
			clearInterval(this.refresh_interval);
		}
		this.refresh_interval = setInterval(jQuery.proxy(this.refresh, this), this.refresh_interval_time);

		// anim
		if(this.anim_interval)
		{
			clearInterval(this.anim_interval);
		}

		this.anim_interval = setInterval(jQuery.proxy(this.animate, this), 1000 / (this.props.speed / 20));
		this.animate();
	}

	this.refresh();
	
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
	// display current coords
	this.view.coord.innerHTML = "x:" + this.props.x + "<br>y:" + this.props.y;

	// update estimated position
	var divider = this.props.direction[0] && this.props.direction[1] ? 0.75 : 1;
	this.props.x += (this.props.direction[0] * divider) * this.props.speed;
	this.props.y += (this.props.direction[1] * divider) * this.props.speed;
	this.place(true);

	this.trigger("refresh", this.refresh_interval_time);
	
	return this;
}

Character.prototype.place = function(animated)
{
	var props = {left: (this.props.x - (this.width * 0.5))+ "px", top: (this.props.y - this.height) + "px"};

	if(animated)
	{
		jQuery(this.view).stop().animate(props, this.refresh_interval_time, "linear");
	}
	else
	{
		jQuery(this.view).stop().css(props);
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

Character.prototype.talk = function(msg)
{
	var bubble = document.createElement("div");
	bubble.className = "bubble";
	bubble.innerHTML = msg;
	this.view.appendChild(bubble);

	jQuery(bubble).animate({opacity: 0, bottom: "250%"}, 5000, function(){
		this.parentNode.removeChild(this);
	});

	return this;
}

Character.prototype.teleport = function(x, y)
{
	this.props.x = x;
	this.props.y = y;
	this.place();

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

	this.update_depth();
	
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
	this.place();
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
		var view = this.view;
		var parent = this.parent;
		jQuery(this.view).stop().animate({opacity: 0}, 1000, function(){ parent.removeChild(view); });
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