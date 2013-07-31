<?php
require_once(SERVER_ROOT . "lib/math/Vector2.php");

class Entity
{
    protected $pos;

    public function __construct($x = 0, $y = 0)
    {
        $this->pos = new Vector2($x, $y);
    }

    public function set_pos($x = 0, $y = 0)
    {
        $this->pos->set($x, $y);
    }

    public function get_pos()
    {
        return $this->pos->get();
    }

    // returns the distance between this entity vector and the specified vector
    public function get_distance($vec2)
    {
        $vec1 = $this->get_pos();

        if($vec2 instanceof Vector2)
        {
            $vec2 = $vec2->get();
        }

        $deltaX = $vec2["x"] - $vec1["x"];
        $deltaY = $vec2["y"] - $vec1["y"];
        return sqrt(pow($deltaX, 2) + pow($deltaY, 2));
    }

    // returns the angle between this entity position vector and the specified vector
    public function get_angle($vec2)
    {
        $vec1 = $this->get_pos();

        if($vec2 instanceof Vector2)
        {
            $vec2 = $vec2->get();
        }

        $deltaX = $vec2["x"] - $vec1["x"];
        $deltaY = $vec2["y"] - $vec1["y"];
        $angle = atan2($deltaY, $deltaX) * 180 / M_PI;
        return $angle < 0 ? 360 + $angle : $angle;
    }

    // return true or false if the specified vector is in line of sight within the specified field of view and sight distance
    public function sight($vec2, $sight_angle = 0, $field_of_view = 45, $sight_distance_max = null, $sight_distance_min = null)
    {
        $a = $this->get_angle($vec2);
        $f = $field_of_view / 2;
        $difference = $a - $sight_angle;
        $good_distance = true;

        if(!is_null($sight_distance_max) || !is_null($sight_distance_min))
        {
            $d = $this->get_distance($vec2);
            $good_distance = (is_null($sight_distance_max) || $d <= $sight_distance_max) && (is_null($sight_distance_min) || $d >= $sight_distance_min) ? true : false;
        }

        return ($difference < $f && $difference > -$f) && $good_distance ? true : false;
    }
}
?>