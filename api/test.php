<?php
// Function to fetch data from a URL using cURL
function get_data($url, $headers = []) {
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
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $data = curl_exec($ch);
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

    // Step 6: Save matches to a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'matches');
    file_put_contents($temp_file, $json_data);

    // Step 7: Upload matches.json to Cloudinary
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

    // Handle Cloudinary API response
    if ($response) {
        echo "Matches uploaded to Cloudinary successfully!";
    } else {
        echo "Failed to upload matches to Cloudinary.";
    }

    // Clean up: Delete the temporary file
    unlink($temp_file);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
