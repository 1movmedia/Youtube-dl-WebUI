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

        echo "Checked frames: " . json_encode($result) . "\n";

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
        $middle = round($duration / 2, 1);

        for(;;) {
            // Binary search to find start of video
            $low = $startTimestamp; // Start at the beginning of the video
            $high = min($middle, $startTimestamp + 60);
    
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

            if ($startTimestamp < $middle) {
                $check_positions = [];

                for($i = $startTimestamp + 1; $i < $middle && count($check_positions) < 5; $i += 0.5) {
                    $check_positions[] = $i;
                }

                $response = self::classifyFrames($filename, $check_positions);

                $ad_positions = array_keys($response, true, true);

                if (!empty($ad_positions)) {
                    $startTimestamp = $ad_positions[count($ad_positions)-1];
                    continue;
                }
            }

            break;
        }

        $end_filename = $filename;
        $end_offset = 0;

        if ($duration - 600 > 600) {
            $offset_keyframe = VideoUtil::findKeyframeAfter($filename, $duration - 660);

            if ($offset_keyframe > 300) {
                $end_filename = substr($filename, 0, -4) . ".end.mp4";
                $end_offset = $offset_keyframe;

                VideoUtil::cutVideo($filename, ['begin' => $offset_keyframe], $end_filename);
            }
        }

        for(;;) {
            // Binary search to find end of video
            $low = max($middle, $endTimestamp - 60);
            $high = round($endTimestamp - 0.5, 1);

            // Check if the video has an ending ad at all
            $response = self::classifyFrames($end_filename, [ $high - $end_offset ]);
            if (array_values($response)[0]) {
                while ($high - $low > 0.15) {
                    // Check if we're stuck
                    assert($mid != round(($low + $high) / 2, 1));
    
                    $mid = round(($low + $high) / 2, 1);
                    
                    // Ensure the timestamp is within the video duration
                    assert($mid < $duration);
    
                    $response = self::classifyFrames($end_filename, [$mid - $end_offset]);
    
                    if (!array_values($response)[0]) {
                        $low = $mid + 0.1;
                        $endTimestamp = $mid;
                    } else {
                        $high = $mid - 0.1;
                    }
                }
            }

            if ($endTimestamp > $middle) {
                $check_positions = [];

                for($i = $endTimestamp - 1; $i > $middle && count($check_positions) < 5; $i -= 0.5) {
                    $check_positions[] = $i - $end_offset;
                }

                sort($check_positions, SORT_NUMERIC);

                $response = self::classifyFrames($end_filename, $check_positions);

                if (($ad_position = array_search(true, $response, true)) !== false) {
                    $endTimestamp = $ad_position + $end_offset;
                    continue;
                }
            }

            break;
        }

        if ($end_filename !== $filename) {
            unlink($end_filename);
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
