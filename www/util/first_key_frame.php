#!/usr/bin/php
<?php

# This script print first key frame of a video file to stdout

$input_filename = $argv[1] ?? die("Argument missing");
$input_ss = floatval($argv[2]) ?? 0;

require __DIR__ . '/../class/VideoUtil.php';

$keyframe = VideoUtil::findKeyframeAfter($input_filename, $input_ss);

echo "$keyframe\n";
