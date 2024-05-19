<?php

	require_once 'vendor/autoload.php';

	$session = Session::getInstance();
	$config = require __DIR__.'/config/config.php';
	$file = new FileHandler($config['db']);

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}
	else
	{
		$json = False;

		if(isset($_REQUEST['url']) && !empty($_REQUEST['url']))
		{
			$json = $file->info($_REQUEST['url']);
		}
	}

	require 'views/layout.php';
	require 'views/info.php';