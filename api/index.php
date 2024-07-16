<?php
// Function to fetch data from a URL using cURL
function get_data($url) {
    $ch = curl_init();
    $timeout = 5; // Timeout in seconds
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
    
    // Set proper SSL verification settings
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verify the SSL host
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL certificate
    
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
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

    // Step 1: Load JSON from Cloudinary
    $matchesFile = 'matches.json'; // This won't be used if we're not saving locally

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

    // Step 3: Remove matches older than yesterday
    $yesterday = strtotime('-1 day');
    $filtered_matches = array_filter($matches, function($match) use ($yesterday) {
        $match_date = strtotime($match->match_date);
        return $match_date > $yesterday;
    });

    // Step 4: Process each remaining match
    foreach ($filtered_matches as $match) {
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
        
        // Update scores in the match data
        $match->score1 = $score1;
        $match->score2 = $score2;
    }

    // Step 6: Upload updated matches to Cloudinary
    $timestamp = time();
    $signature = sha1("invalidate=true&timestamp={$timestamp}&upload_preset={$upload_preset}{$cloudinary_api_secret}");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.cloudinary.com/v1_1/{$cloudinary_cloud_name}/raw/upload/{$matchesFile}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode(array_values($filtered_matches)),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest'
        ),
    ));

    curl_setopt($curl, CURLOPT_HEADER, 1);
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Handle Cloudinary API response
    if ($httpcode === 200) {
        echo "Data uploaded to Cloudinary successfully!";
        // Output JSON response for verification or further processing
        
        echo json_encode(array_values($filtered_matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo "Failed to upload data to Cloudinary. HTTP Error: " . $httpcode;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
