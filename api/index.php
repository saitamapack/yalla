<?php
// Function to fetch data from a URL using cURL
function get_data($url) {
    $ch = curl_init();
    $timeout = 5; // Timeout in seconds
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true); // Enable SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL verification
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
    // Cloudinary credentials (replace with environment variables)
    $cloudinary_cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
    $cloudinary_api_key = getenv('CLOUDINARY_API_KEY');
    $cloudinary_api_secret = getenv('CLOUDINARY_API_SECRET');
    $upload_preset = "yeufjqiy"; // Replace with your Cloudinary upload preset

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

    // Step 6: Output JSON or format as needed for application
    header('Content-Type: application/json');
    echo json_encode(array_values($filtered_matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
