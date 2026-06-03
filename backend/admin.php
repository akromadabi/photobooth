<?php
session_start();

$settingsFile = __DIR__ . '/settings.json';
$uploadDir = __DIR__ . '/uploads/';
$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';

// Load settings from JSON
function loadSettings($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [
        "admin_pin" => "1234",
        "countdown_seconds" => 5,
        "total_shots" => 4,
        "printer_type" => "NONE",
        "use_biometric" => true
    ];
}

$settings = loadSettings($settingsFile);
$adminPin = isset($settings['admin_pin']) ? $settings['admin_pin'] : '1234';

// Check login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    if ($pin === $adminPin) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'PIN Admin salah!';
    }
}

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Admin - Creative Studio Kiosk</title>
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
                position: relative;
                overflow: hidden;
            }
            .background-glow {
                position: absolute;
                width: 500px;
                height: 500px;
                background: radial-gradient(circle, rgba(230, 57, 70, 0.15) 0%, rgba(15, 15, 18, 0) 70%);
                z-index: -1;
                pointer-events: none;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 24px;
                padding: 36px 30px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
                text-align: center;
                z-index: 10;
            }
            .logo {
                font-weight: 800;
                font-size: 2rem;
                letter-spacing: -0.5px;
                margin-bottom: 8px;
            }
            .logo span { color: var(--primary-red); }
            .subtitle {
                font-size: 0.9rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 30px;
            }
            .form-group {
                margin-bottom: 24px;
                text-align: left;
            }
            label {
                display: block;
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--text-muted);
                margin-bottom: 8px;
                text-transform: uppercase;
            }
            input[type="password"] {
                width: 100%;
                padding: 16px;
                background-color: #0d0d11;
                border: 1px solid var(--border-color);
                border-radius: 12px;
                color: white;
                font-size: 1.1rem;
                text-align: center;
                letter-spacing: 4px;
                font-family: inherit;
                outline: none;
                transition: border-color 0.25s;
            }
            input[type="password"]:focus {
                border-color: var(--primary-red);
            }
            .btn {
                width: 100%;
                padding: 16px;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 12px;
                border: none;
                cursor: pointer;
                background-color: var(--primary-red);
                color: white;
                box-shadow: 0 4px 15px rgba(230, 57, 70, 0.25);
                font-family: inherit;
                transition: all 0.25s;
            }
            .btn:hover {
                background-color: #d62d3a;
                transform: translateY(-2px);
            }
            .error-message {
                color: var(--primary-red);
                font-size: 0.85rem;
                margin-top: 12px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="background-glow"></div>
        <div class="login-card">
            <div class="logo">Creative<span>Studio</span></div>
            <div class="subtitle">Kiosk Web Controller</div>
            
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="pin">Masukkan PIN Keamanan Admin</label>
                    <input type="password" id="pin" name="pin" maxlength="8" required autofocus placeholder="••••">
                </div>
                <button type="submit" class="btn">MASUK DASHBOARD</button>
            </form>
            
            <?php if (isset($loginError)): ?>
                <div class="error-message"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Authentication verified. Handle CRUD operations:
// Action: Delete Session Photo
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = preg_replace('/[^a-f0-9]/', '', $_GET['id']);
    if ($id) {
        $photo = $uploadDir . $id . '_photo.png';
        $meta = $uploadDir . $id . '_meta.json';
        
        if (file_exists($photo)) unlink($photo);
        if (file_exists($meta)) unlink($meta);
        
        $timelapseMatches = glob($uploadDir . $id . '_timelapse.*');
        if (!empty($timelapseMatches)) {
            foreach ($timelapseMatches as $tFile) {
                if (file_exists($tFile)) unlink($tFile);
            }
        }
    }
    header('Location: admin.php?status=deleted');
    exit;
}

