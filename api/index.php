<?php
// Function to fetch data from a URL using cURL
function get_data($url, $headers = []) {
    $ch = curl_init();
    $timeout = 60; // Timeout in seconds
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

try {
    // Cloudinary credentials
    $cloudinary_cloud_name = "dqzelggjc";
    $cloudinary_api_key = "128433947257472";
    $cloudinary_api_secret = "hmoYrAusm9CfLjv1uX69kfE-dbI";
    $upload_preset = "ml_default";

    // File path to matches.json on Cloudinary
    $matchesFile = 'matches.json';

    // Step 1: Load JSON from Cloudinary
    $version = rand(1, 999999); // Generate a random version number
$json_url = 'https://res.cloudinary.com/' . $cloudinary_cloud_name . '/raw/upload/v' . $version . '/' . $matchesFile;
    $json_data = get_data($json_url);

    if (!$json_data) {
        die("Failed to fetch JSON data from Cloudinary.");
    }

    // Step 2: Decode JSON data into an array of objects
    $matches = json_decode($json_data);

    if (!$matches) {
        die("Failed to decode JSON data from Cloudinary.");
    }

    // Step 3: Remove matches older than yesterday
    $yesterday = strtotime('-1 day');
    $filtered_matches = array_filter($matches, function($match) use ($yesterday) {
        $match_date = strtotime($match->match_date);
        return $match_date > $yesterday;
    });

    // Step 4: Process each remaining match
    foreach ($filtered_matches as &$match) {
        $match_url = $match->match_url;

        // Step 5: Fetch HTML for each match_url
        $html = get_data($match_url);

        if (!$html) {
            echo "Failed to fetch HTML content for match: " . $match_url . "<br>";
            continue;
        }

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
            }
        }

        // Update scores in the match object
        $match->score1 = $score1;
        $match->score2 = $score2;
    }

    // Step 6: Save updated matches to a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'matches');
    file_put_contents($temp_file, json_encode(array_values($filtered_matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Step 7: Upload updated matches.json to Cloudinary
    $cloudinary_url = "https://api.cloudinary.com/v1_1/{$cloudinary_cloud_name}/auto/upload";
    $timestamp = time();
    $public_id = 'matches.json'; // Specify the public_id for the file name
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
            'public_id' => $public_id // Include public_id in the POST fields
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    echo json_encode(array_values($filtered_matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    
    // Handle Cloudinary API response
    if ($response) {
        echo "Data try saved and uploaded to Cloudinary successfully!";
    } else {
        echo "Failed to upload matches to Cloudinary.";
    }

    // Clean up: Delete the temporary file
    unlink($temp_file);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
