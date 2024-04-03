#!/usr/bin/php
<?php

# This script print first key frame of a video file to stdout

$input_filename = $argv[1] ?? die("Argument missing");
$input_ss = $argv[2] ?? 0;

$command = trim(<<<ENDOFCOMMAND
ffprobe -show_frames -select_streams v -print_format flat $input_filename 2> /dev/null < /dev/null
ENDOFCOMMAND);

$ph = proc_open($command, [
    1 => ['pipe', 'w'],
], $pipes);

if ($ph === false) {
    die("Failed to execute command: $command");
}

$proc_status = proc_get_status($ph);

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

posix_kill($proc_status['pid'], 9);
