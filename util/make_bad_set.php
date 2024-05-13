<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/../class/VideoAdFinder.php';

$test_videos = [
    [
        'id' => '63e54e89101b0',
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=63e54e89101b0',
        'duration' => 343,
        'start_ad' => 10.11,
        'end_ad' => null
    ],
    [
        'id' => 'ph63c7c160ea58a',
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph63c7c160ea58a',
        'duration' => 157,
        'start_ad' => 13.06,
        'end_ad' => null
    ],
    [
        'id' => 'ph634c78ed6c98f',
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph634c78ed6c98f',
        'duration' => 68,
        'start_ad' => null,
        'end_ad' => 59.21
    ],
    [
        'id' => 'ph62191ed983427',
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph62191ed983427',
        'duration' => 582,
        'start_ad' => 2.18,
        'end_ad' => 570.05
    ],
];

function downloadTestVideosIfNecessary() {
    global $test_videos;

    foreach ($test_videos as $index => $video_info) {
        if (empty($test_videos[$index]['filename'])) {
            $filename = "/tmp/test_video_" . md5($video_info['url']) . ".mp4";

            if (!file_exists($filename)) {
                // download using yt-dlp
                $command = "yt-dlp -f mp4 -o ".escapeshellarg($filename)." " . escapeshellarg($video_info['url']);
                shell_exec($command);
            }
    
            // store the downloaded file path
            $test_videos[$index]['filename'] = $filename;
        }
    }
    
    return $test_videos;
}

downloadTestVideosIfNecessary();

$config = require __DIR__ . '/../config/config.php';

foreach($test_videos as $test_video) {
    $sections = [];

    $start = $test_video['start_ad'] ?? 0;
    $end = $test_video['end_ad'] ?? $test_video['duration'];

    $sections[] = [
        's' => $start,
        'e' => $end,
        'c' => 'ok',
    ];

    // if ($start != 0) {
    //     $sections[] = [
    //         's' => 0,
    //         'e' => $start,
    //         'c' => 'promo',
    //     ];
    // }

    // if ($end != $test_video['duration']) {
    //     $sections[] = [
    //         's' => $end,
    //         'e' => $test_video['duration'],
    //         'c' => 'promo',
    //     ];
    // }

    $path = __DIR__ . "/../tmp/badset/$test_video[id]";

    @mkdir($path, 0777, true);

    foreach($sections as $section) {
        $frames = [];

        $s = $section['s'] + 1;
        $e = $section['e'] - 1;

        $dur = $e - $s;
        
        for($i = 0; $i < 1; $i += 1 / 50) {
            $frames[] = $s + $dur * $i;
        }

        $image_files = VideoUtil::extractFrames($test_video['filename'], $frames, "$path/");

        $result = ImageClassifier::classifyFiles($image_files, $config['classifier_api']);

        foreach($result as $file) {
            if ($file['prediction'] == $section['c']) {
                unlink($file['filename']);
            }
        }
    }

    @rmdir($path);
}
