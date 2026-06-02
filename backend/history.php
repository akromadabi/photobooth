<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$uploadsDir = __DIR__ . '/uploads/';
if (!file_exists($uploadsDir)) {
    echo json_encode([]);
    exit;
}

// Find all files matching the photo pattern
$files = glob($uploadsDir . '*_photo.png');
$history = [];

if ($files) {
    // Collect file information
    foreach ($files as $file) {
        $filename = basename($file);
        // Session ID is the part before the first underscore
        $parts = explode('_', $filename);
        if (count($parts) < 2) continue;
        
        $sessionId = $parts[0];
        $mtime = filemtime($file);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        $baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;
        
        $photoUrl = $protocol . $host . $baseDir . '/uploads/' . $filename;
        $downloadUrl = $protocol . $host . $baseDir . '/index.php?id=' . $sessionId;
        
        // Check if matching timelapse exists
        $timelapseUrl = null;
        $timelapseMatches = glob($uploadsDir . $sessionId . '_timelapse.*');
        if (!empty($timelapseMatches)) {
            $timelapseUrl = $protocol . $host . $baseDir . '/uploads/' . basename($timelapseMatches[0]);
        }
        
        $history[] = [
            'id' => $sessionId,
            'photo_url' => $photoUrl,
            'timelapse_url' => $timelapseUrl,
            'download_url' => $downloadUrl,
            'timestamp' => $mtime
        ];
    }
    
    // Sort history by timestamp descending (newest first)
    usort($history, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
}

echo json_encode($history);
