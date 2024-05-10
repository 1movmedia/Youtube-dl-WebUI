<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/VideoAdTrimmer.php';

$test_videos = [
    'https://rt.pornhub.com/view_video.php?viewkey=63e54e89101b0', // 5:43
    'https://rt.pornhub.com/view_video.php?viewkey=ph63c7c160ea58a', // 2:37
    'https://rt.pornhub.com/view_video.php?viewkey=ph634c78ed6c98f', // 1:08
];

function downloadTestVideosIfNecessary() {
    global $test_videos;

    foreach ($test_videos as $index => &$test_video) {
        $filename = "/tmp/test_video_" . $index . ".mp4";

        if ($test_video !== $filename && !file_exists($filename)) {
            // download using yt-dlp
            $command = "yt-dlp -f mp4 -o ".escapeshellarg($filename)." " . escapeshellarg($test_video);
            shell_exec($command);
        }

        // store the downloaded file path
        $test_video = $filename;
    }
    
    return $test_videos;
}

// Define a test function for the VideoAdTrimmer::extractFrames method
function testExtractFrames() {
    global $test_videos;
    $downloaded_files = downloadTestVideosIfNecessary();

    foreach ($downloaded_files as $filename) {
        $timestamps = [1, 10, 11];
        $outputFiles = VideoAdTrimmer::extractFrames($filename, $timestamps);

        // Validate outputs
        foreach ($timestamps as $timestamp) {
            $expectedFile = sprintf("frame_%s.jpg", str_replace([':', ' '], '-', $timestamp));
            assert(isset($outputFiles[$timestamp]), "Error: No file output for timestamp $timestamp");
            assert($outputFiles[$timestamp] === $expectedFile, "Error: Expected filename $expectedFile, got {$outputFiles[$timestamp]}");
        }
    }

    echo "All tests passed for extractFrames.\n";
}

// Define a test function for the VideoAdTrimmer::classifyFrames method
function testClassifyFrames() {
    global $test_videos;
    $downloaded_files = downloadTestVideosIfNecessary();

    foreach ($downloaded_files as $filename) {
        $timestamps = [1, 10, 11];
        $classifiedFrames = VideoAdTrimmer::classifyFrames($filename, $timestamps);

        // Validate outputs
        foreach ($timestamps as $timestamp) {
            assert(isset($classifiedFrames[$timestamp]), "Error: No classification output for timestamp $timestamp");
            assert(is_bool($classifiedFrames[$timestamp]), "Error: Expected boolean value for timestamp $timestamp, got " . gettype($classifiedFrames[$timestamp]));
        }
    }

    echo "All tests passed for classifyFrames.\n";
}

// Define a test function for the VideoAdTrimmer::identifyVideoTimestamps method
function testIdentifyVideoTimestamps() {
    global $test_videos;
    $downloaded_files = downloadTestVideosIfNecessary();

    foreach ($downloaded_files as $filename) {
        $videoTimestamps = VideoAdTrimmer::identifyVideoTimestamps($filename);

        // Validate outputs
        assert(isset($videoTimestamps['begin']), "Error: No begin timestamp output");
        assert(isset($videoTimestamps['end']), "Error: No end timestamp output");
        assert(is_numeric($videoTimestamps['begin']), "Error: Expected numeric value for begin timestamp, got " . gettype($videoTimestamps['begin']));
        assert(is_numeric($videoTimestamps['end']), "Error: Expected numeric value for end timestamp, got " . gettype($videoTimestamps['end']));
        assert($videoTimestamps['begin'] < $videoTimestamps['end'], "Error: Expected begin timestamp to be less than end timestamp");
    }

    echo "All tests passed for identifyVideoTimestamps.\n";
}

downloadTestVideosIfNecessary();

// Call the test functions
testExtractFrames();
testClassifyFrames();
testIdentifyVideoTimestamps();