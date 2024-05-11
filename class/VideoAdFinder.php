<?php

require_once __DIR__ . '/ImageClassifier.php';
require_once __DIR__ . '/VideoUtil.php';

class VideoAdFinder {

    /**
     * Classify frames using an image classifier and return an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     *
     * @param string $filename The path to the MP4 file.
     * @param array $timestamps An array of timestamps in seconds to classify frames.
     * @return array Returns an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     */
    static function classifyFrames(string $filename, array $timestamps): array {
        // Extract frames
        $files = VideoUtil::extractFrames($filename, $timestamps);

        // Load configuration
        $config = require __DIR__ . '/../config/config.php';

        // Classify frames using image classifier
        $response = ImageClassifier::classifyFiles($files, $config['classifier_api']);

        // Remove temporary files
        array_map('unlink', $files);

        $result = [];

        foreach($response as $file_info) {
            $result[substr($file_info['parameter_name'], 4)] = $file_info['prediction'] !== 'ok';
        }

        return $result;
    }

    /**
     * Finds when the actual video starts and ends by checking starting and ending frames for ads.
     * 
     * @param $filename MP4 filename
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

        // Binary search to find start of video
        $low = 1; // Start at the beginning of the video
        $high = min(round($duration / 2, 1), 5 * 60);

        // Initialize if necessary
        assert($mid = PHP_INT_MAX);

        // Check if the video has starting ad at all
        $response = self::classifyFrames($filename, [$low]);
        if (array_values($response)[0]) {
            while ($high - $low > 0.15) {
                // Check if we're stuck
                assert($mid != round(($low + $high) / 2, 1));

                $mid = round(($low + $high) / 2, 1);

                // Ensure the timestamp is within the video duration
                assert($mid < $duration);

                $response = self::classifyFrames($filename, [$mid]);
                
                if (!$response["$mid"]) {
                    $high = $mid;
                    $startTimestamp = $mid;
                } else {
                    $low = $mid;
                }
            }
        }

        // Binary search to find end of video
        $low = max(round($duration / 2, 1), $duration - 5 * 60);
        $high = round($duration - 0.5, 1);

        // Check if the video has an ending ad at all
        $response = self::classifyFrames($filename, [ $high ]);
        if (array_values($response)[0]) {
            while ($high - $low > 0.15) {
                // Check if we're stuck
                assert($mid != round(($low + $high) / 2, 1));

                $mid = round(($low + $high) / 2, 1);
                
                // Ensure the timestamp is within the video duration
                assert($mid < $duration);

                $response = self::classifyFrames($filename, [$mid]);

                if (!$response["$mid"]) {
                    $low = $mid + 0.1;
                    $endTimestamp = $mid;
                } else {
                    $high = $mid - 0.1;
                }
            }
        }

        return [
            'begin' => $startTimestamp,
            'end' => $endTimestamp,
        ];
    }

}
