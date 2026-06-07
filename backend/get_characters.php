<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$charactersFile = __DIR__ . '/characters.json';
if (!file_exists($charactersFile)) {
    echo json_encode([]);
    exit;
}

$characters = json_decode(file_get_contents($charactersFile), true);
if (!is_array($characters)) {
    echo json_encode([]);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : $baseDir;

$formattedCharacters = [];
foreach ($characters as $char) {
    $char['template_url'] = $protocol . $host . $baseDir . '/characters/' . $char['template_filename'];
    $formattedCharacters[] = $char;
}

echo json_encode($formattedCharacters);
