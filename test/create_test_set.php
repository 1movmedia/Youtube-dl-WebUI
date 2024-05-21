<?php

function get_video_set(int $set_size = 10, string $order = 'ORDER BY RANDOM() DESC', bool $cut_start = false): array {
    $db_file = __DIR__ .  '/db.sqlite3';
    
    $db = new SQLite3($db_file, SQLITE3_OPEN_READONLY);
    
    $urls = $db->query('SELECT `url`, `details_json` FROM `urls` ' . $order);
    
    if ($urls === false) {
        die("Can't fetch URLs data");
    }
    
    $test_videos = [];
    
    $mp4_path = __DIR__ . '/../tmp/cache/mp4';
    
    if (!is_dir($mp4_path)) {
        mkdir($mp4_path, 0777, true);
    }
    
    $mp4_path = realpath($mp4_path);
    
    while($row = $urls->fetchArray(SQLITE3_ASSOC)) {
        $entry = json_decode($row['details_json'], true);

        if ($cut_start) {
            $entry['cutEnd'] = 0;
            $entry['cutTo'] = $entry['duration'];
        }
    
        if (!($entry['cutFrom'] || $entry['cutEnd'])) {
            continue;
        }
    
        $entry['url'] = $row['url'];
    
        foreach(['tags', 'pornstars', 'categories', 'userTitle', 'userType', 'userUrl'] as $field_name) {
            unset($entry[$field_name]);
        }
    
        $entry['filename'] = $mp4_path . "/$entry[video_id].mp4";
    
        if (!file_exists($entry['filename'])) {
            $result = null;

            $opts = '';

            if ($entry['cutEnd'] == 0 && $entry['duration'] > 300) {
                $cut_to = max($entry['cutFrom'] * 2, 180);
                if ($cut_to < $entry['duration']) {
                    $opts .= '--postprocessor-args ' . escapeshellarg("-t ");
                }
            }

            $command = 'yt-dlp -o ' . escapeshellarg($entry['filename']) . ' ' . $opts . ' ' . escapeshellarg($row['url']);

            system($command, $result) || die("Can't download video\n");
    
            if ($result != 0) {
                error_log("Download failed");
    
                continue;
            }
        }
    
        $test_videos[] = $entry;
    
        if (count($test_videos) == 10) {
            break;
        }
    }

    return $test_videos;
}

function write_test_set(): void {
    $test_videos = get_video_set();

    if (empty($test_videos)) {
        die("No test data\n");
    }
    
    file_put_contents(__DIR__ . '/test_data.json', json_encode($test_videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
