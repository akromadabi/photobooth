<?php
session_start();

$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';
$configPath = __DIR__ . '/frames/config.json';

// Helper to get state
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

// 1. Handle Order creation (Form POST)
if (isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $packageId = isset($_POST['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['package_id']) : '';
    
    if ($packageId) {
        $state = getQueueState($queueFile);
        
        // Resolve next queue number
        $maxQueue = 0;
        foreach ($state['queue_list'] as $item) {
            if ($item['queue_number'] > $maxQueue) {
                $maxQueue = $item['queue_number'];
            }
        }
        $nextQueue = $maxQueue + 1;
        $sessionId = bin2hex(random_bytes(8));
        
        // Add new unpaid item to queue list
        $state['queue_list'][] = [
            "queue_number" => $nextQueue,
            "session_id" => $sessionId,
            "status" => "UNPAID",
            "package_id" => $packageId,
            "timestamp" => time()
        ];
        
        saveQueueState($queueFile, $state);
        
        // Redirect to payment gateway simulator
        header("Location: payment_gateway.php?order_id=$sessionId&package_id=$packageId");
        exit;
    }
}

// 2. Load configurations
$packages = [];
if (file_exists($packagesFile)) {
    $packages = json_decode(file_get_contents($packagesFile), true);
}

$config = ['events' => [], 'frames' => []];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

// Check if we are in Remote Controller mode (?session_id=...)
$sessionId = isset($_GET['session_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['session_id']) : '';
$isRemoteMode = !empty($sessionId);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isRemoteMode ? 'Remote Controller' : 'Pesan Paket Photobooth'; ?> - Creative Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0c0c0f;
            --card-bg: #14141a;
            --primary-red: #e63946;
            --primary-gold: #f7b801;
            --text-main: #f8f9fa;
            --text-muted: #8d8d9f;
            --border-color: #22222a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .header {
            width: 100%;
            max-width: 480px;
            text-align: center;
            margin-bottom: 24px;
            margin-top: 10px;
        }

        .logo {
            font-weight: 800;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }
        .logo span { color: var(--primary-red); }

        .subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        /* CATALOG SCREEN STYLES */
        .catalog-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .package-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            cursor: pointer;
            transition: all 0.25s;
            position: relative;
        }

        .package-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-2px);
        }

        .package-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .package-name {
            font-size: 1.25rem;
            font-weight: 800;
        }

        .package-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-red);
        }

        .features-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-top: 1px solid var(--border-color);
            padding-top: 14px;
        }

        .feature-item {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-icon {
            font-size: 1rem;
        }

        .feature-active { color: #52b788; }
        .feature-inactive { color: #555; text-decoration: line-through; }

        .btn-order {
            background-color: var(--primary-red);
            color: white;
            font-size: 1rem;
            font-weight: 700;
            padding: 14px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.25s;
            font-family: inherit;
            margin-top: 10px;
        }
        .btn-order:hover { background-color: #d62d3a; }

        /* REMOTE SCREEN STYLES */
        .remote-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .glass-card {
            background-color: rgba(20, 20, 26, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
        }

        .queue-badge {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary-red);
            background-color: rgba(230,57,70,0.08);
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(230,57,70,0.2);
            box-shadow: 0 0 20px rgba(230,57,70,0.1);
        }

        .waiting-status {
            font-size: 1.15rem;
            font-weight: 700;
            color: white;
        }

        .waiting-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Layout & Frame Selection UI */
        .selector-box {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .selector-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .layout-grid-select {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .layout-btn {
            background-color: #0c0c0f;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 10px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .layout-btn.active {
            border-color: var(--primary-red);
            color: white;
            background-color: rgba(230,57,70,0.05);
        }

        .frame-scroll-select {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 12px;
            width: 100%;
        }

        .frame-item-card {
            width: 120px;
            height: 190px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            background-color: #0c0c0f;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            padding: 6px;
            gap: 6px;
        }

        .frame-item-card.active {
            border-color: var(--primary-gold);
            transform: scale(1.02);
        }

        .frame-item-preview {
            flex: 1;
            border-radius: 6px;
            overflow: hidden;
            background-color: #08080a;
            display: flex;
            justify-content: center;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="35" r="12" fill="rgb(40,40,50)"/><path d="M50 53c-15 0-25 8-25 20h50c0-12-10-20-25-20z" fill="rgb(40,40,50)"/></svg>');
            background-repeat: repeat;
        }
        
        .frame-item-preview.layout-strip {
            background-size: 100% 25%;
        }
        
        .frame-item-preview.layout-grid {
            background-size: 50% 50%;
        }
        
        .frame-item-preview.layout-postcard {
            background-size: 100% 100%;
            background-repeat: no-repeat;
        }
        
        .frame-item-preview img {
            height: 100%;
            width: auto;
            object-fit: contain;
            position: relative;
            z-index: 2;
        }

        .frame-item-name {
            font-size: 0.7rem;
            text-align: center;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-main);
            padding: 2px 0;
        }

        /* Glowing red capture button */
        .btn-capture-glowing {
            background: linear-gradient(135deg, var(--primary-red), #ff6b6b);
            color: white;
            font-size: 1.25rem;
            font-weight: 900;
            padding: 20px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0.4);
            animation: pulseCapture 2s infinite cubic-bezier(0.66, 0, 0, 1);
            width: 100%;
            margin-top: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        @keyframes pulseCapture {
            0% { transform: scale(0.98); box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0.4); }
            70% { transform: scale(1.02); box-shadow: 0 15px 35px rgba(230, 57, 70, 0.5), 0 0 0 15px rgba(230, 57, 70, 0); }
            100% { transform: scale(0.98); box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0); }
        }

        .btn-capture-glowing:disabled {
            background: #2a2a35;
            box-shadow: none;
            animation: none;
            color: var(--text-muted);
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">Creative<span>Studio</span></div>
        <div class="subtitle">Kiosk Self-Service Portal</div>
    </div>

    <?php if (!$isRemoteMode): ?>
        <!-- CATALOG SCREEN -->
        <div class="catalog-container">
            <div class="section-title">Pilih Paket Foto:</div>
            
            <form action="order.php" method="POST">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" id="selectedPackageInput" name="package_id" value="">
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($packages as $pkg): ?>
                        <div class="package-card" onclick="selectPackage('<?php echo $pkg['id']; ?>', this)">
                            <div class="package-header">
                                <div class="package-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                                <div class="package-price">Rp <?php echo number_format($pkg['price'], 0, ',', '.'); ?></div>
                            </div>
                            <div class="features-list">
                                <div class="feature-item <?php echo $pkg['features']['print']?'feature-active':'feature-inactive'; ?>">
                                    <span class="feature-icon"><?php echo $pkg['features']['print'] ? '✓' : '✗'; ?></span> Cetak Struk Fisik
                                </div>
                                <div class="feature-item <?php echo $pkg['features']['download']?'feature-active':'feature-inactive'; ?>">
                                    <span class="feature-icon"><?php echo $pkg['features']['download'] ? '✓' : '✗'; ?></span> Download Foto Strip
                                </div>
                                <div class="feature-item <?php echo $pkg['features']['gif']?'feature-active':'feature-inactive'; ?>">
                                    <span class="feature-icon"><?php echo $pkg['features']['gif'] ? '✓' : '✗'; ?></span> Live Animated GIF
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn-order" id="btnOrderSubmit" disabled>PESAN SEKARANG</button>
            </form>
        </div>

        <script>
            function selectPackage(id, element) {
                document.getElementById('selectedPackageInput').value = id;
                document.querySelectorAll('.package-card').forEach(c => c.style.borderColor = 'var(--border-color)');
                element.style.borderColor = 'var(--primary-red)';
                document.getElementById('btnOrderSubmit').disabled = false;
            }
        </script>

    <?php else: ?>
        <!-- REMOTE CONTROLLER & QUEUE SCREEN -->
        <div class="remote-container">
            
            <!-- Dynamic state block populated by JS polling -->
            <div class="glass-card" id="remoteStatusCard">
                <div class="queue-badge" id="queueNumBadge">-</div>
                <div class="waiting-status" id="queueStatusTitle">Memuat status antrean...</div>
                <div class="waiting-desc" id="queueStatusDesc">Menghubungkan ke sistem antrean Kiosk. Silakan tunggu sebentar.</div>
            </div>

            <!-- Controller Selection Box (Visible only when ACTIVE) -->
            <div class="glass-card" id="remoteControllerBox" style="display:none;">
                <div class="selector-box">
                    <span class="selector-label">1. Pilih Layout</span>
                    <div class="layout-grid-select">
                        <button class="layout-btn active" id="layout-strip" onclick="setSessionLayout('strip')">Strip</button>
                        <button class="layout-btn" id="layout-grid" onclick="setSessionLayout('grid')">Grid</button>
                        <button class="layout-btn" id="layout-postcard" onclick="setSessionLayout('postcard')">Card</button>
                    </div>
                </div>

                <div class="selector-box">
                    <span class="selector-label">2. Pilih Bingkai</span>
                    <div class="frame-scroll-select" id="remoteFrameList">
                        <!-- Loaded dynamically via JS -->
                    </div>
                </div>

                <button class="btn-capture-glowing" id="btnCaptureStart" onclick="sendCaptureCommand()">
                    MULAI FOTO
                </button>
            </div>

        </div>

        <script>
            const sessionId = '<?php echo $sessionId; ?>';
            const serverConfig = <?php echo json_encode($config); ?>;
            
            let currentStatus = 'WAITING';
            let selectedLayout = 'strip';
            let selectedFrameId = '';
            
            // TTS countdown assistant offline Indonesian
            function speakAssistiveCue(text) {
                if ('speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'id-ID';
                    window.speechSynthesis.speak(utterance);
                }
            }

            // Start polling status
            function pollQueueStatus() {
                fetch('kiosk_control.php?action=check_queue&session_id=' + sessionId)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            currentStatus = data.status;
                            document.getElementById('queueNumBadge').innerText = '#' + data.queue_number;
                            
                            const title = document.getElementById('queueStatusTitle');
                            const desc = document.getElementById('queueStatusDesc');
                            const controller = document.getElementById('remoteControllerBox');
                            
                            if (data.status === 'WAITING') {
                                title.innerText = 'Menunggu Giliran Anda...';
                                desc.innerHTML = `Antrean Aktif Kiosk saat ini: <b>#${data.active_queue_number}</b>.<br>Ada <b>${data.total_waiting} orang</b> lagi di depan Anda.`;
                                controller.style.display = 'none';
                            } 
                            else if (data.status === 'ACTIVE') {
                                title.innerText = 'GILIRAN ANDA AKTIF';
                                desc.innerHTML = 'Silakan pilih layout dan frame di bawah, lalu tekan tombol ambil foto untuk memicu kamera tablet!';
                                controller.style.display = 'flex';
                                loadFramesList(data.package_id);
                            }
                            else if (data.status === 'CAPTURING') {
                                title.innerText = 'PROSES MEMOTRET...';
                                desc.innerHTML = 'Kamera depan tablet sedang aktif mengambil pose Anda. Bersiaplah berpose di depan Kiosk!';
                                controller.style.display = 'none';
                            }
                            else if (data.status === 'FINISHED') {
                                title.innerText = 'SESI FOTO SELESAI';
                                desc.innerHTML = 'Foto Anda sedang diproses. Menuju halaman portal unduhan...';
                                controller.style.display = 'none';
                                
                                speakAssistiveCue("Sesi foto selesai. Terima kasih!");
                                
                                // Redirect to index.php
                                setTimeout(() => {
                                    window.location.href = 'index.php?id=' + sessionId;
                                }, 2000);
                            }
                        }
                    })
                    .catch(err => console.error("Polling error", err));
            }

            // Load frames matching active event ID or general fallback
            function loadFramesList() {
                const list = document.getElementById('remoteFrameList');
                if (list.children.length > 0) return; // Already loaded
                
                list.innerHTML = "";
                let matchedFrames = [];
                if (serverConfig.frames) {
                    matchedFrames = serverConfig.frames.filter(f => f.type.toLowerCase() === selectedLayout.toLowerCase());
                }
                
                // Load general fallback frames
                let filtered = matchedFrames.filter(f => f.event_id === 'general' || !f.event_id);
                
                filtered.forEach((f, idx) => {
                    const card = document.createElement('div');
                    card.className = 'frame-item-card ' + (idx === 0 ? 'active' : '');
                    if (idx === 0) selectedFrameId = f.id;
                    
                    card.onclick = () => {
                        document.querySelectorAll('.frame-item-card').forEach(c => c.classList.remove('active'));
                        card.classList.add('active');
                        selectedFrameId = f.id;
                    };
                    
                    card.innerHTML = `
                        <div class="frame-item-preview layout-${selectedLayout}">
                            <img src="../${f.image_url}" onerror="this.src='https://placehold.co/50x120/121212/ffffff?text=${encodeURIComponent(f.name)}'">
                        </div>
                        <div class="frame-item-name">${f.name}</div>
                    `;
                    list.appendChild(card);
                });
            }

            function setSessionLayout(layout) {
                selectedLayout = layout;
                document.querySelectorAll('.layout-btn').forEach(b => b.classList.remove('active'));
                document.getElementById('layout-' + layout).classList.add('active');
                
                // Reload frames matching this layout
                const list = document.getElementById('remoteFrameList');
                list.innerHTML = "";
                loadFramesList();
            }

            function sendCaptureCommand() {
                if (!selectedFrameId) return;
                
                document.getElementById('btnCaptureStart').disabled = true;
                document.getElementById('btnCaptureStart').innerText = "MENYIAPKAN KAMERA...";
                
                const formData = new FormData();
                formData.append('session_id', sessionId);
                formData.append('command', 'START_CAPTURE');
                formData.append('frame_id', selectedFrameId);
                formData.append('layout', selectedLayout);
                
                fetch('kiosk_control.php?action=send_command', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Play local sync countdown audio cue simulation
                        speakAssistiveCue("Mempersiapkan kamera. Bersiaplah untuk pose pertama.");
                        setTimeout(() => { playSyncCountdown(); }, 2500);
                    } else {
                        alert("Gagal: " + data.message);
                        document.getElementById('btnCaptureStart').disabled = false;
                        document.getElementById('btnCaptureStart').innerText = "MULAI FOTO";
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('btnCaptureStart').disabled = false;
                });
            }

            function playSyncCountdown() {
                let slotsCount = selectedLayout === 'postcard' ? 1 : 4;
                let pose = 1;
                
                function runVoicePrompt() {
                    if (pose > slotsCount) return;
                    
                    speakAssistiveCue("Pose ke " + pose);
                    setTimeout(() => { speakAssistiveCue("Tiga"); }, 1500);
                    setTimeout(() => { speakAssistiveCue("Dua"); }, 2500);
                    setTimeout(() => { speakAssistiveCue("Satu"); }, 3500);
                    setTimeout(() => { speakAssistiveCue("Senyum"); }, 4500);
                    
                    // Trigger next pose sync offset (~6s total loop)
                    setTimeout(() => {
                        pose++;
                        runVoicePrompt();
                    }, 6500);
                }
                
                runVoicePrompt();
            }

            // Poll every 1s
            setInterval(pollQueueStatus, 1000);
            pollQueueStatus();
        </script>
    <?php endif; ?>
</body>
</html>
