<?php
require_once("../core/index.php"); // NOTE: make this path absolute if script is executed outside "/executables" dir

// import the filesManager helper to save players data in text files
require_once("../core/helpers/FilesManager.php");

class Game_SocketServer extends SocketServer
{
    //protected $objects = array();

    // SERVER INIT
    //===========================================================================
    protected function init()
    {
        $this->set_server_name("Simple Game Server");
        $this->recognized_keys = array("w", "a", "s", "d", "space", "enter", "shift", "e", "q", "t");
        $this->npcs_candy = false;

        // create dirs (if they don't exists) to save game players data
        $this->fm = new FilesManager();
        $this->fm->make_dir("game/");
        $this->fm->make_dir("game/players/");

        // create some NPCs at the begining
        // cats in grass world
        for($i = 1; $i <= 3; $i++)
        {
            $this->create_npc()->set_group("grass");
        }

        // a cool dude in grass world
        $this->create_npc("hero13")->set(array("username" => "Paladin NPC"))->set_group("grass");

        // a cool dude in desert
        $this->create_npc("hero10")->set(array("username" => "Badass NPC"))->set_group("sand");
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
                $this->save_client_state($client);
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

        $this->update_client($client);
        $this->save_client_state($client);
        $this->send_to_others_in_group($client, "player_quit", $client->id);
    }


    // RECEIVED DATA HANDLING
    //===========================================================================

    // executed when a new client enter in the game world
    protected function handle_player_join($client, $data)
    {
        if($data)
        {
            $username = strtolower($data);
            $client->set("username", $username);
            $initial_state = $this->get_client_state($client);

            if($initial_state)
            {
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
            }
            
            $client->set($initial_state);
            $client->set_group($client->get("area") ? $client->get("area") : "grass");
            $this->sync_client($client, true);
            $this->send_new_player($client);
        }
    }

    // executed when a player press or release a key
    protected function handle_keyevent($client, $data)
    {
        if(in_array($data->key, $this->recognized_keys))
        {
            if($data->key == "space" && $data->type == "keydown")
            {
                $this->send_to_others_in_group($client, "player_action", array("id" => $client->id, "action" => "jump"));
            }
            else
            {
                // calculate client position
                $this->update_client($client);
                
                // if shift key, change client movement speed
                if($data->key == "shift")
                {
                    $client->set(array("speed" => $data->type == "keydown" ? 300 : 200));
                }

                // if one of the movement keys, update player direction
                else if(in_array($data->key, array("w", "a", "s", "d")))
                {
                    $props = $client->get(array("keys", "direction"));
                    $props["keys"][$data->key] = $data->type == "keydown" ? true : false;
                    $props["direction"][0] = ($props["keys"]["a"] ? -1 : 0) + ($props["keys"]["d"] ? 1 : 0);
                    $props["direction"][1] = ($props["keys"]["w"] ? -1 : 0) + ($props["keys"]["s"] ? 1 : 0);
                    $client->set($props);
                }

                // syncronize client with server
                $this->sync_client($client);

                // broadcast key event result to other connected clients
                $this->send_to_others_in_group($client, "player_update", array("id" => $client->id, "props" => $client->get(array("x", "y", "speed", "direction"))));
            }
        }
    }

    protected function handle_teleport($client, $data)
    {
        $this->update_client($client);
        $client->set("x", $data->x);
        $client->set("y", $data->y);
        $client->set("last_update", microtime(true));
        $this->send($client, "teleport", array("x" => $data->x, "y" => $data->y));
        $this->send_to_others_in_group($client, "player_teleport", array("id" => $client->id, "props" => $client->get(array("x", "y"))));
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
        $npc->set($client->get(array("x", "y")));
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
        $this->send_to_others_in_group($client, "player_quit", $client->id);
        $client->set_group($data)->set(array("area" => $data));
        $this->send_new_player($client);
        $this->send_players_list($client);
    }
    


    // OTHER METHODS
    //===========================================================================

    // calculate current player state
    protected function update_client($client)
    {
        $props = $client->get(array("last_update", "x", "y", "speed", "direction"));
        $now = microtime(true);
        $deltatime = $now - $props["last_update"];
        $divider = $props["direction"][0] && $props["direction"][1] ? 0.75 : 1;
        $client->set("x", round($props["x"] + (($props["speed"] * $deltatime) * ($props["direction"][0] * $divider))));
        $client->set("y", round($props["y"] + (($props["speed"] * $deltatime) * ($props["direction"][1] * $divider))));
        $client->set("last_update", $now);
    }

