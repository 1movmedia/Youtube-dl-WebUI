<?php

	require_once 'class/Session.php';
	require_once 'class/Downloader.php';
	require_once 'class/FileHandler.php';
	require_once 'class/URLManager.php';

	$session = Session::getInstance();
	$file = new FileHandler;

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}
	else
	{
		if(isset($_GET['kill']) && !empty($_GET['kill']) && $_GET['kill'] === "all")
		{
			Downloader::kill_them_all();
		}

		if(isset($_POST['urls']) && !empty($_POST['urls']))
		{
			unset($_SESSION['errors']);

			$audio_only = false;
			if(isset($_POST['audio']) && !empty($_POST['audio']))
			{
				$audio_only = true;
			}

			$outfilename = False;
			if(isset($_POST['outfilename']) && !empty($_POST['outfilename']))
			{
				$outfilename = $_POST['outfilename'];
			}

			$vformat = False;
			if(isset($_POST['vformat']) && !empty($_POST['vformat']))
			{
				$vformat = $_POST['vformat'];
			}

			$metadata = false;

			if (!empty($_POST['metadata'])) {
				$metadata = $_POST['metadata'];
			}

			$downloader = new Downloader($_POST['urls']);

			$downloader->download($audio_only, $outfilename, $vformat, $metadata);

			if ($_SERVER['HTTP_ACCEPT'] === 'application/json') {
				header('Content-Type: application/json');
				echo json_encode(array(
					'success' => empty($_SESSION['errors']),
					'errors' => @$_SESSION['errors']
				));
				
				unset($_SESSION['errors']);
				
				die;
			}

			if(!isset($_SESSION['errors']))
			{
				header("Location: index.php");
				exit;
			}
		}
	}

	require 'views/header.php';
?>
		<div class="container my-4">
			<?php

				if(isset($_SESSION['errors']) && $_SESSION['errors'] > 0)
				{
					foreach ($_SESSION['errors'] as $e)
					{
						echo "<div class=\"alert alert-warning\" role=\"alert\">$e</div>";
					}
				}

			?>
			<br>
			<div class="row">
				<div class="col-lg-6 mb-2">
					<div class="card">
						<div class="card-header">Info</div>
						<div class="card-body">
							<p>Free space : <?php echo $file->free_space(); ?></b></p>
							<p>Used space : <?php echo $file->used_space(); ?></b></p>
							<p>Download folder : <?php echo $file->get_downloads_folder(); ?></p>
							<p>Youtube-dl version : <?php echo Downloader::get_youtubedl_version(); ?></p>
						</div>
					</div>
				</div>
				<div class="col-lg-6 mb-2">
					<div class="card">
						<div class="card-header">Help</div>
						<div class="card-body">
							<p><b>How does it work ?</b></p>
							<p>Install userscript and use download button on video page</p>
							<p><b>With which sites does it work?</b></p>
							<p>At the moment it works with <a href="https://pornhub.com">Pornhub.com</a></p>
							<p><b>How can I download the video on my computer?</b></p>
							<p>Go to <a href="./list.php">List of files</a> -> choose one -> right click on the link -> "Save target as ..." </p>
							<p><b>What's Filename or Format field?</b></p>
							<p>They are optional, see the official documentation about <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#format-selection">Format selection</a> or <a href="https://github.com/yt-dlp/yt-dlp/blob/master/README.md#output-template">Output template</a>. At the moment there is a requirement. Filenames must starts with remote ID. </p>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
	unset($_SESSION['errors']);
	require 'views/footer.php';
?>
