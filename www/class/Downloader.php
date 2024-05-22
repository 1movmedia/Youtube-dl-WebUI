<?php

class Downloader
{
	private $video_info = null;
	private $id = null;
	private $url = null;
	private $config = [];
	private $errors = [];
	private $download_path = "";
	private $log_path = "";
	private $log_file = '/dev/null';
	private $vformat = false;

	static function ytdlp_path(): string {
		return trim(`which yt-dlp`);
	}

	public function __construct($video_info)
	{
		$this->config = require dirname(__DIR__).'/config/config.php';
		$this->config['bin'] = self::ytdlp_path();

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

	public static function check_can_download($config) {
		if($config["max_dl"] == 0)
		{
			return true;
		}
		elseif($config["max_dl"] > 0)
		{
			$bg_jobs = self::background_jobs();

			if($bg_jobs >= 0 && $bg_jobs < $config["max_dl"])
			{
				return true;
			}
			else
			{
				$GLOBALS['_ERRORS'] = ["Simultaneous downloads limit reached !"];
			}
		}

		return false;
	}

	public function download($vformat=False, $deferred = false, $index = true) {
		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return;
		}

		if ($vformat)
		{
			$this->vformat = $vformat;
		}

		if ($deferred || self::check_can_download($this->config)) {
			$this->do_download($deferred, $index);
		}
		else {
			$this->errors = $GLOBALS['_ERRORS'];
		}

		if(isset($this->errors) && count($this->errors) > 0)
		{
			$GLOBALS['_ERRORS'] = $this->errors;
			return;
		}

	}

	public static function background_jobs()
	{
		return count(self::get_current_background_jobs());
	}

