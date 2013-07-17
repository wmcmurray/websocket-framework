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
        $this->recognized_keys = array("w", "a", "s", "d", "space", "enter", "e");

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
            $this->update_clients();
            $clients = $this->get_clients();

            foreach($clients as $client)
            {
                $this->save_client_state($client);
            }            
        }
    }

    protected function on_client_disconnect($client)
    {
        //$this->update_clients($client);
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
            $file = "game/players/" . $data . ".json";
            $player_data_exists = $this->fm->file_exists($file);

            if($player_data_exists)
            {
                $initial_state = json_decode($this->fm->read_file_content($file), true);
            }
            else
            {
                $initial_state = $this->get_initial_player_state();
            }
            
            $client->set($initial_state);
            $client->set("username", $data);
            $this->initial_sync_client($client);

            $clients = $this->get_clients($client);
            if($clients)
            {
                $this->send($client, "players_list", $this->list_clients(array("username", "skin", "x", "y", "speed", "direction"), $clients));
            }

            $this->send_to_others($client, "new_player", array("id" => $client->id, "username" => $client->get("username"), "props" => $client->get(array("skin", "x", "y", "speed", "direction"))));
        }
    }

    // executed when a player press or release a key
    protected function handle_keyevent($client, $data)
    {
        if(in_array($data->key, $this->recognized_keys))
        {
            if($data->key == "space" && $data->type == "keydown")
            {
                $this->send_to_others($client, "player_jump", array("id" => $client->id));
            }
            else
            {
                // calculate client position
                $this->update_client($client);
                
                // change client direction after key event
                $props = $client->get(array("keys", "direction"));
                $props["keys"][$data->key] = $data->type == "keydown" ? true : false;
                $props["direction"][0] = ($props["keys"]["a"] ? -1 : 0) + ($props["keys"]["d"] ? 1 : 0);
                $props["direction"][1] = ($props["keys"]["w"] ? -1 : 0) + ($props["keys"]["s"] ? 1 : 0);
                $client->set($props);

                // syncronize client with server
                $this->sync_client($client);

                // broadcast key event to other connected clients
                $this->send_to_others($client, "player_update", array("id" => $client->id, "props" => $client->get(array("x", "y", "speed", "direction"))));
            }
        }
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
        $client->set("x", $props["x"] + (($props["speed"] * $deltatime) * ($props["direction"][0] * $divider)));
        $client->set("y", $props["y"] + (($props["speed"] * $deltatime) * ($props["direction"][1] * $divider)));
        $client->set("last_update", $now);
    }

    // update many clients at once
    protected function update_clients($clients = null)
    {
        if(is_null($clients))
        {
            $clients = $this->get_clients();
        }

        foreach($clients as $client)
        {
            $this->update_client($client);
        }
    }

    // syncronize a client estimated state with it's real state on the server
    protected function sync_client($client, $send_to = null)
    {
        $this->send((is_null($send_to) ? $client : $send_to), "sync", $client->get(array("x", "y", "speed", "direction")));
    }

    // syncronize a client estimated state with it's real state on the server
    protected function initial_sync_client($client, $send_to = null)
    {
        $this->send((is_null($send_to) ? $client : $send_to), "sync", $client->get(array("skin", "x", "y", "speed", "direction")));
    }

    protected function save_client_state($client)
    {
        $username = $client->get("username");

        if($username)
        {
            output("Saving " . $client->get("username") . " state " . ($this->fm->save_file("game/players/" . $username . ".json", json_encode($client->get())) ? "sucessful" : "failed"));
        }
    }

    // returns a initial state for new players
    protected function get_initial_player_state()
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