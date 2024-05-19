<?php

if ($_SERVER['HTTP_ACCEPT'] !== 'application/json') {
    header('HTTP/1.0 400 Bad Request');
    header('Content-Type: application/json');
    die('false');
}

require_once 'vendor/autoload.php';

$session = Session::getInstance();
if(!$session->is_logged_in()) {
    header('HTTP/1.0 403 Login Required');
    header('Content-Type: application/json');
    die('false');
}

$config = require __DIR__.'/config/config.php';

$response = [
    'downloaded' => null,
    'targets' => $config['targets'],
    'username' => @$_SESSION["username"],
];

$id = $_REQUEST['id'];

if (!empty($id)) {
    $files = new FileHandler($config['db']);

    $response['downloaded'] = $files->isIdPresent($id);
}

header('Content-Type: application/json');
echo json_encode($response);
