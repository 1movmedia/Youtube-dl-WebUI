#!/usr/bin/php
<?php

# This script print first key frame of a video file to stdout

$input_filename = $argv[1] ?? die("Argument missing");
$input_ss = $argv[2] ?? 0;

$command = [`which ffprobe`, '-show_frames', '-select_streams', 'v', '-print_format', 'flat', escapeshellarg($input_filename)];

$ph = proc_open($command, [
    0 => ['file', '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['file', '/dev/null', 'a'],
], $pipes);

if ($ph === false) {
    die("Failed to execute command: " . implode(' ', $command));
}

$frames = [];

$ss = $input_ss;

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

    if (isset($frame['pkt_dts_time'])) {
        $pkt_dts_time = $frame['pkt_dts_time'];

        if (@$frame['key_frame'] === '1' && @$frame['pict_type'] == 'I') {
            if ($pkt_dts_time >= $input_ss) {
                $ss = $pkt_dts_time;
                break;
            }
        }

        if ($pkt_dts_time > $input_ss + 10) {
            break;
        }
    }
}

echo "$ss\n";

foreach ($pipes as $pipe) {
    fclose($pipe);
}

proc_terminate($ph, 9);

proc_close($ph);
