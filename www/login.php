<?php

if ($_SERVER['HTTP_ACCEPT'] === 'application/json') {
	header('HTTP/1.0 400 Obsolete Userscript');
	die('Obsolete Userscript. Update it.');
}

require_once 'vendor/autoload.php';

$session = Session::getInstance();
$loginError = "";

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
