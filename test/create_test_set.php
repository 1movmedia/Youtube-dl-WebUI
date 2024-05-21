<?php

$set_size = 10;

$db_file = __DIR__ .  '/db.sqlite3';

$db = new SQLite3($db_file, SQLITE3_OPEN_READONLY);

$urls = $db->query('SELECT `url`, `details_json` FROM `urls` ORDER BY RANDOM() DESC');

if ($urls === false) {
    die("Can't fetch URLs data");
}

$test_videos = [];

while($row = $urls->fetchArray(SQLITE3_ASSOC)) {
    $entry = json_decode($row['details_json'], true);

    if (!($entry['cutFrom'] && $entry['cutEnd'])) {
        continue;
    }

    $entry['url'] = $row['url'];

    foreach(['tags', 'pornstars', 'categories', 'userTitle', 'userType', 'userUrl'] as $field_name) {
        unset($entry[$field_name]);
    }

    $entry['filename'] = realpath(__DIR__ . '/../tmp') . "/$entry[video_id].mp4";

    if (!file_exists($entry['filename'])) {
        system('yt-dlp -o ' . escapeshellarg($entry['filename']) . ' ' . escapeshellarg($row['url'])) || die("Can't download video\n");
    }

    $test_videos[] = $entry;

    if (count($test_videos) == 10) {
        break;
    }
}

if (empty($test_videos)) {
    die("No test data\n");
}

file_put_contents(__DIR__ . '/test_data.json', json_encode($test_videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Done\n";
