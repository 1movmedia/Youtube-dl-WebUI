<?php

if (!file_exists(__DIR__ . '/test_data.json')) {
    require_once __DIR__ . '/create_test_set.php';
}
else {
    $test_videos = json_decode(file_get_contents(__DIR__ . '/test_data.json'), true);
}
