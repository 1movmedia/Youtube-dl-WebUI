<?php
	// Increase memory limit to 512MB
	ini_set('memory_limit', '512M');
	// Increase max execution time to 1 day
	set_time_limit(86400);

	require_once 'vendor/autoload.php';

	$session = Session::getInstance();
	$file = new FileHandler;

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}

	$files = $file->listLogs();
	$deferred = $file->list_deferred();

	if($session->is_logged_in() && isset($_GET["delete"]))
	{
		$file->deleteLog($_GET["delete"]);
		header("Location: logs.php");
	}

	$deferred = $file->list_deferred();

	require 'views/layout.php';
	require 'views/logs.php';
