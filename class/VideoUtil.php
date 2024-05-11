<?php

class VideoUtil {

    static function findKeyframeAfter(string $filename, float $after_position): float {
        $command = [trim(`which ffprobe`), '-show_frames', '-select_streams', 'v', '-print_format', 'flat', escapeshellarg($filename)];

        $command = implode(' ', $command) . " 2> /dev/null";

        if (($ph = popen($command, 'r')) !== false) {
            try {
                $frames = [];
            
                $ss = $after_position;
        
                $successful = false;
                
                while (($line = fgets($ph)) !== false) {
                    $line = trim($line);
                
                    if (!preg_match('/^frames\.frame\.(\d+)\.([^=]+)=(.+)$/', $line, $matches)) {
                        continue;
                    }
        
                    $successful = true;
                
                    $frame_index = $matches[1];
                    $key = $matches[2];
                    $value = $matches[3];
                    
                    if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                        $value = substr($value, 1, -1);
                    }
                
                    $frame = &$frames[$frame_index];
                
                    $frame[$key] = $value;
                
                    if (isset($frame['pkt_dts_time'])) {
                        $pkt_dts_time = $frame['pkt_dts_time'];
                
                        if (@$frame['key_frame'] === '1' && isset($frame['pict_type']) && @$frame['pict_type'] == 'I') {
                            if ($pkt_dts_time >= $after_position) {
                                $ss = floatval($pkt_dts_time);
                                break;
                            }
                        }
                
                        if ($pkt_dts_time > $after_position + 10) {
                            break;
                        }
                    }
                }
                
                if (!$successful) {
                    throw new Exception("ffprobe didn't return any data! (command: $command)");
                }

                return $ss;
            }
            finally {
                pclose($ph);
            }
        }
        else {
            throw new Exception("Can't execute ffprobe!");
        }
    }

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

            // Verify that all files were created
            foreach ($outputFiles as $timestamp => $outputFile) {
                if (!file_exists($outputFile)) {
                    throw new Exception("Expected frame file was not created: $outputFile");
                }
            }
        }

        // Return array of output files
        return $outputFiles;
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
