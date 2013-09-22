<?php
require_once("../../core/index.php");
require_once("../../core/helpers/FilesManager.php");

require_once(SERVER_ROOT . "lib/characters/Character.php");
require_once(SERVER_ROOT . "lib/objects/Game_Object.php");

class RPG_SocketServer extends SocketServer
{
    protected $objects = array();

    // SERVER INIT
    //===========================================================================
    protected function init()
    {
        $this->recognized_keys = array("w", "a", "s", "d", "space", "enter", "shift", "e", "q", "t");
        $this->areas = array("grass", "sand");
        $this->possible_objects = array("knife", "shortsword", "broadsword", "steel broadsword", "health potion");
        $this->npcs_candy = false;

        // create dirs (if they don't exists) to save game players data
        $this->fm = new FilesManager();
        $this->fm->make_dir("game-rpg/");
        $this->fm->make_dir("game-rpg/players/");

        // create empty objects arrays for each areas
        foreach ($this->areas as $area)
        {
            $this->objects[$area] = array();
        }

        // create some NPCs at the begining
        // cats in grass world
        for($i = 1; $i <= 3; $i++)
        {
            $this->create_npc()->set_group("grass");
        }

        // a cool dude in grass world
        $this->create_npc("hero13")->set_group("grass")->character->set_state(array("username" => "Paladin NPC", "max_health" => 200, "health" => 200));

        // a cool dude in desert
        $this->create_npc("hero10")->set_group("sand")->character->set_state(array("username" => "Badass NPC", "max_health" => 200, "health" => 200));
    }


    // SERVER EVENTS
    //===========================================================================
    protected function on_server_tick($counter)
    {
        // update NPCs behaviors (each seconds)
        $this->update_npcs();

        // players state autosaving loop (each 5 minutes)
        if($counter%300 == 0)
        {
            output("AUTO-SAVING players state...");
            $clients = $this->get_clients();

            foreach($clients as $client)
            {
                $this->update_client($client);
                $this->save_player_state($client);
            }            
        }

        // regen clients health
        $clients = $this->get_clients();
        foreach($clients as $client)
        {
            if(isset($client->character))
            {
                $props = $client->character->get_state(array("health", "max_health"));
                if($props["health"] < $props["max_health"])
                {
                    $client->character->set_state(array("health" => $props["health"] + round($props["max_health"] / 50)));
                    $this->sync_client($client, true);
                }
            }
        } 
    }

    protected function on_client_disconnect($client)
    {
        // if the client disconnecting was the NPC attraction
        if($client == $this->npcs_candy)
        {
            $this->npcs_candy = false;
        }

        if(isset($client->character))
        {
            $this->update_client($client);
            $this->save_player_state($client);
            $this->send_to_others_in_group($client, "player_quit", $client->id);
        }
    }


    // RECEIVED DATA HANDLING
    //===========================================================================

    // executed when a new client enter in the game world
    protected function handle_player_join($client, $data)
    {
        if($data)
        {
            $username = strtolower($data);
            $initial_state = $this->get_player_state($username);

            if($initial_state)
            {
                // compare saved data with a new player state to add missing props if we updated
                // the game since this player saved his character
                $default_state = $this->create_initial_character_state();
                foreach($default_state as $k => $v)
                {
                    if(!isset($initial_state[$k]))
                    {
                        $initial_state[$k] = $v;
                    }
                }

                // TODO: delete props that dosen't exist now too
                //...

                // reset last update time and pressed keys to prevent calculation problems
                $initial_state["last_update"] = microtime(true);
                $initial_state["direction"] = array(0, 0);

                foreach($initial_state["keys"] as $k => $v)
                {
                    $initial_state["keys"][$k] = false;
                }
            }
            else
            {
                $initial_state = $this->create_initial_character_state("hero" . rand(1, 13));
                $initial_state["username"] = $username;
            }
            
            $client->character = new Character($initial_state);
            $client->set_group($initial_state["area"] ? $initial_state["area"] : "grass");
            $this->initial_sync_client($client);
            $this->send_new_player($client);
        }
    }

