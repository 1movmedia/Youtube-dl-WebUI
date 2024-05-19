<?php

if (!empty($_SERVER['SERVER_NAME'])) {
    die("Forbidden\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

$badly_classified = __DIR__ . '/../tmp/badclass';

if (!is_dir($badly_classified)) {
    mkdir($badly_classified);
}

$it = new RecursiveDirectoryIterator(readlink(__DIR__ . '/../tmp/vids'), FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);

$all_extracted_frames = [];

foreach($it as $dir) {
    foreach($it->getChildren() as $file) {
        if ($file->getExtension() !== 'mp4') {
            continue;
        }
    
        $duration = VideoUtil::getVideoDuration($file->getPathname());

        $usable_duration = min($duration, 600);

        $frames = [
            $usable_duration * (1/4),
            $usable_duration * (1/2),
            $usable_duration * (3/4),
        ];

        echo "Getting frames from $file\n";

        $extracted_frames = VideoUtil::extractFrames($file->getPathname(), $frames, "$badly_classified/");

        $all_extracted_frames = array_merge($all_extracted_frames, $extracted_frames);
    }
}

echo "Done scanning, classifying...\n";

$extracted_frames = $all_extracted_frames;

while(!empty($extracted_frames)) {
    $frames = array_splice($extracted_frames, -20, 20);

    $classification = ImageClassifier::classifyFiles($frames, $config['classifier_api']);

    var_dump([
        'frames' => $frames,
        'classification' => $classification,
    ]);

    foreach($classification as $file_info) {
        if ($file_info['prediction'] === 'ok') {
            if (in_array($file_info['filename'], $frames)) {
                unlink($file_info['filename']);
            }
        }
    }
}