// Action: Clear All Uploads
if (isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    $files = glob($uploadDir . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    header('Location: admin.php?status=cleared');
    exit;
}

// Action: Update Settings
if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $newPin = isset($_POST['admin_pin']) ? preg_replace('/[^0-9]/', '', $_POST['admin_pin']) : '1234';
    $countdown = isset($_POST['countdown_seconds']) ? intval($_POST['countdown_seconds']) : 5;
    $shots = isset($_POST['total_shots']) ? intval($_POST['total_shots']) : 4;
    $printer = isset($_POST['printer_type']) ? $_POST['printer_type'] : 'NONE';
    $biometric = isset($_POST['use_biometric']) && $_POST['use_biometric'] == '1';
    
    $settings = [
        "admin_pin" => $newPin ? $newPin : '1234',
        "countdown_seconds" => $countdown,
        "total_shots" => $shots,
        "printer_type" => $printer,
        "use_biometric" => $biometric
    ];
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=saved');
    exit;
}

// Action: Reset Queue
if (isset($_GET['action']) && $_GET['action'] === 'reset_queue') {
    $state = [
        "active_queue_number" => 0,
        "active_session_id" => "",
        "queue_list" => []
    ];
    file_put_contents($queueFile, json_encode($state, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=queue_reset');
    exit;
}

// Action: Update Packages
if (isset($_POST['action']) && $_POST['action'] === 'update_packages') {
    $packages = [];
    if (file_exists($packagesFile)) {
        $packages = json_decode(file_get_contents($packagesFile), true);
    }
    
    foreach ($packages as &$pkg) {
        $id = $pkg['id'];
        if (isset($_POST["price_$id"])) {
            $pkg['price'] = intval($_POST["price_$id"]);
        }
        $pkg['features']['print'] = isset($_POST["feature_print_$id"]) ? true : false;
        $pkg['features']['download'] = isset($_POST["feature_download_$id"]) ? true : false;
        $pkg['features']['gif'] = isset($_POST["feature_gif_$id"]) ? true : false;
        $pkg['features']['sticker'] = isset($_POST["feature_sticker_$id"]) ? true : false;
    }
    
    file_put_contents($packagesFile, json_encode($packages, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=packages_saved');
    exit;
}

// Load packages & queue state
$queueState = [
    "active_queue_number" => 0,
    "active_session_id" => "",
    "queue_list" => []
];
if (file_exists($queueFile)) {
    $queueState = json_decode(file_get_contents($queueFile), true);
}

$packagesList = [];
if (file_exists($packagesFile)) {
    $packagesList = json_decode(file_get_contents($packagesFile), true);
}

// Gather Metrics Information
$photosCount = 0;
$todayPhotosCount = 0;
$totalSize = 0;
$historyList = [];

if (file_exists($uploadDir)) {
    $files = glob($uploadDir . '*_photo.png');
    if ($files) {
        $photosCount = count($files);
        $todayStart = strtotime('today');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $parts = explode('_', $filename);
            if (count($parts) < 2) continue;
            
            $sessionId = $parts[0];
            $mtime = filemtime($file);
            
            if ($mtime >= $todayStart) {
                $todayPhotosCount++;
            }
            
            // Check matching metadata
            $frameName = 'Default';
            if (file_exists($uploadDir . $sessionId . '_meta.json')) {
                $meta = json_decode(file_get_contents($uploadDir . $sessionId . '_meta.json'), true);
                if (isset($meta['frame_id'])) {
                    $frameName = ucwords(str_replace('_', ' ', $meta['frame_id']));
                }
            }
            
            $historyList[] = [
                'id' => $sessionId,
                'photo' => 'uploads/' . $filename,
                'frame' => $frameName,
                'time' => $mtime
            ];
        }
        
        // Sort history: newest first
        usort($historyList, function($a, $b) {
            return $b['time'] - $a['time'];
        });
    }
    
    // Calculate folder size
    $allFiles = glob($uploadDir . '*');
    if ($allFiles) {
        foreach ($allFiles as $f) {
            if (is_file($f)) {
                $totalSize += filesize($f);
            }
        }
    }
}

// Format Disk Size
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

$formattedSize = formatSize($totalSize);

// Weekly statistics calculation for Chart.js
$weeklyStats = [];
$labels = [];
$counts = [];

for ($i = 6; $i >= 0; $i--) {
    $dayDate = date('Y-m-d', strtotime("-$i days"));
    $weeklyStats[$dayDate] = 0;
}

if (!empty($historyList)) {
    foreach ($historyList as $item) {
        $itemDate = date('Y-m-d', $item['time']);
        if (isset($weeklyStats[$itemDate])) {
            $weeklyStats[$itemDate]++;
        }
    }
}

foreach ($weeklyStats as $date => $cnt) {
    $labels[] = date('d M', strtotime($date));
    $counts[] = $cnt;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Admin Dashboard - Creative Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #0c0c0e;
            --card-bg: #14141a;
            --primary-red: #e63946;
            --primary-green: #52b788;
            --primary-amber: #f7b801;
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
        }

        header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -0.5px;
        }
        .header-logo span { color: var(--primary-red); }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-header {
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            border: 1px solid var(--border-color);
            background-color: #1a1a24;
            color: var(--text-main);
            transition: all 0.25s;
        }
        .btn-header:hover {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* Metrics grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1f));
            gap: 20px;
            width: 100%;
        }

        .metric-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background-color: #1a1a24;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-red);
        }

        .metric-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .metric-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Two column layout */
        .content-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-section {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* Configuration Form */
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-row label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            background-color: #0c0c0e;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: white;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.25s;
        }

        .form-control:focus {
            border-color: var(--primary-red);
        }

        .btn-submit {
            background-color: var(--primary-red);
            color: white;
            font-weight: 600;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.25s;
            font-family: inherit;
        }

        .btn-submit:hover {
            background-color: #d62d3a;
        }

        /* History Photo Grid */
        .history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 16px;
        }

        .history-card {
            background-color: #0c0c0e;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.25s, transform 0.25s;
            position: relative;
            aspect-ratio: 0.45;
        }

        .history-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-2px);
        }

        .history-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .history-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.85));
            padding: 8px;
            text-align: center;
        }

        .history-time {
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }

        .alert-status {
            padding: 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
        }
        .alert-saved { background-color: rgba(82, 183, 136, 0.15); color: var(--primary-green); border: 1px solid var(--primary-green); }
        .alert-deleted { background-color: rgba(230, 57, 70, 0.15); color: var(--primary-red); border: 1px solid var(--primary-red); }
        .alert-cleared { background-color: rgba(247, 184, 1, 0.15); color: var(--primary-amber); border: 1px solid var(--primary-amber); }

        /* Modal Dialog Styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(8px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            max-width: 720px;
            width: 100%;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.25s;
        }
        .modal-close:hover { color: white; }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .modal-split {
            display: flex;
            gap: 24px;
            height: 380px;
        }

        @media (max-width: 600px) {
            .modal-split {
                flex-direction: column;
                height: auto;
            }
        }

        .modal-preview {
            flex: 1;
            background-color: black;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        .modal-preview img {
            height: 100%;
            width: auto;
            object-fit: contain;
        }

        .modal-actions {
            width: 220px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            justify-content: center;
        }

        @media (max-width: 600px) {
            .modal-actions {
                width: 100%;
            }
        }

        .qr-box {
            background-color: white;
            border-radius: 12px;
            padding: 10px;
            width: 160px;
            height: 160px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-box canvas { width: 100% !important; height: 100% !important; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            transition: all 0.25s;
            border: none;
        }
        .btn-action-primary {
            background-color: var(--primary-red);
            color: white;
        }
        .btn-action-primary:hover { background-color: #d62d3a; }
        .btn-action-secondary {
            background-color: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        .btn-action-secondary:hover { background-color: #1a1a24; }
        .btn-action-danger {
            background-color: rgba(230, 57, 70, 0.15);
            color: var(--primary-red);
            border: 1px solid rgba(230, 57, 70, 0.3);
        }
        .btn-action-danger:hover { background-color: rgba(230, 57, 70, 0.3); }

        .btn-clear-history {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary-red);
            border: 1px solid rgba(230, 57, 70, 0.2);
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.25s;
            font-weight: 600;
        }
        .btn-clear-history:hover {
            background-color: var(--primary-red);
            color: white;
        }
    </style>
    <!-- Include QRCode Generator Library for Web -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
</head>
<body>

    <header>
        <div class="header-logo">Creative<span>Studio</span> Admin</div>
        <div class="header-actions">
            <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;"><?php echo date('d M Y, H:i'); ?></span>
            <a href="admin.php?action=logout" class="btn-header">Logout Portal</a>
        </div>
    </header>

    <div class="main-content">
        
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'saved'): ?>
                <div class="alert-status alert-saved">✓ Konfigurasi remote kiosk berhasil disimpan dan disinkronkan!</div>
            <?php elseif ($_GET['status'] === 'packages_saved'): ?>
                <div class="alert-status alert-saved">✓ Pengaturan harga paket photobooth berhasil disimpan!</div>
            <?php elseif ($_GET['status'] === 'queue_reset'): ?>
                <div class="alert-status alert-cleared">⚠ Semua antrean Kiosk berhasil direset!</div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="alert-status alert-deleted">✕ Sesi riwayat foto berhasil dihapus permanen!</div>
            <?php elseif ($_GET['status'] === 'cleared'): ?>
                <div class="alert-status alert-cleared">⚠ Semua file riwayat foto kiosk dibersihkan!</div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Upper Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon">📷</div>
                <div class="metric-info">
                    <div class="metric-label">Total Sesi Foto</div>
                    <div class="metric-value"><?php echo $photosCount; ?> Sesi</div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-icon" style="color: var(--primary-green);">⚡</div>
                <div class="metric-info">
                    <div class="metric-label">Sesi Hari Ini</div>
                    <div class="metric-value"><?php echo $todayPhotosCount; ?> Sesi</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="color: var(--primary-amber);">💾</div>
                <div class="metric-info">
                    <div class="metric-label">Memori Terpakai</div>
                    <div class="metric-value"><?php echo $formattedSize; ?></div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="color: #2196f3;">🖨</div>
                <div class="metric-info">
                    <div class="metric-label">Printer Aktif</div>
                    <div class="metric-value"><?php echo htmlspecialchars($settings['printer_type']); ?></div>
                </div>
            </div>
        </div>

        <div class="content-layout">
            <!-- Left Column: Weekly Chart & Photo Logs -->
            <div style="display: flex; flex-direction: column; gap: 30px; width: 100%;">
                
                <!-- Weekly Analytics Chart -->
                <div class="dashboard-section">
                    <div class="section-title">Tren Aktivitas 7 Hari Terakhir</div>
                    <div style="height: 220px; width: 100%;">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>

                <!-- Gallery Logs -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <div class="section-title">Log Hasil Foto Kiosk (<?php echo count($historyList); ?>)</div>
                        <?php if (!empty($historyList)): ?>
                            <form action="admin.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS SEMUA riwayat foto? Tindakan ini tidak dapat dibatalkan.');">
                                <input type="hidden" name="action" value="clear_all">
                                <button type="submit" class="btn-clear-history">Bersihkan Semua Riwayat</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($historyList)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            Belum ada riwayat jepretan foto yang diunggah ke server.
                        </div>
                    <?php else: ?>
                        <div class="history-grid">
                            <?php foreach ($historyList as $item): ?>
                                <div class="history-card" onclick="openDetails('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($item['photo']); ?>', '<?php echo htmlspecialchars($item['frame']); ?>', '<?php echo date('d M Y, H:i', $item['time']); ?>')">
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>" class="history-img" alt="Photo strip">
                                    <div class="history-overlay">
                                        <div class="history-time"><?php echo date('d/M, H:i', $item['time']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Settings remote Kiosk -->
            <div class="dashboard-section">
                <div class="section-title">Kontrol & Pengaturan Kiosk</div>
                
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-row">
                        <label for="admin_pin">PIN Keamanan Admin</label>
                        <input type="text" id="admin_pin" name="admin_pin" class="form-control" value="<?php echo htmlspecialchars($settings['admin_pin']); ?>" pattern="[0-9]{4,8}" required placeholder="Contoh: 1234">
                    </div>

                    <div class="form-row">
                        <label for="countdown_seconds">Durasi Hitung Mundur (Detik)</label>
                        <input type="number" id="countdown_seconds" name="countdown_seconds" class="form-control" value="<?php echo intval($settings['countdown_seconds']); ?>" min="2" max="15" required>
                    </div>

                    <div class="form-row">
                        <label for="total_shots">Jumlah Jepretan Sesi</label>
                        <input type="number" id="total_shots" name="total_shots" class="form-control" value="<?php echo intval($settings['total_shots']); ?>" min="1" max="8" required>
                    </div>

                    <div class="form-row">
                        <label for="printer_type">Mode Pencetakan</label>
                        <select id="printer_type" name="printer_type" class="form-control">
                            <option value="NONE" <?php echo $settings['printer_type'] === 'NONE' ? 'selected' : ''; ?>>NONE (Mode Digital Saja)</option>
                            <option value="THERMAL" <?php echo $settings['printer_type'] === 'THERMAL' ? 'selected' : ''; ?>>THERMAL (Printer XP-420B)</option>
                            <option value="COLOR" <?php echo $settings['printer_type'] === 'COLOR' ? 'selected' : ''; ?>>COLOR (Printer Warna Sistem)</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="use_biometric">Autentikasi Biometrik Tablet</label>
                        <select id="use_biometric" name="use_biometric" class="form-control">
                            <option value="1" <?php echo $settings['use_biometric'] ? 'selected' : ''; ?>>Aktif (Sensor Sidik Jari)</option>
                            <option value="0" <?php echo !$settings['use_biometric'] ? 'selected' : ''; ?>>Nonaktif (PIN Saja)</option>
                        </select>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn-submit">SIMPAN & SINKRON KIOSK</button>
                    </div>
                </form>
            </div>

            <!-- Queue monitoring dashboard -->
            <div class="dashboard-section" style="margin-top: 20px;">
                <div class="section-title">📊 Antrean Remote Kiosk</div>
                <div class="bill-details" style="background-color: #0c0c0f; border: 1px solid var(--border-color); border-radius: 16px; padding: 16px; font-size: 0.9rem; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Antrean Aktif Saat Ini:</span>
                        <span style="font-weight: bold; color: var(--primary-amber);">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Sesi Aktif:</span>
                        <span style="font-weight: bold; font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars($queueState['active_session_id']) : 'Tidak ada'; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Total Mengantre (Waiting):</span>
                        <span style="font-weight: bold; color: var(--primary-red);"><?php 
                            $waitingNum = 0;
                            foreach ($queueState['queue_list'] as $item) {
                                if ($item['status'] === 'WAITING') $waitingNum++;
                            }
                            echo $waitingNum;
                        ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 4px;">
                    <a href="admin.php?action=reset_queue" class="btn-action btn-action-danger" style="text-align: center; text-decoration: none; width: 100%; display: block;" onclick="return confirm('Apakah Anda yakin ingin MERESET TOTAL antrean? Sesi remote aktif dan antrean berbayar akan dihapus.');">RESET ANTRIAN KIOSK</a>
                </div>
            </div>

            <!-- Package pricing & feature management -->
            <div class="dashboard-section" style="margin-top: 20px;">
                <div class="section-title">🛍️ Manajemen Paket Photobooth</div>
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="update_packages">
                    
                    <?php foreach ($packagesList as $pkg): ?>
                        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 12px;">
                            <div style="font-weight: bold; font-size: 0.95rem; margin-bottom: 6px;"><?php echo htmlspecialchars($pkg['name']); ?></div>
                            <div class="form-row">
                                <label>Harga (Rp)</label>
                                <input type="number" name="price_<?php echo $pkg['id']; ?>" class="form-control" value="<?php echo intval($pkg['price']); ?>" required>
                            </div>
                            <div class="form-row" style="margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                                <label style="font-size: 0.75rem;">Fitur Paket:</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="checkbox" name="feature_print_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['print']?'checked':''; ?>> 
                                    <span style="font-size:0.8rem; color: var(--text-main);">Cetak Struk Fisik</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="checkbox" name="feature_download_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['download']?'checked':''; ?>> 
                                    <span style="font-size:0.8rem; color: var(--text-main);">Download Foto Strip</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="checkbox" name="feature_gif_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['gif']?'checked':''; ?>> 
                                    <span style="font-size:0.8rem; color: var(--text-main);">Live Animated GIF</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="checkbox" name="feature_sticker_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['sticker']?'checked':''; ?>> 
                                    <span style="font-size:0.8rem; color: var(--text-main);">Stiker WhatsApp</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn-submit" style="background-color: var(--primary-gold); color: black;">SIMPAN HARGA & FITUR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Dialog Details -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeDetails()">&times;</button>
            <div class="modal-title">Sesi Foto: <span id="modalIdVal"></span></div>
            
            <div class="modal-split">
                <div class="modal-preview">
                    <img id="modalImg" src="" alt="Preview jepretan">
                </div>
                
                <div class="modal-actions">
                    <div class="qr-box">
                        <canvas id="modalQr"></canvas>
                    </div>
                    <div style="text-align: center; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 12px;">
                        Scan QR untuk Download
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a id="modalDownloadBtn" href="" download="" class="btn-action btn-action-primary">Unduh Gambar</a>
                        <button id="modalDeleteBtn" class="btn-action btn-action-danger">Hapus Sesi</button>
                        <button class="btn-action btn-action-secondary" onclick="closeDetails()">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js render
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Jumlah Sesi',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: 'rgba(230, 57, 70, 0.85)',
                    borderColor: 'rgba(230, 57, 70, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#1a1a24' },
                        ticks: { color: '#8d8d9f', stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#8d8d9f' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Modal Dialog handlers
        const modal = document.getElementById('detailsModal');
        let qrGenerator = null;

        function openDetails(id, photoPath, frame, timeStr) {
            document.getElementById('modalIdVal').innerText = id;
            document.getElementById('modalImg').src = photoPath;
            
            // Setup download link
            const downloadBtn = document.getElementById('modalDownloadBtn');
            downloadBtn.href = photoPath;
            downloadBtn.download = `PhotoBooth_${id}.png`;
            
            // Setup delete action
            const deleteBtn = document.getElementById('modalDeleteBtn');
            deleteBtn.onclick = function() {
                if (confirm(`Apakah Anda yakin ingin menghapus sesi ${id} secara permanen?`)) {
                    window.location.href = `admin.php?action=delete&id=${id}`;
                }
            };
            
            // Generate QR Code dynamically
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathname = window.location.pathname;
            const baseDir = pathname.substring(0, pathname.lastIndexOf('/'));
            const downloadUrl = `${protocol}//${host}${baseDir}/index.php?id=${id}`;
            
            if (!qrGenerator) {
                qrGenerator = new QRious({
                    element: document.getElementById('modalQr'),
                    size: 150,
                    value: downloadUrl
                });
            } else {
                qrGenerator.value = downloadUrl;
            }
            
            modal.classList.add('active');
        }

        function closeDetails() {
            modal.classList.remove('active');
        }

        // Close on background click
        window.onclick = function(event) {
            if (event.target === modal) {
                closeDetails();
            }
        }
    </script>
</body>
</html>
