<?php

// Increase memory limit to 512MB
ini_set('memory_limit', '512M');

require_once 'vendor/autoload.php';

$config = require __DIR__.'/config/config.php';

$fh = new FileHandler($config['db']);

if ('y' == @$_REQUEST['as_json']) {
    $dataset = $_REQUEST['ds'] ?? 'all';
    if ($dataset === 'all') {
        header('Content-Type: application/json');
        echo $fh->dumpAllToJson();
    }
    elseif ($dataset === 'files') {
        header('Content-Type: application/json');
        echo json_encode(iterator_to_array($fh->listFiles()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    elseif ($dataset === 'ids') {
        header('Content-Type: application/json');
        echo json_encode($fh->getAllIds(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
$remove_marked = @$_REQUEST['remove_marked'] === 'y';
$limit = (int) @$_REQUEST['limit'] ?? PHP_INT_MAX;

//header('Content-Type: text/tab-separated-values');
header('Content-Type: text/plain');

if ($limit <= 0) {
    exit(0);
}

$dl_uri_prefix = (@$_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']) . $config['outputFolder'] . '/';

$out = fopen('php://output', 'w');

foreach($fh->listFiles() as $file) {
    if (!empty($file['info'])) {
        if ($limit <= 0) {
            break;
        }

        $video_id = $file['id'];
        
        $data = $file['info'];

        if (empty($data)) {
            continue;
        }

        if (!empty($target)) {
            if ($data['target'] != $target) {
                continue;
            }
        }

        if ($remove_marked && !!$data['last_export']) {
            $filename = __DIR__ . '/' . $config['outputFolder'] . '/' . $file['name'];
            @unlink($filename);
        }

        $uri = $dl_uri_prefix . $file['name'];

        $v = $data['details_json'];

        $tags = array_map(function($c) { return $c['tag_name']; }, $v['tags']);
        $categories = array_map(function($c) { return $c['category']; }, $v['categories']);
        $pornstars = array_map(function($c) { return $c['pornstar_name']; }, $v['pornstars']);

        $tags = array_map('sanitize_unicode', $tags);
        $categories = array_map('sanitize_unicode', $categories);
        $pornstars = array_map('sanitize_unicode', $pornstars);

        $pornstars_str = implode(',', $pornstars);

        $row = [
            //  1. mp4 download link
            $uri,
            //  2. how much to trim from start
            round(@$v['cutFrom'] ?? 0),
            //  3. how much to trim from end
            round(@$v['cutEnd'] ?? 0),
            //  4. video title
            sanitize_unicode($v['title']),
            //  6. categories
            implode(',', $categories),
            //  7. tags
            implode(',', $tags),
            //  8. models
            $pornstars_str,
            //  9. content owner (channels only)
            $v['userType'] === 'channel' || $v['userType'] === 'Content Partner' ? sanitize_unicode($v['userTitle']) : '',
            // 10. user who initiated download
            sanitize_unicode($data['username'] ?? ''),
        ];

        fputcsv($out, $row, "\t");

        if ($mark_exported) {
            $fh->updateLastExport($video_id);
        }

        $limit--;
    }
}

fclose($out);

function sanitize_unicode($str) {
    $str = preg_replace('/[^\x20-\x7E]/u', '', $str);
    $str = trim($str);
    
    return $str;
}
