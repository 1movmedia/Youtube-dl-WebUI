<?php

require_once 'class/Session.php';
require_once 'class/FileHandler.php';

$config = require __DIR__.'/config/config.php';

$files = new FileHandler($config['db']);

$id = $_REQUEST['id'];

if (empty($id)) {
    header('HTTP/1.0 400 No ID');
    header('Content-Type: text/plain');
    die('No ID');    
}

header('Content-Type: application/json');
echo json_encode($files->isIdPresent($id));
