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

$state = getQueueState($queueFile);
$currentQueueNumber = 0;
foreach ($state['queue_list'] as $item) {
    if ($item['session_id'] === $orderId) {
        $currentQueueNumber = $item['queue_number'];
        break;
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
            <div class="title">💵 GERBANG PEMBAYARAN SIMULASI</div>
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
    </div>

    <script>
        const orderId = '<?php echo $orderId; ?>';
        
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
                    // Redirect back to order page which will act as remote controller
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
    </script>
</body>
</html>
