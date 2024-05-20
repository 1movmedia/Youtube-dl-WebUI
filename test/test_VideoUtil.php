<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

require_once __DIR__ . '/test_data.php';

function testKeyframes() {
    $test_videos = downloadTestVideosIfNecessary();

    foreach ($test_videos as $video_info) {
        $frames = VideoUtil::keyframes($video_info['filename'], 1, 60, 4);

        assert(count($frames) == 4);

        $previous = -1;

        // Validate outputs
        foreach ($frames as $frame) {
            assert($frame > $previous);
            assert(is_numeric($frame));
            assert($frame > 0);
            assert($frame < 60);

            $previous = $frame;
        }
    }

    echo "All tests passed for keyframes.\n";
}

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

testKeyframes();
testExtractFrames();
