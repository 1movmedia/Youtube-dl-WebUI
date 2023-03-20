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
$mark_exported = @$_REQUEST['mark_exported'] === 'y';
$remove_exported = @$_REQUEST['remove_exported'] === 'y';

//header('Content-Type: text/tab-separated-values');
header('Content-Type: text/plain');

$dl_uri_prefix = (@$_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']) . $config['outputFolder'] . '/';

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

        if ($remove_exported && !!$data['last_export']) {
            $filename = __DIR__ . '/' . $config['outputFolder'] . '/' . $file['name'];
            @unlink($filename);
        }

        $uri = $dl_uri_prefix . $file['name'];

        $v = $data['details_json'];

        $tags = array_map(function($c) { return $c['tag_name']; }, $v['tags']);
        $categories = array_map(function($c) { return $c['category']; }, $v['categories']);
        $pornstars = array_map(function($c) { return $c['pornstar_name']; }, $v['pornstars']);

        $pornstars_str = implode(',', $pornstars);

        $row = [
            //  1. mp4 ссылка для скачивания
            $uri,
            //  2. сколько обрезать сначала
            round(@$v['cutFrom'] ?? 0),
            //  3. сколько обрезать в конце
            round(@$v['cutEnd'] ?? 0),
            //  4. название ролика
            $v['title'],
            //  6. категории
            implode(',', $categories),
            //  7. теги
            implode(',', $tags),
            //  8. модели
            $pornstars_str,
            //  9. владелец контента
            mb_strtolower($pornstars_str) === mb_strtolower($v['userTitle']) ? '' : $v['userTitle'],
            // 10. пользователь инициировавший скачивание
            $data['username'] ?? '',
        ];

        fputcsv($out, $row, "\t");

        if ($mark_exported) {
            $urls->updateLastExport($video_id);
        }
    }
}

fclose($out);
