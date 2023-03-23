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

	require 'views/layout.php';
	require 'views/login.php';

