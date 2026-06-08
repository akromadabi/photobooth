<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// 1. Load settings to get Fal.ai key
$settingsFile = __DIR__ . '/settings.json';
if (!file_exists($settingsFile)) {
    echo json_encode([
        'success' => false,
        'message' => 'File settings.json tidak ditemukan.'
    ]);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);
$falKey = isset($settings['fal_key']) ? trim($settings['fal_key']) : '';
if (empty($falKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Kunci API Fal.ai belum dikonfigurasi di Dashboard Admin.'
    ]);
    exit;
}

// 2. Validate parameters
$characterId = isset($_POST['character_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['character_id']) : '';
if (empty($characterId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter character_id diperlukan.'
    ]);
    exit;
}

// 3. Resolve character template
$charactersFile = __DIR__ . '/characters.json';
if (!file_exists($charactersFile)) {
    echo json_encode([
        'success' => false,
        'message' => 'File characters.json tidak ditemukan.'
    ]);
    exit;
}

$characters = json_decode(file_get_contents($charactersFile), true);
$selectedChar = null;
foreach ($characters as $char) {
    if ($char['id'] === $characterId) {
        $selectedChar = $char;
        break;
    }
}

if (!$selectedChar) {
    echo json_encode([
        'success' => false,
        'message' => 'Karakter tidak ditemukan dalam katalog.'
    ]);
    exit;
}

$templatePath = __DIR__ . '/characters/' . $selectedChar['template_filename'];
if (!file_exists($templatePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'File template karakter tidak ditemukan di server.'
    ]);
    exit;
}

// 4. Resolve session_id
$sessionId = isset($_REQUEST['session_id']) ? preg_replace('/[^a-f0-9]/', '', $_REQUEST['session_id']) : '';
if (empty($sessionId)) {
    $sessionId = bin2hex(random_bytes(8));
}

$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 5. Handle uploaded face photo
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'File foto wajah tidak terunggah atau terjadi error.'
    ]);
    exit;
}

$tempFacePath = $uploadDir . $sessionId . '_temp_face.jpg';
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $tempFacePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan foto wajah sementara.'
    ]);
    exit;
}

// Helper function to upload local file to Fal.ai CDN
function uploadToFalCdn($localFilePath, $falKey) {
    $url = "https://queue.fal.run/upload";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Key $falKey"
    ]);

    $mimeType = mime_content_type($localFilePath);
    $cFile = new CURLFile($localFilePath, $mimeType, basename($localFilePath));
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => $cFile
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $json = json_decode($response, true);
    return isset($json['url']) ? $json['url'] : null;
}

// 6. Upload user face and template to Fal.ai CDN
$userFaceUrl = uploadToFalCdn($tempFacePath, $falKey);
// Clean up temp face photo immediately after upload
if (file_exists($tempFacePath)) {
    unlink($tempFacePath);
}

if (!$userFaceUrl) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengunggah foto wajah ke Fal.ai CDN.'
    ]);
    exit;
}

$templateImageUrl = uploadToFalCdn($templatePath, $falKey);
if (!$templateImageUrl) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengunggah template karakter ke Fal.ai CDN.'
    ]);
    exit;
}

// 7. Call Face Swap API (Synchronous)
$faceSwapUrl = "https://fal.run/fal-ai/face-swap";
$payload = json_encode([
    'base_image_url' => $templateImageUrl,
    'swap_image_url' => $userFaceUrl
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $faceSwapUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Key $falKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'message' => 'AI Generation gagal (Fal.ai Error HTTP ' . $httpCode . '). Response: ' . $response
    ]);
    exit;
}

$result = json_decode($response, true);
$aiImageUrl = isset($result['image']['url']) ? $result['image']['url'] : '';

if (empty($aiImageUrl)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mendapatkan URL gambar AI dari respons Fal.ai.'
    ]);
    exit;
}

// 8. Download AI generated image and save locally
$finalPhotoPath = $uploadDir . $sessionId . '_photo.png';
$imgData = file_get_contents($aiImageUrl);
if ($imgData === false || !file_put_contents($finalPhotoPath, $imgData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengunduh dan menyimpan hasil foto AI di server.'
    ]);
    exit;
}

// 9. Create metadata for session downloads
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;

$metaData = [
    'timestamp' => time(),
    'frame_id' => $characterId,
    'package_id' => isset($_REQUEST['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['package_id']) : '',
    'event_id' => isset($_REQUEST['event_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['event_id']) : 'general'
];
file_put_contents($uploadDir . $sessionId . '_meta.json', json_encode($metaData));

// Complete session queue on AI generation success
require_once __DIR__ . '/queue_helper.php';
completeSessionQueue($sessionId);

echo json_encode([
    'success' => true,
    'session_id' => $sessionId,
    'photo_url' => $protocol . $host . $baseDir . '/uploads/' . $sessionId . '_photo.png',
    'download_url' => $protocol . $host . $baseDir . '/index.php?id=' . $sessionId,
    'message' => 'AI generation successful!'
]);