    // syncronize a client estimated state with it's real state on the server (if it's the first sync, we send more data)
    protected function sync_client($client, $initial_sync = false)
    {
        $props = $client->get(array("x", "y", "speed", "direction"));

        if($initial_sync)
        {
            $initial_sync_props = array(
                "props"         => $props,
                "skin"          => $client->get("skin"),
                "area"          => $client->get("area"),
            );

            $this->send($client, "sync", array("initial_sync" => $initial_sync_props));
            $this->send_players_list($client);
        }
        else
        {
            $this->send($client, "sync", $props);
        }
    }

    // send a list of all players in this area to a client
    protected function send_players_list($client)
    {
        $this->send($client, "players_list", $this->list_clients(array("username", "skin", "x", "y", "speed", "direction"), $this->get_clients_from_group($client->get_group(), $client)));
    }

    protected function send_new_player($client)
    {
        $this->send_to_others_in_group($client, "new_player", array("id" => $client->id, "props" => $client->get(array("username", "skin", "x", "y", "speed", "direction"))));
    }

    // save client data in a text file
    protected function save_client_state($client)
    {
        $username = $client->get("username");

        // save state of human clients only (no NPCs)
        if($username && !$client->is_npc())
        {
            output("Saving " . $client->get("username") . " state " . ($this->fm->save_file("game/players/" . $username . ".json", json_encode($client->get())) ? "sucessful" : "failed"));
        }
    }

    // check if a username has data saved on server, if yes, return it
    protected function get_client_state($client)
    {
        $file = "game/players/" . $client->get("username") . ".json";
        return $this->fm->file_exists($file) ? json_decode($this->fm->read_file_content($file), true) : false;
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
            "keys"          => $keystates,
            "direction"     => array(0,0),
            //"inventory"     => array("shortsword"),
            "last_update"   => microtime(true)
        );
    }

    // creates an NPC
    protected function create_npc($skin = "npc1")
    {
        $initial_npc_state = $this->create_initial_character_state($skin);
        $initial_npc_state["speed"] = rand(15, 50);

        $npc = $this->create_client();
        $npc->set($initial_npc_state);
        $npc->set(array("username" => "Brainless NPC", "x" => 1000 + rand(-100, 100), "y" => 1000 + rand(-100, 100)));

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
                $props = $npc->get(array("x", "y"));
                $candy = $this->npcs_candy->get(array("x", "y", "username"));

                $directionX = ($props["x"] > $candy["x"] ? -1 : 1);
                $directionY = ($props["y"] > $candy["y"] ? -1 : 1);
                $npc->set(array("direction" => array($directionX, $directionY), "speed" => rand(100, 180)));

                $this->send_to_others_in_group($npc, "player_update", array("id" => $npc->id, "props" => $npc->get(array("x", "y", "speed", "direction"))));
                
                // they may scream
                if(rand(0,4) == 1)
                {
                    $msgs = array("Come here ".$candy["username"]." !!", "I'll get ya ".$candy["username"]." !!", "Get himm !!!");
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "talk", "msg" => $msgs[rand(0, count($msgs) -1)]));
                }

                // and they may jump
                if(rand(0,2) == 1)
                {
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "jump"));
                }
            }

            // ELSE there is 1 chance out of 3 of changing direction randomly
            else if(rand(0,3) == 1 || $all)
            {
                // random direction OR forced direction if NPC go too far from map center
                $props = $npc->get(array("x", "y", "skin"));
                $directionX = ($props["x"] >= 1500 ? -1 : ($props["x"] <= 500 ? 1 : rand(-1, 1) ) );
                $directionY = ($props["y"] >= 1500 ? -1 : ($props["y"] <= 500 ? 1 : rand(-1, 1) ) );
                $npc->set(array("direction" => array($directionX, $directionY), "speed" => rand(15, 75)));

                $this->send_to_others_in_group($npc, "player_update", array("id" => $npc->id, "props" => $npc->get(array("x", "y", "speed", "direction"))));
                
                // 1 chance out of five that it will talk
                if(rand(0,5) == 1)
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

                // else... 1 chance out of five that it will jump (and talk)
                else if(rand(0,5) == 1)
                {
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "jump"));
                    $this->send_to_others_in_group($npc, "player_action", array("id" => $npc->id, "action" => "talk", "msg" => "Bounce !"));
                }
            }
        }
    }
}

$server = new Game_SocketServer($CONFIG["default_ip"], 8083);
$server->run();
?>