<div class="container my-4">
			<?php

				if(isset($GLOBALS['_ERRORS']) && $GLOBALS['_ERRORS'] > 0)
				{
					foreach ($GLOBALS['_ERRORS'] as $e)
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
							<p>Install userscript and use download button on a video page</p>
							<p><b>With which sites does it work?</b></p>
							<p>At the moment it works with <a href="https://pornhub.com">Pornhub.com</a></p>
							<p><b>How can I download the video on my computer?</b></p>
							<p>Go to <a href="./list.php">List of files</a> -> choose one -> right click on the link -> "Save target as ..." </p>
						</div>
					</div>
				</div>
			</div>
		</div>
