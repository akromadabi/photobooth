<?php
$sessionId = isset($_GET['id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['id']) : '';
if (empty($sessionId)) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Creative Studio Web Portal</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --bg-color: #0c0c0e;
                --card-bg: #14141a;
                --primary: #4f46e5;
                --primary-hover: #4338ca;
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
                justify-content: center;
                align-items: center;
                padding: 24px;
                position: relative;
                overflow: hidden;
            }
            .background-glow-1 {
                position: absolute;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(79, 70, 229, 0.12) 0%, rgba(12, 12, 14, 0) 70%);
                z-index: -1;
                pointer-events: none;
                top: -10%;
                left: -10%;
            }
            .background-glow-2 {
                position: absolute;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, rgba(12, 12, 14, 0) 70%);
                z-index: -1;
                pointer-events: none;
                bottom: -10%;
                right: -10%;
            }
            .portal-container {
                width: 100%;
                max-width: 900px;
                text-align: center;
                z-index: 10;
            }
            .logo {
                font-weight: 800;
                font-size: 2.8rem;
                letter-spacing: -1.5px;
                margin-bottom: 8px;
                cursor: pointer;
                user-select: none;
                display: inline-block;
                color: #ffffff;
            }
            .logo span { color: var(--primary); }
            .subtitle {
                font-size: 0.8rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 2px;
                font-weight: 600;
                margin-bottom: 48px;
            }
            .portal-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 24px;
                width: 100%;
                margin-bottom: 40px;
            }
            .portal-card {
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 24px;
                padding: 36px 28px;
                text-decoration: none;
                color: inherit;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 20px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            }
            .portal-card:hover {
                transform: translateY(-8px);
                border-color: var(--primary);
                box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }
            .card-icon {
                width: 68px;
                height: 68px;
                border-radius: 20px;
                background-color: #1a1a24;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.8rem;
                color: var(--primary);
                transition: all 0.3s ease;
            }
            .portal-card:hover .card-icon {
                background-color: var(--primary);
                color: white;
                transform: scale(1.1);
                box-shadow: 0 0 15px rgba(79, 70, 229, 0.4);
            }
            .card-title {
                font-size: 1.2rem;
                font-weight: 700;
                color: #ffffff;
            }
            .card-desc {
                font-size: 0.85rem;
                color: var(--text-muted);
                line-height: 1.6;
            }
            .footer-info {
                font-size: 0.75rem;
                color: var(--text-muted);
                margin-top: 20px;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class="background-glow-1"></div>
        <div class="background-glow-2"></div>
        
        <div class="portal-container">
            <div class="logo" onclick="handleLogoClick()">Creative<span>Studio</span></div>
            <div class="subtitle">Creative Studio Web Portal</div>
            
            <div class="portal-grid">
                <a href="order.php" class="portal-card">
                    <div class="card-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div class="card-title">Halaman Pemesanan</div>
                    <div class="card-desc">Pilih paket photobooth Anda, lakukan pembayaran simulator, dan terima tiket antrean digital Anda.</div>
                </a>
                
                <a href="display.php" class="portal-card">
                    <div class="card-icon"><i class="fa-solid fa-desktop"></i></div>
                    <div class="card-title">Layar Antrean Kiosk</div>
                    <div class="card-desc">Tampilan antrean berjalan saat ini serta galeri slideshow foto terbaru untuk audiens kiosk.</div>
                </a>
                
                <a href="kiosk_sim.php" class="portal-card">
                    <div class="card-icon"><i class="fa-solid fa-camera"></i></div>
                    <div class="card-title">Simulator Kiosk</div>
                    <div class="card-desc">Simulasi layar sentuh interaktif mesin Kiosk untuk menguji proses pengambilan foto dan cetak.</div>
                </a>
            </div>
            
            <div class="footer-info">
                © <?php echo date('Y'); ?> Creative Studio. Seluruh hak cipta dilindungi.
            </div>
        </div>

        <script>
            let logoClicks = 0;
            let clickTimer = null;

            function handleLogoClick() {
                logoClicks++;
                
                if (logoClicks === 1) {
                    clickTimer = setTimeout(() => {
                        logoClicks = 0;
                    }, 1500);
                } else if (logoClicks === 3) {
                    clearTimeout(clickTimer);
                    logoClicks = 0;
                    
                    const toast = document.createElement('div');
                    toast.style.position = 'fixed';
                    toast.style.bottom = '30px';
                    toast.style.backgroundColor = '#4f46e5';
                    toast.style.color = '#ffffff';
                    toast.style.padding = '12px 24px';
                    toast.style.borderRadius = '20px';
                    toast.style.fontSize = '0.9rem';
                    toast.style.fontWeight = '600';
                    toast.style.boxShadow = '0 10px 15px -3px rgba(79, 70, 229, 0.3)';
                    toast.style.zIndex = '1000';
                    toast.innerHTML = 'Menuju Portal Administrasi... ⚙️';
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        window.location.href = 'admin.php';
                    }, 800);
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}
$photoFile = '';
$timelapseFile = '';

$uploadDir = __DIR__ . '/uploads/';

