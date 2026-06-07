<?php
session_start();

$sessionId = isset($_GET['id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['id']) : '';

$photoFile = '';
$metaData = null;
$characterDetail = null;
$eventName = 'Umum (Default)';
$licenseKey = '';
$dateString = '';

$uploadDir = __DIR__ . '/uploads/';

if ($sessionId) {
    $photoPath = $uploadDir . $sessionId . '_photo.png';
    $metaPath = $uploadDir . $sessionId . '_meta.json';
    
    if (file_exists($photoPath)) {
        $photoFile = 'uploads/' . $sessionId . '_photo.png';
    }
    
    if (file_exists($metaPath)) {
        $metaData = json_decode(file_get_contents($metaPath), true);
        
        // 1. Resolve character details
        $characterId = isset($metaData['frame_id']) ? $metaData['frame_id'] : '';
        if ($characterId) {
            $charactersFile = __DIR__ . '/characters.json';
            if (file_exists($charactersFile)) {
                $characters = json_decode(file_get_contents($charactersFile), true);
                if (is_array($characters)) {
                    foreach ($characters as $char) {
                        if ($char['id'] === $characterId) {
                            $characterDetail = $char;
                            break;
                        }
                    }
                }
            }
        }
        
        // 2. Resolve event name
        $eventId = isset($metaData['event_id']) ? $metaData['event_id'] : 'general';
        $configPath = __DIR__ . '/frames/config.json';
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            if (is_array($config) && isset($config['events'])) {
                foreach ($config['events'] as $evt) {
                    if ($evt['id'] === $eventId) {
                        $eventName = $evt['name'];
                        break;
                    }
                }
            }
        }
        
        // 3. Generate key and dates
        $timestamp = isset($metaData['timestamp']) ? intval($metaData['timestamp']) : time();
        $dateString = date('d F Y • H:i', $timestamp);
        
        // Dynamic Key Format: LIC-[session_id_part]-[md5_hash_part]-[date]
        $hashPart = strtoupper(substr(md5($sessionId), 0, 4));
        $sessionPart = strtoupper(substr($sessionId, 0, 4));
        $datePart = date('Ymd', $timestamp);
        $licenseKey = "LIC-{$sessionPart}-{$hashPart}-{$datePart}";
    }
}

$verified = !empty($photoFile) && $metaData !== null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $verified ? "Lisensi Resmi: " . ($characterDetail ? htmlspecialchars($characterDetail['name']) : "Karakter AI") : "Verifikasi Lisensi Gagal"; ?> - Creative Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #08080a;
            --card-bg: rgba(20, 20, 28, 0.65);
            --primary-red: #e63946;
            --primary-gold: #f7b801;
            --text-main: #f8f9fa;
            --text-muted: #8e8e9f;
            --border-color: rgba(255, 255, 255, 0.08);
            --neon-green: #39ff14;
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
            justify-content: center;
            padding: 24px 16px;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Glow Backgrounds */
        .glow-1 {
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(230, 57, 70, 0.1) 0%, rgba(8, 8, 10, 0) 70%);
            pointer-events: none;
            z-index: 1;
        }

        .glow-2 {
            position: absolute;
            bottom: -250px;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(247, 184, 1, 0.05) 0%, rgba(8, 8, 10, 0) 70%);
            pointer-events: none;
            z-index: 1;
        }

        .license-container {
            max-width: 840px;
            width: 100%;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 20px;
            animation: scaleIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes scaleIn {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: white;
            text-decoration: none;
        }
        .logo span { color: var(--primary-red); }

        .verification-badge {
            background-color: rgba(57, 255, 20, 0.1);
            border: 1px solid rgba(57, 255, 20, 0.3);
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--neon-green);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 0 15px rgba(57, 255, 20, 0.15);
        }

        .verification-badge.invalid {
            background-color: rgba(230, 57, 70, 0.1);
            border: 1px solid rgba(230, 57, 70, 0.3);
            color: var(--primary-red);
            box-shadow: 0 0 15px rgba(230, 57, 70, 0.15);
        }

        /* main card */
        .license-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative stamp background */
        .license-card::before {
            content: "ORIGINAL";
            position: absolute;
            font-size: 8.5rem;
            font-weight: 900;
            color: rgba(255,255,255,0.015);
            letter-spacing: 8px;
            transform: rotate(-35deg) translate(-50px, 150px);
            pointer-events: none;
            z-index: 0;
            user-select: none;
        }

        @media (max-width: 768px) {
            .license-card {
                grid-template-columns: 1fr;
                padding: 24px;
                gap: 28px;
            }
        }

        /* Image Side */
        .image-side {
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .image-frame {
            width: 100%;
            max-width: 320px;
            aspect-ratio: 3/4;
            border-radius: 20px;
            overflow: hidden;
            background-color: #000;
            border: 2px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 15px 30px rgba(0,0,0,0.5);
            position: relative;
            transition: all 0.4s;
        }

        .image-frame:hover {
            border-color: var(--primary-gold);
            transform: scale(1.02);
            box-shadow: 0 20px 40px rgba(247, 184, 1, 0.15);
        }

        .image-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Info Side */
        .info-side {
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 20px;
        }

        .license-title {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1.2;
            background: linear-gradient(135deg, #ffffff 40%, #c0c0d0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .license-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .meta-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 16px 0;
            margin: 6px 0;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }

        .meta-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-val {
            color: white;
            font-weight: 600;
            text-align: right;
        }

        .meta-val.serial {
            font-family: monospace;
            font-size: 0.95rem;
            color: var(--primary-gold);
            letter-spacing: 0.5px;
        }

        .badge-seal-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }

        .seal-stamp {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .seal-stamp i {
            font-size: 1.5rem;
            color: var(--primary-gold);
            text-shadow: 0 0 10px rgba(247, 184, 1, 0.3);
        }

        .btn-download {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s;
        }

        .btn-download:hover {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            box-shadow: 0 5px 15px rgba(230, 57, 70, 0.3);
            transform: translateY(-2px);
        }

        /* Error state styling */
        .error-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 48px 32px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
        }

        .error-icon {
            font-size: 4rem;
            color: var(--primary-red);
            text-shadow: 0 0 20px rgba(230, 57, 70, 0.4);
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
        }

        .error-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 480px;
        }

        .footer {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 40px;
            z-index: 10;
        }
    </style>
