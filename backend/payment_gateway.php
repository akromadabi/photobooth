<?php
session_start();

$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';

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

function saveQueueState($file, $state) {
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
}

// -------------------------------------------------------------
// MIDTRANS WEBHOOK / NOTIFICATION HANDLER
// -------------------------------------------------------------
$rawBody = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && !empty($rawBody)) {
    $notification = json_decode($rawBody, true);
    if ($notification && isset($notification['order_id']) && isset($notification['transaction_status']) && isset($notification['signature_key'])) {
        header('Content-Type: application/json');
        
        $orderId = $notification['order_id'];
        $statusCode = $notification['status_code'];
        $grossAmount = $notification['gross_amount'];
        $txStatus = $notification['transaction_status'];
        $signatureKey = $notification['signature_key'];
        
        // Load settings to verify signature
        $settingsFile = __DIR__ . '/settings.json';
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
        
        // Load appropriate server key based on environment with fallback
        $midtransEnv = isset($settings['midtrans_environment']) ? $settings['midtrans_environment'] : 'sandbox';
        if ($midtransEnv === 'production') {
            $serverKey = !empty($settings['midtrans_production_server_key']) ? $settings['midtrans_production_server_key'] : (isset($settings['midtrans_server_key']) ? $settings['midtrans_server_key'] : '');
        } else {
            $serverKey = !empty($settings['midtrans_sandbox_server_key']) ? $settings['midtrans_sandbox_server_key'] : (isset($settings['midtrans_server_key']) ? $settings['midtrans_server_key'] : '');
        }
        
        // Verify signature
        $localSignature = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);
        
        if ($localSignature === $signatureKey) {
            if ($txStatus === 'settlement' || $txStatus === 'capture') {
                $state = getQueueState($queueFile);
                $found = false;
                
                foreach ($state['queue_list'] as &$item) {
                    if ($item['session_id'] === $orderId) {
                        if ($item['status'] === 'UNPAID') {
                            $item['status'] = 'WAITING'; // Paid and waiting
                            
                            $activeExists = false;
                            foreach ($state['queue_list'] as $q) {
                                if ($q['status'] === 'ACTIVE' || $q['status'] === 'CAPTURING') {
                                    $activeExists = true;
                                    break;
                                }
                            }
                            
                            if (!$activeExists) {
                                $item['status'] = 'ACTIVE';
                                $state['active_queue_number'] = $item['queue_number'];
                                $state['active_session_id'] = $item['session_id'];
                            }
                        }
                        $found = true;
                        break;
                    }
                }
                unset($item);
                if ($found) {
                    saveQueueState($queueFile, $state);
                    echo json_encode(['status' => 'OK', 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['status' => 'ERROR', 'message' => 'Order not found in queue']);
                }
            } else {
                echo json_encode(['status' => 'OK', 'message' => 'Transaction status is ' . $txStatus]);
            }
        } else {
            http_response_code(403);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid signature']);
        }
        exit;
    }
}