    // executed when a player press or release a key
    protected function handle_keyevent($client, $data)
    {
        if(in_array($data->key, $this->recognized_keys))
        {
            // USE/GRAB KEY
            if($data->key == "e" && $data->type == "keydown")
            {
                $area = $client->character->get_state("area");
                $nearest = false;
                $nearestKey;
                $nearestDistance;

                foreach($this->objects[$area] as $k => $object)
                {
                    $distance = $client->character->get_distance($object->get_pos());
                    $lootRadius = $client->character->loot_radius;

                    if($distance <= $lootRadius && (!$nearest || $nearestDistance >= $distance))
                    {
                        $nearest = $object;
                        $nearestKey = $k;
                        $nearestDistance = $distance;
                    }
                }

                if($nearest)
                {
                    if($client->character->grab($nearest))
                    {
                        $this->sync_client($client);
                        unset($this->objects[$area][$nearestKey]);
                        $this->send_objects_list($client);
                    }
                }
            }

            // JUMP KEY
            else if($data->key == "space" && $data->type == "keydown")
            {
                $this->send_to_others_in_group($client, "player_action", array("id" => $client->id, "action" => "jump"));
            }

            // MOVEMENT KEYS & OTHERS
            else
            {
                // calculate client position
                $this->update_client($client);
                
                // if shift key, change client movement speed
                if($data->key == "shift")
                {
                    $client->character->set_state(array("speed" => $data->type == "keydown" ? 300 : 200));
                }

                // if one of the movement keys, update player direction
                else if(in_array($data->key, array("w", "a", "s", "d")))
                {
                    $props = $client->character->get_state(array("keys", "direction"));
                    $props["keys"][$data->key] = $data->type == "keydown" ? true : false;
                    $props["direction"][0] = ($props["keys"]["a"] ? -1 : 0) + ($props["keys"]["d"] ? 1 : 0);
                    $props["direction"][1] = ($props["keys"]["w"] ? -1 : 0) + ($props["keys"]["s"] ? 1 : 0);
                    $client->character->set_state($props);
                }

                // syncronize client with server
                $this->sync_client($client, true);
            }
        }
    }

    // executed when a player press mouse button
    protected function handle_mouseevent($client, $data)
    {
        $click = new Vector2($data->x, $data->y);

        $sight_distance_min = 5;
        $sight_distance_max = 75;
        $field_of_view = 135;

        $this->update_client($client);
        $angle = $client->character->get_angle($click);

        $clients = $this->get_clients_from_group($client->get_group(), $client);
        
        // check if we hit an other player/npc
        foreach($clients as $other)
        {
            $this->update_client($other);

            if($client->character->sight($other->character->get_pos(), $angle, $field_of_view, $sight_distance_max, $sight_distance_min))
            {
                $health = $other->character->get_state("health") - 10;
                
                if($health < 0)
                {
                    $health = 0;
                }

                $other->character->set_state(array("health" => $health));
                $this->sync_client($other, true);

                if($health <= 0)
                {
                    $other->character->set_state(array("health" => $other->character->get_state("max_health")));
                    
                    if($other->is_npc())
                    {
                        $this->character_drop_loot($other);
                    }
                    else
                    {
                        // TODO: drop inventory?...
                    }
                    
                    $this->disconnect($other);
                }
            }
        }
    }

    protected function handle_teleport($client, $data)
    {
        $this->update_client($client);
        $client->character->set_state("x", $data->x);
        $client->character->set_state("y", $data->y);
        $client->character->set_state("last_update", microtime(true));
        $this->send($client, "teleport", array("x" => $data->x, "y" => $data->y));
        $this->send_to_others_in_group($client, "player_teleport", array("id" => $client->id, "props" => $client->character->get_state(array("x", "y"))));
    }

    protected function handle_talk($client, $data)
    {
        $this->send_to_others_in_group($client, "player_action", array("id" => $client->id, "action" => "talk", "msg" => $data));
    }

    protected function handle_create_npc($client, $data)
    {
        $npc = $this->create_npc($data ? $data : "npc1");
        $npc->set_group($client->get_group());
        $this->update_client($client);
        $npc->character->set_state($client->character->get_state(array("x", "y")));
        $this->send_new_player($npc);
    }

