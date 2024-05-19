#!/usr/bin/php
<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die('This script can only be run from the command line!');
}

require_once 'class/Downloader.php';
require_once 'class/FileHandler.php';

$config = require __DIR__.'/config/config.php';

if (!Downloader::check_can_download($config)) {
    echo "Simultaneous downloads limit reached. Sleeping\n";
    while (!Downloader::check_can_download($config)) {
        sleep(5);
    }
    echo "Download capacity is available again\n";
}

$fh = new FileHandler();

foreach($fh->list_deferred() as $def_log => $log_info) {
    echo "Deferred download found: $def_log\n";

    $log = $log_info['log_filename'];
    $cmd = $log_info['cmd'];

    rename($def_log, $log);

    $output = shell_exec($cmd);

    if ($log !== '/dev/null') {
        file_put_contents($log, "Output:\n\n$output", FILE_APPEND);
    }

    echo "Executed deferred download ($log) with command \"$cmd\"\n";

    break;
}

# Sleep before restarting the script
sleep(5);
