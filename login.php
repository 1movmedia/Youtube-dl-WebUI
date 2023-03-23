<?php
	require_once 'class/Session.php';
	require_once 'class/Downloader.php';

	$session = Session::getInstance();
	$loginError = "";

	if ($_SERVER['HTTP_ACCEPT'] === 'application/json') {
		header('Content-Type: application/json');

		echo json_encode([
			'logged_in' => @$_SESSION["logged_in"],
            'username' => @$_SESSION["username"],

		]);
		exit;
	}

	if(isset($_POST["username"]) && isset($_POST["password"]))
	{
		if($session->login($_POST["username"], $_POST["password"]))
		{
			header("Location: index.php");
			exit;
		}
		else
		{
			$loginError = "Wrong username or password !";
		}
	}
?>

<?php require 'views/layout.php'; ?>
<div class="container my-4">
	<?php
		if($loginError !== "")
		{
	?>
	<div class="alert alert-danger" role="alert"><?php echo $loginError; ?></div>
	<?php
		}
	?>
	<form class="form-horizontal" action="login.php" method="POST" data-bitwarden-watching="1">
		<div class="row my-3 justify-content-md-center">
			<div class="col col-md-4">
				<h2>Login</h2>
			</div>
		</div>
		<div class="row my-3 justify-content-md-center">
			<div class="col col-lg-4 ">
				<div class="input-group">
					<input class="form-control" id="username" name="username" placeholder="Username" type="text">
				</div>
			</div>
		</div>
		<div class="row my-3 justify-content-md-center">
			<div class="col col-lg-4 ">
				<div class="input-group">
					<input class="form-control" id="password" name="password" placeholder="Password" type="password">
				</div>
			</div>
		</div>
		<div class="row my-3 justify-content-md-center">
			<div class="col col-lg-4">
				<div class="input-group">
					<button type="submit" class="btn btn-primary">Sign in</button>
				</div>
			</div>
		</div>
	</form>
</div>
