<?php
$sessionId = isset($_GET['id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['id']) : '';
if (empty($sessionId)) {
    header("Location: display.php");
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
        'gif' => true
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

// Load event name from config.json
if ($eventId && $eventId !== 'general') {
    $configPath = __DIR__ . '/frames/config.json';
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        if (isset($config['events'])) {
            foreach ($config['events'] as $evt) {
                if ($evt['id'] === $eventId) {
                    $eventName = $evt['name'];
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
    <style>
        :root {
            --bg-color: #0f0f12;
            --card-bg: #18181f;
            --primary-red: #e63946;
            --text-main: #f8f9fa;
            --text-muted: #a0a0b0;
            --border-color: #2a2a35;
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
            padding: 20px;
            overflow-x: hidden;
        }

        .background-glow {
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(230, 57, 70, 0.15) 0%, rgba(15, 15, 18, 0) 70%);
            z-index: -1;
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

        /* 1. Film Scratches & Moving Dust (Newspaper / Black Strip) */
        .media-container.effect-classic_strip_black::after,
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
    </style>
</head>
<body>
    <div class="background-glow"></div>

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
                <?php if ($frameId === 'classic_strip_black' || $frameId === 'newspaper_strip'): ?>
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

                    // Draw effects based on frameId
                    if (frameId === 'classic_strip_black' || frameId === 'newspaper_strip') {
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

                // Slices coordinates based on frameId
                let slots = [
                    { x: 50, y: 50, width: 500, height: 375 },
                    { x: 50, y: 455, width: 500, height: 375 },
                    { x: 50, y: 860, width: 500, height: 375 },
                    { x: 50, y: 1265, width: 500, height: 375 }
                ];

                if (frameId === 'retro_film_strip') {
                    slots = [
                        { x: 80, y: 80, width: 440, height: 330 },
                        { x: 80, y: 475, width: 440, height: 330 },
                        { x: 80, y: 870, width: 440, height: 330 },
                        { x: 80, y: 1265, width: 440, height: 330 }
                    ];
                }

                // Slicing and drawing to separate canvases with custom polaroid frames
                const frameImages = [];
                const padding = 15;
                const bottomTextSpace = 55;

                for (let i = 0; i < slots.length; i++) {
                    const slot = slots[i];
                    
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
                        slot.x, slot.y, slot.width, slot.height, // source
                        padding, padding, slot.width, slot.height // destination
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
    </script>
</body>
</html>
