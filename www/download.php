<?php

if ($_SERVER['HTTP_ACCEPT'] !== 'application/json') {
	header('HTTP/1.0 400 Bad Request');
	header('Content-Type: text/plain');
	die('Bad Request');
}

require_once 'vendor/autoload.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
	header('HTTP/1.0 403 Unauthorized');
	header('Content-Type: text/plain');
	die('Unauthorized');
}

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
