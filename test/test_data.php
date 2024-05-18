<?php

$test_videos = [
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=63e54e89101b0',
        'duration' => 343,
        'start_ad' => 10.11,
        'end_ad' => null
    ],
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph63c7c160ea58a',
        'duration' => 157,
        'start_ad' => 13.06,
        'end_ad' => null
    ],
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph634c78ed6c98f',
        'duration' => 68,
        'start_ad' => null,
        'end_ad' => 59.21
    ],
    [
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