// -------------------------------------------------------------
// MIDTRANS STATUS CHECK API (POLLING TRIGGER)
// -------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'check_midtrans_status') {
    header('Content-Type: application/json');
    $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : '';
    
    if ($orderId) {
        $settingsFile = __DIR__ . '/settings.json';
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
        $isProduction = isset($settings['midtrans_environment']) && $settings['midtrans_environment'] === 'production';
        
        if ($isProduction) {
            $serverKey = !empty($settings['midtrans_production_server_key']) ? $settings['midtrans_production_server_key'] : (isset($settings['midtrans_server_key']) ? $settings['midtrans_server_key'] : '');
        } else {
            $serverKey = !empty($settings['midtrans_sandbox_server_key']) ? $settings['midtrans_sandbox_server_key'] : (isset($settings['midtrans_server_key']) ? $settings['midtrans_server_key'] : '');
        }
        
        if (!$serverKey) {
            echo json_encode(['success' => false, 'message' => 'Server Key Midtrans tidak dikonfigurasi.']);
            exit;
        }
        
        $statusUrl = $isProduction 
            ? "https://api.midtrans.com/v2/$orderId/status"
            : "https://api.sandbox.midtrans.com/v2/$orderId/status";
            
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $resData = json_decode($response, true);
            $txStatus = isset($resData['transaction_status']) ? $resData['transaction_status'] : '';
            
            if ($txStatus === 'settlement' || $txStatus === 'capture') {
                $state = getQueueState($queueFile);
                $found = false;
                
                foreach ($state['queue_list'] as &$item) {
                    if ($item['session_id'] === $orderId) {
                        if ($item['status'] === 'UNPAID') {
                            $item['status'] = 'WAITING';
                            
                            $activeExists = false;
                            foreach ($state['queue_list'] as $q) {
                                if ($q['status'] === 'ACTIVE' || $q['status'] === 'CAPTURING') {
                                    $activeExists = true;
                                    break;
                                }
                            }
                            
                            if (!$activeExists) {
                                $item['status'] = 'ACTIVE';
                                $state['active_queue_number'] = $item['queue_number'];
                                $state['active_session_id'] = $item['session_id'];
                            }
                        }
                        $found = true;
                        break;
                    }
                }
                unset($item);
                
                if ($found) {
                    saveQueueState($queueFile, $state);
                    echo json_encode(['success' => true, 'status' => 'PAID', 'message' => 'Pembayaran lunas terverifikasi!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan dalam antrean.']);
                }
            } else {
                $statusMap = [
                    'pending' => 'Menunggu Pembayaran',
                    'deny' => 'Pembayaran Ditolak',
                    'cancel' => 'Pembayaran Dibatalkan',
                    'expire' => 'Pembayaran Kedaluwarsa'
                ];
                $readableStatus = isset($statusMap[$txStatus]) ? $statusMap[$txStatus] : $txStatus;
                echo json_encode(['success' => true, 'status' => 'PENDING', 'message' => 'Status: ' . $readableStatus]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memeriksa status ke Midtrans (HTTP ' . $httpCode . ').']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid.']);
    }
    exit;
}

