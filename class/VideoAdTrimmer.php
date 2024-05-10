<?php

require_once __DIR__ . '/ImageClassifier.php';

class VideoAdTrimmer {

    /**
     * Extracts specific MP4 file frames into JPEG files using a single ffmpeg command, and returns an array with timestamps as keys.
     *
     * @param string $filename The path to the MP4 file.
     * @param array $timestamps An array of timestamps in seconds to extract frames.
     * @return array Returns an associative array with timestamps as keys and the paths to the extracted frame files as values.
     */
    /**
     * @throws Exception if ffmpeg command fails
     */
    static function extractFrames(string $filename, array $timestamps, string $prefix = '/tmp/'): array {
        // Initialize array to store output file names
        $outputFiles = [];
        // Initialize array to store ffmpeg command outputs
        $outputs = [];

        $fn_chs = md5($filename);

        // Loop through each timestamp
        foreach ($timestamps as $timestamp) {
            // Create output file name
            $outputFile = "$prefix$fn_chs-frame_$timestamp.jpg";
            // Store output file name in associative array with timestamp as key
            $outputFiles["$timestamp"] = $outputFile;

            if (file_exists($outputFile)) {
                continue;
            }

            // Construct ffmpeg command output for this timestamp
            $outputs[] = " -ss " . escapeshellarg(sprintf("%f", $timestamp)) . " -vframes 1 " . escapeshellarg($outputFile);
        }

        if (!empty($outputs)) {
            // Construct base ffmpeg command
            $cmd = escapeshellcmd("ffmpeg -loglevel error -i " . escapeshellarg($filename));
    
            // Add each output to the ffmpeg command
            foreach ($outputs as $output) {
                $cmd .= $output;
            }
    
            // Initialize output array
            $output = [];
            // Initialize return value
            $returnValue = 0;
    
            // Execute ffmpeg command
            exec($cmd, $output, $returnValue);
    
            // Check if command failed
            if ($returnValue !== 0) {
                throw new Exception("FFmpeg command failed with return code $returnValue");
            }
        }

        // Return array of output files
        return $outputFiles;
    }

    /**
     * Classify frames using an image classifier and return an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     *
     * @param string $filename The path to the MP4 file.
     * @param array $timestamps An array of timestamps in seconds to classify frames.
     * @return array Returns an associative array with timestamps as keys and boolean values indicating that the frame is a part of an ad.
     */
    static function classifyFrames(string $filename, array $timestamps): array {
        // Extract frames
        $files = self::extractFrames($filename, $timestamps);

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
    static function identifyVideoTimestamps(string $filename, float $duration = -1): array {
        if ($duration < 0) {
            // Get video duration
            $duration = self::getVideoDuration($filename);
        }
    
        // Initialize start and end timestamps
        $startTimestamp = 0;
        $endTimestamp = $duration;
    
        // Binary search to find start of video
        $low = 0;
        $high = intval($duration / 2);
        while ($low <= $high) {
            $mid = intval(($low + $high) / 2);
            $response = self::classifyFrames($filename, [$mid]);
            if (!$response[$mid]) {
                $high = $mid - 1;
                $startTimestamp = $mid;
            } else {
                $low = $mid + 1;
            }
        }
    
        // Binary search to find end of video
        $low = intval($duration / 2);
        $high = $duration;
        while ($low <= $high) {
            $mid = intval(($low + $high) / 2);
            $response = self::classifyFrames($filename, [$mid]);
            if (!$response[$mid]) {
                $low = $mid + 1;
                $endTimestamp = $mid;
            } else {
                $high = $mid - 1;
            }
        }
    
        return [
            'begin' => $startTimestamp,
            'end' => $endTimestamp,
        ];
    }

    /**
     * Gets the duration of a video file in seconds.
     * 
     * @param string $filename The path to the video file.
     * @return int The duration of the video file in seconds.
     */
    static function getVideoDuration(string $filename): int {
        $cmd = escapeshellcmd("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ". escapeshellarg($filename));
        $output = [];
        exec($cmd, $output);
        return (int) round(floatval($output[0]));
    }

}
