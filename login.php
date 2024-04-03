<?php

require_once 'class/Session.php';
require_once 'class/Downloader.php';

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
