<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

require_once __DIR__ . '/test_data.php';

// Define a test function for the VideoAdTrimmer::extractFrames method
function testExtractFrames() {
    $test_videos = downloadTestVideosIfNecessary();

    foreach ($test_videos as $video_info) {
        $timestamps = [
            0, $video_info['duration'] - 1, $video_info['duration'] / 2
        ];

        $outputFiles = VideoUtil::extractFrames($video_info['filename'], $timestamps);

        // Validate outputs
        foreach ($timestamps as $timestamp) {
            assert(isset($outputFiles["$timestamp"]), "Error: No file output for timestamp $timestamp");
            $filename = $outputFiles["$timestamp"];
            assert(file_exists($filename));
        }
    }

    echo "All tests passed for extractFrames.\n";
}

testExtractFrames();
