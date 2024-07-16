<?php
// Cloudinary credentials
$cloudinary_cloud_name = "ds8s4fn5p";
$cloudinary_api_key = "731143319737329";
$cloudinary_api_secret = "HD479cTPf2KY6iI7LEuJzrvNTpM";

// Example JSON data
$json_data = '{
    "name": "Jjru37john Doe",
    "age": 30,
    "city": "New York",
    "email": "john.doe@example.com"
}';

// Create a temporary file to store JSON data
$temp_file = tempnam(sys_get_temp_dir(), 'ff');
file_put_contents($temp_file, $json_data);

// Cloudinary upload API endpoint
$upload_url = 'https://api.cloudinary.com/v1_1/' . $cloudinary_cloud_name . '/upload';

// Prepare signature data
$timestamp = time();
$upload_preset = 'yeufjqiy'; // Adjust with your actual upload preset name
$public_id = 'ff.json'; // Specify the public_id as 'ff.json' for the file name

// Construct data string for signature
$signature_data = "public_id={$public_id}&timestamp={$timestamp}&upload_preset={$upload_preset}";

// Generate signature using SHA-1 (or SHA-256 if preferred)
$signature = sha1($signature_data . $cloudinary_api_secret);

// Prepare POST data
$post_data = array(
    'file' => new CURLFile($temp_file), // Upload the temporary file
    'upload_preset' => $upload_preset,
    'api_key' => $cloudinary_api_key,
    'timestamp' => $timestamp,
    'signature' => $signature,
    'public_id' => $public_id, // Specify the public_id as 'ff.json'
);

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $upload_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

// Check for errors
if(curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    // Output the response (JSON format)
    echo $response;
}

// Close cURL session
curl_close($ch);

// Clean up: Delete the temporary file
unlink($temp_file);
?>