// Handle simulated callback / verification
if (isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    header('Content-Type: application/json');
    $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : '';
    
    if ($orderId) {
        $state = getQueueState($queueFile);
        $found = false;
        
        foreach ($state['queue_list'] as &$item) {
            if ($item['session_id'] === $orderId) {
                if ($item['status'] === 'UNPAID') {
                    $item['status'] = 'WAITING'; // Paid and now waiting in queue
                    
                    // If no one is active, make this active
                    $activeExists = false;
                    foreach ($state['queue_list'] as $q) {
                        if ($q['status'] === 'ACTIVE' || $q['status'] === 'CAPTURING') {
                            $activeExists = true;
                            break;
                        }
                    }
                    
                    if (!$activeExists) {
                        $item['status'] = 'ACTIVE';
                        $state['active_queue_number'] = $item['queue_number'];
                        $state['active_session_id'] = $item['session_id'];
                    }
                }
                $found = true;
                break;
            }
        }
        
        if ($found) {
            saveQueueState($queueFile, $state);
            echo json_encode(['success' => true, 'message' => 'Pembayaran lunas! Silakan periksa halaman remote Anda.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid.']);
    }
    exit;
}

// Handle coupon validation / redemption
if (isset($_POST['action']) && $_POST['action'] === 'redeem_coupon') {
    header('Content-Type: application/json');
    $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : '';
    $packageId = isset($_POST['package_id']) ? $_POST['package_id'] : '';
    $couponCode = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
    
    if (!$orderId || !$packageId || !$couponCode) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
        exit;
    }
    
    require_once __DIR__ . '/coupon_helper.php';
    
    // Validate and use coupon
    $res = validateAndUseCoupon($couponCode, $packageId, $orderId);
    
    if ($res['success']) {
        // Coupon redeemed successfully, now mark order as paid
        $state = getQueueState($queueFile);
        $found = false;
        
        foreach ($state['queue_list'] as &$item) {
            if ($item['session_id'] === $orderId) {
                if ($item['status'] === 'UNPAID') {
                    $item['status'] = 'WAITING';
                    
                    $activeExists = false;
                    foreach ($state['queue_list'] as $q) {
                        if ($q['status'] === 'ACTIVE' || $q['status'] === 'CAPTURING') {
                            $activeExists = true;
                            break;
                        }
                    }
                    
                    if (!$activeExists) {
                        $item['status'] = 'ACTIVE';
                        $state['active_queue_number'] = $item['queue_number'];
                        $state['active_session_id'] = $item['session_id'];
                    }
                }
                $found = true;
                break;
            }
        }
        unset($item);
        
        if ($found) {
            saveQueueState($queueFile, $state);
            echo json_encode(['success' => true, 'message' => 'Kupon berhasil digunakan! Sesi Anda telah aktif.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan dalam antrean.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $res['message']]);
    }
    exit;
}

// Visual Page Setup
$orderId = isset($_GET['order_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['order_id']) : '';
$packageId = isset($_GET['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['package_id']) : '';

$packages = [];
if (file_exists($packagesFile)) {
    $packages = json_decode(file_get_contents($packagesFile), true);
}

$selectedPackage = null;
foreach ($packages as $pkg) {
    if ($pkg['id'] === $packageId) {
        $selectedPackage = $pkg;
        break;
    }
}

if (!$orderId || !$selectedPackage) {
    die("Akses ditolak. Transaksi tidak valid.");
}

$settingsFile = __DIR__ . '/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}
$defaults = [
    "payment_mode" => "dummy",
    "midtrans_server_key" => "",
    "midtrans_client_key" => "",
    "midtrans_sandbox_server_key" => "",
    "midtrans_sandbox_client_key" => "",
    "midtrans_production_server_key" => "",
    "midtrans_production_client_key" => "",
    "midtrans_environment" => "sandbox"
];
$settings = array_merge($defaults, (array)$settings);

// Dynamic mapping resolution:
if ($settings['midtrans_environment'] === 'production') {
    $settings['midtrans_client_key'] = !empty($settings['midtrans_production_client_key']) ? $settings['midtrans_production_client_key'] : $settings['midtrans_client_key'];
    $settings['midtrans_server_key'] = !empty($settings['midtrans_production_server_key']) ? $settings['midtrans_production_server_key'] : $settings['midtrans_server_key'];
} else {
    $settings['midtrans_client_key'] = !empty($settings['midtrans_sandbox_client_key']) ? $settings['midtrans_sandbox_client_key'] : $settings['midtrans_client_key'];
    $settings['midtrans_server_key'] = !empty($settings['midtrans_sandbox_server_key']) ? $settings['midtrans_sandbox_server_key'] : $settings['midtrans_server_key'];
}

$state = getQueueState($queueFile);
$currentQueueNumber = 0;
$orderQueueItem = null;

foreach ($state['queue_list'] as &$item) {
    if ($item['session_id'] === $orderId) {
        $currentQueueNumber = $item['queue_number'];
        $orderQueueItem = &$item;
        break;
    }
}
unset($item);

if (!$orderQueueItem) {
    die("Akses ditolak. Transaksi tidak ditemukan dalam antrean.");
}

// Redirect if already paid/redeemed to prevent losing session
if ($orderQueueItem['status'] !== 'UNPAID') {
    header("Location: order.php?session_id=" . urlencode($orderId));
    exit;
}

$midtransQrUrl = '';
$midtransError = '';

if ($settings['payment_mode'] === 'midtrans' && $orderQueueItem['status'] === 'UNPAID') {
    if (isset($orderQueueItem['midtrans_qr_url']) && $orderQueueItem['midtrans_qr_url']) {
        $midtransQrUrl = $orderQueueItem['midtrans_qr_url'];
    } else {
        $serverKey = $settings['midtrans_server_key'];
        $isProduction = $settings['midtrans_environment'] === 'production';
        $chargeUrl = $isProduction 
            ? "https://api.midtrans.com/v2/charge" 
            : "https://api.sandbox.midtrans.com/v2/charge";

        $payload = [
            "payment_type" => "qris",
            "transaction_details" => [
                "order_id" => $orderId,
                "gross_amount" => intval($selectedPackage['price'])
            ],
            "item_details" => [
                [
                    "id" => $selectedPackage['id'],
                    "price" => intval($selectedPackage['price']),
                    "quantity" => 1,
                    "name" => $selectedPackage['name']
                ]
            ],
            "qris" => [
                "acquirer" => "gopay"
            ]
        ];

        $ch = curl_init($chargeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $midtransError = "Koneksi Error: " . curl_error($ch);
        } else if ($httpCode >= 400) {
            $resError = json_decode($response, true);
            $errMsgs = isset($resError['status_message']) ? $resError['status_message'] : $response;
            $midtransError = "Midtrans Core API Error ($httpCode): " . $errMsgs;
        } else {
            $resData = json_decode($response, true);
            $qrUrl = '';
            if (isset($resData['actions']) && is_array($resData['actions'])) {
                foreach ($resData['actions'] as $action) {
                    if (isset($action['name']) && $action['name'] === 'generate-qr-code') {
                        $qrUrl = $action['url'];
                        break;
                    }
                }
            }
            
            if ($qrUrl) {
                $midtransQrUrl = $qrUrl;
                
                // Save to queue state
                $state = getQueueState($queueFile);
                foreach ($state['queue_list'] as &$item) {
                    if ($item['session_id'] === $orderId) {
                        $item['midtrans_qr_url'] = $midtransQrUrl;
                        break;
                    }
                }
                unset($item);
                saveQueueState($queueFile, $state);
            } else {
                $midtransError = "Gagal mendapatkan kode QRIS dari respon Midtrans.";
            }
        }
        curl_close($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS - Jeprat-jepret Kiosk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f0f12;
            --card-bg: #18181f;
            --primary-red: #e63946;
            --text-main: #f8f9fa;
            --text-muted: #a0a0b0;
            --border-color: #2a2a35;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .payment-card {
            width: 100%;
            max-width: 440px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }

        .logo {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }
        .logo span { color: var(--primary-red); }

        .title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .bill-details {
            background-color: #0c0c0f;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
        }

        .bill-label { color: var(--text-muted); }
        .bill-val { font-weight: 600; }

        .price-tag {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-red);
            text-align: center;
            margin-top: 4px;
        }

        .qris-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
        }

        .qris-box {
            background-color: white;
            border-radius: 16px;
            padding: 16px;
            width: 220px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            position: relative;
        }

        .qris-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .dummy-badge {
            position: absolute;
            background-color: rgba(230, 57, 70, 0.9);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            border: 2px solid white;
        }

        .btn-verify {
            background-color: #25D366;
            color: white;
            font-weight: 700;
            padding: 16px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.25);
            transition: all 0.25s;
            text-align: center;
            width: 100%;
        }

        .btn-verify:hover {
            transform: translateY(-1px);
            background-color: #20b858;
        }

        .instruction {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.5;
        }

        /* Camera Scanner Styles */
        .scanner-container {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            background: #09090b;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
            transition: border-color 0.3s ease;
        }

        #coupon-qr-reader {
            width: 100%;
            height: 100%;
        }

        /* Hide any unwanted built-in video borders/anchors from html5-qrcode */
        #coupon-qr-reader video {
            object-fit: cover !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 12px;
        }
        
        #coupon-qr-reader__header_message {
            display: none !important;
        }

        .scanner-laser {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, transparent, #25D366, transparent);
            box-shadow: 0 0 8px #25D366;
            animation: scan-anim 2.5s linear infinite;
            pointer-events: none;
            z-index: 5;
        }

        @keyframes scan-anim {
            0% { top: 0%; }
            50% { top: 100%; }
            100% { top: 0%; }
        }

        .scanner-status {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            background: rgba(15, 15, 18, 0.85);
            backdrop-filter: blur(4px);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #fff;
            text-align: center;
            pointer-events: none;
            z-index: 5;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
        }
    </style>
    <script src="html5-qrcode.min.js"></script>
</head>
<body>

    <div class="payment-card">
        <div class="header">
            <div class="logo">Creative<span>Studio</span></div>
            <?php if ($settings['payment_mode'] === 'midtrans'): ?>
                <div class="title">💳 SECURE CHECKOUT (MIDTRANS)</div>
            <?php else: ?>
                <div class="title">💵 GERBANG PEMBAYARAN SIMULASI</div>
            <?php endif; ?>
        </div>

        <div class="bill-details">
            <div class="bill-row">
                <span class="bill-label">Paket Dipilih</span>
                <span class="bill-val"><?php echo htmlspecialchars($selectedPackage['name']); ?></span>
            </div>
            <div class="bill-row">
                <span class="bill-label">ID Transaksi</span>
                <span class="bill-val" style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($orderId); ?></span>
            </div>
            <div class="bill-row">
                <span class="bill-label">Nomor Antrean</span>
                <span class="bill-val" style="color:var(--primary-red);">#<?php echo $currentQueueNumber; ?></span>
            </div>
            <div class="price-tag">Rp <?php echo number_format($selectedPackage['price'], 0, ',', '.'); ?></div>
        </div>

        <!-- Tab Navigation for Payment Methods -->
        <div class="payment-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-top: 15px;">
            <button class="pay-tab active" id="tab-btn-online" onclick="switchPayTab('online')" style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: #fff; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                📱 <span>QRIS / Online</span>
            </button>
            <button class="pay-tab" id="tab-btn-coupon" onclick="switchPayTab('coupon')" style="flex: 1; background: transparent; border: 1px solid transparent; color: var(--text-muted); padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                🎫 <span>Kupon Tunai</span>
            </button>
        </div>

        <!-- ONLINE METHOD VIEW -->
        <div id="payment-view-online" style="display: block;">
            <?php if ($settings['payment_mode'] === 'midtrans'): ?>
                <?php if ($midtransError): ?>
                    <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 16px; text-align: center;">
                        <div style="color: #ef4444; font-weight: 700; font-size: 0.95rem; margin-bottom: 8px;">❌ Gagal Memulai Pembayaran</div>
                        <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 16px; line-height: 1.4;"><?php echo htmlspecialchars($midtransError); ?></div>
                        <button class="btn-verify" onclick="window.location.reload()" style="background-color: var(--primary-red); box-shadow: 0 4px 15px rgba(230, 57, 70, 0.25);">Coba Lagi</button>
                    </div>
                <?php else: ?>
                    <div class="qris-section">
                        <div class="qris-box">
                            <img src="<?php echo htmlspecialchars($midtransQrUrl); ?>" alt="Midtrans QRIS">
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight: 600;">Scan QRIS di atas untuk membayar</div>
                    </div>
                    
                    <button class="btn-verify" id="btnCheckStatus" onclick="checkMidtransStatus(true)" style="background-color: transparent; border: 2px solid var(--border-color); color: #fff; box-shadow: none; font-size: 0.85rem; padding: 12px;">Cek Status Pembayaran Manual</button>

                    <div class="instruction">
                        *Menunggu pembayaran terdeteksi secara otomatis... Halaman ini akan dialihkan setelah transaksi sukses.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- DUMMY MODE -->
                <div class="qris-section">
                    <div class="qris-box">
                        <!-- Static generic QRIS placeholder -->
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://github.com/akromadabi/photobooth" alt="QRIS Simulator">
                        <div class="dummy-badge">DUMMY QRIS</div>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight: 600;">Scan QRIS di atas untuk membayar</div>
                </div>

                <button class="btn-verify" onclick="simulatePaymentSuccess()">BAYAR SEKARANG (SIMULASI)</button>

                <div class="instruction">
                    *Ini adalah gerbang pembayaran dummy. Klik tombol hijau di atas untuk menyimulasikan transaksi sukses secara gratis tanpa saldo rekening Anda terpotong.
                </div>
            <?php endif; ?>
        </div>

        <!-- COUPON METHOD VIEW -->
        <div id="payment-view-coupon" style="display: none; flex-direction: column; gap: 16px;">
            <div style="text-align: center; display: flex; flex-direction: column; gap: 6px; margin: 10px 0;">
                <div style="font-size: 1.5rem;">🎟️</div>
                <div style="font-size: 0.95rem; font-weight: 700; color: #fff;">Gunakan Kupon Pembayaran</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4; max-width: 280px; margin: 0 auto;">
                    Bayar cash ke kasir untuk mendapatkan struk kupon, lalu masukkan kodenya di bawah ini.
                </div>
            </div>
            
            <!-- Camera Scanner Container -->
            <div class="scanner-container" id="scanner-wrapper">
                <div id="coupon-qr-reader"></div>
                <div class="scanner-laser" id="scanner-laser-line"></div>
                <div class="scanner-status" id="scanner-status">Menginisialisasi kamera...</div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <input type="text" id="coupon_code_input" placeholder="MASUKKAN KODE KUPON" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); color: #fff; padding: 14px; border-radius: 12px; font-size: 1.05rem; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: 2px; width: 100%; box-sizing: border-box; font-family: monospace; outline: none; transition: border-color 0.2s;" maxlength="20">
                <div id="coupon-error-msg" style="font-size: 0.8rem; font-weight: 600; text-align: center; display: none; line-height: 1.3;"></div>
            </div>

            <button class="btn-verify" id="btnRedeemCoupon" onclick="redeemCoupon()" style="background-color: var(--primary-red); box-shadow: 0 4px 15px rgba(230, 57, 70, 0.25);">GUNAKAN KUPON</button>
        </div>
    </div>

    <!-- No snap.js script required for Core API -->

    <script>
        const orderId = '<?php echo $orderId; ?>';
        
        let html5QrCode = null;

        function startScanner() {
            const statusDiv = document.getElementById('scanner-status');
            const laser = document.getElementById('scanner-laser-line');
            const wrapper = document.getElementById('scanner-wrapper');
            
            if (html5QrCode) {
                return; // Already initialized
            }

            statusDiv.style.display = 'block';
            statusDiv.innerText = 'Menginisialisasi kamera...';
            statusDiv.style.color = '#fff';
            laser.style.display = 'block';
            wrapper.style.borderColor = 'var(--border-color)';

            html5QrCode = new Html5Qrcode("coupon-qr-reader");
            
            const config = {
                fps: 10,
                qrbox: function(width, height) {
                    const size = Math.min(width, height) * 0.75;
                    return { width: size, height: size };
                }
            };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    console.log("Scanned Coupon Code:", decodedText);
                    
                    const codeInput = document.getElementById('coupon_code_input');
                    codeInput.value = decodedText;
                    
                    statusDiv.innerText = 'Kupon Terdeteksi: ' + decodedText;
                    statusDiv.style.color = '#25D366';
                    
                    wrapper.style.borderColor = '#25D366';
                    
                    // Stop camera scanning after successful read to prevent duplicate triggers
                    stopScanner();
                    
                    // Auto submit coupon
                    redeemCoupon();
                },
                (errorMessage) => {
                    // Suppress verbose scanning output log to keep console clean
                }
            )
            .then(() => {
                statusDiv.innerText = 'Arahkan QR Code kupon ke kamera';
            })
            .catch((err) => {
                console.error("Scanner Error:", err);
                statusDiv.innerText = 'Gagal mengakses kamera (Izin ditolak atau sedang digunakan).';
                statusDiv.style.color = '#ef4444';
                laser.style.display = 'none';
                html5QrCode = null;
            });
        }

        function stopScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode = null;
                    const statusDiv = document.getElementById('scanner-status');
                    if (statusDiv) {
                        statusDiv.innerText = 'Kamera dinonaktifkan';
                        statusDiv.style.color = 'var(--text-muted)';
                    }
                    const laser = document.getElementById('scanner-laser-line');
                    if (laser) {
                        laser.style.display = 'none';
                    }
                }).catch(err => {
                    console.error("Gagal menghentikan kamera:", err);
                    html5QrCode = null;
                });
            }
        }

        // Tab switching logic for payment gateway
        function switchPayTab(tab) {
            const btnOnline = document.getElementById('tab-btn-online');
            const btnCoupon = document.getElementById('tab-btn-coupon');
            const viewOnline = document.getElementById('payment-view-online');
            const viewCoupon = document.getElementById('payment-view-coupon');

            if (tab === 'online') {
                viewOnline.style.display = 'block';
                viewCoupon.style.display = 'none';

                btnOnline.style.background = 'rgba(255, 255, 255, 0.05)';
                btnOnline.style.borderColor = 'var(--border-color)';
                btnOnline.style.color = '#fff';

                btnCoupon.style.background = 'transparent';
                btnCoupon.style.borderColor = 'transparent';
                btnCoupon.style.color = 'var(--text-muted)';
                
                // Stop camera scanner when leaving Coupon tab
                stopScanner();
            } else {
                viewOnline.style.display = 'none';
                viewCoupon.style.display = 'flex';

                btnCoupon.style.background = 'rgba(255, 255, 255, 0.05)';
                btnCoupon.style.borderColor = 'var(--border-color)';
                btnCoupon.style.color = '#fff';

                btnOnline.style.background = 'transparent';
                btnOnline.style.borderColor = 'transparent';
                btnOnline.style.color = 'var(--text-muted)';
                
                setTimeout(() => {
                    document.getElementById('coupon_code_input').focus();
                }, 100);

                // Start camera scanner when entering Coupon tab
                startScanner();
            }
        }

        // Coupon redemption AJAX call
        function redeemCoupon() {
            const codeInput = document.getElementById('coupon_code_input');
            const code = codeInput.value.trim().toUpperCase();
            const errorDiv = document.getElementById('coupon-error-msg');
            const btn = document.getElementById('btnRedeemCoupon');

            if (!code) {
                errorDiv.innerText = 'Silakan masukkan kode kupon terlebih dahulu.';
                errorDiv.style.color = '#ef4444';
                errorDiv.style.display = 'block';
                return;
            }

            errorDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerText = 'MEMVERIFIKASI...';

            const formData = new FormData();
            formData.append('action', 'redeem_coupon');
            formData.append('order_id', orderId);
            formData.append('package_id', '<?php echo $packageId; ?>');
            formData.append('coupon_code', code);

            fetch('payment_gateway.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    errorDiv.style.color = '#52b788';
                    errorDiv.innerText = data.message;
                    errorDiv.style.display = 'block';
                    
                    setTimeout(() => {
                        window.location.href = 'order.php?session_id=' + orderId;
                    }, 1000);
                } else {
                    errorDiv.style.color = '#ef4444';
                    errorDiv.innerText = data.message;
                    errorDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerText = 'GUNAKAN KUPON';
                }
            })
            .catch(err => {
                console.error(err);
                errorDiv.style.color = '#ef4444';
                errorDiv.innerText = 'Koneksi error. Silakan coba beberapa saat lagi.';
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerText = 'GUNAKAN KUPON';
            });
        }
        
        // Dummy Mode Success handler
        function simulatePaymentSuccess() {
            const formData = new FormData();
            formData.append('action', 'confirm_payment');
            formData.append('order_id', orderId);
            
            fetch('payment_gateway.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("✓ Pembayaran Sukses! Anda sekarang terdaftar dalam antrean aktif.");
                    window.location.href = 'order.php?session_id=' + orderId;
                } else {
                    alert("❌ Gagal: " + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert("❌ Gagal menyambungkan ke server pembayaran.");
            });
        }

        // Midtrans Status verification via PHP backend
        function checkMidtransStatus(showAlert) {
            const formData = new FormData();
            formData.append('action', 'check_midtrans_status');
            formData.append('order_id', orderId);
            
            fetch('payment_gateway.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'PAID') {
                        if (showAlert) {
                            alert("✓ " + data.message);
                        }
                        window.location.href = 'order.php?session_id=' + orderId;
                    } else {
                        if (showAlert) {
                            alert("ℹ️ " + data.message);
                        }
                    }
                } else {
                    if (showAlert) {
                        alert("❌ Gagal: " + data.message);
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (showAlert) {
                    alert("❌ Gagal terhubung ke server verifikasi.");
                }
            });
        }

        // Automatic Status Polling for Midtrans Mode (every 4 seconds)
        <?php if ($settings['payment_mode'] === 'midtrans' && !$midtransError && $midtransQrUrl): ?>
            setInterval(() => {
                checkMidtransStatus(false);
            }, 4000);
        <?php endif; ?>
    </script>
</body>
</html>
