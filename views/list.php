<div class="container my-4">
		<?php
			if(!empty($files))
			{
		?>
			<h2>List of available files:</h2>
			<table class="table table-striped table-hover ">
				<thead>
					<tr>
						<th>Title</th>
						<th>Size</th>
						<th>User</th>
						<th>Cut</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($files as $f): ?>
						<tr id="video_<?= $f['id'] ?>">
							<td>
								<?php if($file->get_relative_downloads_folder()): ?>
									<a href="<?= rawurlencode($file->get_relative_downloads_folder()).'/'.rawurlencode($f["name"]) ?>" download><?= $f["name"] ?></a>
								<?php else: ?>
									<?= $f["name"] ?>
								<?php endif; ?><br>
								<br>
								<a target="_blank" href="<?= $f['info']['url'] ?>"><?= htmlspecialchars(@$f["info"]["details_json"]["title"]) ?></a>
							</td>
							<td><?= $f["size"] ?></td>
							<td><?= @$f["info"]["username"] ?></td>
							<td><?= @$f["info"]["details_json"]["cutFrom"] . '-' . @$f["info"]["details_json"]["cutTo"] ?></td>
							<td>
								<a href="<?= "info.php?url=" . urlencode($f['info']['url']) ?>" class="btn btn-info btn-sm pull-right">Info</a>
								<a href="<?= "list.php?delete=".sha1($f['name']) ?>" class="btn btn-danger btn-sm pull-right">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
			}
			else
			{
				echo "<br><div class=\"alert alert-warning\" role=\"alert\">No files!</div>";
			}
		?>
			<br/>
		<?php
			if(!empty($parts))
			{
		?>
			<h2>List of part files:</h2>
			<table class="table table-striped table-hover ">
				<thead>
					<tr>
						<th>Title</th>
						<th>Size</th>
						<th><span class="pull-right">Delete link</span></th>
					</tr>
				</thead>
				<tbody>
			<?php
				foreach($parts as $f)
				{
					echo "<tr>";
					if ($file->get_relative_downloads_folder())
					{
						echo "<td><a href=\"".rawurlencode($file->get_relative_downloads_folder()).'/'.rawurlencode($f["name"])."\" download>".$f["name"]."</a></td>";
					}
					else
					{
						echo "<td>".$f["name"]."</td>";
					}
					echo "<td>".$f["size"]."</td>";
					echo "<td><a href=\"./list.php?delete=".sha1($f["name"])."\" class=\"btn btn-danger btn-sm pull-right\">Delete</a></td>";
					echo "</tr>";
				}
			?>
				</tbody>
			</table>
			<br/>
			<br/>
		<?php
			}
		?>
			<br/>
		</div><!-- End container -->
