#!/usr/bin/php
<?php

# This script print first key frame of a video file to stdout

$input_filename = $argv[1] ?? die("Argument missing");
$input_ss = $argv[2] ?? 0;

# ffprobe -show_frames -select_streams v -print_format flat ph-ph63521b12ca3a4.mp4

$command = trim(<<<ENDOFCOMMAND
ffprobe -show_frames -select_streams v -print_format flat $input_filename 2> /dev/null < /dev/null
ENDOFCOMMAND);

$ph = proc_open($command, [
    1 => ['pipe', 'w'],
], $pipes);

if ($ph === false) {
    die("Failed to execute command: $command");
}

$frames = [];

while (($line = fgets($pipes[1])) !== false) {
    $line = trim($line);

    if (!preg_match('/^frames\.frame\.(\d+)\.([^=]+)=(.+)$/', $line, $matches)) {
        continue;
    }

    $frame_index = $matches[1];
    $key = $matches[2];
    $value = $matches[3];
    
    if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
        $value = substr($value, 1, -1);
    }

    $frame = &$frames[$frame_index];

    $frame[$key] = $value;

    if (@$frame['key_frame'] === '1' && @$frame['pict_type'] == 'I' && isset($frame['pkt_dts_time'])) {
        $pkt_dts_time = $frame['pkt_dts_time'];
        if ($pkt_dts_time > $input_ss) {
            echo ($pkt_dts_time - (1/60)) . "\n";
            break;
        }
    }
}

foreach ($pipes as $pipe) {
    fclose($pipe);
}

proc_terminate($ph, 9);
