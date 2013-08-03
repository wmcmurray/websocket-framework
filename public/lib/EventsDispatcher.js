//===========================================================
// ===== EventsDispatcher() =================================
//===========================================================

function EventsDispatcher()
{
	this.init();
}

EventsDispatcher.prototype.init = function()
{
    this.events_listeners = new Array();
}

EventsDispatcher.prototype.on = function (evt, fnc)
{
    if(!this.events_listeners[evt])
    {
        this.events_listeners[evt] = new Array();
    }
    this.events_listeners[evt].push(fnc);
}

EventsDispatcher.prototype.trigger = function (evt, args)
{
    if(this.events_listeners[evt])
    {
        for(var i in this.events_listeners[evt])
        {
            this.events_listeners[evt][i](args ? args : "");
        }
    }
}