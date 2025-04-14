<div class="container my-4">
	<h1>JSON Info</h1>
	<?php

	if (isset($GLOBALS['_ERRORS']) && $GLOBALS['_ERRORS'] > 0) {
		foreach ($GLOBALS['_ERRORS'] as $e) {
			echo "<div class=\"alert alert-warning\" role=\"alert\">$e</div>";
		}
	}

	?>
	<form id="info-form" action="info.php" method="post">
		<div class="row my-3">
			<div class="input-group">
				<div class="input-group-text" id="urls-addon">URL:</div>
				<input class="form-control" id="url" name="url" value="<?= htmlspecialchars($_REQUEST['url']) ?>"
					placeholder="Link(s) separated by a space" type="text" aria-describedby="urls-addon" required />
			</div>
		</div>
		<div class="row mt-3 align-items-center">
			<div class="col-auto">
				<button type="submit" class="btn btn-primary">Query</button>
			</div>
		</div>

	</form>
	<br>
	<div class="row">
		<?php
		if ($json) {
			?>
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">Player</h3>
				</div>
				<div class="panel-body">
					<video controls style="width: 100%"
						src="<?= $config['outputFolder'] . '/' . $json['file']['name'] ?>"></video>
				</div>
			</div>
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">Info</h3>
				</div>
				<div class="panel-body">
					<pre><?= htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
				</div>
			</div>
			<?php
		}
		?>
	</div>
</div>