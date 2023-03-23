<?php
	require_once 'class/Session.php';
	require_once 'class/Downloader.php';
	require_once 'class/FileHandler.php';

	$session = Session::getInstance();
	$file = new FileHandler;

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}
	else
	{
		$json = False;

		if(isset($_POST['urls']) && !empty($_POST['urls']))
		{
			$downloader = new Downloader($_POST['urls']);
			$json = $downloader->info();
		}
	}

	require 'views/layout.php';
	require 'views/info.php';