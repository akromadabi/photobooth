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

// Auto-cleanup: Delete uploads older than 14 days to preserve server memory
$retentionSeconds = 14 * 86400; // 14 days
$now = time();
$files = glob($uploadDir . '*');
if ($files) {
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $retentionSeconds) {
            unlink($file);
        }
    }
}

// Generate or resolve unique session ID
$sessionId = isset($_REQUEST['session_id']) ? preg_replace('/[^a-f0-9]/', '', $_REQUEST['session_id']) : '';
if (empty($sessionId)) {
    $sessionId = bin2hex(random_bytes(8));
}
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
    $frameId = isset($_GET['frame_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['frame_id']) : (isset($_POST['frame_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['frame_id']) : '');
    $eventId = isset($_GET['event_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['event_id']) : (isset($_POST['event_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['event_id']) : '');
    
    $packageId = isset($_REQUEST['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['package_id']) : '';
    
    $metaData = [
        'timestamp' => time()
    ];
    if ($frameId) {
        $metaData['frame_id'] = $frameId;
    }
    if ($eventId) {
        $metaData['event_id'] = $eventId;
    }
    if ($packageId) {
        $metaData['package_id'] = $packageId;
    }
    
    file_put_contents($uploadDir . $sessionId . '_meta.json', json_encode($metaData));

    // Complete session queue on upload success
    require_once __DIR__ . '/queue_helper.php';
    completeSessionQueue($sessionId);

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
