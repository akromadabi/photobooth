<?php
$sessionId = isset($_GET['id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['id']) : '';
$photoFile = '';
$timelapseFile = '';

$uploadDir = __DIR__ . '/uploads/';

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
}

$found = !empty($photoFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Studio - Receipt Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
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

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="background-glow"></div>

    <header>
        <div class="logo">Creative<span>Studio</span></div>
        <div class="subtitle">Receipt Photo Booth</div>
    </header>

    <?php if ($found): ?>
        <main class="main-container">
            <?php if ($timelapseFile): ?>
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('photo')">Photo Strip</button>
                    <button class="tab-btn" onclick="switchTab('timelapse')">Behind the Scenes</button>
                </div>
            <?php endif; ?>

            <div class="media-container" id="photo-wrapper">
                <img src="<?php echo htmlspecialchars($photoFile); ?>" class="photo-img" alt="Your Photo Strip">
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
                <a href="<?php echo htmlspecialchars($photoFile); ?>" download="CreativeStudio_Photo.png" class="btn btn-primary" id="btn-download-photo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Download Photo Strip
                </a>

                <?php if ($timelapseFile): ?>
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
    </footer>

    <script>
        function switchTab(type) {
            const photoTab = document.querySelector('.tab-btn:nth-child(1)');
            const timelapseTab = document.querySelector('.tab-btn:nth-child(2)');
            const photoWrapper = document.getElementById('photo-wrapper');
            const timelapseWrapper = document.getElementById('timelapse-wrapper');
            const downloadPhoto = document.getElementById('btn-download-photo');
            const downloadTimelapse = document.getElementById('btn-download-timelapse');

            if (type === 'photo') {
                photoTab.classList.add('active');
                timelapseTab.classList.remove('active');
                photoWrapper.classList.remove('hidden');
                timelapseWrapper.classList.add('hidden');
                
                downloadPhoto.classList.remove('hidden');
                if(downloadTimelapse) downloadTimelapse.classList.add('hidden');
            } else {
                photoTab.classList.remove('active');
                timelapseTab.classList.add('active');
                photoWrapper.classList.add('hidden');
                timelapseWrapper.classList.remove('hidden');

                downloadPhoto.classList.add('hidden');
                if(downloadTimelapse) downloadTimelapse.classList.remove('hidden');
                
                // If it is a video, try to play it
                const video = timelapseWrapper.querySelector('video');
                if (video) {
                    video.play().catch(e => console.log('Video autoplay interrupted'));
                }
            }
        }
    </script>
</body>
</html>
