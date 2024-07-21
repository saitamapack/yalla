<?php
// Function to fetch data from a URL using cURL
function get_data($url, $headers = []) {
    $ch = curl_init();
    $timeout = 30; // Timeout in seconds
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "<br>";
    }
    curl_close($ch);
    return $data;
}

try {
    // Cloudinary credentials
    $cloudinary_cloud_name = "ds8s4fn5p";
    $cloudinary_api_key = "731143319737329";
    $cloudinary_api_secret = "HD479cTPf2KY6iI7LEuJzrvNTpM";
    $upload_preset = "yeufjqiy";

    // File path to matches.json on Cloudinary
    $matchesFile = 'matches.json';

    // Step 1: Load JSON from Cloudinary
    $json_url = 'https://res.cloudinary.com/'.$cloudinary_cloud_name.'/raw/upload/'.$matchesFile;
    $json_data = get_data($json_url);

    if (!$json_data) {
        die("Failed to fetch JSON data from Cloudinary.");
    }

    // Step 2: Decode JSON data into an array of objects
    $matches = json_decode($json_data);

    if (!$matches) {
        die("Failed to decode JSON data from Cloudinary.");
    }

    // Step 3: Process each match
    $multi_curl = [];
    $mh = curl_multi_init();

    foreach ($matches as &$match) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $match->match_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_multi_add_handle($mh, $ch);
        $multi_curl[$match->match_url] = ['ch' => $ch, 'index' => array_search($match, $matches)];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($multi_curl as $match_url => $data) {
        $html = curl_multi_getcontent($data['ch']);
        curl_multi_remove_handle($mh, $data['ch']);

        if ($html) {
            // Use DOMDocument to parse HTML
            $dom = new DOMDocument();
            libxml_use_internal_errors(true); // Disable libxml errors
            $dom->loadHTML($html);

            // Find the specific <div class="main-result">
            $xpath = new DOMXPath($dom);
            $divClass = 'main-result';
            $mainResultDiv = $xpath->query("//div[contains(@class, '$divClass')]")->item(0);

            // Initialize scores
            $score1 = 0;
            $score2 = 0;

            if ($mainResultDiv) {
                // Extract scores from <div class="main-result">
                $bElements = $xpath->query(".//b", $mainResultDiv);

                if ($bElements->length >= 2) {
                    $score1 = (int) $bElements->item(0)->nodeValue;
                    $score2 = (int) $bElements->item(1)->nodeValue;
                } else {
                    echo "Score elements not found in HTML for URL: " . $match_url . "<br>";
                }
            } else {
                echo "main-result div not found in HTML for URL: " . $match_url . "<br>";
            }

            // Update scores in the match object
            $index = $data['index'];
            $matches[$index]->score1 = $score1;
            $matches[$index]->score2 = $score2;

            // Debugging output
            echo "Updated match at index {$index}: URL {$match_url}, Score1: {$score1}, Score2: {$score2}<br>";
        } else {
            echo "Failed to fetch HTML content for match: " . $match_url . "<br>";
        }
    }
    curl_multi_close($mh);

    // Step 4: Save updated matches to a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'matches');
    file_put_contents($temp_file, json_encode(array_values($matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Step 5: Upload updated matches2.json to Cloudinary
    $cloudinary_url = "https://api.cloudinary.com/v1_1/{$cloudinary_cloud_name}/auto/upload";
    $timestamp = time();
    $public_id = 'matches3.json'; // Updated public_id
    $signature = sha1("invalidate=true&public_id={$public_id}&timestamp={$timestamp}&upload_preset={$upload_preset}{$cloudinary_api_secret}");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $cloudinary_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array(
            'file' => new CURLFile($temp_file),
            'upload_preset' => $upload_preset,
            'timestamp' => $timestamp,
            'api_key' => $cloudinary_api_key,
            'signature' => $signature,
            'invalidate' => 'true',
            'public_id' => $public_id // Include updated public_id in the POST fields
        ),
    ));
    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        echo "cURL Error: " . curl_error($curl) . "<br>";
    }
    curl_close($curl);

    // Handle Cloudinary API response
    if ($response) {
        echo "Data saved and uploaded to Cloudinary successfully!";
    } else {
        echo "Failed to upload matches to Cloudinary.";
    }

    // Clean up: Delete the temporary file
    unlink($temp_file);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
