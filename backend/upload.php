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

$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique session ID
$sessionId = bin2hex(random_bytes(8));
$response = [
    'success' => false,
    'session_id' => $sessionId,
    'message' => ''
];

$photoUrl = '';
$gifUrl = '';

// Handle photo upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photoName = $sessionId . '_photo.png';
    $targetPhotoPath = $uploadDir . $photoName;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPhotoPath)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        // Clean baseDir for trailing slash or empty
        $baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;
        
        $photoUrl = $protocol . $host . $baseDir . '/uploads/' . $photoName;
        $response['success'] = true;
    } else {
        $response['message'] .= 'Failed to save uploaded photo. ';
    }
} else {
    $response['message'] .= 'No photo file uploaded or error occurred. ';
}

// Handle optional timelapse upload (GIF or MP4)
if (isset($_FILES['timelapse']) && $_FILES['timelapse']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['timelapse']['name'], PATHINFO_EXTENSION);
    $ext = $ext ? $ext : 'mp4'; // Default to mp4 if no extension
    $timelapseName = $sessionId . '_timelapse.' . $ext;
    $targetTimelapsePath = $uploadDir . $timelapseName;
    
    if (move_uploaded_file($_FILES['timelapse']['tmp_name'], $targetTimelapsePath)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        $baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;
        
        $gifUrl = $protocol . $host . $baseDir . '/uploads/' . $timelapseName;
    }
}

if ($response['success']) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;
    
    $response['download_url'] = $protocol . $host . $baseDir . '/index.php?id=' . $sessionId;
    if ($photoUrl) $response['photo_url'] = $photoUrl;
    if ($gifUrl) $response['timelapse_url'] = $gifUrl;
    $response['message'] = 'Upload successful!';
}

echo json_encode($response);