$frameId = '';
$eventId = '';
$eventName = '';
if ($sessionId) {
    // Look for photo
    if (file_exists($uploadDir . $sessionId . '_photo.png')) {
        $photoFile = 'uploads/' . $sessionId . '_photo.png';
    }
    
    // Look for timelapse (could be gif, mp4, etc.)
    $timelapseMatches = glob($uploadDir . $sessionId . '_timelapse.*');
    if (!empty($timelapseMatches)) {
        $timelapseFile = 'uploads/' . basename($timelapseMatches[0]);
    }
    
    // Look for metadata & package rules
    $packageFeatures = [
        'print' => true,
        'download' => true,
        'gif' => true,
        'sticker' => true
    ];
    if (file_exists($uploadDir . $sessionId . '_meta.json')) {
        $meta = json_decode(file_get_contents($uploadDir . $sessionId . '_meta.json'), true);
        if (isset($meta['frame_id'])) {
            $frameId = $meta['frame_id'];
        }
        if (isset($meta['event_id'])) {
            $eventId = $meta['event_id'];
        }
        if (isset($meta['package_id'])) {
            $packageId = $meta['package_id'];
            $packagesPath = __DIR__ . '/packages.json';
            if (file_exists($packagesPath)) {
                $packages = json_decode(file_get_contents($packagesPath), true);
                if (is_array($packages)) {
                    foreach ($packages as $pkg) {
                        if ($pkg['id'] === $packageId) {
                            if (isset($pkg['features'])) {
                                $packageFeatures = array_merge($packageFeatures, $pkg['features']);
                            }
                            break;
                        }
                    }
                }
            }
        }
    }
}

// Load event name and frame details from config.json
$frameWidth = 600;
$frameHeight = 2000;
$frameSlots = [];

$configPath = __DIR__ . '/frames/config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    if (is_array($config)) {
        if ($eventId && $eventId !== 'general' && isset($config['events'])) {
            foreach ($config['events'] as $evt) {
                if ($evt['id'] === $eventId) {
                    $eventName = $evt['name'];
                    break;
                }
            }
        }
        if (isset($config['frames']) && is_array($config['frames'])) {
            foreach ($config['frames'] as $frm) {
                if ($frm['id'] === $frameId) {
                    $frameWidth = isset($frm['width']) ? intval($frm['width']) : 600;
                    $frameHeight = isset($frm['height']) ? intval($frm['height']) : 2000;
                    $frameSlots = isset($frm['slots']) ? $frm['slots'] : [];
                    break;
                }
            }
        }
    }
}

