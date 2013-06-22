//===========================================================
// ===== MMouse() ===========================================
//===========================================================
var MMouse_root="http://localhost/testing_ground/mmouse/";
function MMouse(address,port)
{
	var self=this;
	
	this.init = function()
	{
		this.me=new MMouse_user("MMOUSE_PREFS");
		this.fps=24;
		this.users={};
		this.users_selections={};
		
		this.init_interface();
		this.update_interface();
		this.init_socket();
	}
	
	this.init_socket = function()
	{
		this.socket=new SocketClient(address,port);
		this.socket.on("open",function(){self.socket.send("list_clients"); setTimeout(function(){self.send_user_prefs();},10);});
		this.socket.on("open",function(){self.socket.sys_alert("Socket opened.");});
		this.socket.on("message",this.handle_messages);
		this.socket.on("error",function(e){alert(e.type);});
		this.socket.on("close",function(){self.socket.sys_alert("Socket closed.");});
		this.socket.on("alert",function(t){console.log("WS : "+t);});
		this.socket.on("ping",function(ms){alert("Your ping is "+Math.round(ms*1000)/1000)+" milliseconds";});
		this.socket.open("url="+encodeURIComponent(window.location));
		this.set_timer(this.fps);
	}
	
	this.init_interface = function()
	{
		this.ui=document.createElement("div");
		this.ui.className="mmouse_ui";
		this.ui.innerHTML='<div class="left"><img id="mmouse_avatar" width="32" src="'+MMouse_root+'cursors/'+this.me.prefs.cursor+'" alt=""></div><div id="mmouse_infos" class="left"></div>';
		document.body.appendChild(this.ui);
		this.ui_avatar=document.getElementById("mmouse_avatar");
		this.ui_infos=document.getElementById("mmouse_infos");
		this.ui_infos.style.display="none";
		
		this.ui.onmouseover=function(){$(self.ui_infos).css({display:"block"});};
		this.ui.onmouseout=function(){$(self.ui_infos).css({display:"none"});};
	}
	
	this.update_interface = function()
	{
		this.ui_infos.innerHTML="";
		var usernameField=document.createElement("input");
		usernameField.type="text";
		usernameField.value=this.me.prefs.username;
		usernameField.onchange=function(){self.me.set_prefs({username:this.value}); self.send_user_prefs();}
		var cursorSelect=document.createElement("select");
		cursorSelect.onchange=function(){self.me.set_prefs({cursor:this.value}); self.ui_avatar.src=MMouse_root+"cursors/"+this.value; self.send_user_prefs();}
		var cursors=Array("default.png","gamescreat.png","runescape.png","zelda.gif","wow.gif","wow2.gif","goomba.png","pokemon.png","pikachu.png","charmander.png","bulbasaur.png","squirtle.png","pokebal.png","skyrim.png","lol.png","perfect_world.png","rose.png","troll.png","nyan.png","pacman.gif");
		for(var i in cursors)
		{
			var o=document.createElement("option");
			if(cursors[i]==this.me.prefs.cursor){o.selected=true;}
			o.value=cursors[i];
			o.innerHTML=cursors[i];
			cursorSelect.appendChild(o);
		}
		
		this.ui_infos.appendChild(usernameField);
		this.ui_infos.appendChild(cursorSelect);
	}
	
	this.set_timer = function(fps)
	{
		this.fps=fps;
		this.timer=setInterval(this.send_mouse_data,1000/fps);
	}
	
	this.mouse_event = function(evt)
	{
		if(!self.mouse_data){self.mouse_data={};}
		//var x=(evt.pageX-(document.documentElement.scrollLeft||document.body.scrollLeft)||evt.clientX)||0;
		//var y=(evt.pageY-(document.documentElement.scrollTop||document.body.scrollTop)||evt.clientY)||0;
		var x=evt.pageX;
		var y=evt.pageY;
		
		//t(x+","+y);
		
		switch(evt.type)
		{
			case "mousemove" :
				self.mouse_data.x=x;
				self.mouse_data.y=y;
			break;
			
			case "mousedown" :
				self.mouse_data.md={x:x,y:y};
			break;
			
			case "mouseup" :
				this.mouse_data.mu={x:x,y:y};
			break;
			
			case "click" :
				if(!self.mouse_data.mc){self.mouse_data.mc=new Array();}
				this.mouse_data.mc.push({x:x,y:y});
			break;
		}
		
	}
	
	this.send_mouse_data = function()
	{
		if(self.mouse_data&&self.socket.send("mouse_data",self.mouse_data)){self.mouse_data=null;}
	}
	
	this.send_user_prefs = function()
	{
		this.socket.send("user_prefs",this.me.prefs);
	}
	
	//----------
	
	this.handle_messages = function(data)
	{
		switch(data.action)
		{
			case "list_clients" :
				self.handle_list_clients(data.content);
			break;
			
			case "mouse_data" :
				if(!self.users[data.content.id]){self.make_user(data.content);}
				self.handle_mouse_data(data.content);
			break;
			
			case "user_prefs" :
				self.users[data.content.id].set_prefs(data.content.prefs);
				self.users[data.content.id].update_display();
			break;
			
			case "login" :
				if(!self.users[data.content.id]){self.make_user(data.content);}
			break;
			
			case "logout" :
				self.remove_user(data.content);
			break;
			
			case "fps" :
				self.set_timer(data.content);
			break;
		}
	}
	
	this.handle_list_clients = function(data)
	{
		for(var i in data)
		{
			if(i!=this.socket.clientID)
			{
				var user=self.make_user({id:i});
				if(data[i].x&&data[i].y){self.place_user({id:i,x:data[i].x,y:data[i].y},1);}
				if(data[i].cursor){user.set_prefs({cursor:data[i].cursor});}
				if(data[i].username){user.set_prefs({username:data[i].username});}
				user.update_display();
			}
		}
	}
	
	this.handle_mouse_data = function(data)
	{
		if(data.x||data.y)
		{
			self.place_user(data);
			if(self.users_selections[data.id]){self.place_user_selection(data);}
			//new MMouse_user_click(data.x,data.y);
		}
		
		if(data.mc)
		{
			for(var i in data.mc)
			{
				new MMouse_user_click(data.mc[i].x,data.mc[i].y);
			}
		}
		
		if(data.md&&!data.mu)
		{
			if(!self.users_selections[data.id]){self.make_user_selection(data);}
			self.place_user_selection(data);
		}
		
		if(data.mu)
		{
			self.remove_user_selection(data.id);
		}
	}
	
	this.make_user = function(data){this.users[data.id]=new MMouse_user(); this.users[data.id].init_display(); return this.users[data.id];}
	this.place_user = function(data,speed){if(self.users[data.id]){self.users[data.id].place(data.x,data.y,(speed?speed:1000/self.fps));}}
	this.remove_user = function(id){if(this.users[id]){self.users[id].remove(); this.users[id]=null;}}
	
	this.make_user_selection = function(data)
	{
		var selection=document.createElement("div");
		selection.className="mmouse_user_selection";
		selection.init_position=data.md;
		$(selection).css({opacity:1});
		document.body.appendChild(selection);
		this.users_selections[data.id]=selection;
	}
	
	this.place_user_selection = function(data)
	{
		var initPos=self.users_selections[data.id].init_position;
		//var offset=$(self.users_selections[data.id]).offset();
		var o={opacity:1};
		o.top=(initPos.y<data.y?initPos.y:data.y)+"px";
		o.left=(initPos.x<data.x?initPos.x:data.x)+"px";
		o.width=(initPos.x<data.x?data.x-initPos.x:initPos.x-data.x)+"px";
		o.height=(initPos.y<data.y?data.y-initPos.y:initPos.y-data.y)+"px";
		$(self.users_selections[data.id]).stop().animate(o,1000/self.fps,"linear");
	}
	
	this.remove_user_selection = function(id)
	{
		if(this.users_selections[id])
		{
			document.body.removeChild(this.users_selections[id]);
			this.users_selections[id]=null;
			//$(this.users_selections[id]).animate({opacity:0},250,"swing",function(){document.body.removeChild(self.users_selections[id]); self.users_selections[id]=null;});
		}
	}
	
	this.init();
}

