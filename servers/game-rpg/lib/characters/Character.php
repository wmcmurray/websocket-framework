<?php
require_once(SERVER_ROOT . "lib/Entity.php");

class Character extends Entity
{
	public $loot_radius = 34;
    public $inventory_size = 12;

    protected $state;
    protected $state_sync;

    public function __construct($state = array())
    {
        parent::__construct();
        
        $this->state = $state;

        if($this->state["x"] && $this->state["y"])
        {
        	$this->set_pos($this->state["x"], $this->state["y"]);
        }
    }

    public function set_state($props = null, $value = null)
    {
		if(!is_array($props))
		{
			$props = array($props => $value);
		}

		foreach($props as $k => $v)
		{
			$this->state[$k] = $v;
		}

		$this->set_pos($this->state["x"], $this->state["y"]);
    }

    public function get_state($props = null)
    {
    	// all props
    	if(is_null($props))
    	{
    		return $this->state;
    	}
    	else
    	{
    		// single prop
    		if(!is_array($props))
    		{
    			if(isset($this->state[$props]))
    			{
    				return $this->state[$props];
    			}
    			else
    			{
    				return null;
    			}
    		}

    		// multiple props
    		$a = array();
    		foreach($props as $k => $v)
    		{
    			if(isset($this->state[$v]))
    			{
    				$a[$v] = $this->state[$v];
    			}
    		}

    		return $a;
    	}
    }

    public function get_unsync_state($forced = array())
    {
    	$a = array();
    	$not_synced = array("keys", "last_update");

    	foreach($this->state as $k => $v)
    	{
    		if(!in_array($k, $not_synced) && (!isset($this->state_sync[$k]) || $this->state_sync[$k] != $this->state[$k]) || in_array($k, $forced))
    		{
    			$this->state_sync[$k] = $this->state[$k];
    			$a[] = $k;
    		}
    	}

    	return $this->get_state($a);
    }

    public function grab($object = array())
    {
        if(count($this->state["inventory"]) < $this->inventory_size)
        {
            $this->state["inventory"][] = $object->name;
            return true;
        }
        return false;
    }
}
?>