<?php

class FileHandler
{
	private $config = [];
	private $re_partial = '/(?:\.part(?:-Frag\d+)?|\.ytdl|\.uncut\.mp4)$/m';
    private $db;

	public function __construct($filename = null)
	{
		$this->config = require dirname(__DIR__).'/config/config.php';
		if (empty($filename)) {
			$this->db = null;
		}
		else {
			$this->db = new SQLite3($filename);
		}
	}

    public function __destruct() {
		if ($this->db !== null) {
			$this->db->close();
		}
    }

	public function listFiles()
	{
		$files = [];

		if(!$this->outuput_folder_exists())
			return;

		$folder = $this->get_downloads_folder().'/';

		foreach(glob($folder.'*.*', GLOB_BRACE) as $file)
		{
			$content = [];
			$content["name"] = str_replace($folder, "", $file);

			if (preg_match($this->re_partial, $content["name"]) !== 0) {
				continue;
			}

			$content["size"] = $this->to_human_filesize(filesize($file));

			if (preg_match('/^[^\\.]+/', $content['name'], $match)) {
				$content['id'] = $match[0];

				if ($this->db) {
					$content["info"] = $this->getById($content['id']);
				}
			}

			if (empty($content['id'])) {
				$content['id'] = $content['name'];
			}

			$files[$content['id']] = $content;
			
		}

		return $files;
	}

	public function listParts()
	{
		$files = [];

		if(!$this->outuput_folder_exists())
			return;

		$folder = $this->get_downloads_folder().'/';

		foreach(glob($folder.'*.*', GLOB_BRACE) as $file)
		{
			$content = [];
			$content["name"] = str_replace($folder, "", $file);
			$content["size"] = $this->to_human_filesize(filesize($file));
			
			if (preg_match($this->re_partial, $content["name"]) !== 0) {
				$files[] = $content;
			}
			
		}

		return $files;
	}
	
	public function is_log_enabled()
	{
		return !!($this->config["log"]);
	}
	
	public function countLogs()
	{
		if(!$this->config["log"])
			return;

		if(!$this->logs_folder_exists())
			return;

		$folder = $this->get_logs_folder().'/';
		return count(glob($folder.'*.txt', GLOB_BRACE));
	}

	public function list_deferred() {
		if(!$this->config["log"])
			return [];

		if(!$this->logs_folder_exists())
			return [];

		$folder = $this->get_logs_folder().'/';

		$files = [];

		foreach(glob($folder.'*.txt.deferred', GLOB_BRACE) as $file)
		{
			$content = [];
			$content['log_filename'] = preg_replace('/\.deferred$/', '', $file);
			$content["name"] = str_replace($folder, "", $file);

			if (($fh = fopen($file, 'r')) === false) {
				continue;
			}
		
			while (!feof($fh)) {
				$line = fgets($fh);
		
				if (preg_match('/^Command: (.*)$/', $line, $match)) {
					$content['cmd'] = $match[1];
					break;
				}
			}
		
			fclose($fh);

			$files[$file] = $content;
		}

		return $files;
	}

	public function listLogs()
	{
		$files = [];
		
		if(!$this->config["log"])
			return;

		if(!$this->logs_folder_exists())
			return;

		$folder = $this->get_logs_folder().'/';

		foreach(glob($folder.'*.txt', GLOB_BRACE) as $file)
		{
			$content = [];
			$content["name"] = str_replace($folder, "", $file);
			$content["size"] = $this->to_human_filesize(filesize($file));

			$content["100"] = False;

			try {
				$lines = explode("\r", file_get_contents($file));

				$first_line = 0;

				for($line = 0; $line < count($lines); $line++)
				{
					if (preg_match('/\(frag \d+\/\d+\)$/', rtrim($lines[$line])) === 1) {
						$first_line = $line;
						$content["100"] = False;
					}

					if (strpos($lines[$line], ' 100% of ') > 0) {
						$content["100"] = True;
					}
				}

				if ($first_line !== 0) {
					$lines = array_slice($lines, $first_line);
				}

				$content["lastline"] = array_slice($lines, -1)[0];
			} catch (Exception $e) {
				$content["lastline"] = '';
			}	
			try {
				$handle = fopen($file, 'r');
				fseek($handle, filesize($file) - 1);
				$lastc = fgets($handle);
				fclose($handle);
				$content["ended"] = ($lastc === "\n");
			} catch (Exception $e) {
				$content["ended"] = False;
			}


			$files[] = $content;
		}

		return $files;
	}

	public function delete($id)
	{
		$folder = $this->get_downloads_folder().'/';

		foreach(glob($folder.'*.*', GLOB_BRACE) as $file)
		{
			if(sha1(str_replace($folder, "", $file)) == $id)
			{
				unlink($file);
			}
		}
	}

	public function deleteLog($id)
	{
		$folder = $this->get_logs_folder().'/';

		foreach(glob($folder.'*.txt', GLOB_BRACE) as $file)
		{
			if(sha1(str_replace($folder, "", $file)) == $id)
			{
				unlink($file);
			}
		}
	}

