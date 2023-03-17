<?php

require_once 'class/Session.php';
require_once 'class/FileHandler.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
    header("Location: login.php");
    exit;
}

$file = new FileHandler;

$config = require __DIR__.'/config/config.php';

$files = [];
$ids = [];

//header('Content-Type: text/tab-separated-values');
header('Content-Type: text/plain');

$dl_uri_prefix = ($_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']) . $config['outputFolder'] . '/';

foreach($file->listFiles() as $file) {
    if (preg_match('/(?:ph)?[\\da-f]{4,}/', $file['name'], $match)) {
        $uri = $dl_uri_prefix . $file['name'];
        $files[$match[0]] = $uri;
    }
}

// var_dump($files);

$out = fopen('php://output', 'w');

$fh = fopen($config['logFolder'] . '/metadata', 'r');
while(($l = fgets($fh)) !== false) {
    $record = json_decode(base64url_decode($l), true);

    foreach($record as $v) {
        if (!isset($files[$v['video_id']])) {
            continue;
        }

        $tags = array_map(function($c) { return $c['tag_name']; }, $v['tags']);
        $categories = array_map(function($c) { return $c['category']; }, $v['categories']);
        $pornstars = array_map(function($c) { return $c['pornstar_name']; }, $v['pornstars']);
        
        $row = [
            // 1. mp4 ссылка для скачивания
            $files[$v['video_id']],
            // 2. сколько обрезать сначала
            '',
            // 3. сколько обрезать в конце
            '',
            // 4. название ролика
            $v['title'],
            // 5. категории
            implode(',', $categories),
            // 5. теги
            implode(',', $tags),
            // 5. модели
            implode(',', $pornstars),
            // 5. владелец контента
            $v['userTitle']
        ];

        fputcsv($out, $row, "\t");
        // echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // die;
    }
}

fclose($out);

fclose($fh);

function base64url_decode($base64url)
{
    $base64 = strtr($base64url, '-_', '+/');
    $plainText = base64_decode($base64);
    return $plainText;
}
