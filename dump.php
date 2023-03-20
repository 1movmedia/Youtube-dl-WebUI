<?php

require_once 'class/Session.php';
require_once 'class/FileHandler.php';
require_once 'class/URLManager.php';

$config = require __DIR__.'/config/config.php';

$file = new FileHandler;
$urls = new URLManager($config['db']);

if ('y' == @$_REQUEST['as_json']) {
    $dataset = $_REQUEST['ds'] ?? 'all';
    if ($dataset === 'all') {
        header('Content-Type: application/json');
        echo $urls->dumpAllToJson();
    }
    elseif ($dataset === 'ids') {
        header('Content-Type: application/json');
        echo json_encode($urls->getAllIds(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    else {
        header('HTTP/1.0 404 Invalid dataset');
        header('Content-Type: text/plain');
        echo 'Invalid dataset specified';
    }

    die;
}

$target = $_REQUEST['target'] ?? null;

//header('Content-Type: text/tab-separated-values');
header('Content-Type: text/plain');

$dl_uri_prefix = ($_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']) . $config['outputFolder'] . '/';

$out = fopen('php://output', 'w');

foreach($file->listFiles() as $file) {
    if (preg_match('/(?:ph)?[\\da-f]{4,}/', $file['name'], $match)) {
        $video_id = $match[0];

        $data = $urls->getById($video_id);

        if (empty($data)) {
            continue;
        }

        if (!empty($target)) {
            if ($data['target'] != $target) {
                continue;
            }
        }

        $uri = $dl_uri_prefix . $file['name'];

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
            $v['userTitle'],
            // 6. пользователь инициировавший скачивание
            $data['username'] ?? '',
        ];

        array_unshift($row, $uri);

        fputcsv($out, $row, "\t");

        $urls->updateLastExport($video_id);
    }
}

fclose($out);