	private function outuput_folder_exists()
	{
		if(!is_dir($this->get_downloads_folder()))
		{
			//Folder doesn't exist
			if(!mkdir($this->get_downloads_folder(),0777))
			{
				return false; //No folder and creation failed
			}
		}
		
		return true;
	}

	public function to_human_filesize($bytes, $decimals = 1)
	{
		$sz = 'BKMGTP';
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
	}

	public function free_space()
	{
		return $this->to_human_filesize(disk_free_space(realpath($this->get_downloads_folder())));
	}

	public function used_space()
	{
		$path = realpath($this->get_downloads_folder());
		$bytestotal = 0;
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
			$bytestotal += $object->getSize();
		}
		return $this->to_human_filesize($bytestotal);
	}

	public function get_downloads_folder()
	{
		$path =  $this->config["outputFolder"];
		if(strpos($path , "/") !== 0)
		{
				$path = dirname(__DIR__).'/' . $path;
		}
		return $path;
	}

	public function get_logs_folder()
	{
		$path =  $this->config["logFolder"];
		if(strpos($path , "/") !== 0)
		{
				$path = dirname(__DIR__).'/' . $path;
		}
		return $path;
	}

	public function get_relative_downloads_folder()
	{
		$path =  $this->config["outputFolder"];
		if(strpos($path , "/") !== 0)
		{
				return $this->config["outputFolder"];
		}
		return false;
	}

	public function get_relative_log_folder()
	{
		$path =  $this->config["logFolder"];
		if(strpos($path , "/") !== 0)
		{
				return $this->config["logFolder"];;
		}
		return false;
	}

	private function logs_folder_exists()
	{
		if(!is_dir($this->get_logs_folder()))
		{
			//Folder doesn't exist
			if(!mkdir($this->get_logs_folder(),0777))
			{
				return false; //No folder and creation failed
			}
		}
		
		return true;
	}

	public function info($url)
	{
		$cmd = $this->config["bin"]." -J ";

		$cmd .= " ".escapeshellarg($url);

		if (self::is_python_installed() == 0)
		{
			$cmd .= " | python -m json.tool";
		}

		$soutput = shell_exec($cmd);

		if (!$soutput)
		{
			$GLOBALS['_ERRORS'] = "No video found";
		}

		$info = json_decode($soutput, true);

		if ($this->db) {
			$files = $this->listFiles();

			$info['db_info'] = $this->getByUrl($url);
			$info['file'] = $files[$info['db_info']['id']];
		}

		return $info;

	}

	private static function is_python_installed()
	{
		exec("which python", $out, $r);
		return $r;
	}

	public function isIdPresent($id) {
        $stmt = $this->db->prepare("SELECT id FROM urls WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC) !== false;
    }

    public function addURL($id, $url, $details_json) {
        if ($this->isIdPresent($id)) {
            return false;
        }

        $details_array = json_decode($details_json, true);
        $target = $details_array['target'] ?? null;

        $stmt = $this->db->prepare("INSERT INTO urls (id, username, url, details_json, target) VALUES (:id, :username, :url, :details_json, :target)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':username', $_SESSION["username"] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':details_json', $details_json, SQLITE3_TEXT);
        $stmt->bindValue(':target', $target, SQLITE3_TEXT);

        if (!$stmt->execute()) {
            throw new Exception("Error: Could not insert the new row with ID '$id', URL '$url' for target '$target'. SQLite error: " . $this->db->lastErrorMsg());
        }

        return true;
    }

    public function updateLastExport($id) {
        $current_ctime = time();
        $query = $this->db->prepare("UPDATE urls SET last_export = :last_export WHERE id = :id");
        $query->bindValue(':last_export', $current_ctime, SQLITE3_INTEGER);
        $query->bindValue(':id', $id, SQLITE3_TEXT);

        return $query->execute() !== false;
    }

    public function removeMissingIds($ids) {
        // Convert the array of IDs into a comma-separated string enclosed in parentheses
        $ids_string = '(' . implode(',', array_map(function($id) { return "'" . SQLite3::escapeString($id) . "'"; }, $ids)) . ')';

        // Prepare the SQL statement to delete rows with IDs not in the provided array
        $stmt = $this->db->prepare("DELETE FROM urls WHERE id NOT IN {$ids_string}");

        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Error: Could not remove rows with IDs missing from the provided array.");
        }
    }

    public function getAllIds() {
        $ids = [];
        $result = $this->db->query("SELECT id FROM urls");

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }

    public function dumpAllToJson() {
        $data = [];
        $result = $this->db->query("SELECT * FROM urls");

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['details_json'] = json_decode($row['details_json'], true);
            $data[] = $row;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getById($id, $target = null) {
		return $this->getRow('id', $id, $target);
	}

    public function getByUrl($url, $target = null) {
		return $this->getRow('url', $url, $target);
	}

	public function getRow($column, $value, $target = null) {
			$query = "SELECT * FROM urls WHERE $column = :value";

        if ($target) {
            $query .= " AND target = :target";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);

        if ($target) {
            $stmt->bindValue(':target', $target, SQLITE3_TEXT);
        }

        $result = $stmt->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $row['details_json'] = json_decode($row['details_json'], true);
            
            return $row;
        } else {
            return null;
        }
    }
}

?>
