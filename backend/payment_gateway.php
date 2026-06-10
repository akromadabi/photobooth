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

$midtransToken = '';
$midtransRedirectUrl = '';
$midtransError = '';

if ($settings['payment_mode'] === 'midtrans' && $orderQueueItem['status'] === 'UNPAID') {
    if (isset($orderQueueItem['midtrans_token']) && $orderQueueItem['midtrans_token']) {
        $midtransToken = $orderQueueItem['midtrans_token'];
        $midtransRedirectUrl = $orderQueueItem['midtrans_redirect_url'];
    } else {
        $serverKey = $settings['midtrans_server_key'];
        $isProduction = $settings['midtrans_environment'] === 'production';
        $snapUrl = $isProduction 
            ? "https://app.midtrans.com/snap/v1/transactions" 
            : "https://app.sandbox.midtrans.com/snap/v1/transactions";

        $payload = [
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
            "credit_card" => [
                "secure" => true
            ]
        ];

        $ch = curl_init($snapUrl);
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
            $errMsgs = isset($resError['error_messages']) ? implode(', ', $resError['error_messages']) : $response;
            $midtransError = "Midtrans API Error ($httpCode): " . $errMsgs;
        } else {
            $resData = json_decode($response, true);
            if (isset($resData['token'])) {
                $midtransToken = $resData['token'];
                $midtransRedirectUrl = $resData['redirect_url'];
                
                // Save to queue state
                $state = getQueueState($queueFile);
                foreach ($state['queue_list'] as &$item) {
                    if ($item['session_id'] === $orderId) {
                        $item['midtrans_token'] = $midtransToken;
                        $item['midtrans_redirect_url'] = $midtransRedirectUrl;
                        break;
                    }
                }
                unset($item);
                saveQueueState($queueFile, $state);
            } else {
                $midtransError = "Struktur respon Midtrans tidak valid.";
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
    <title>Pembayaran QRIS - Creative Studio Kiosk</title>
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
    </style>
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

        <?php if ($settings['payment_mode'] === 'midtrans'): ?>
            <?php if ($midtransError): ?>
                <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 16px; text-align: center;">
                    <div style="color: #ef4444; font-weight: 700; font-size: 0.9rem; margin-bottom: 8px;">❌ Gagal Memulai Pembayaran</div>
                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 16px; line-height: 1.4;"><?php echo htmlspecialchars($midtransError); ?></div>
                    <button class="btn-verify" onclick="window.location.reload()" style="background-color: var(--primary-red); box-shadow: 0 4px 15px rgba(230, 57, 70, 0.25);">Coba Lagi</button>
                </div>
            <?php else: ?>
                <div class="qris-section" style="padding: 10px 0; text-align: center; display: flex; flex-direction: column; gap: 8px;">
                    <div style="font-size: 1.5rem;">📱</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: #fff;">Selesaikan Pembayaran Anda</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4; max-width: 300px; margin: 0 auto;">
                        Klik tombol di bawah untuk membuka pilihan pembayaran (QRIS, GoPay, ShopeePay, Virtual Account, dll).
                    </div>
                </div>

                <button class="btn-verify" id="btnPayMidtrans" onclick="payWithMidtrans()" style="background-color: #4f46e5; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.25); margin-bottom: 4px;">BAYAR SEKARANG</button>
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

    <!-- Script imports based on mode -->
    <?php if ($settings['payment_mode'] === 'midtrans' && !$midtransError): ?>
        <?php 
        $snapScriptUrl = ($settings['midtrans_environment'] === 'production') 
            ? "https://app.midtrans.com/snap/snap.js" 
            : "https://app.sandbox.midtrans.com/snap/snap.js";
        ?>
        <script src="<?php echo $snapScriptUrl; ?>" data-client-key="<?php echo htmlspecialchars($settings['midtrans_client_key']); ?>"></script>
    <?php endif; ?>

    <script>
        const orderId = '<?php echo $orderId; ?>';
        
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

        // Midtrans Pay trigger
        function payWithMidtrans() {
            const token = '<?php echo $midtransToken; ?>';
            if (!token) return;
            
            snap.pay(token, {
                onSuccess: function(result){
                    console.log('success', result);
                    checkMidtransStatus(false);
                },
                onPending: function(result){
                    console.log('pending', result);
                    alert("Pembayaran Anda sedang tertunda. Selesaikan pembayaran Anda.");
                },
                onError: function(result){
                    console.log('error', result);
                    alert("Terjadi kesalahan pada pembayaran.");
                },
                onClose: function(){
                    console.log('customer closed the popup without finishing the payment');
                }
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
        <?php if ($settings['payment_mode'] === 'midtrans' && !$midtransError && $midtransToken): ?>
            // Automatically open Snap popup on load for better UX
            window.addEventListener('DOMContentLoaded', () => {
                setTimeout(payWithMidtrans, 800);
            });
            
            setInterval(() => {
                checkMidtransStatus(false);
            }, 4000);
        <?php endif; ?>
    </script>
</body>
</html>
