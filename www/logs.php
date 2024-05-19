<?php
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
