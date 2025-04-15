<div class="container my-4">
	<?php
	if (!empty($files)) {
		?>
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div class="d-flex gap-3 align-items-center">
				<h1>List of logs:</h1>
				<?php if ($file->hasRestartableTasks($files)): ?>
				<a href="./download.php?restart_all=1&filter=<?php echo urlencode($filter); ?>" class="btn btn-success">Restart All</a>
				<?php endif; ?>
			</div>
			<div class="btn-group" role="group" aria-label="Filter logs">
				<a href="?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
				<a href="?filter=ok" class="btn btn-outline-primary <?php echo $filter === 'ok' ? 'active' : ''; ?>">OK</a>
				<a href="?filter=not-ok" class="btn btn-outline-primary <?php echo $filter === 'not-ok' ? 'active' : ''; ?>">Not OK</a>
			</div>
		</div>
		<table class="table table-striped table-hover ">
			<thead>
				<tr>
					<th>Timestamp</th>
					<th>Ended?</th>
					<th>Ok?</th>
					<th>Size</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php

				foreach ($files as $f) {
					echo "<tr>";

					$lastline = $f["lastline"];
					if (strlen($lastline) > 1500) {
						$lastline = substr($lastline, 0, 1500) . "...";
					}
					$lastline = htmlspecialchars($lastline);

					if ($file->get_relative_log_folder()) {
						echo "<td class=\"log-last-line\"><div><a href=\"" . rawurlencode($file->get_relative_log_folder()) . '/' . rawurlencode($f["name"]) . "\" target=\"_blank\">" . $f["name"] . "</a></div><div>" . $lastline . "</div></td>";
					} else {
						echo "<td class=\"log-last-line\"><div>" . $f["name"] . "</div><div>" . $lastline . "</div></td>";
					}
					echo "<td>" . ($f["ended"] ? '&#10003;' : '') . "</td>";
					echo "<td>" . ($f["100"] ? '&#10003;' : '') . "</td>";
					echo "<td>" . $f["size"] . "</td>";
					echo "<td>";
					echo "<a href=\"./logs.php?delete=" . sha1($f["name"]) . "&filter=" . urlencode($filter) . "\" class=\"btn btn-danger btn-sm\">Delete</a>";
					if (isset($f["restartable"]) && $f["restartable"] === true) {
						echo " <a href=\"./download.php?restart_log=" . rawurlencode($file->get_relative_log_folder() . '/' . $f["name"]) . "\" class=\"btn btn-success btn-sm\">Restart</a>";
					}
					echo "</td>";
					echo "</tr>";
				}
				?>
			</tbody>
		</table>
		<?php
	} else {
		?>
		<div class="alert alert-warning">No logs found.</div>
		<?php
	}
	?>
</div>