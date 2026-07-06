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

// 1. Scan all timelapses once to avoid expensive glob calls inside the loop
$timelapseFiles = glob($uploadsDir . '*_timelapse.*');
$timelapseMap = [];
if ($timelapseFiles) {
    foreach ($timelapseFiles as $tlFile) {
        $tlFilename = basename($tlFile);
        $tlParts = explode('_', $tlFilename);
        if (count($tlParts) > 0) {
            $tlSessionId = $tlParts[0];
            $timelapseMap[$tlSessionId] = $tlFilename;
        }
    }
}

// 2. Find all photo files
$files = glob($uploadsDir . '*_photo.png');
$history = [];

if ($files) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;
    
    // Collect file information
    foreach ($files as $file) {
        $filename = basename($file);
        // Session ID is the part before the first underscore
        $parts = explode('_', $filename);
        if (count($parts) < 2) continue;
        
        $sessionId = $parts[0];
        $mtime = filemtime($file);
        
        $photoUrl = $protocol . $host . $baseDir . '/uploads/' . $filename;
        $downloadUrl = $protocol . $host . $baseDir . '/index.php?id=' . $sessionId;
        
        // 3. Fast O(1) lookup in memory instead of O(N) filesystem call
        $timelapseUrl = null;
        if (isset($timelapseMap[$sessionId])) {
            $timelapseUrl = $protocol . $host . $baseDir . '/uploads/' . $timelapseMap[$sessionId];
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
    
    // Limit to the newest 200 items to prevent network payload bloat and mobile rendering lag
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
    if ($limit > 0 && count($history) > $limit) {
        $history = array_slice($history, 0, $limit);
    }
}

echo json_encode($history);
