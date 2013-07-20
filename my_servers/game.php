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
        $this->set_tick_interval(10);
        $this->recognized_keys = array("w", "a", "s", "d", "space", "enter", "shift", "e", "q", "t");

        // create dirs (if they don't exists) to save game players data
        $this->fm = new FilesManager();
        $this->fm->make_dir("game/");
        $this->fm->make_dir("game/players/");
    }


    // SERVER EVENTS
    //===========================================================================
    protected function on_server_tick($counter)
    {
        //$this->update_npcs();
        
        if($counter%30 == 0)
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
        $this->update_client($client);
        $this->save_client_state($client);
        $this->send_to_others($client, "player_quit", $client->id);
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
                $initial_state = $this->create_initial_player_state();
            }
            
            $client->set($initial_state);
            $this->sync_client($client, true);
            $this->send_to_others($client, "new_player", array("id" => $client->id, "props" => $client->get(array("username", "skin", "x", "y", "speed", "direction"))));
        }
    }

    // executed when a player press or release a key
    protected function handle_keyevent($client, $data)
    {
        if(in_array($data->key, $this->recognized_keys))
        {
            if($data->key == "space" && $data->type == "keydown")
            {
                $this->send_to_others($client, "player_action", array("id" => $client->id, "action" => "jump"));
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
                $this->send_to_others($client, "player_update", array("id" => $client->id, "props" => $client->get(array("x", "y", "speed", "direction"))));
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
        $this->send_to_others($client, "player_teleport", array("id" => $client->id, "props" => $client->get(array("x", "y"))));
    }

    protected function handle_talk($client, $data)
    {
        $this->send_to_others($client, "player_action", array("id" => $client->id, "action" => "talk", "msg" => $data));
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
                "players_list"  => $this->list_clients(array("username", "skin", "x", "y", "speed", "direction"), $this->get_clients($client))
            );

            $this->send($client, "sync", array("initial_sync" => $initial_sync_props));
        }
        else
        {
            $this->send($client, "sync", $props);
        }
    }

    // save client data in a text file
    protected function save_client_state($client)
    {
        $username = $client->get("username");

        if($username)
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
    protected function create_initial_player_state()
    {
        $keystates = array();

        foreach($this->recognized_keys as $v)
        {
            $keystates[$v] = false;
        }

        return 
        array(
            "skin"          => "hero" . rand(1, 13),
            "x"             => 100,
            "y"             => 100,
            "speed"         => 200,
            "keys"          => $keystates,
            "direction"     => array(0,0),
            //"inventory"     => array("shortsword"),
            "last_update"   => microtime(true)
        );
    }
}

$server = new Game_SocketServer("127.0.0.1", 8083);
$server->run();
?>