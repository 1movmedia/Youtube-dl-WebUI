<?php
include_once('FileHandler.php');

class Downloader
{
	private $video_info = null;
	private $id = null;
	private $url = null;
	private $config = [];
	private $errors = [];
	private $download_path = "";
	private $log_path = "";
	private $vformat = false;

	public function __construct($video_info)
	{
		$this->config = require dirname(__DIR__).'/config/config.php';
		$fh = new FileHandler();
		$this->download_path = $fh->get_downloads_folder();
		
		if($this->config["log"])
		{
			$this->log_path = $fh->get_logs_folder();
		}

		$this->video_info = $video_info;
		$this->id = $video_info['id'];
		$this->url = $video_info['url'];

		if(!$this->check_requirements())
		{
			return;
		}	

		if(!$this->is_valid_url($this->url))
		{
			$this->errors[] = "\"$this->url\" is not a valid url !";
		}

		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return;
		}
	}

	public function download($vformat=False) {
		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return;
		}

		if ($vformat)
		{
			$this->vformat = $vformat;
		}

		if($this->config["max_dl"] == 0)
		{
			$this->do_download();
		}
		elseif($this->config["max_dl"] > 0)
		{
			if($this->background_jobs() >= 0 && $this->background_jobs() < $this->config["max_dl"])
			{
				$this->do_download();
			}
			else
			{
				$this->errors[] = "Simultaneous downloads limit reached !";
			}
		}

		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return;
		}

	}

	public function info()
	{
		$info = $this->do_info();

		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
		}

		return $info;
	}

	public static function background_jobs()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		return shell_exec("ps aux | grep -v grep | grep -v \"".$config["bin"]." -U\" | grep \"".$config["bin"]." \" | wc -l");
	}

	public static function max_background_jobs()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		return $config["max_dl"];
	}

	public static function get_current_background_jobs()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		exec("ps -A -o user,pid,etime,cmd | grep -v grep | grep -v \"".$config["bin"]." -U\" | grep \"".$config["bin"]." \"", $output);

		$bjs = [];

		if(count($output) > 0)
		{
			foreach($output as $line)
			{
				$line = explode(' ', preg_replace ("/ +/", " ", $line), 4);
				$bjs[] = array(
					'user' => $line[0],
					'pid' => $line[1],
					'time' => $line[2],
					'cmd' => $line[3]
					);
			}

			return $bjs;
		}
		else
		{
			return null;
		}
	}

	public static function kill_them_all()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		exec("ps -A -o pid,cmd | grep -v grep | grep -v \"".$config["bin"]." -U\" | grep \"".$config["bin"]." \" | awk '{print $1}'", $output);

		if(count($output) <= 0)
		{
			return;
		}

		foreach($output as $p)
		{
			shell_exec("kill ".$p);
		}

		$fh = new FileHandler();
		$folder = $fh->get_downloads_folder();

		foreach(glob($folder.'*.part') as $file)
		{
			unlink($file);
		}
	}

	private function check_requirements()
	{
		if($this->is_youtubedl_installed() != 0)
		{
			$this->errors[] = "Binary not found in <code>".$this->config["bin"]."</code>, see <a href='https://github.com/yt-dlp/yt-dlp'>yt-dlp site</a> !";
		}

		$this->check_outuput_folder();

		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return false;
		}

		return true;
	}

	private function is_youtubedl_installed()
	{
		exec("which ".$this->config["bin"], $out, $r);
		return $r;
	}

	public static function get_youtubedl_version()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		$soutput = shell_exec($config["bin"]." --version");
		return trim($soutput);
	}

	private function is_extracter_installed()
	{
		exec("which ".$this->config["extracter"], $out, $r);
		return $r;
	}

	private function is_python_installed()
	{
		exec("which python", $out, $r);
		return $r;
	}

	private function is_valid_url($url)
	{
		return filter_var($url, FILTER_VALIDATE_URL);
	}

	private function check_outuput_folder()
	{
		if(!is_dir($this->download_path))
		{
			//Folder doesn't exist
			if(!mkdir($this->download_path, 0775))
			{
				$this->errors[] = "Output folder doesn't exist and creation failed! (".$this->download_path.")";
			}
		}
		else
		{
			//Exists but can I write ?
			if(!is_writable($this->download_path))
			{
				$this->errors[] = "Output folder isn't writable! (".$this->download_path.")";
			}
		}
		
		// LOG folder
		if($this->config["log"])
		{
			if(!is_dir($this->log_path))
			{
				//Folder doesn't exist
				if(!mkdir($this->log_path, 0775))
				{
					$this->errors[] = "Log folder doesn't exist and creation failed! (".$this->log_path.")";
				}
			}
			else
			{
				//Exists but can I write ?
				if(!is_writable($this->log_path))
				{
					$this->errors[] = "2: Log folder isn't writable! (".$this->log_path.")";
				}
			}
		}
		
	}

	private function do_download()
	{
		$fh = new FileHandler($this->config['db']);

		$cmd = $this->config["bin"];
		$cmd .= " --ignore-error -o ".$this->download_path."/";
		$cmd .= escapeshellarg($this->id . '.%(ext)s');
		
		if ($this->vformat) 
		{
			$cmd .= " --format ";
			$cmd .= escapeshellarg($this->vformat);
		}
		$cmd .= " --restrict-filenames"; // --restrict-filenames is for specials chars

		$json_info = $this->video_info;

		unset($json_info['id']);
		unset($json_info['url']);

		if (!$fh->addURL($this->id, $this->url, json_encode($json_info))) {
			$this->errors[] = "Failed to add $this->id";
			return;
		}

		if (is_numeric(@$this->video_info['cutTo']) && $this->video_info['cutTo'] > 0) {
			$from = 0;
			$cmd .= " --download-sections " . escapeshellarg("*$from-$this->video_info[cutTo]");
		}

		$cmd .= " ".escapeshellarg($this->url);

		if($this->config["log"])
		{
			$cmd = "{ echo Command: ".escapeshellarg($cmd)."; ".$cmd." ; }";
			$cmd .= " > ".$this->log_path."/$(date  +\"%Y-%m-%d_%H-%M-%S-%N\").txt";
		}
		else
		{
			$cmd .= " > /dev/null ";
		}

		$cmd .= " & echo $!";

		shell_exec($cmd);
	}

	private function do_info()
	{
		$cmd = $this->config["bin"]." -J ";

		$cmd .= " ".escapeshellarg($this->url);

		if ($this->is_python_installed() == 0)
		{
			$cmd .= " | python -m json.tool";
		}

		$soutput = shell_exec($cmd);
		if (!$soutput)
		{
			$this->errors[] = "No video found";
		}
		return $soutput;

	}
	
	private static function base64url_decode($base64url)
	{
		$base64 = strtr($base64url, '-_', '+/');

		return base64_decode($base64, false);
	}

}

?>
