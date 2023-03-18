<?php

require_once 'class/Session.php';
require_once 'class/FileHandler.php';
require_once 'class/URLManager.php';

$session = Session::getInstance();

if(!$session->is_logged_in())
{
    header("Location: login.php");
    exit;
}

$config = require __DIR__.'/config/config.php';

$file = new FileHandler;
$urls = new URLManager($config['db']);

if (!empty($_REQUEST['as_json'])) {
    echo $urls->dumpAllToJson();

    die;
}

//header('Content-Type: text/tab-separated-values');
header('Content-Type: text/plain');

$dl_uri_prefix = ($_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']) . $config['outputFolder'] . '/';

$out = fopen('php://output', 'w');

foreach($file->listFiles() as $file) {
    if (preg_match('/(?:ph)?[\\da-f]{4,}/', $file['name'], $match)) {
        $video_id = $match[0];

        $data = $urls->getById($video_id);

        $uri = $dl_uri_prefix . $file['name'];

        if ($data !== null) {
            $v = $data['details_json'];

            $tags = array_map(function($c) { return $c['tag_name']; }, $v['tags']);
            $categories = array_map(function($c) { return $c['category']; }, $v['categories']);
            $pornstars = array_map(function($c) { return $c['pornstar_name']; }, $v['pornstars']);
            
            $row = [
                // 1. mp4 ссылка для скачивания
                $files[$v['video_id']],
                // 2. сколько обрезать сначала
                round(@$v['cutFrom'] ?? 0),
                // 3. сколько обрезать в конце
                round(@$v['cutEnd'] ?? 0),
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
    
            array_unshift($row, $uri);

            fputcsv($out, $row, "\t");
        }
    }
}

fclose($out);
