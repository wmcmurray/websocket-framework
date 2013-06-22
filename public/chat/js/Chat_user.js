//===========================================================
// ===== Chat_user() ======================================
//===========================================================

function Chat_user(prefs_cookie_name)
{
	var self=this;
	
	this.init = function()
	{
		this.prefs={};
		this.prefs.username="Anonymous";
		if(prefs_cookie_name)
		{
			this.cookie=prefs_cookie_name;
			this.get_prefs();
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
	
	this.save_prefs = function()
	{
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
	
	this.init();
}