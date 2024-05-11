<?php

$input_filename = $argv[1] ?? die("Argument #1 missing");
$output_filename = $argv[2] ?? die("Argument #2 missing");

require_once __DIR__ . '/../class/VideoUtil.php';
require_once __DIR__ . '/../class/VideoAdFinder.php';

$video_duration = VideoUtil::getVideoDuration($input_filename);
$video_constraints = VideoAdFinder::identifyAds($input_filename, $video_duration);

if ($video_constraints['begin'] == 0 && $video_constraints['end'] === $video_duration) {
    link($input_filename, $output_filename);
    exit(0);
}

VideoUtil::cutVideo($input_filename, $video_constraints, $output_filename);
