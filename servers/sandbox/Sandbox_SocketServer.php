<?php
require_once("../core/index.php");
require_once("../core/helpers/FilesManager.php");

class Sandbox_SocketServer extends SocketServer
{
    protected function init()
    {
        // files manager instance
        $this->fm = new FilesManager("sandbox/");
    }

    // executed when server receive data
    protected function handle_msg($client, $data)
    {
        // display data in console
        output("Normal data received : " . $data);

        // broadcast data to every connected clients
        $this->send_to_all("msg", $data);
    }

    // executed when server receive raw data
    protected function raw_handle($client, $data)
    {
        output("RAW data received : " . $data);
        $this->send($client, $data);
        $this->send_to_all($data);
        $this->send_to_others($client, $data);
        $this->send_to_others_in_group($client, $data);
        $this->send_to_group($client->get_group(), $data);
    }

    // custom system commands handling
    protected function sys_handle_destroy($client, $data)
    {
        output("SERVER DESTROYED !!!!!");
        $this->send($client, "You destroyed the server bitch !");
    }

    protected function sys_handle_save_file($client, $data)
    {
        output("File saving...");
        $result = $this->fm->save_file("téÀst.txt", $data);
        $this->send($client, "msg", "Your file " . ($result ? "as been saved !" : "haven't been saved due to an error."));
    }

    protected function sys_handle_append_file($client, $data)
    {
        $result = $this->fm->append_line_in_file("téÀst.txt", $data);
        $this->send($client, "msg", "Line" . ($result ? " " : " NOT ") . "appended.");
    }

    protected function sys_handle_delete_file($client, $data)
    {
        $result = $this->fm->delete_file($data);
        $this->send($client, "msg", "File" . ($result ? " " : " NOT ") . "deleted");
    }

    protected function sys_handle_delete_dir($client, $data)
    {
        $result = $this->fm->delete_dir($data);
        $this->send($client, "msg", "Dir" . ($result ? " " : " NOT ") . "deleted");
    }

    protected function sys_handle_make_dir($client, $data)
    {
        $result = $this->fm->make_dir($data);
        $this->send($client, "msg", "Dir" . ($result ? " " : " NOT ") . "created");
    }

    protected function sys_handle_path_exists($client, $data)
    {
        $result = $this->fm->path_exists($data);
        $this->send($client, "msg", "Path" . ($result ? " " : " DOSEN'T ") . "exists");
    }

    protected function sys_handle_read_file_lines($client, $data)
    {
        $result = $this->fm->read_file_lines("téÀst.txt", intval($data));
        $this->send($client, "msg", $result);
        //print_r($result);
    }

    protected function sys_handle_read_file_content($client, $data)
    {
        $result = $this->fm->read_file_content("téÀst.txt");
        $this->send($client, "msg", $result);
        //print_r($result);
    }

    protected function sys_handle_scandir($client, $data)
    {
        $result = $this->fm->scandir($data);
        $this->send($client, "msg", $result);
        //print_r($result);
    }
}
?>