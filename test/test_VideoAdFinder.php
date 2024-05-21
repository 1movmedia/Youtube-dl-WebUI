<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require __DIR__ . '/../www/vendor/autoload.php';

require_once __DIR__ . '/test_data.php';

// Define a test function for the VideoAdTrimmer::classifyFrames method
function testClassifyFrames() {
    global $test_videos;

    $total_classifications = 0;
    $bad_classifications = 0;

    foreach ($test_videos as $timestamp => $video_info) {
        if ($video_info['cutFrom'] === null) {
            continue;
        }

        $classifiedFrames = VideoAdFinder::classifyFrames($video_info['filename'], 0, $video_info['cutFrom'] * 2 + 5);

        assert(!empty($classifiedFrames));
        assert(count($classifiedFrames) > $video_info['cutFrom'] / 7);

        // Validate outputs
        foreach ($classifiedFrames as $timestamp => $class) {
            $ad_time = $timestamp < $video_info['cutFrom'] || $timestamp > $video_info['cutTo'];

            $total_classifications++;
            if ($class !== $ad_time) {
                $bad_classifications++;
            }
        }
    }

    assert($bad_classifications < ceil($total_classifications / 3));

    echo "All tests passed for classifyFrames.\n";
}

// Define a test function for the VideoAdTrimmer::identifyVideoTimestamps method
function testIdentifyVideoTimestamps() {
    global $test_videos;

    foreach ($test_videos as $video_info) {
        echo "Testing on video $video_info[video_id]\n";

        $cache = __DIR__ . '/../tmp/cache/' . $video_info['video_id'];
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }

        $start = time();
        $videoTimestamps = VideoAdFinder::identifyAds($video_info['filename'], $video_info['duration'], $cache);
        $dur = time() - $start;

        // Validate outputs
        $expectedStart = $video_info['cutFrom'] ?? 0;
        $expectedEnd = $video_info['cutTo'] ?? $video_info['duration'];

        assert(abs($videoTimestamps['begin'] - $expectedStart) < 5, "Error: Expected begin ad timestamp to be close to $expectedStart, got " . $videoTimestamps['begin']);
        assert(abs($videoTimestamps['end'] - $expectedEnd) < 5, "Error: Expected end ad timestamp to be close to $expectedEnd, got " . $videoTimestamps['end']);

        if ($dur > 300) {
            error_log("Processing took $dur seconds");
        }
    }

    echo "All tests passed for identifyVideoTimestamps.\n";
}

// Call the test functions
// testClassifyFrames();
testIdentifyVideoTimestamps();
