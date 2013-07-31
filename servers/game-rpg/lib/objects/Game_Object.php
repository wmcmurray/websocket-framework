<?php
require_once(SERVER_ROOT . "lib/Entity.php");

class Game_Object extends Entity
{
    public $name = "";

    public function __construct($name = "Unknown", $x = 0, $y = 0)
    {
        parent::__construct($x, $y);
        
        $this->name = $name;
    }
}
?>