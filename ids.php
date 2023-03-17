<?php

require_once 'class/Session.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
    header("Location: login.php");
    exit;
}

$config = require __DIR__.'/config/config.php';

$ids = [];

$fh = fopen($config['logFolder'] . '/metadata', 'r');
while(($l = fgets($fh)) !== false) {
    $record = json_decode(base64url_decode($l), true);

    foreach($record as $v) {
        $ids[] = $v['video_id'];
    }
}
fclose($fh);

header('Content-Type: application/json');
echo json_encode($ids);

function base64url_decode($base64url)
{
    $base64 = strtr($base64url, '-_', '+/');
    $plainText = base64_decode($base64);
    return $plainText;
}