//===========================================================
// ===== MMouse_user() ======================================
//===========================================================
function MMouse_user(prefs_cookie_name)
{
	var self=this;
	
	this.init = function()
	{
		this.prefs={};
		this.prefs.cursor="default.png";
		this.prefs.username="Anonymous";
		if(prefs_cookie_name)
		{
			this.cookie=prefs_cookie_name;
			this.get_prefs();
		}
	}
	
	this.init_display = function()
	{
		this.div=document.createElement("div");
		this.div.className="mmouse_user";
		$(this.div).css({opacity:1,top:-$(this.div).outerHeight()+"px",left:-$(this.div).outerWidth()+"px"});
		document.body.appendChild(this.div);
		this.info_div=document.createElement("div");
		this.info_div.className="mmouse_user_info_div";
		this.div.appendChild(this.info_div);
		this.update_display();
	}
	
	this.update_display = function()
	{
		if(this.div&&this.info_div)
		{
			this.div.style.backgroundImage="url("+MMouse_root+"cursors/"+this.prefs.cursor+")";
			this.info_div.innerHTML=this.prefs.username;
		}
	}
	
	this.get_prefs = function()
	{
		var cookie=getCookie(this.cookie);
		if(cookie)
		{
			var p=cookie.split("&");
			for(var i in p)
			{
				var p2=p[i].split("=");
				this.prefs[p2[0]]=p2[1];
			}
		}
	}
	
	this.set_prefs = function(new_prefs)
	{
		for(var i in new_prefs)
		{
			this.prefs[i]=new_prefs[i];
		}
		
		if(this.cookie)
		{
			var str="";
			for(var i in this.prefs)
			{
				str+=(str==""?"":"&")+i+"="+this.prefs[i];
			}
			setCookie(this.cookie,str,30);
		}
	}
	
	this.place = function(x,y,speed)
	{
		$(this.div).stop().animate({top:y+"px",left:x+"px"},(speed?speed:1),"linear");
	}
	
	this.remove = function()
	{
		//this.info_div.innerHTML="<span style='color:#f00;'>Goodbye!</span>";
		$(this.div).animate({opacity:0},3000,"swing",function(){document.body.removeChild(self.div); /*self.div=null;*/});
	}
	
	this.init();
}

