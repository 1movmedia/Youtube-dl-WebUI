<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

$video_info = [
    'id' => '662a7d80e6a9c',
    'url' => 'https://rt.pornhub.com/view_video.php?viewkey=662a7d80e6a9c',
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

    $argv = [
        __DIR__ . '/../www/util/remove_ads.php',
        $cmd['download_file'],
        $cmd['output_file']
    ];

    var_dump($argv);

    require $argv[0];
}
