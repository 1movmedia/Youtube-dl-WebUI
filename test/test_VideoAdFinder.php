<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/../class/VideoAdFinder.php';
require_once __DIR__ . '/test_data.php';

// Define a test function for the VideoAdTrimmer::classifyFrames method
function testClassifyFrames() {
    $test_videos = downloadTestVideosIfNecessary();

    $total_classifications = 0;
    $bad_classifications = 0;

    foreach ($test_videos as $timestamp => $video_info) {
        if ($video_info['start_ad'] === null) {
            continue;
        }

        $classes = [
            "".$video_info['start_ad'] - 1 => true,
            "".$video_info['start_ad'] + 1 => false,
        ];

        $classifiedFrames = VideoAdFinder::classifyFrames($video_info['filename'], array_keys($classes));

        // Validate outputs
        foreach ($classes as $timestamp => $class) {
            assert(isset($classifiedFrames[$timestamp]), "Error: No classification output for timestamp $timestamp");
            assert(is_bool($classifiedFrames[$timestamp]), "Error: Expected boolean value for timestamp $timestamp, got " . gettype($classifiedFrames[$timestamp]));

            $total_classifications++;
            if ($classifiedFrames[$timestamp] !== $class) {
                $bad_classifications++;
            }
        }
    }

    assert($bad_classifications < ceil($total_classifications / 3));

    echo "All tests passed for classifyFrames.\n";
}

// Define a test function for the VideoAdTrimmer::identifyVideoTimestamps method
function testIdentifyVideoTimestamps() {
    $test_videos = downloadTestVideosIfNecessary();

    foreach ($test_videos as $video_info) {
        var_dump($video_info);

        $start = time();
        $videoTimestamps = VideoAdFinder::identifyAds($video_info['filename'], $video_info['duration']);
        $dur = time() - $start;

        // Validate outputs
        $expectedStart = $video_info['start_ad'] ?? 0;
        $expectedEnd = $video_info['end_ad'] ?? $video_info['duration'];

        assert(abs($videoTimestamps['begin'] - $expectedStart) < 1, "Error: Expected begin ad timestamp to be close to $expectedStart, got " . $videoTimestamps['begin']);
        assert(abs($videoTimestamps['end'] - $expectedEnd) < 1, "Error: Expected end ad timestamp to be close to $expectedEnd, got " . $videoTimestamps['end']);

        assert($dur < 120, "Processing took $dur seconds");
    }

    echo "All tests passed for identifyVideoTimestamps.\n";
}

// Call the test functions
testClassifyFrames();
testIdentifyVideoTimestamps();
