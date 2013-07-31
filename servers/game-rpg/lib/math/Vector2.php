<?php
class Vector2
{
    public $x = 0;
    public $y = 0;

    public function __construct($x = 0, $y = 0)
    {
        $this->set($x, $y);
    }

    public function set($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function get()
    {
        return array("x" => $this->x, "y" => $this->y);
    }
}
?>