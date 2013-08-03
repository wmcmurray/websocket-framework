//===========================================================
// ===== Game_Object() ======================================
//===========================================================

function Game_Object(name, parent, x, y)
{
	this.name = name;
	this.parent = parent;
	this.width = 34;
	this.height = 34;
	this.init();
	this.place(x, y);

	switch(this.name)
	{
		case "knife" : this.update_sprite(1, 7); break;
		case "shortsword" : this.update_sprite(1, 6); break;
		case "broadsword" : this.update_sprite(6, 6); break;
		case "steel broadsword" : this.update_sprite(7, 6); break;
		case "health potion" : this.update_sprite(1, 3); break;
	}
}

Game_Object.prototype.init = function()
{
	this.view = document.createElement("div");
	this.view.className = "object " + this.name;
	this.view.title = this.name;

	this.parent.appendChild(this.view);
	
	return this;
}

Game_Object.prototype.place = function(x, y)
{
	this.x = x;
	this.y = y;
	
	this.view.style.left = (this.x - this.width * 0.5) + "px";
	this.view.style.top = (this.y - this.height * 0.5) + "px";

	return this;
}

Game_Object.prototype.update_depth = function()
{
	this.view.style.zIndex = Math.round(Number(this.view.style.top.replace("px", "")) + this.height);

	return this;
}

Game_Object.prototype.update_sprite = function(x, y)
{
	jQuery(this.view).css({backgroundPosition : -((x-1) * this.width) + "px " + -((y-1) * this.height) + "px"});

	return this;
}

Game_Object.prototype.remove = function()
{
	this.parent.removeChild(this.view);
	
	return this;
}