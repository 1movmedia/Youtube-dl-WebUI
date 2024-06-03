<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

$video_info = [
    'id' => '6655bec070659',
    'url' => 'https://www.pornhub.com/view_video.php?viewkey=6655bec070659',
    'cutFrom' => 0,
    'cutEnd' => 0,
    // 'cutTo' => ?, // not necessary?

];

chdir(__DIR__ . '/../www');

$downloader = new Downloader($video_info);

$cmd = $downloader->download_prepare(false, true);

var_dump($cmd);

if (!file_exists($cmd['download_file'])) {
    system($cmd['dl_cmd']);
}

if (isset($cmd['convert_cmd'])) {
    assert(strpos($cmd['convert_cmd'], '/remove_ads.php') !== false);

    $input_filename = $cmd['download_file'];
    $output_filename = $cmd['output_file'];

    require __DIR__ . '/../www/util/remove_ads.php';
}