    protected function handle_kill_npcs($client, $data)
    {
        $npcs = $this->get_npcs();
        foreach($npcs as $npc)
        {
            if($npc->in_group($client->get_group()))
            {
                $this->disconnect($npc);
            }
        }
    }

    protected function handle_attract_npcs($client, $data)
    {
        $this->npcs_candy = $data ? $client : false;
        $this->update_npcs(true);
    }

    protected function handle_change_area($client, $data)
    {
        if(in_array($data, $this->areas))
        {
            $this->send_to_others_in_group($client, "player_quit", $client->id);
            $client->set_group($data)->character->set_state(array("area" => $data));
            $this->send_new_player($client);
            $this->send_objects_list($client);
            $this->send_players_list($client);
        }
    }
    


    // OTHER METHODS
    //===========================================================================

    // calculate current player state
    protected function update_client($client)
    {
        if(isset($client->character))
        {
            $props = $client->character->get_state(array("last_update", "x", "y", "speed", "direction"));
            $now = microtime(true);
            $deltatime = $now - $props["last_update"];
            $divider = $props["direction"][0] && $props["direction"][1] ? 0.75 : 1;
            $client->character->set_state("x", round($props["x"] + (($props["speed"] * $deltatime) * ($props["direction"][0] * $divider))));
            $client->character->set_state("y", round($props["y"] + (($props["speed"] * $deltatime) * ($props["direction"][1] * $divider))));
            $client->character->set_state("last_update", $now);
        }
    }

    // first syncronisation of a client
    protected function initial_sync_client($client)
    {
        $this->send($client, "initial_sync", $client->character->get_unsync_state());
    }

    // syncronize a client estimated state with it's real state on the server
    protected function sync_client($client, $broadcast = false)
    {
        $state = $client->character->get_unsync_state();

        if($state)
        {
            $this->send($client, "sync", $state);

            if($broadcast)
            {
                $this->send_to_group($client->get_group(), "player_update", array("id" => $client->id, "props" => $state));
            }
        }
        
    }

    // send a list of all objects in this area to a client
    protected function send_objects_list($client)
    {
        $objects = array();
        $area = $client->character->get_state("area");

        foreach ($this->objects[$area] as $k => $v)
        {
            $objects[] = array("name" => $v->name, "pos" => $v->get_pos());
        }

        $this->send($client, "objects_list", $objects);
    }

    // send a list of all players in this area to a client
    protected function send_players_list($client)
    {
        $clients = $this->get_clients_from_group($client->get_group(), $client);
        $a = array();

        foreach($clients as $k => $v)
        {
            $a[$v->id] = $v->character->get_state(array("username", "skin", "x", "y", "speed", "health", "max_health", "direction"));
        }

        $this->send($client, "players_list", $a);
    }

    protected function send_new_player($client)
    {
        $this->send_to_others_in_group($client, "new_player", array("id" => $client->id, "props" => $client->character->get_state(array("username", "skin", "x", "y", "speed", "health", "max_health", "direction"))));
    }

    protected function send_new_object($area, $object)
    {
        $this->send_to_group($area, "new_object", array("name" => $object->name, "pos" => $object->get_pos()));
    }

    // save client data in a text file
    protected function save_player_state($client)
    {
        $username = $client->character->get_state("username");

        // save state of human clients only (no NPCs)
        if($username && !$client->is_npc())
        {
            output("Saving " . $username . " state " . ($this->fm->save_file("game-rpg/players/" . $username . ".json", json_encode($client->character->get_state())) ? "sucessful" : "failed"));
        }
    }

    // check if a username has data saved on server, if yes, return it
    protected function get_player_state($username)
    {
        $file = "game-rpg/players/" . $username . ".json";
        return $this->fm->file_exists($file) ? json_decode($this->fm->read_file_content($file), true) : false;
    }


    protected function create_object($name = "knife", $area = "grass", $x = 0, $y = 0)
    {
        $object = new Game_Object($name, $x, $y);
        $this->objects[$area][] = $object;
        
        return $object ;
    }

