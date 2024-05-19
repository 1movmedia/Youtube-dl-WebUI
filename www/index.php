<?php

	require_once 'vendor/autoload.php';
	
	$session = Session::getInstance();
	$file = new FileHandler;

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}
	else
	{
		if(isset($_GET['kill']) && !empty($_GET['kill']))
		{
			if ($_GET['kill'] === "all") {
				Downloader::kill_them_all();
			}
			elseif (is_numeric($_GET['kill'])) {
				Downloader::kill($_GET['kill']);
			}
		}
	}

	$deferred = $file->list_deferred();

	require 'views/layout.php';
	require 'views/index.php';
