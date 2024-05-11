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

}
