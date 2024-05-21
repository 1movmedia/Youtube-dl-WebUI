<?php

$videos_file = __DIR__ . '/train_videos.json';

if (file_exists($videos_file)) {
    $videos = json_decode(file_get_contents($videos_file));
}
else {
    require_once __DIR__ . '/create_test_set.php';

    $videos = get_video_set(250, '', true);

    file_put_contents($videos_file, json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

foreach($videos as $video_info) {
    // TODO : cut frames and send to classfier. Store bad results into 2 sets.
}
