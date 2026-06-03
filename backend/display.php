<?php
session_start();
$configPath = __DIR__ . '/frames/config.json';
$eventName = '';

// Load active event name if kiosk is locked to an event
$config = ['events' => []];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

// Get queue status for info display
$queueFile = __DIR__ . '/queue.json';
$waitingCount = 0;
$activeQueue = 0;
if (file_exists($queueFile)) {
    $queueState = json_decode(file_get_contents($queueFile), true);
    if ($queueState) {
        $activeQueue = $queueState['active_queue_number'];
        foreach ($queueState['queue_list'] as $item) {
            if ($item['status'] === 'WAITING') {
                $waitingCount++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Promosi - Creative Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        :root {
            --bg-color: #0a0a0d;
            --card-bg: #121217;
            --primary-red: #e63946;
            --primary-gold: #f7b801;
            --text-main: #f8f9fa;
            --text-muted: #8e8e9f;
            --border-color: #22222a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 40px 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background glows */
        .glow-left {
            position: absolute;
            top: -200px;
            left: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(230, 57, 70, 0.08) 0%, rgba(10, 10, 13, 0) 70%);
            pointer-events: none;
            z-index: 1;
        }

        .glow-right {
            position: absolute;
            bottom: -200px;
            right: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(247, 184, 1, 0.05) 0%, rgba(10, 10, 13, 0) 70%);
            pointer-events: none;
            z-index: 1;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin: auto;
            z-index: 2;
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
                gap: 40px;
                text-align: center;
            }
            .qr-card-wrapper {
                margin: 0 auto;
            }
        }

        /* Left Side Brand Info */
        .info-side {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .brand-header {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .logo {
            font-size: 3rem;
            font-weight: 900;
            letter-spacing: -1.5px;
            line-height: 1;
        }
        .logo span {
            color: var(--primary-red);
            text-shadow: 0 0 20px rgba(230, 57, 70, 0.3);
        }

        .tagline {
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
        }

        .main-headline {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, #ffffff 30%, #a0a0b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .desc-text {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Live Queue Display */
        .queue-status-box {
            display: flex;
            gap: 16px;
            margin-top: 10px;
        }

        @media (max-width: 900px) {
            .queue-status-box {
                justify-content: center;
            }
        }

        .status-pill {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-pill-icon {
            font-size: 1.3rem;
        }

        .status-pill-text {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .status-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .status-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Right Side QR Card */
        .qr-card-wrapper {
            max-width: 420px;
            width: 100%;
            position: relative;
        }

        .qr-card-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 104%;
            height: 104%;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-gold));
            border-radius: 32px;
            filter: blur(15px);
            opacity: 0.35;
            z-index: 1;
            animation: pulseGlow 4s infinite alternate;
        }

        @keyframes pulseGlow {
            0% { opacity: 0.25; filter: blur(12px); }
            100% { opacity: 0.45; filter: blur(20px); }
        }

        .qr-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 30px;
            text-align: center;
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .qr-container {
            background-color: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 260px;
            height: 260px;
        }

        #qrCanvas {
            width: 100%;
            height: 100%;
        }

        .qr-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .qr-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .qr-arrow {
            font-size: 1.5rem;
            animation: bounceY 1.5s infinite;
            color: var(--primary-gold);
        }

        @keyframes bounceY {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .footer {
            font-size: 0.8rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-top: 30px;
            z-index: 2;
        }
    </style>
</head>
<body>

    <div class="glow-left"></div>
    <div class="glow-right"></div>

    <div class="container">
        
        <!-- Left Column: Branding Info -->
        <div class="info-side">
            <div class="brand-header">
                <div class="tagline">Creative Studio</div>
                <div class="logo">Receipt<span>Photo</span></div>
            </div>
            
            <h1 class="main-headline">Self-Service Remote Kiosk Photobooth</h1>
            
            <p class="desc-text">
                Ingin berfoto dengan seru? Sekarang Anda bisa memesan paket, memilih bingkai, 
                dan mengontrol kamera Kiosk langsung menggunakan smartphone Anda! Pindai kode QR 
                di samping untuk memulai sesi foto instan Anda.
            </p>
            
            <div class="queue-status-box">
                <div class="status-pill">
                    <span class="status-pill-icon"><i class="fa-solid fa-ticket"></i></span>
                    <div class="status-pill-text">
                        <span class="status-label">Antrean Saat Ini</span>
                        <span class="status-value" id="infoActiveQueue">#<?php echo $activeQueue > 0 ? $activeQueue : '-'; ?></span>
                    </div>
                </div>
                <div class="status-pill">
                    <span class="status-pill-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                    <div class="status-pill-text">
                        <span class="status-label">Menunggu Antrean</span>
                        <span class="status-value" id="infoWaitingCount"><?php echo $waitingCount; ?> Orang</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: QR Code Display -->
        <div class="qr-card-wrapper">
            <div class="qr-card-glow"></div>
            <div class="qr-card">
                <div class="qr-arrow">▼</div>
                <div class="qr-title">SCAN UNTUK ORDER</div>
                
                <div class="qr-container">
                    <canvas id="qrCanvas"></canvas>
                </div>
                
                <div class="qr-desc">
                    Pindai QR Code di atas dengan kamera HP Anda untuk memilih paket hemat/best deal, melakukan pembayaran otomatis, dan mengambil foto!
                </div>
            </div>
        </div>

    </div>

    <footer class="footer">
        &copy; <?php echo date('Y'); ?> Creative Studio. All Rights Reserved.
    </footer>

    <script>
        // Resolve Order URL dynamically based on the current website domain
        const orderUrl = window.location.protocol + '//' + window.location.host + '/order.php';
        
        // Render QR Code
        const qr = new QRious({
            element: document.getElementById('qrCanvas'),
            value: orderUrl,
            size: 300,
            background: 'white',
            foreground: '#0a0a0d',
            level: 'H'
        });

        // Real-time update of queue status via get_queue_stats API
        function checkQueueInfo() {
            fetch('kiosk_control.php?action=get_queue_stats')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('infoActiveQueue').innerText = data.active_queue_number > 0 ? '#' + data.active_queue_number : '-';
                        document.getElementById('infoWaitingCount').innerText = data.total_waiting + ' Orang';
                    }
                })
                .catch(err => {});
        }

        // Long poll queue stats every 3 seconds
        setInterval(checkQueueInfo, 3000);
        checkQueueInfo();
    </script>
</body>
</html>
