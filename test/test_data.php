<?php

$test_videos = json_decode(file_get_contents(__DIR__ . '/test_data.json'), true);

function downloadTestVideosIfNecessary() {
    global $test_videos;

    foreach ($test_videos as $index => $video_info) {
        if (empty($test_videos[$index]['filename'])) {
            $filename = __DIR__ . "/../tmp/test_video_$index.mp4";

            if (!file_exists($filename)) {
                echo "Downloading $filename\n";
    
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
