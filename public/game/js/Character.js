//===========================================================
// ===== Character() ========================================
//===========================================================

function Character(username, parent, props)
{
	this.username = username;
	this.skin = "hero8";
	this.parent = parent;
	this.props = props ? props : {};
	this.width = 32;
	this.height = 48;
	this.anim_frame = 1;
	this.anim_interval;
	this.refresh_interval;
	this.init();
}

Character.prototype.init = function()
{
	this.view = document.createElement("div");
	this.view.className = "character";
	this.view.innerHTML = '<span class="shadow"></span>';

	this.view.sprite = document.createElement("div");
	this.view.sprite.className = "sprite";
	
	this.view.username = document.createElement("div");
	this.view.username.className = "username";
	this.view.username.innerHTML = this.username ? this.username : "Anonymous";

	this.view.sprite.appendChild(this.view.username);
	this.view.appendChild(this.view.sprite);

	this.change_skin(this.skin);
	
	switch(this.skin)
	{
		case "npc1" :
			this.height = 32;
			this.view.style.height = "32px"
		break;
	}

	this.appear();
	
	return this;
}

Character.prototype.change_username = function(username)
{
	jQuery(this.view).find(".username").html(username);

	return this;
}

Character.prototype.change_skin = function(skin)
{
	jQuery(this.view).removeClass(this.skin).addClass(skin);
	this.view.sprite.style.backgroundImage = "url(images/" + skin + ".png)";
	this.skin = skin;
	
	return this;
}

Character.prototype.sync = function(props)
{
	this.props = props;

	if(this.props.direction[0] != 0 || this.props.direction[1] != 0)
	{
		// refresh
		if(this.refresh_interval)
		{
			clearInterval(this.refresh_interval);
		}
		this.refresh_interval = setInterval(jQuery.proxy(this.refresh, this), 1000);

		// anim
		if(this.anim_interval)
		{
			clearInterval(this.anim_interval);
		}

		this.anim_interval = setInterval(jQuery.proxy(this.animate, this), 1000 / (this.props.speed / 20));
		//this.anim_interval = setInterval(jQuery.proxy(this.animate, this), 200);
	}

	this.refresh();
	
	return this;
}

Character.prototype.refresh = function()
{
	// update estimated position
	var divider = this.props.direction[0] && this.props.direction[1] ? 0.75 : 1;
	this.props.x += (this.props.direction[0] * divider) * this.props.speed;
	this.props.y += (this.props.direction[1] * divider) * this.props.speed;
	this.place(true);
	
	return this;
}

Character.prototype.animate = function()
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
	}
	else
	{
		//this.anim_frame = 1;
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

	var zIndex = Math.round(jQuery(this.view).offset().top + this.height);
	//console.log(zIndex);
	this.view.style.zIndex = zIndex;
	
	return this;
}

Character.prototype.place = function(animated)
{
	var props = {left: (this.props.x - (this.width * 0.5))+ "px", top: (this.props.y - this.height) + "px"};

	if(animated)
	{
		jQuery(this.view).stop().animate(props, 1000, "linear");
	}
	else
	{
		jQuery(this.view).stop().css(props);
	}
	
	return this;
}

Character.prototype.appear = function()
{
	this.place();
	jQuery(this.view).stop().css({opacity: 1}).animate({opacity: 1}, 1000);
	this.parent.appendChild(this.view);
	
	return this;
}

Character.prototype.disappear = function()
{
	if(this.refresh_interval)
	{
		clearInterval(this.refresh_interval);
	}

	if(this.anim_interval)
	{
		clearInterval(this.anim_interval);
	}

	var parent = this.parent;
	var view = this.view;
	jQuery(this.view).stop().animate({opacity: 0}, 1000, function(){parent.removeChild(view);});

	return this;
}

Character.prototype.apply_keyevent = function(type, key)
{
	switch(type)
	{
		case "keydown" :
			switch(key)
			{
				case "w" :
					//this.props.y -= this.props.speed;
					//this.update_sprite(1, 4);
				break;

				case "a" :
					//this.props.x -= this.props.speed;
					//this.update_sprite(1, 2);
				break;

				case "s" :
					//this.props.y += this.props.speed;
					//this.update_sprite(1, 1);
				break;

				case "d" :
					//this.props.x += this.props.speed;
					//this.update_sprite(1, 3);
				break;

				case "space" :
					this.jump();
				break;
			}
		break;
	}

	//this.place(true);
	return this;
}

Character.prototype.jump = function()
{
	var sprite = jQuery(this.view).find(".sprite");
	if(!sprite.prop("jumping"))
	{
		sprite.stop().prop("jumping", true).animate({top: -this.height + "px"}, 500, function(){ jQuery(this).animate({top: "0px"}, 400, function(){ jQuery(this).prop("jumping", false); }); });
	}
}

Character.prototype.update_sprite = function(x, y)
{
	jQuery(this.view).find(".sprite").css({backgroundPosition : -((x-1) * this.width) + "px " + -((y-1) * this.height) + "px"});

	return this;
}