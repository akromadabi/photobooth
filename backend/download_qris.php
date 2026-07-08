<?php
session_start();

$queueFile = __DIR__ . '/queue.json';
$settingsFile = __DIR__ . '/settings.json';

// Get current state
function getQueueState($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [
        "active_queue_number" => 0,
        "active_session_id" => "",
        "queue_list" => []
    ];
}

$orderId = isset($_GET['order_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['order_id']) : '';

if (!$orderId) {
    die("Akses ditolak. Parameter tidak valid.");
}

$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}
$paymentMode = isset($settings['payment_mode']) ? $settings['payment_mode'] : 'dummy';

$state = getQueueState($queueFile);
$qrUrl = '';

foreach ($state['queue_list'] as $item) {
    if ($item['session_id'] === $orderId) {
        if ($paymentMode === 'midtrans' && isset($item['midtrans_qr_url'])) {
            $qrUrl = $item['midtrans_qr_url'];
        } elseif ($paymentMode === 'siapp_pay' && isset($item['siapp_pay_qr_url'])) {
            $qrUrl = $item['siapp_pay_qr_url'];
        }
        break;
    }
}

// Fallback to dummy if mode is dummy or if URL wasn't generated/found for other reasons
if (!$qrUrl && $paymentMode === 'dummy') {
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://github.com/akromadabi/photobooth";
}

if (!$qrUrl) {
    die("QRIS belum di-generate atau tidak ditemukan.");
}

// Download the image content
$ch = curl_init($qrUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local/sandbox SSL check issues
$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || empty($imageData)) {
    // Fallback: try file_get_contents if curl failed
    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ]);
    $imageData = @file_get_contents($qrUrl, false, $context);
    if ($imageData === false) {
        die("Gagal mengunduh gambar QRIS.");
    }
    $contentType = 'image/png';
}

// Ensure the content type is correct or default to image/png
if (!$contentType || strpos($contentType, 'image/') === false) {
    $contentType = 'image/png';
}

// Send download headers
header("Content-Description: File Transfer");
header("Content-Type: " . $contentType);
header("Content-Disposition: attachment; filename=\"qris-" . $orderId . ".png\"");
header("Content-Transfer-Encoding: binary");
header("Expires: 0");
header("Cache-Control: must-revalidate");
header("Pragma: public");
header("Content-Length: " . strlen($imageData));

echo $imageData;
exit;