$found = !empty($photoFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $eventName ? htmlspecialchars($eventName) : "Creative Studio"; ?> - Receipt Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.3.2/gifshot.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --bg-color: #0f0f12;
            --card-bg: #18181f;
            --primary-red: #e63946;
            --text-main: #f8f9fa;
            --text-muted: #a0a0b0;
            --border-color: #2a2a35;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
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
            padding: 20px 12px 100px 12px;
            box-sizing: border-box;
        }

        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: -1;
            pointer-events: none;
        }

        .background-glow {
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(230, 57, 70, 0.15) 0%, rgba(15, 15, 18, 0) 70%);
            pointer-events: none;
        }

        header {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 30px;
            animation: fadeInDown 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .logo {
            font-weight: 800;
            font-size: 2.2rem;
            color: var(--text-main);
            letter-spacing: -0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logo span {
            color: var(--primary-red);
        }

        .subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-top: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .main-container {
            width: 100%;
            max-width: 520px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            gap: 20px;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 10;
        }

        .media-container {
            width: 100%;
            background-color: #0b0b0d;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .photo-img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
            border-radius: 20px;
            transition: transform 0.5s ease;
        }

        .video-player {
            width: 100%;
            height: auto;
            border-radius: 20px;
            outline: none;
            display: block;
        }

        .tabs-header {
            display: flex;
            background-color: #0d0d11;
            border-radius: 12px;
            padding: 4px;
            border: 1px solid var(--border-color);
        }

        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: inherit;
        }

        .tab-btn.active {
            background-color: var(--primary-red);
            color: var(--text-main);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
        }

        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 16px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            transition: all 0.25s ease;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-red);
            color: var(--text-main);
            box-shadow: 0 8px 24px rgba(230, 57, 70, 0.25);
        }

        .btn-primary:hover {
            background-color: #d62d3a;
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(230, 57, 70, 0.35);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: var(--text-muted);
        }

        .footer-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 40px;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        /* Error state styling */
        .error-card {
            text-align: center;
            padding: 40px 20px;
        }

        .error-icon {
            font-size: 3rem;
            color: var(--primary-red);
            margin-bottom: 16px;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .error-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animated Effects CSS */

        /* 1. Film Scratches & Moving Dust (Newspaper strip only) */
        .media-container.effect-newspaper_strip::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.08;
            pointer-events: none;
            z-index: 5;
            animation: grainAnimation 1s steps(10) infinite;
        }

        @keyframes grainAnimation {
            0%, 100% { transform:translate(0, 0); }
            10% { transform:translate(-2%, -4%); }
            20% { transform:translate(-4%, 2%); }
            30% { transform:translate(2%, -6%); }
            40% { transform:translate(-6%, 4%); }
            50% { transform:translate(-2%, 8%); }
            60% { transform:translate(4%, -2%); }
            70% { transform:translate(-4%, 4%); }
            80% { transform:translate(2%, -2%); }
            90% { transform:translate(-2%, 2%); }
        }

        .scratches-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 6;
            overflow: hidden;
        }

        .scratch {
            position: absolute;
            width: 1px;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            opacity: 0;
            animation: scratchAnimation 2s infinite linear;
        }

        .scratch:nth-child(1) { left: 25%; animation-delay: 0.2s; }
        .scratch:nth-child(2) { left: 65%; animation-delay: 0.8s; }
        .scratch:nth-child(3) { left: 85%; animation-delay: 1.4s; }

        @keyframes scratchAnimation {
            0% { transform: translateX(0); opacity: 0; }
            5% { transform: translateX(5px); opacity: 0.3; }
            10% { transform: translateX(-3px); opacity: 0.3; }
            15% { transform: translateX(2px); opacity: 0; }
            100% { transform: translateX(0); opacity: 0; }
        }

        /* 2. Vogue Neon Glowing Borders (Magazine) */
        .media-container.effect-magazine_strip {
            box-shadow: 0 0 15px rgba(255, 0, 127, 0.2);
            animation: neonGlowAnimation 4s infinite alternate ease-in-out;
            border: 2px solid transparent !important;
        }

        @keyframes neonGlowAnimation {
            0% {
                box-shadow: 0 0 10px rgba(255, 0, 127, 0.3), inset 0 0 5px rgba(255, 0, 127, 0.2);
                border-color: rgba(255, 0, 127, 0.3) !important;
            }
            50% {
                box-shadow: 0 0 25px rgba(0, 242, 254, 0.5), inset 0 0 10px rgba(0, 242, 254, 0.3);
                border-color: rgba(0, 242, 254, 0.5) !important;
            }
            100% {
                box-shadow: 0 0 15px rgba(189, 0, 255, 0.4), inset 0 0 8px rgba(189, 0, 255, 0.2);
                border-color: rgba(189, 0, 255, 0.4) !important;
            }
        }

        /* 3. Shift Light Leaks (Retro Film) */
        .light-leak-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 6;
            background: radial-gradient(circle at 10% 20%, rgba(255, 100, 0, 0.25) 0%, rgba(255, 100, 0, 0) 50%),
                        radial-gradient(circle at 90% 80%, rgba(255, 60, 180, 0.2) 0%, rgba(255, 60, 180, 0) 50%);
            mix-blend-mode: color-dodge;
            animation: lightLeaksAnimation 8s infinite alternate ease-in-out;
        }

        @keyframes lightLeaksAnimation {
            0% {
                opacity: 0.6;
                transform: scale(1) rotate(0deg);
            }
            50% {
                opacity: 0.9;
                transform: scale(1.15) rotate(5deg) translate(2%, 3%);
            }
            100% {
                opacity: 0.4;
                transform: scale(0.95) rotate(-3deg) translate(-2%, -1%);
            }
        }

        /* Custom Spinner for rendering button */
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        @keyframes dash {
            0% {
                stroke-dasharray: 1, 150;
                stroke-dashoffset: 0;
            }
            50% {
                stroke-dasharray: 90, 150;
                stroke-dashoffset: -35;
            }
            100% {
                stroke-dasharray: 90, 150;
                stroke-dashoffset: -124;
            }
        }

        .hidden {
            display: none !important;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            header {
                margin-top: 10px;
                margin-bottom: 20px;
            }
            .logo {
                font-size: 1.8rem !important;
                display: flex !important;
                flex-wrap: wrap !important;
                justify-content: center !important;
                text-align: center !important;
                white-space: normal !important;
                word-break: break-word !important;
            }
            .subtitle {
                font-size: 0.8rem;
            }
            .main-container {
                padding: 14px;
                border-radius: 20px;
                gap: 16px;
            }
            .btn {
                padding: 12px;
                font-size: 0.95rem;
                border-radius: 12px;
            }
            .media-container {
                border-radius: 12px;
            }
            .photo-img, .video-player {
                border-radius: 12px;
            }
        }

        /* Floating Actions Bar */
        .floating-actions-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 480px;
            background: rgba(24, 24, 31, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 10px;
            display: flex;
            gap: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            z-index: 999;
            box-sizing: border-box;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { transform: translate(-50%, 100px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        .floating-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 6px;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            color: white;
            text-align: center;
            box-sizing: border-box;
        }

        .floating-btn-strip {
            background: linear-gradient(135deg, #e63946 0%, #b32431 100%);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
        }

        .floating-btn-strip:hover {
            background: linear-gradient(135deg, #f84f5c 0%, #d62d3a 100%);
            transform: translateY(-1px);
        }

        .floating-btn-gif {
            background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
            box-shadow: 0 4px 12px rgba(0, 242, 254, 0.3);
        }

        .floating-btn-gif:hover {
            background: linear-gradient(135deg, #33f5ff 0%, #66b5ff 100%);
            transform: translateY(-1px);
        }

        .floating-btn-sticker {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }

        .floating-btn-sticker:hover {
            background: linear-gradient(135deg, #3df77d 0%, #17a898 100%);
            transform: translateY(-1px);
        }

        .floating-btn:active {
            transform: scale(0.95) translateY(0);
        }
    </style>
</head>
<body>
    <div class="bg-container">
        <div class="background-glow"></div>
    </div>

    <header>
        <?php if ($eventName): ?>
            <div class="logo" style="display: block; text-align: center; justify-content: center;"><?php echo htmlspecialchars($eventName); ?></div>
            <div class="subtitle">Special Event Receipt Photo</div>
        <?php else: ?>
            <div class="logo">Creative<span>Studio</span></div>
            <div class="subtitle">Receipt Photo Booth</div>
        <?php endif; ?>
    </header>

    <?php if ($found): ?>
        <main class="main-container">
            <?php if ($timelapseFile): ?>
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('photo')">Photo Strip</button>
                    <button class="tab-btn" onclick="switchTab('timelapse')">Behind the Scenes</button>
                </div>
            <?php endif; ?>

            <div class="media-container effect-<?php echo htmlspecialchars($frameId); ?>" id="photo-wrapper">
                <img src="<?php echo htmlspecialchars($photoFile); ?>" class="photo-img" alt="Your Photo Strip">
                <?php if ($frameId === 'newspaper_strip'): ?>
                    <div class="scratches-overlay">
                        <div class="scratch"></div>
                        <div class="scratch"></div>
                        <div class="scratch"></div>
                    </div>
                <?php elseif ($frameId === 'retro_film_strip'): ?>
                    <div class="light-leak-overlay"></div>
                <?php endif; ?>
            </div>

            <?php if ($timelapseFile): ?>
                <?php 
                $isGif = pathinfo($timelapseFile, PATHINFO_EXTENSION) === 'gif';
                ?>
                <div class="media-container hidden" id="timelapse-wrapper">
                    <?php if ($isGif): ?>
                        <img src="<?php echo htmlspecialchars($timelapseFile); ?>" class="photo-img" alt="Behind the Scenes GIF">
                    <?php else: ?>
                        <video src="<?php echo htmlspecialchars($timelapseFile); ?>" class="video-player" controls autoplay loop muted></video>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="actions-group">
                <?php if ($packageFeatures['download']): ?>
                    <a href="<?php echo htmlspecialchars($photoFile); ?>" download="CreativeStudio_Photo.png" class="btn btn-primary" id="btn-download-photo">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download Photo Strip
                    </a>

                    <!-- Live video button removed due to client-side browser recorder issues -->
                <?php endif; ?>

                <?php if ($packageFeatures['gif']): ?>
                    <button onclick="generateGifSlideshow()" class="btn btn-secondary" id="btn-download-gif" style="background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); border: none; box-shadow: 0 4px 15px rgba(0, 242, 254, 0.3); color: white;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h18a2 2 0 0 1 2 2z"></path><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        Download GIF Animasi Lucu
                    </button>
                <?php endif; ?>

                <?php if ($packageFeatures['sticker']): ?>
                    <div class="sticker-buttons-container" style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px; width: 100%;">
                        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600; margin-bottom: 2px; text-align: center;">Koleksi Stiker WA (Sesuai Pose):</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%;">
                            <button onclick="downloadPoseSticker(0)" class="btn btn-secondary" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white; padding: 12px; font-size: 0.85rem; border-radius: 12px; height: auto; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); display: flex; align-items: center; justify-content: center; gap: 4px;">
                                Pose 1: Haha! 😂
                            </button>
                            <button onclick="downloadPoseSticker(1)" class="btn btn-secondary" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white; padding: 12px; font-size: 0.85rem; border-radius: 12px; height: auto; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); display: flex; align-items: center; justify-content: center; gap: 4px;">
                                Pose 2: Hehe.. 🤭
                            </button>
                            <button onclick="downloadPoseSticker(2)" class="btn btn-secondary" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white; padding: 12px; font-size: 0.85rem; border-radius: 12px; height: auto; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); display: flex; align-items: center; justify-content: center; gap: 4px;">
                                Pose 3: Gokil! 🤯
                            </button>
                            <button onclick="downloadPoseSticker(3)" class="btn btn-secondary" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white; padding: 12px; font-size: 0.85rem; border-radius: 12px; height: auto; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); display: flex; align-items: center; justify-content: center; gap: 4px;">
                                Pose 4: Kece! 😎
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($timelapseFile && $packageFeatures['download']): ?>
                    <a href="<?php echo htmlspecialchars($timelapseFile); ?>" download="CreativeStudio_Timelapse.<?php echo pathinfo($timelapseFile, PATHINFO_EXTENSION); ?>" class="btn btn-secondary hidden" id="btn-download-timelapse">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download Video Pose
                    </a>
                <?php endif; ?>
            </div>
        </main>
    <?php else: ?>
        <main class="main-container error-card">
            <div class="error-icon">✕</div>
            <div class="error-title">Photo Not Found</div>
            <div class="error-desc">
                Maaf, file foto Anda tidak ditemukan atau sudah kedaluwarsa. Pastikan QR code yang Anda scan sudah benar.
            </div>
            <div style="margin-top: 24px;">
                <a href="javascript:void(0)" onclick="location.reload()" class="btn btn-secondary">Coba Segarkan Halaman</a>
            </div>
        </main>
    <?php endif; ?>

    <footer class="footer-text">
        &copy; <?php echo date('Y'); ?> Creative Studio. All Rights Reserved.
    </footer>    <script>
        const activeFrameConfig = <?php echo json_encode([
            'width' => $frameWidth,
            'height' => $frameHeight,
            'slots' => $frameSlots
        ]); ?>;
        function switchTab(type) {
            const photoTab = document.querySelector('.tab-btn:nth-child(1)');
            const timelapseTab = document.querySelector('.tab-btn:nth-child(2)');
            const photoWrapper = document.getElementById('photo-wrapper');
            const timelapseWrapper = document.getElementById('timelapse-wrapper');
            const downloadPhoto = document.getElementById('btn-download-photo');
            const downloadAnimated = document.getElementById('btn-download-animated');
            const downloadTimelapse = document.getElementById('btn-download-timelapse');

            if (type === 'photo') {
                photoTab.classList.add('active');
                timelapseTab.classList.remove('active');
                photoWrapper.classList.remove('hidden');
                timelapseWrapper.classList.add('hidden');
                
                if(downloadPhoto) downloadPhoto.classList.remove('hidden');
                if(downloadAnimated) downloadAnimated.classList.remove('hidden');
                if(downloadTimelapse) downloadTimelapse.classList.add('hidden');
            } else {
                photoTab.classList.remove('active');
                timelapseTab.classList.add('active');
                photoWrapper.classList.add('hidden');
                timelapseWrapper.classList.remove('hidden');

                if(downloadPhoto) downloadPhoto.classList.add('hidden');
                if(downloadAnimated) downloadAnimated.classList.add('hidden');
                if(downloadTimelapse) downloadTimelapse.classList.remove('hidden');
                
                // If it is a video, try to play it
                const video = timelapseWrapper.querySelector('video');
                if (video) {
                    video.play().catch(e => console.log('Video autoplay interrupted'));
                }
            }
        }

        async function downloadAnimatedStrip() {
            const btn = document.getElementById('btn-download-animated');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 50 50" style="animation: spin 1s linear infinite; margin-right: 8px;">
                    <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" style="stroke-dasharray: 90, 150; stroke-dashoffset: 0; stroke: white; animation: dash 1.5s ease-in-out infinite;"></circle>
                </svg>
                Rendering... (0%)
            `;

            try {
                const img = document.querySelector('.photo-img');
                const frameId = "<?php echo htmlspecialchars($frameId); ?>";
                
                // Wait for image load if not fully cached
                if (!img.complete) {
                    await new Promise(resolve => img.onload = resolve);
                }

                const width = img.naturalWidth || 600;
                const height = img.naturalHeight || 2000;

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');

                const fps = 30;
                const durationSec = 4;
                const totalFrames = fps * durationSec;

                // Collect MediaRecorder chunks
                const stream = canvas.captureStream(fps);
                
                // Select supported MIME type (prefer mp4, fallback to webm)
                let mimeType = 'video/mp4;codecs=avc1';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'video/webm;codecs=vp9';
                }
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'video/webm;codecs=vp8';
                }
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'video/webm';
                }

                const recorder = new MediaRecorder(stream, { mimeType: mimeType });
                const chunks = [];
                recorder.ondataavailable = e => {
                    if (e.data && e.data.size > 0) chunks.push(e.data);
                };

                const downloadPromise = new Promise(resolve => {
                    recorder.onstop = () => {
                        const blob = new Blob(chunks, { type: mimeType });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `LiveStrip_${frameId || 'creative'}_${Date.now()}.` + (mimeType.includes('mp4') ? 'mp4' : 'webm');
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        resolve();
                    };
                });

                recorder.start();

                // Animation Loop
                for (let f = 0; f < totalFrames; f++) {
                    // Draw original photo strip
                    ctx.clearRect(0, 0, width, height);
                    ctx.drawImage(img, 0, 0, width, height);

                    // Draw effects
                    if (frameId === 'newspaper_strip') {
                        // Draw film grain noise
                        const noiseData = ctx.createImageData(width, height);
                        const buffer = new Uint32Array(noiseData.data.buffer);
                        const noiseOpacity = 0.08;
                        for (let i = 0; i < buffer.length; i++) {
                            if (Math.random() < noiseOpacity) {
                                buffer[i] = (Math.random() > 0.5) ? 0xFFFFFFFF : 0xFF000000;
                            }
                        }
                        ctx.putImageData(noiseData, 0, 0);

                        // Draw random scratches
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
                        ctx.lineWidth = 1;
                        for (let s = 0; s < 2; s++) {
                            if (Math.random() < 0.4) {
                                const sx = Math.random() * width;
                                ctx.beginPath();
                                ctx.moveTo(sx, 0);
                                ctx.lineTo(sx + (Math.random() * 4 - 2), height);
                                ctx.stroke();
                            }
                        }
                    } else if (frameId === 'magazine_strip') {
                        // Neon glowing borders around slots or strip edge
                        const pulse = Math.sin((f / totalFrames) * Math.PI * 4) * 0.5 + 0.5; // 0 to 1
                        const hue = (f / totalFrames) * 360;
                        
                        ctx.strokeStyle = `hsla(${hue}, 100%, 50%, ${0.5 + pulse * 0.3})`;
                        ctx.lineWidth = 12;
                        ctx.strokeRect(6, 6, width - 12, height - 12);
                        
                        ctx.strokeStyle = `hsla(${hue + 120}, 100%, 60%, ${0.3 + pulse * 0.2})`;
                        ctx.lineWidth = 6;
                        ctx.strokeRect(12, 12, width - 24, height - 24);
                    } else if (frameId === 'retro_film_strip') {
                        // Moving orange/pink warm light leaks
                        const time = f / totalFrames;
                        const angle = time * Math.PI * 2;
                        
                        // Red-orange warm gradient moving from left-top
                        const lx = width * (0.2 + Math.cos(angle) * 0.1);
                        const ly = height * (0.1 + Math.sin(angle) * 0.05);
                        const lr = width * 0.7;
                        
                        const g1 = ctx.createRadialGradient(lx, ly, 0, lx, ly, lr);
                        g1.addColorStop(0, 'rgba(255, 100, 0, 0.35)');
                        g1.addColorStop(0.5, 'rgba(255, 100, 0, 0.12)');
                        g1.addColorStop(1, 'rgba(255, 100, 0, 0)');
                        
                        ctx.fillStyle = g1;
                        ctx.globalCompositeOperation = 'color-dodge';
                        ctx.fillRect(0, 0, width, height);
                        
                        // Pink warm gradient moving from right-bottom
                        const rx = width * (0.8 - Math.cos(angle) * 0.1);
                        const ry = height * (0.9 - Math.sin(angle) * 0.05);
                        
                        const g2 = ctx.createRadialGradient(rx, ry, 0, rx, ry, lr);
                        g2.addColorStop(0, 'rgba(255, 60, 180, 0.28)');
                        g2.addColorStop(0.5, 'rgba(255, 60, 180, 0.08)');
                        g2.addColorStop(1, 'rgba(255, 60, 180, 0)');
                        
                        ctx.fillStyle = g2;
                        ctx.fillRect(0, 0, width, height);
                        ctx.globalCompositeOperation = 'source-over';
                    }

                    // Update status percentage
                    const pct = Math.floor((f / totalFrames) * 100);
                    btn.innerText = `Rendering... (${pct}%)`;
                    
                    // Push frames into Stream recorder
                    await new Promise(r => setTimeout(r, 1000 / fps));
                }

                recorder.stop();
                await downloadPromise;

            } catch (err) {
                console.error(err);
                alert("Gagal merender animasi video di browser perangkat Anda.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        async function generateGifSlideshow() {
            const btn = document.getElementById('btn-download-gif');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 50 50" style="animation: spin 1s linear infinite; margin-right: 8px;">
                    <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" style="stroke-dasharray: 90, 150; stroke-dashoffset: 0; stroke: white; animation: dash 1.5s ease-in-out infinite;"></circle>
                </svg>
                Membuat GIF...
            `;

            try {
                const img = document.querySelector('.photo-img');
                const frameId = "<?php echo htmlspecialchars($frameId); ?>";
                const eventName = "<?php echo $eventName ? htmlspecialchars($eventName) : 'Creative Studio'; ?>";

                if (!img.complete) {
                    await new Promise(resolve => img.onload = resolve);
                }

                // Slices coordinates based on frame configurations
                const defaultSlots = [
                    { x: 50, y: 50, width: 500, height: 375 },
                    { x: 50, y: 455, width: 500, height: 375 },
                    { x: 50, y: 860, width: 500, height: 375 },
                    { x: 50, y: 1265, width: 500, height: 375 }
                ];
                const slots = (activeFrameConfig.slots && activeFrameConfig.slots.length > 0) 
                    ? activeFrameConfig.slots 
                    : defaultSlots;

                const scaleFactor = img.naturalWidth / (activeFrameConfig.width || 600);

                // Slicing and drawing to separate canvases with custom polaroid frames
                const frameImages = [];
                const padding = 15;
                const bottomTextSpace = 55;

                for (let i = 0; i < slots.length; i++) {
                    const slot = slots[i];
                    
                    // Scale slot coordinates to natural image dimensions
                    const actualSlot = {
                        x: slot.x * scaleFactor,
                        y: slot.y * scaleFactor,
                        width: slot.width * scaleFactor,
                        height: slot.height * scaleFactor
                    };

                    // Create polaroid canvas
                    const pCanvas = document.createElement('canvas');
                    pCanvas.width = slot.width + padding * 2;
                    pCanvas.height = slot.height + padding + bottomTextSpace;
                    const pCtx = pCanvas.getContext('2d');

                    // 1. Draw white Polaroid background
                    pCtx.fillStyle = '#ffffff';
                    pCtx.fillRect(0, 0, pCanvas.width, pCanvas.height);

                    // 2. Draw photo cropped from original strip
                    pCtx.drawImage(
                        img, 
                        actualSlot.x, actualSlot.y, actualSlot.width, actualSlot.height, // source (scaled)
                        padding, padding, slot.width, slot.height // destination (1x)
                    );

                    // 3. Draw a shadow border around the photo slot
                    pCtx.strokeStyle = 'rgba(0, 0, 0, 0.1)';
                    pCtx.lineWidth = 2;
                    pCtx.strokeRect(padding, padding, slot.width, slot.height);

                    // 4. Draw nice custom text
                    pCtx.fillStyle = '#121212';
                    pCtx.font = 'bold 20px "Outfit", Arial, sans-serif';
                    pCtx.textAlign = 'center';
                    pCtx.fillText(eventName, pCanvas.width / 2, pCanvas.height - 30);

                    // Draw brand logo watermark below event name
                    pCtx.fillStyle = '#e63946';
                    pCtx.font = '800 12px "Outfit", Arial, sans-serif';
                    pCtx.fillText('CREATIVE STUDIO', pCanvas.width / 2, pCanvas.height - 12);

                    // 5. Draw a cute icon/heart
                    pCtx.fillStyle = '#e63946';
                    pCtx.font = '22px Arial';
                    pCtx.fillText('❤️', pCanvas.width - 35, pCanvas.height - 24);
                    pCtx.fillText('📸', 35, pCanvas.height - 24);

                    frameImages.push(pCanvas.toDataURL('image/png'));
                }

                // Compile into GIF using gifshot
                gifshot.createGIF({
                    images: frameImages,
                    gifWidth: slots[0].width + padding * 2,
                    gifHeight: slots[0].height + padding + bottomTextSpace,
                    interval: 0.4, // speed of transition
                    numFrames: slots.length,
                    frameDuration: 4,
                    sampleInterval: 2 // High-quality color mapping to prevent blurriness
                }, function (obj) {
                    if (!obj.error) {
                        const a = document.createElement('a');
                        a.href = obj.image;
                        a.download = `CreativeGIF_${frameId || 'creative'}_${Date.now()}.gif`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    } else {
                        alert("Gagal membuat GIF: " + obj.error);
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });

            } catch (err) {
                console.error(err);
                alert("Gagal membuat GIF animasi.");
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Initialize MediaPipe Selfie Segmentation for client-side background removal
        let selfieSegmentation = null;
        let isMediaPipeLoaded = false;

        try {
            if (typeof SelfieSegmentation !== 'undefined') {
                selfieSegmentation = new SelfieSegmentation({
                    locateFile: (file) => {
                        return `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${file}`;
                    }
                });
                selfieSegmentation.setOptions({
                    modelSelection: 1, // 1 for landscape/faster performance
                });
                isMediaPipeLoaded = true;
                console.log("MediaPipe Selfie Segmentation initialized successfully!");
            }
        } catch (e) {
            console.error("MediaPipe initialization error:", e);
        }

        // Helper to segment the image (remove background) asynchronously
        function segmentImage(canvasInput) {
            return new Promise((resolve, reject) => {
                if (!selfieSegmentation) {
                    reject("MediaPipe not loaded");
                    return;
                }

                selfieSegmentation.onResults((results) => {
                    try {
                        const width = canvasInput.width;
                        const height = canvasInput.height;
                        
                        const outputCanvas = document.createElement('canvas');
                        outputCanvas.width = width;
                        outputCanvas.height = height;
                        const outCtx = outputCanvas.getContext('2d');

                        // 1. Draw segmentation mask
                        outCtx.drawImage(results.segmentationMask, 0, 0, width, height);

                        // 2. Overlay source-in to crop the person only
                        outCtx.globalCompositeOperation = 'source-in';
                        outCtx.drawImage(canvasInput, 0, 0, width, height);
                        
                        resolve(outputCanvas);
                    } catch (e) {
                        reject(e);
                    }
                });

                selfieSegmentation.send({ image: canvasInput }).catch(err => reject(err));
            });
        }

        async function downloadPoseSticker(poseIndex) {
            // Update UI feedback on header during generation
            const btnContainer = document.querySelector('.sticker-buttons-container');
            const originalHeader = btnContainer.firstElementChild.innerHTML;
            btnContainer.firstElementChild.innerHTML = "Memproses Stiker WA... ⏳";

            try {
                const img = document.querySelector('.photo-img');
                const frameId = "<?php echo htmlspecialchars($frameId); ?>";

                if (!img.complete) {
                    await new Promise(resolve => img.onload = resolve);
                }

                // Slices coordinates based on frame configurations
                const defaultSlots = [
                    { x: 50, y: 50, width: 500, height: 375 },
                    { x: 50, y: 455, width: 500, height: 375 },
                    { x: 50, y: 860, width: 500, height: 375 },
                    { x: 50, y: 1265, width: 500, height: 375 }
                ];
                const slots = (activeFrameConfig.slots && activeFrameConfig.slots.length > 0) 
                    ? activeFrameConfig.slots 
                    : defaultSlots;

                const slot = slots[poseIndex];

                const scaleFactor = img.naturalWidth / (activeFrameConfig.width || 600);
                const actualSlot = {
                    x: slot.x * scaleFactor,
                    y: slot.y * scaleFactor,
                    width: slot.width * scaleFactor,
                    height: slot.height * scaleFactor
                };

                // 1. Crop original pose to a temporary canvas (at 1x destination resolution)
                const cropCanvas = document.createElement('canvas');
                cropCanvas.width = slot.width;
                cropCanvas.height = slot.height;
                const cropCtx = cropCanvas.getContext('2d');
                cropCtx.drawImage(
                    img,
                    actualSlot.x, actualSlot.y, actualSlot.width, actualSlot.height,
                    0, 0, slot.width, slot.height
                );

                const canvas = document.createElement('canvas');
                canvas.width = 512;
                canvas.height = 512;
                const ctx = canvas.getContext('2d');

                // Sticker Themes corresponding to each pose index (max 4)
                const themes = [
                    { text: "Haha! 😂", color: "#e63946", emojis: ['✨', '⭐', '😂', '🤣'] },
                    { text: "Hehe.. 🤭", color: "#833ab4", emojis: ['✨', '💖', '🤭', '🌸'] },
                    { text: "Gokil! 🤯", color: "#bd00ff", emojis: ['🤯', '⚡', '🔥', '💥'] },
                    { text: "Kece! 😎", color: "#121212", emojis: ['😎', '🕶️', '✨', '🔥'] }
                ];
                const theme = themes[poseIndex] || themes[0];

                const cX = 256;
                const cYVal = 220; // Center offset vertically for emojis reference

                // If MediaPipe model loaded, cut out person and make a cool outline sticker
                if (isMediaPipeLoaded && selfieSegmentation) {
                    // Segment person (remove backdrop)
                    const segmentedCanvas = await segmentImage(cropCanvas);

                    const maxDim = 320; // scale boundary inside the 512x512 canvas
                    let destW = maxDim;
                    let destH = maxDim / (slot.width / slot.height);
                    
                    const destX = cX - destW / 2;
                    // Make the bottom of the person overlap the text badge by 15px to sit on top of it cleanly
                    const destY = 425 - destH;

                    // Draw segmented person scaled to a helper canvas to facilitate silhouette creation
                    const personCanvas = document.createElement('canvas');
                    personCanvas.width = 512;
                    personCanvas.height = 512;
                    const personCtx = personCanvas.getContext('2d');
                    personCtx.drawImage(segmentedCanvas, destX, destY, destW, destH);

                    // Draw solid white outline outline border around the person silhouette
                    ctx.save();
                    const d = 10; // outline border thickness
                    ctx.fillStyle = '#ffffff';
                    for (let x = -d; x <= d; x += 3) {
                        for (let y = -d; y <= d; y += 3) {
                            if (x*x + y*y <= d*d) {
                                ctx.drawImage(personCanvas, x, y);
                            }
                        }
                    }
                    
                    // Transform silhouette outline to solid white fill
                    ctx.globalCompositeOperation = 'source-in';
                    ctx.fillRect(0, 0, 512, 512);
                    ctx.restore();

                    // Overlay original colored segmented person shape on top
                    ctx.drawImage(personCanvas, 0, 0);

                } else {
                    // Fallback to circular badge outline style if MediaPipe is not ready
                    const radius = 170;
                    const currentCY = 255; // Touch text badge with 15px overlap

                    // Sticker white background glow/offset
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.arc(cX, currentCY, radius + 12, 0, Math.PI * 2);
                    ctx.fill();

                    // Clip path for the photo
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(cX, currentCY, radius, 0, Math.PI * 2);
                    ctx.clip();

                    // Draw photo scaled to fit circle directly from cropCanvas
                    const sAspect = cropCanvas.width / cropCanvas.height;
                    let sWidth = cropCanvas.width;
                    let sHeight = cropCanvas.height;
                    let sx = 0;
                    let sy = 0;

                    if (sAspect > 1) { // Landscape
                        sWidth = cropCanvas.height;
                        sx = (cropCanvas.width - sWidth) / 2;
                    } else {
                        sHeight = cropCanvas.width;
                        sy = (cropCanvas.height - sHeight) / 2;
                    }

                    ctx.drawImage(
                        cropCanvas,
                        sx, sy, sWidth, sHeight,
                        cX - radius, currentCY - radius, radius * 2, radius * 2
                    );
                    ctx.restore();

                    // Draw nice thick border over the cropped photo
                    ctx.strokeStyle = theme.color;
                    ctx.lineWidth = 6;
                    ctx.beginPath();
                    ctx.arc(cX, currentCY, radius, 0, Math.PI * 2);
                    ctx.stroke();
                }

                // 5. Draw stickers/emojis on top
                ctx.font = '36px Arial';
                ctx.textAlign = 'center';
                
                ctx.fillText(theme.emojis[0], cX - 180, cYVal - 130);
                ctx.fillText(theme.emojis[1], cX + 180, cYVal - 130);
                ctx.fillText(theme.emojis[2], cX - 180, cYVal + 120);
                ctx.fillText(theme.emojis[3], cX + 180, cYVal + 120);

                // 6. Draw Sticker Text Badge at the bottom
                const textWidth = 320;
                const textHeight = 65;
                const tx = cX - textWidth / 2;
                const ty = 410;

                // Draw white text bubble border first
                ctx.fillStyle = '#ffffff';
                drawRoundedRect(ctx, tx - 6, ty - 6, textWidth + 12, textHeight + 12, 18);
                ctx.fill();

                // Draw background of the text badge
                ctx.fillStyle = theme.color;
                drawRoundedRect(ctx, tx, ty, textWidth, textHeight, 14);
                ctx.fill();

                // Draw text inside badge
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 26px "Outfit", Arial, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillText(theme.text, cX, ty + textHeight / 2);

                // Download WebP image
                const stickerUrl = canvas.toDataURL('image/webp', 0.85);
                const a = document.createElement('a');
                a.href = stickerUrl;
                a.download = `StikerWA_Pose${poseIndex + 1}_${Date.now()}.webp`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

            } catch (err) {
                console.error(err);
                alert("Gagal membuat Stiker WhatsApp.");
            } finally {
                btnContainer.firstElementChild.innerHTML = originalHeader;
            }
        }

        // Helper to draw rounded rectangle in Canvas
        function drawRoundedRect(ctx, x, y, width, height, radius) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height - radius);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
        }

        function scrollToStickers() {
            const container = document.querySelector('.sticker-buttons-container');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                container.style.transition = 'transform 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                container.style.transform = 'scale(1.03)';
                setTimeout(() => {
                    container.style.transform = 'scale(1)';
                }, 300);
            }
        }
    </script>

    <?php if ($found): ?>
        <div class="floating-actions-bar">
            <?php if ($packageFeatures['download']): ?>
                <a href="<?php echo htmlspecialchars($photoFile); ?>" download="CreativeStudio_Photo.png" class="floating-btn floating-btn-strip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Unduh Strip
                </a>
            <?php endif; ?>

            <?php if ($packageFeatures['gif']): ?>
                <button onclick="generateGifSlideshow()" class="floating-btn floating-btn-gif">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h18a2 2 0 0 1 2 2z"></path><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    Unduh GIF
                </button>
            <?php endif; ?>

            <?php if ($packageFeatures['sticker']): ?>
                <button onclick="scrollToStickers()" class="floating-btn floating-btn-sticker">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                    Stiker WA
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
