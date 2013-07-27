<?php
class Game_Object
{
    public $name = "";

    protected $x = 0;
    protected $y = 0;

    public function __construct($name = "Unknown", $x = 0, $y = 0)
    {
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
    }

    public function get_pos()
    {
        return array("x" => $this->x, "y" => $this->y);
    }
}
?>