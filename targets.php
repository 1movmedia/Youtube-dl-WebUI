<?php

require_once 'class/Session.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
    header("Location: login.php");
    exit;
}

$config = require __DIR__.'/config/config.php';

header('Content-Type: application/json');
echo json_encode($config['targets']);
