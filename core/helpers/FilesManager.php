<?php
/**
 * Contains useful methods to easily save and retreive data from text files
 *
 * @version    1.0
 * @author     William McMurray
 */

class FilesManager
{
	private $data_dir = "";
	private $base_dir = "";
	
	// PUBLIC
	//==========================================================
	/**
	 * Class constructor, pass a base dir if you want (see set_base_dir() method below)
	 */
	public function __construct($dir = null)
	{
		global $CONFIG;
		$this->data_dir = $CONFIG["data_path"];

		if(!is_null($dir))
		{
			$this->set_base_dir($dir);
		}
	}

	/**
	 * Optional, set a base path inside the data dir (if you are too lazy to specify it everytime)
	 */
	public function set_base_dir($dir)
	{
		$this->base_dir = trim($dir, "/") . "/";
	}

	/**
	 * Check if a file or directory exists
	 *
	 * @return bool
	 */
	public function file_exists($dir)
	{
		return file_exists($this->get_real_path($dir)) ? true : false;
	}

	/**
	 * Scan a directory and return it's content (files and directories)
	 *
	 * @return array
	 */
	public function scandir($dir, $recursive = true, $filesonly = false)
	{
		$dirpath = $this->get_real_path($dir);
		$files = scandir($dirpath);

		foreach($files as $k => $file)
		{
			if(!in_array($file, array(".", "..")))
			{
				$files[$k] = $dir . iconv("ISO-8859-1", "UTF-8//IGNORE", $file);
				if($recursive && is_dir($this->data_dir . $this->base_dir . $dir . $file))
				{
					$files[$k] .= "/";
					$files2 = $this->scandir($files[$k]);
					foreach($files2 as $file2)
					{
						$files[] = $file2;
					}
					
					if($filesonly)
					{
						unset($files[$k]);
					}
				}
			}
			else
			{
				unset($files[$k]);
			}
		}

		sort($files);
		return $files;
	}

	/**
	 * Creates a directory
	 *
	 * @return bool
	 */
	public function make_dir($dir)
	{
		$dirpath = $this->get_real_path($dir);
		return !file_exists($dirpath) ? mkdir($dirpath) : false;
	}

	/**
	 * Delete a directory and everything inside if $recursive is true
	 *
	 * @return bool
	 */
	public function delete_dir($dir, $recursive = true)
	{
		$dirpath = $this->get_real_path($dir);
		if($recursive)
		{
			if(is_dir($dirpath))
			{
				$objects = scandir($dirpath);
				foreach($objects as $object)
				{
					if($object != "." && $object != "..")
					{
						if(filetype($dirpath . "/" . $object) == "dir")
						{
							$this->delete_dir($dir . "/" . iconv("ISO-8859-1", "UTF-8//IGNORE", $object));
						}
						else
						{
							unlink($dirpath."/".$object);
						}
					}
				}
				reset($objects);
				return rmdir($dirpath);
			}
			else
			{
				debug("This path is not a directory : " . $dirpath);
			}
		}
		else
		{
			return unlink($dirpath);
		}

		return false;
	}

	/**
	 * General method to save data in file, this open a file with the specified pointer
	 *
	 * @return bool
	 */
	public function save_file($file, $text, $mode = "w")
	{
		$filepath = $this->get_real_path($file);
		$handle = fopen($filepath, !file_exists($filepath) ? "x" : $mode);

		if(is_writable($filepath))
		{
			$result = fwrite($handle, $text);
			fclose($handle);
			return $result === false ? false : true;
		}
		else
		{
			debug("Filepath is not writable.");
			fclose($handle);
			return false;
		}
	}

	/**
	 * Appends data in a file, it the file dosen't exists it'll be created
	 *
	 * @return bool
	 */
	public function append_in_file($file, $text)
	{
		return $this->save_file($file, $text, "a");
	}

	/**
	 * Appends a line in a file, if the file dosen't exists it'll be created
	 *
	 * @return bool
	 */
	public function append_line_in_file($file, $text)
	{
		$filepath = $this->get_real_path($file);
		return $this->append_in_file($file, (file_exists($filepath) ? "\r\n" : "") . $text);
	}

	/**
	 * Get the whole file content
	 *
	 * @return file content OR false if error occured
	 */
	public function read_file_content($file)
	{
		$filepath = $this->get_real_path($file);
		if(file_exists($filepath))
		{
			return file_get_contents($filepath);
		}
		else
		{
			debug("Can't open file.");
			return false;
		}
	}

	/**
	 * Get specified lines in a file
	 * The starting line can be specified, and the limit of returned lines too
	 * use a negative $start_line to read from the end of a file
	 * Example : read_file_lines("file.txt", -1); will return only the last line
	 * 
	 * @return array OR false if error occured
	 */
	public function read_file_lines($file, $start_line = 0, $limit = 0)
	{
		$filepath = $this->get_real_path($file);

		$handle = @fopen($filepath, 'r');
		if(file_exists($filepath) && $handle)
		{
			$lines = array();
			$lines_count = $start_line;
			$pos = 0;

			if($lines_count < 0)
			{
				$modifier = -1;
				$whence = SEEK_END;
			}
			else
			{
				$modifier = 1;
				$whence = SEEK_SET;
			}

			while($lines_count != 0 && !feof($handle))
			{
				$pos += $modifier;
				fseek($handle, $pos, $whence);
				$c = fgetc($handle);

				if($c == "\n")
				{
					$lines_count -= $modifier;
				}
			}

			if(feof($handle))
			{
				fseek($handle, $pos - $modifier, $whence);
			}
			
			$i = 0;
			while(!feof($handle))
			{
				$i++;
				if($limit === 0 || $i <= $limit)
				{
					$buffer = fgets($handle);
					$lines[] = $buffer;
				}
				else
				{
					break;
				}
			}

			fclose($handle);
			if($buffer == "" && count($lines) == 1)
			{
				return false;
			}
			else
			{
				return $lines;
			}
		}
		else
		{
			debug("Can't open file.");
		}
		return false;
	}

	/**
	 * Delete a file if it exists
	 *
	 * @return bool
	 */
	public function delete_file($file)
	{
		$filepath = $this->get_real_path($file);
		return file_exists($filepath) ? unlink($filepath) : false;
	}

	// PROTECTED
	//==========================================================
	/**
	 * Returns a correct encoded filepath in the data dir specified in config file
	 *
	 * @return string
	 */
	private function get_real_path($file)
	{
		return $this->data_dir . $this->base_dir . iconv("UTF-8", "ISO-8859-1//IGNORE", $file);
	}
	
	// PRIVATE
	//==========================================================
}
?>