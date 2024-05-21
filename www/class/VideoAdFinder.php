<?php

class VideoAdFinder {

    /**
     * Classify frames using an image classifier and return an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     *
     * @param string $filename The path to the MP4 file.
     * @param float $start The start time for frame extraction.
     * @param float $end The end time for frame extraction.
     * @return array Returns an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     */
    static function classifyFrames(string $filename, float $start, float $end): array {
        $keyframesBinary = '/home/m/.local/bin/keyframes';  // Path to the keyframes binary
        $tempDir = sys_get_temp_dir();

        // Generate command to extract keyframes
        $command = sprintf('%s -f %s -s %.3f -e %.3f -d %s -j -i',
            escapeshellcmd($keyframesBinary),
            escapeshellarg($filename),
            $start,
            $end,
            escapeshellarg($tempDir)
        );

        // Execute the command
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Failed to extract keyframes using command: $command");
        }

        // Read the index file
        $indexFile = $tempDir . '/index.json';
        if (!file_exists($indexFile)) {
            throw new Exception("Index file not found: $indexFile");
        }

        $indexContent = file_get_contents($indexFile);
        $files = json_decode($indexContent, true);

        // Load configuration
        $config = require __DIR__ . '/../config/config.php';

        // Classify frames using image classifier
        $response = ImageClassifier::classifyFiles($files, $config['classifier_api']);

        // Remove temporary files
        array_map('unlink', $files);
        unlink($indexFile);

        $result = [];

        foreach ($response as $file_info) {
            // Extract timestamp from the filename
            $timestamp = floatval(substr($file_info['parameter_name'], 4));
            $result["$timestamp"] = $file_info['prediction'] !== 'ok';
        }

        echo "Classification API response: " . json_encode($result) . "\n";

        return $result;
    }

    /**
     * Finds when the actual video starts and ends by checking starting and ending frames for ads.
     * 
     * @param string $filename MP4 filename
     * @param float $duration The duration of the video in seconds (optional)
     * @return array Returns an associative array with keys "begin" and "end" and timestamps for values.
     */
    static function identifyAds(string $filename, float $duration = -1): array {
        if ($duration < 0) {
            // Get video duration
            $duration = VideoUtil::getVideoDuration($filename);
        }

        // Ensure that the timestamps are within the video duration
        if ($duration <= 0) {
            throw new Exception("Video duration must be greater than zero.");
        }

        // Initialize start and end timestamps
        $startTimestamp = 0;
        $endTimestamp = $duration;
        $middle = round($duration / 2, 1);

        $stop_threshold = 0.33;
        $weight_prev = 0.9;
        $weight_cur = 1 - $weight_prev;

        $start_classes = self::classifyFrames($filename, 0, min(60, $middle));

        $value = 0.75;

        $prev_ad = true;

        foreach ($start_classes as $frame => $is_ad) {
            echo "Frame at $frame is " . ($is_ad ? "ad" : "not ad") . "\n";

            if ($prev_ad && !$is_ad) {
                $startTimestamp = $frame;
            }

            $value = ($value * $weight_prev) + ($is_ad ? $weight_cur : 0);

            if ($value < $stop_threshold && !$is_ad) {
                break;
            }

            $prev_ad = $is_ad;
        }

        echo "Video start: $startTimestamp\n";

        $end_classes = self::classifyFrames($filename, max($duration - 60, $middle), $duration);

        foreach (array_reverse($end_classes, true) as $frame => $is_ad) {
            echo "Frame at $frame is " . ($is_ad ? "ad" : "not ad") . "\n";

            $endTimestamp = $frame;

            if (!$is_ad) {
                echo "Video end: $endTimestamp\n";
                break;
            }
        }

        if ($endTimestamp - $startTimestamp < 15) {
            throw new Exception("Remaining video is too short!");
        }

        return [
            'begin' => $startTimestamp,
            'end' => $endTimestamp,
        ];
    }

}
