<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

$videos_file = __DIR__ . '/train_videos.json';

if (file_exists($videos_file)) {
    $videos = json_decode(file_get_contents($videos_file), true);
}
else {
    require_once __DIR__ . '/create_test_set.php';

    $videos = get_video_set(250, '', true);

    file_put_contents($videos_file, json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$cache = __DIR__ . '/../tmp/cache';

if (!is_dir($cache)) {
    mkdir($cache, 0777, true);
}

$training_set_dir = __DIR__ . '/../taining_set';
$training_set_dirs = [];
foreach(['ok', 'promo'] as $class) {
    $dir = $training_set_dir . '/' . $class;
    $training_set_dirs[$class] = $dir;

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

foreach($videos as $video_info) {
    $frame_classes = VideoAdFinder::classifyFrames($video_info['filename'], 0, $video_info['cutFrom'] * 2, $cache, true);

    foreach($frame_classes as $class) {
        $real_ad = $class['timestamp'] < $video_info['cutFrom'];

        if ($real_ad === $class['is_ad']) {
            continue; // Correct result
        }

        $set_dir = $training_set_dirs[$real_ad ? 'promo' : 'ok'];

        copy($class['filename'], $set_dir . '/' . $video_info['video_id'] . '-' . $class['timestamp'] . '.jpg');
    }
}