	public static function max_background_jobs()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		return $config["max_dl"];
	}

	public static function get_current_background_jobs()
	{
		$currentUid = posix_getuid();
		$currentUsername = posix_getpwuid($currentUid)['name'];
		
		$ps_command = "ps -A -o user,pid,ppid,etime,cmd";

		$ignore_commands = [
			"sh -c $ps_command",
			$ps_command,
			'/usr/sbin/apache2 -D FOREGROUND',
			'php /var/www/html/youtube-dl/download_deferred.php',
		];

		exec($ps_command, $output);

		$bjs = [];

		if(count($output) > 0)
		{
			$config = require dirname(__DIR__).'/config/config.php';
			$config['bin'] = self::ytdlp_path();

			$markers = [ $config["bin"], "sh -c ( $config[bin]" ];

			foreach($output as $line)
			{
				$cells = explode(' ', preg_replace ("/ +/", " ", $line), 5);

				if ($cells[1] === 'PID' || $cells[0] != $currentUsername || in_array($cells[4], $ignore_commands)) {
					continue;
				}

				$job = array(
					'line' => $line,
					'user' => $cells[0],
					'pid' => $cells[1],
					'ppid' => $cells[2],
					'time' => $cells[3],
					'cmd' => $cells[4],
					'chld' => [],
				);

				$job['is_download'] = false;
				if ($job['ppid'] == '1') {
					foreach($markers as $marker) {
						if (substr($job['cmd'], 0, strlen($marker)) === $marker) {
							$job['is_download'] = true;
							break;
						}
					}
				}

				$bjs[$job['pid']] = $job;

				if (isset($bjs[''])) {
					var_dump([$job, $bjs]);
					die;
				}
			}

			foreach($bjs as $k => &$job) {
				$ppid = $job['ppid'];

				if ($ppid == '1' || !isset($bjs[$ppid])) {
					continue;
				}

				$bjs[$ppid]['chld'][] = $job['pid'];
			}

			foreach($bjs as &$job) {
				$ppid = $job['ppid'];

				if ($ppid != '1') {
					continue;
				}
				
				$pids = [ $job['pid'] ];
				$tree = [];

				while(count($pids) > 0) {
					$pid = array_pop($pids);
					$tree[] = $pid;

					foreach($bjs[$pid]['chld'] as $chld) {
						$pids[] = $chld;
					}
				}

				$job['tree'] = $tree;
			}

			foreach($bjs as $pid => $job) {
				if ($pid != $job['pid'] || !$job['is_download']) {
					unset($bjs[$pid]);
				}
			}

			return $bjs;
		}
		else
		{
			return null;
		}
	}

	public static function kill($pid) {
		$pid = intval($pid);

		foreach(self::get_current_background_jobs() as $job) {
			if ($job['pid'] == $pid) {
				posix_kill($pid, 15);

				foreach($job['tree'] as $pid) {
					$pid = intval($pid);

					posix_kill($pid, 15);
				}

				return true;
			}
		}

		return false;
	}

	public static function kill_them_all()
	{
		foreach(self::get_current_background_jobs() as $job) {
			foreach($job['tree'] as $pid) {
				$pid = intval($pid);

				posix_kill($pid, 15);
			}
		}

		$fh = new FileHandler();
		$folder = $fh->get_downloads_folder();

		foreach(glob($folder.'*.part') as $file)
		{
			unlink($file);
		}

		foreach(glob($folder.'*.uncut.mp4') as $file)
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

	private function do_download($deferred = false, $index = true) {
		$cmd = $this->download_prepare($index);

		if ($this->log_file !== '/dev/null') {
			file_put_contents($this->log_file, "Command: $cmd\n\n");
		}

		if ($deferred) {
			if (!rename($this->log_file, $this->log_file . '.deferred')) {
				$this->errors[] = "Failed to rename log file to .deferred";
			}

			return;
		}

		$output = shell_exec($cmd);

		if ($this->log_file !== '/dev/null') {
			file_put_contents($this->log_file, "Output:\n\n$output", FILE_APPEND);
		}
	}

	function download_prepare($index = true, $array = false)
	{
		$fh = new FileHandler($index ? $this->config['db'] : null);

		$cmd = $this->config["bin"];
		$cmd .= " --ignore-error";
		$cmd .= " --socket-timeout 30";
		$cmd .= " --retry-sleep exp=1:600:2";
		
		if ($this->vformat) 
		{
			$cmd .= " --format ";
			$cmd .= escapeshellarg($this->vformat);
		}
		$cmd .= " --restrict-filenames"; // --restrict-filenames is for specials chars

		$json_info = $this->video_info;

		unset($json_info['id']);
		unset($json_info['url']);

		if ($index && !$fh->addURL($this->id, $this->url, json_encode($json_info))) {
			$this->errors[] = "Failed to add $this->id";
			return;
		}
		
		$from = $this->video_info['cutFrom'] ?? 0;
		$from_end = $this->video_info['cutEnd'] ?? 0;
		$to = $this->video_info['cutTo'] ?? 0;

		$convert_cmd = '';
		$output_file = $this->download_path."/".$this->id . '.%(ext)s';
		$download_file = $output_file;

		if ($from != 0 || $from_end != 0) {
			// TODO : Revert to use `--download-sections` yt-dlp argument when yt-dlp will fix partial download issue
			// $cmd .= " --download-sections " . escapeshellarg("*0-$to");
			$cmd .= " --postprocessor-args " . escapeshellarg("-t $to");

			$output_file = $this->download_path."/".$this->id . '.mp4';
			$download_file = $this->download_path."/".$this->id . '.uncut.mp4';
			$convert_from = max(0, $from - 0.33);
			$convert_cmd = "ffmpeg -ss \$(php ".escapeshellarg(__DIR__.'/../util/first_key_frame.php')." ".escapeshellarg($download_file)." $convert_from) -i " . escapeshellarg($download_file) . " -to $to -avoid_negative_ts make_zero -map 0:0 -c:0 copy -map 0:1 -c:1 copy -map_metadata 0 -movflags +faststart -default_mode infer_no_subs -ignore_unknown -f mp4 " . escapeshellarg($output_file);
			$convert_cmd .= " && rm " . escapeshellarg($download_file);
		}
		else {
			$output_file = $this->download_path."/".$this->id . '.mp4';
			$download_file = $this->download_path."/".$this->id . '.uncut.mp4';

			$convert_cmd = implode(' ', [
				'php', escapeshellarg(__DIR__.'/../util/remove_ads.php'), escapeshellarg($download_file), escapeshellarg($output_file),
				
				'&&',
				'rm', '-f', escapeshellarg($download_file),
			]);
		}
		
		$cmd .= " -o ".escapeshellarg($download_file);

		$cmd .= " ".escapeshellarg($this->url);

		$dl_cmd = $cmd;

		if ($convert_cmd != '') {
			$cmd = "( $cmd && $convert_cmd )";
		}

		// $cmd = "( while true; do $cmd && break; sleep 15; done )";

		if($this->config["log"])
		{
			$this->log_file = $this->log_path . "/" . date("Y-m-d_H-i-s") . "_" . floor(fmod(microtime(true), 1) * 1000000) . '-' . $this->id . ".txt";
		}

		$cmd .= " >> " . $this->log_file;
		$cmd .= " 2>&1";

		$cmd .= " & echo $!";

		if ($array) {
			return [
				'dl_cmd' => $dl_cmd,
				'output_file' => $output_file,
				'download_file' => $download_file,
				'convert_cmd' => $convert_cmd,
				'log' => $this->log_file,
				'cmd' => $cmd,
			];
		}

		return $cmd;
	}

}

?>
