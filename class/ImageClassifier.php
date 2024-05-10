<?php

class ImageClassifier {

    /**
     * Sends images to the server for classification.
     *
     * @param array $files An array of image files to be classified.
     * @return string The server's response as a string.
     */
    static function classifyFiles(array $files, string $base_url = 'http://localhost:5000'): array {
        $url = $base_url . '/predict';
        $data = [];
        $headers = [
            'Accept: application/json',
            'Expect:',
            'Transfer-Encoding:',
        ];

        foreach ($files as $index => $file) {
            assert(file_exists($file));
            
            $data["file{$index}"] = new CURLFile($file, mime_content_type($file));
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode != 200) {
            throw new Exception("HTTP request failed with status code $httpCode: $result");
        }

        return is_string($result) ? json_decode($result, true) : $result;
    }

}