//===========================================================
// ===== MMouse_user_click() ================================
//===========================================================
function MMouse_user_click(x,y)
{
	var self=this;
	
	this.init = function()
	{
		this.div=document.createElement("div");
		this.div.className="mmouse_user_click";
		this.div.onclick=function(){self.remove();};
		$(this.div).css({top:(y-($(this.div).outerHeight()*0.5))+"px",left:(x-($(this.div).outerWidth()*0.5))+"px"});
		var img=document.createElement("img");
		img.src="imgs/portal.png";
		$(img).css({width:"100%",height:"100%",opacity:0.35});//,position:"relative",top:"-5%",left:"-5%"
		this.div.appendChild(img);
		document.body.appendChild(this.div);
		this.display();
	}
	
	this.display = function()
	{
		var size=Math.random()*300;
		var sd=(size+20)*0.5;
		$(this.div).animate({opacity:1,borderRadius:sd+"px",height:size+"px",width:size+"px",top:(y-sd)+"px",left:(x-sd)+"px"},1000,"swing");
	}
	
	this.remove = function()
	{
		$(this.div).stop().animate({opacity:0.1},500,"swing",function(){document.body.removeChild(self.div);});
	}
	
	this.init();
}

var mmouse;
window.onload=function()
{
	mmouse=new MMouse("localhost","8080");//"192.168.1.101" //69.51.206.226
	window.document.body.onmousemove=function(evt){mmouse.mouse_event(evt)};
	//window.document.body.onmousedown=function(evt){mmouse.mouse_event(evt)};
	//window.document.body.onmouseup=function(evt){mmouse.mouse_event(evt)};
	window.document.body.onclick=function(evt){mmouse.mouse_event(evt)};
}
