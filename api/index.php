<?php
// Function to fetch data from a URL using cURL
function get_data($url) {
    $ch = curl_init();
    $timeout = 5; // Timeout in seconds
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

try {
    // Cloudinary credentials (should be stored securely as environment variables in Vercel)
    $cloudinary_cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
    $cloudinary_api_key = getenv('CLOUDINARY_API_KEY');
    $cloudinary_api_secret = getenv('CLOUDINARY_API_SECRET');
    $upload_preset = "yeufjqiy"; // This can be set as per your Cloudinary setup

    // URL of the JSON file on Cloudinary
    $json_url = 'https://res.cloudinary.com/'.$cloudinary_cloud_name.'/raw/upload/matches.json';

    // Fetch JSON data from Cloudinary
    $json_data = get_data($json_url);

    if (!$json_data) {
        die("Failed to fetch JSON data from Cloudinary.");
    }

    // Decode JSON data into an array of objects
    $matches = json_decode($json_data);

    if (!$matches) {
        die("Failed to decode JSON data from Cloudinary.");
    }

    // Example: Update scores in the $matches array
    foreach ($matches as &$match) {
        // Example: Fetch HTML content and update scores
        // This part should handle updating scores as per your original logic
        // Replace this with your actual logic to update scores
        $match->score1 = 1; // Example score update
        $match->score2 = 2; // Example score update
    }

    // Encode updated $matches array back to JSON
    $updated_json_data = json_encode($matches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Example: Upload updated JSON data to Cloudinary
    $cloudinary_url = "https://api.cloudinary.com/v1_1/{$cloudinary_cloud_name}/auto/upload";
    $timestamp = time();
    $signature = sha1("invalidate=true&timestamp={$timestamp}&upload_preset={$upload_preset}{$cloudinary_api_secret}");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $cloudinary_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array(
            'file' => $updated_json_data, // Directly pass JSON data
            'upload_preset' => $upload_preset,
            'timestamp' => $timestamp,
            'api_key' => $cloudinary_api_key,
            'signature' => $signature,
            'invalidate' => 'true'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    // Handle Cloudinary API response
    if ($response) {
        echo "Data saved and uploaded to Cloudinary successfully!";
    } else {
        echo "Failed to upload matches to Cloudinary.";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