</head>
<body>

    <div class="glow-1"></div>
    <div class="glow-2"></div>

    <div class="license-container">
        
        <header>
            <a href="index.php" class="logo">Creative<span>Studio</span></a>
            
            <?php if ($verified): ?>
                <div class="verification-badge">
                    <i class="fa-solid fa-circle-check"></i> Lisensi Terverifikasi Resmi
                </div>
            <?php else: ?>
                <div class="verification-badge invalid">
                    <i class="fa-solid fa-triangle-exclamation"></i> Lisensi Tidak Terdaftar
                </div>
            <?php endif; ?>
        </header>

        <?php if ($verified): ?>
            <!-- LICENSE METADATA CARD (VERIFIED) -->
            <div class="license-card">
                
                <!-- Portrait Photo Box -->
                <div class="image-side">
                    <div class="image-frame">
                        <img src="<?php echo htmlspecialchars($photoFile); ?>" alt="Karakter Lisensi Resmi">
                    </div>
                </div>

                <!-- Technical Specs & Certifications -->
                <div class="info-side">
                    <div class="license-title">
                        <?php echo $characterDetail ? htmlspecialchars($characterDetail['name']) : "Karakter Kustom"; ?>
                    </div>
                    
                    <div class="license-desc">
                        <?php echo $characterDetail ? htmlspecialchars($characterDetail['description']) : "Lisensi resmi untuk potret karakter kustom yang digenerasikan secara real-time via sistem AI Creative Studio Kiosk."; ?>
                    </div>

                    <div class="meta-list">
                        <div class="meta-item">
                            <span class="meta-label">Nomor Lisensi Serial</span>
                            <span class="meta-val serial"><?php echo htmlspecialchars($licenseKey); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Sesi Event Asal</span>
                            <span class="meta-val"><?php echo htmlspecialchars($eventName); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Tanggal Terbit Lisensi</span>
                            <span class="meta-val"><?php echo htmlspecialchars($dateString); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Status Otoritas</span>
                            <span class="meta-val" style="color:var(--neon-green); font-weight:700;">AKTIF & SAH</span>
                        </div>
                    </div>

                    <div class="badge-seal-container">
                        <div class="seal-stamp">
                            <i class="fa-solid fa-award"></i>
                            <span>AI Verified Security</span>
                        </div>
                        <a href="<?php echo htmlspecialchars($photoFile); ?>" download class="btn-download">
                            <i class="fa-solid fa-download"></i> Unduh Foto Asli
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- EXCEPTION CARD (NOT VERIFIED) -->
            <div class="error-card">
                <div class="error-icon">
                    <i class="fa-solid fa-shield-virus"></i>
                </div>
                <div class="error-title">Sertifikat Tidak Valid</div>
                <p class="error-desc">
                    Maaf, kode identitas sesi atau berkas lisensi yang Anda cari tidak terdaftar dalam database Creative Studio. 
                    Pastikan Anda memindai kode QR dari ID Card resmi yang dicetak oleh mesin Kiosk kami.
                </p>
                <a href="index.php" class="btn-download" style="border-color: var(--primary-red); color: white;">
                    <i class="fa-solid fa-house"></i> Kembali ke Beranda
                </a>
            </div>
        <?php endif; ?>

        <footer class="footer">
            &copy; <?php echo date('Y'); ?> Creative Studio AI Licensing Manager. Seluruh Hak Cipta Dilindungi.
        </footer>

    </div>

</body>
</html>
