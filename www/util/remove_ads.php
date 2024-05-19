<?php

$input_filename = $argv[1] ?? die("Argument #1 missing");
$output_filename = $argv[2] ?? die("Argument #2 missing");

require_once __DIR__ . '/../vendor/autoload.php';

echo "Removing ads from $input_filename ...\n";

$video_duration = VideoUtil::getVideoDuration($input_filename);

echo "Searching for ads...\n";

$video_constraints = VideoAdFinder::identifyAds($input_filename, $video_duration);

if ($video_constraints['begin'] == 0 && $video_constraints['end'] === $video_duration) {
    echo "No ads found.\n";
    link($input_filename, $output_filename);
    exit(0);
}

echo "Ads found. Cut constraints: " . json_encode($video_constraints) . "\n";

VideoUtil::cutVideo($input_filename, $video_constraints, $output_filename);

echo "Finished cutting\n";
