<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/VideoAdTrimmer.php';

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

// Define a test function for the VideoAdTrimmer::extractFrames method
function testExtractFrames() {
    $test_videos = downloadTestVideosIfNecessary();

    foreach ($test_videos as $video_info) {
        $timestamps = [
            0, $video_info['duration'] - 1, $video_info['duration'] / 2
        ];

        $outputFiles = VideoAdTrimmer::extractFrames($video_info['filename'], $timestamps);

        // Validate outputs
        foreach ($timestamps as $timestamp) {
            assert(isset($outputFiles["$timestamp"]), "Error: No file output for timestamp $timestamp");
            $filename = $outputFiles["$timestamp"];
            assert(file_exists($filename));
        }
    }

    echo "All tests passed for extractFrames.\n";
}

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

        $classifiedFrames = VideoAdTrimmer::classifyFrames($video_info['filename'], array_keys($classes));

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
        $videoTimestamps = VideoAdTrimmer::identifyAds($video_info['filename']);

        // Validate outputs
        $expectedStartAd = $video_info['start_ad'] ?? 0;
        $expectedEndAd = $video_info['end_ad'] ?? $video_info['duration'];
        assert(abs($videoTimestamps['begin'] - $expectedStartAd) < 1, "Error: Expected begin ad timestamp to be close to $expectedStartAd, got " . $videoTimestamps['begin']);
        assert(abs($videoTimestamps['end'] - $expectedEndAd) < 1, "Error: Expected end ad timestamp to be close to $expectedEndAd, got " . $videoTimestamps['end']);
    }

    echo "All tests passed for identifyVideoTimestamps.\n";
}

// Call the test functions
testExtractFrames();
testClassifyFrames();
testIdentifyVideoTimestamps();
