<?php
// PHP Script to test Fal.ai upload
$falKey = "YOUR_FAL_KEY"; // Replace with key for testing if needed
$localPath = __DIR__ . '/logo.png'; // Use logo.png since it exists in the workspace
if (!file_exists($localPath)) {
    // If logo.png isn't there, create a small dummy file
    $localPath = __DIR__ . '/dummy.png';
    file_put_contents($localPath, 'dummy data');
}

$url = "https://queue.fal.run/upload";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// Set authorization header
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Key $falKey"
]);

// Set post fields with curl_file
$mimeType = mime_content_type($localPath);
$cFile = new CURLFile($localPath, $mimeType, 'logo.png');

curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'file' => $cFile
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
