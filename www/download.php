<?php

require_once 'vendor/autoload.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
	header('HTTP/1.0 403 Unauthorized');
	header('Content-Type: text/plain');
	die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$json = file_get_contents('php://input');
	
	$video_info = json_decode($json, true);
	
	$downloader = new Downloader($video_info);
	
	$index = $video_info['index'] !== 'n';
	
	$downloader->download(false, true, $index);
	
	header('Content-Type: application/json');
	
	echo json_encode(array(
		'success' => empty($GLOBALS['_ERRORS']),
		'errors' => @$GLOBALS['_ERRORS']
	));
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if (isset($_GET['restart_log'])) {
		// Append filename to path
		$filename = __DIR__ . '/' . $_GET['restart_log'];
		if (!rename($filename, $filename . '.deferred')) {
			header('HTTP/1.0 500 Internal Server Error');
			header('Content-Type: text/plain');
			die('Internal Server Error: ' . $filename . ': ' . error_get_last()['message']);
		}

		header('Location: ./logs.php');
		exit;
	}

	header('HTTP/1.0 400 Bad Request');
	header('Content-Type: text/plain');
	die('Bad Request');
}

header('HTTP/1.0 405 Method Not Allowed');
header('Content-Type: text/plain');
die('Method Not Allowed');
