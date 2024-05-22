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
    static function classifyFrames(string $filename, float $start, float $end, ?string $cache = null, $detailed = false): array {
        $keyframesBinary = trim(`which keyframes`);  // Path to the keyframes binary
        if ($cache === null) {
            $tempDir = sys_get_temp_dir();
            $cacheFile = null;
        }
        else {
            $tempDir = "$cache/frames/$start-$end";

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $cacheFile = $tempDir . '/classes' . ($detailed ? '-detailed' : '') . '.json';
        }

        $indexFile = $tempDir . '/index.json';

        if ($cache !== null && file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // Generate command to extract keyframes
        $command = sprintf('%s -f %s -s %.3f -e %.3f -d %s -j -i',
            escapeshellcmd($keyframesBinary),
            escapeshellarg($filename),
            $start,
            $end,
            escapeshellarg($tempDir)
        );

        echo "Frame extraction command: $command\n";

        // Execute the command
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Failed to extract keyframes using command: $command");
        }

        // Read the index file
        if (!file_exists($indexFile)) {
            throw new Exception("Index file not found: $indexFile");
        }

        $indexContent = file_get_contents($indexFile);
        $files = json_decode($indexContent, true);

        // Load configuration
        $config = require __DIR__ . '/../config/config.php';

        // Classify frames using image classifier
        $response = ImageClassifier::classifyFiles($files, $config['classifier_api']);

        if ($cache === null) {
            // Remove temporary files
            array_map('unlink', $files);
            unlink($indexFile);
        }

        $result = [];

        foreach ($response as $file_info) {
            // Extract timestamp from the filename
            $timestamp = floatval(substr($file_info['parameter_name'], 4));
            if ($detailed) {
                $result["$timestamp"] = [
                    'is_ad' => $file_info['prediction'] !== 'ok',
                    'filename' => $file_info['filename'],
                    'timestamp' => $timestamp,
                    'file_info' => $file_info,
                ];
            }
            else {
                $result["$timestamp"] = $file_info['prediction'] !== 'ok';
            }
        }

        if ($cacheFile !== null) {
            file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
    static function identifyAds(string $filename, float $duration = -1, ?string $cache = null): array {
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

        // Determine start timestamp
        $start_classes = self::classifyFrames($filename, 0, min(90, $middle), $cache);
        $startTimestamp = self::detectTransition($start_classes, 0.75, 0.33, 0.9, 0);
        
        echo "Video start: $startTimestamp\n";

        // Determine end timestamp
        $end_classes = self::classifyFrames($filename, max($duration - 90, $middle), $duration, $cache);
        $endTimestamp = self::detectTransition($end_classes, 0.75, 0.33, 0.9, $duration, true);
        
        echo "Video end: $endTimestamp\n";

        if ($endTimestamp - $startTimestamp < 15) {
            throw new Exception("Remaining video is too short!");
        }

        return [
            'begin' => $startTimestamp,
            'end' => $endTimestamp,
        ];
    }

    /**
     * Detects the transition from ads to non-ads using a weighted moving average.
     *
     * @param array $classes The associative array of frame classifications with timestamps as keys and boolean values.
     * @param float $initialValue The initial value for the weighted moving average.
     * @param float $stopThreshold The threshold below which to stop.
     * @param float $weightPrev The weight of the previous value in the moving average.
     * @param bool $reverse If true, processes the frames in reverse.
     * @return float The timestamp of the detected transition.
     */
    private static function detectTransition(array $classes, float $initialValue, float $stopThreshold, float $weightPrev, float $startTimestamp, bool $reverse = false): float {
        $weightCur = 1 - $weightPrev;
        $value = $initialValue;
        $prevAd = true;
        $transitionTimestamp = $startTimestamp;

        foreach ($reverse ? array_reverse($classes, true) : $classes as $frame => $isAd) {
            echo "Frame at $frame is " . ($isAd ? "ad" : "not ad") . " (MA: $value)\n";

            if ($reverse) {
                if ($isAd) {
                    $transitionTimestamp = $frame;
                }
            }
            else {
                if ($prevAd && !$isAd) {
                    $transitionTimestamp = $frame;
                }
            }

            $value = ($value * $weightPrev) + ($isAd ? $weightCur : 0);

            if ($value < $stopThreshold && !$isAd) {
                break;
            }

            $prevAd = $isAd;
        }

        return $transitionTimestamp;
    }
}
