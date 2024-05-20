<?php

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

        echo "Classification API response: " . json_encode($result) . "\n";

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

        $start_frames = VideoUtil::keyframes($filename, 0, min(60, $middle));
        $start_classes = self::classifyFrames($filename, $start_frames);

        foreach($start_classes as $frame => $is_ad) {
            echo "Frame at $frame is " . ($is_ad ? "ad" : "not ad") . "\n";

            $startTimestamp = $frame;

            if (!$is_ad) {
                echo "Video start: $startTimestamp\n";

                break;
            }
        }

        $end_frames = VideoUtil::keyframes($filename, max($duration - 60, $middle), $duration);
        $end_classes = self::classifyFrames($filename, $end_frames);

        foreach($end_classes as $frame => $is_ad) {
            echo "Frame at $frame is " . ($is_ad ? "ad" : "not ad") . "\n";

            $endTimestamp = $frame;

            if ($is_ad) {
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
