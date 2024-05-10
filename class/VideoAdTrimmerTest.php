<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/VideoAdTrimmer.php';

$test_videos = [
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=63e54e89101b0',
        'duration' => '5:43',
        'start_ad' => 10.11,
        'end_ad' => null
    ],
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph63c7c160ea58a',
        'duration' => '2:37',
        'start_ad' => 13.06,
        'end_ad' => null
    ],
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph634c78ed6c98f',
        'duration' => '1:08',
        'start_ad' => null,
        'end_ad' => 59.21
    ],
    [
        'url' => 'https://rt.pornhub.com/view_video.php?viewkey=ph62191ed983427',
        'duration' => '9:42',
        'start_ad' => 2.18,
        'end_ad' => 570.05 // Converted 9:30.05 to seconds
    ],
];

function downloadTestVideosIfNecessary(&$test_videos) {
    global $test_videos;

    foreach ($test_videos as $index => $video_info) {
        $filename = "/tmp/test_video_" . md5($video_info['url']) . ".mp4";

        if ($test_video !== $filename && !file_exists($filename)) {
            // download using yt-dlp
            $command = "yt-dlp -f mp4 -o ".escapeshellarg($filename)." " . escapeshellarg($test_video);
            shell_exec($command);
        }

        // store the downloaded file path
        $test_videos[$index]['filename'] = $filename;
    }
    
    return $test_videos;
}

// Define a test function for the VideoAdTrimmer::extractFrames method
function testExtractFrames() {
    global $test_videos;
    $downloaded_files = downloadTestVideosIfNecessary($test_videos);

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
    $downloaded_files = downloadTestVideosIfNecessary($test_videos);

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
function testIdentifyVideoTimestamps(&$test_videos) {
    global $test_videos;
    $downloaded_files = downloadTestVideosIfNecessary($test_videos);

    foreach ($test_videos as $video_info) {
        $videoTimestamps = VideoAdTrimmer::identifyVideoTimestamps($video_info['filename']);

        // Validate outputs
        $expectedStartAd = $video_info['start_ad'] ?? 0;
        $expectedEndAd = $video_info['end_ad'] ?? $video_info['duration'];
        assert(abs($videoTimestamps['begin'] - $expectedStartAd) < 1, "Error: Expected begin ad timestamp to be close to $expectedStartAd, got " . $videoTimestamps['begin']);
        assert(abs($videoTimestamps['end'] - $expectedEndAd) < 1, "Error: Expected end ad timestamp to be close to $expectedEndAd, got " . $videoTimestamps['end']);
    }

    echo "All tests passed for identifyVideoTimestamps.\n";
}

downloadTestVideosIfNecessary($test_videos);

// Call the test functions
testExtractFrames();
testClassifyFrames();
testIdentifyVideoTimestamps($test_videos);
/**
 * Converts a duration string in the format "hh:mm:ss" to seconds.
 *
 * @param string $duration The duration string.
 * @return int The duration in seconds.
 */
function convertDurationToSeconds($duration) {
    $parts = explode(':', $duration);
    $seconds = 0;
    $multiplier = 1;

    while ($parts) {
        $seconds += $multiplier * array_pop($parts);
        $multiplier *= 60;
    }

    return $seconds;
}