    protected function character_drop_loot($client)
    {
        // 1 chance out of 2 of droping and item
        if(rand(1,2) == 1)
        {
            $area = $client->get_group();
            $state = $client->character->get_state(array("x", "y"));
            $object = $this->create_object($this->possible_objects[rand(0, count($this->possible_objects) -1)], $area, $state["x"], $state["y"]);
            $this->send_new_object($area, $object);
        }
    }

    // returns a initial state for a new players
    protected function create_initial_character_state($skin = "hero1")
    {
        $keystates = array();

        foreach($this->recognized_keys as $v)
        {
            $keystates[$v] = false;
        }

        return 
        array(
            "skin"          => $skin,
            "x"             => 1000,
            "y"             => 1000,
            "speed"         => 200,
            "health"        => 100,
            "max_health"    => 100,
            "exp"           => 0,
            "keys"          => $keystates,
            "direction"     => array(0,0),
            "inventory"     => array(),
            "last_update"   => microtime(true)
        );
    }

    // creates an NPC
    protected function create_npc($skin = "npc1")
    {
        $initial_npc_state = $this->create_initial_character_state($skin);
        $initial_npc_state["speed"] = rand(15, 50);
        $initial_npc_state["username"] = "Brainless NPC";
        $initial_npc_state["x"] = 1000 + rand(-100, 100);
        $initial_npc_state["y"] = 1000 + rand(-100, 100);

        $npc = $this->create_client();
        $npc->character = new Character($initial_npc_state);

        if($skin == "npc1")
        {
            $npc->character->set_state(array("max_health" => 30, "health" => 30));
        }

        return $npc;
    }

    // update NPCs behaviors
    protected function update_npcs($all = false)
    {
        $npcs = $this->get_npcs();
        foreach($npcs as $npc)
        {
            $this->update_client($npc);
            
            // follow the player "candy"
            if($this->npcs_candy && $this->npcs_candy->get_group() == $npc->get_group())
            {
                $props = $npc->character->get_state(array("x", "y"));
                $candy = $this->npcs_candy->character->get_state(array("x", "y", "username"));

                $directionX = ($props["x"] > $candy["x"] ? -1 : 1);
                $directionY = ($props["y"] > $candy["y"] ? -1 : 1);
                $npc->character->set_state(array("direction" => array($directionX, $directionY), "speed" => rand(100, 180)));
                $this->sync_client($npc, true);
                
                // they may scream
                if(rand(1,4) == 1)
                {
                    $msgs = array("Come here ".$candy["username"]." !!", "I'll get ya ".$candy["username"]." !!", "Get himm !!!");
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "talk", "msg" => $msgs[rand(0, count($msgs) -1)]));
                }

                // and they may jump
                if(rand(1,2) == 1)
                {
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "jump"));
                }
            }

            // ELSE there is 1 chance out of 3 of changing direction randomly
            else if(rand(1,3) == 1 || $all)
            {
                // random direction OR forced direction if NPC go too far from map center
                $props = $npc->character->get_state(array("x", "y", "skin"));
                $directionX = ($props["x"] >= 1500 ? -1 : ($props["x"] <= 500 ? 1 : rand(-1, 1) ) );
                $directionY = ($props["y"] >= 1500 ? -1 : ($props["y"] <= 500 ? 1 : rand(-1, 1) ) );
                $npc->character->set_state(array("direction" => array($directionX, $directionY), "speed" => rand(15, 75)));
                $this->sync_client($npc, true);
                
                // 1 chance out of 6 that it will talk
                if(rand(1,6) == 1)
                {
                    if($props["skin"] == "npc1")
                    {
                        $msgs = array("Miaow", "Where is that damn mouse...", "I love being dumb.", "=^.^=", "I'm a cat", "-_-", "Pet me, please", "Websockets are awesome");
                    }
                    else
                    {
                        $msgs = array("Websockets are awesome", "Humm...", "Arggggh !", "This game got nice graphics.", "Walking is so amazing");
                    }
                    
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "talk", "msg" => $msgs[rand(0, count($msgs) -1)]));
                }

                // else... 1 chance out of 8 that it will jump (and talk)
                else if(rand(1,8) == 1)
                {
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "jump"));
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "talk", "msg" => "Bounce !"));
                }
            }
        }
    }
}
?>