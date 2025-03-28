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

	if($session->is_logged_in() && isset($_GET["delete"]))
	{
		$file->delete($_GET["delete"]);
		header("Location: list.php");
		exit;
	}

	$files = iterator_to_array($file->listFiles());
	$parts = $file->listParts();
	$deferred = $file->list_deferred();

	require 'views/layout.php';
	require 'views/list.php';
