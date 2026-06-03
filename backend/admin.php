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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --bg-color: #f8fafc;
                --card-bg: #ffffff;
                --primary: #4f46e5;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --border-color: #e2e8f0;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Outfit', sans-serif;
                background: linear-gradient(135deg, #eef2f6 0%, #f8fafc 100%);
                color: var(--text-main);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
                position: relative;
                overflow: hidden;
            }
            .background-glow-1 {
                position: absolute;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(248, 250, 252, 0) 70%);
                z-index: 1;
                pointer-events: none;
                top: -10%;
                left: -10%;
            }
            .background-glow-2 {
                position: absolute;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, rgba(248, 250, 252, 0) 70%);
                z-index: 1;
                pointer-events: none;
                bottom: -10%;
                right: -10%;
            }
            .login-card {
                width: 100%;
                max-width: 420px;
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 24px;
                padding: 44px 36px;
                box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1);
                text-align: center;
                z-index: 10;
            }
            .logo {
                font-weight: 800;
                font-size: 2.2rem;
                letter-spacing: -1px;
                margin-bottom: 6px;
                color: #1e293b;
            }
            .logo span { color: var(--primary); }
            .subtitle {
                font-size: 0.8rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 1.5px;
                font-weight: 600;
                margin-bottom: 36px;
            }
            .form-group {
                margin-bottom: 24px;
                text-align: left;
            }
            label {
                display: block;
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--text-muted);
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            input[type="password"] {
                width: 100%;
                padding: 16px;
                background-color: #f8fafc;
                border: 1px solid var(--border-color);
                border-radius: 14px;
                color: var(--text-main);
                font-size: 1.2rem;
                text-align: center;
                letter-spacing: 6px;
                font-family: inherit;
                outline: none;
                transition: all 0.2s ease;
            }
            input[type="password"]:focus {
                border-color: var(--primary);
                background-color: #ffffff;
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            }
            .btn {
                width: 100%;
                padding: 16px;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 14px;
                border: none;
                cursor: pointer;
                background-color: var(--primary);
                color: white;
                box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2);
                font-family: inherit;
                transition: all 0.25s ease;
            }
            .btn:hover {
                background-color: #4338ca;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(79, 70, 229, 0.25);
            }
            .error-message {
                color: #ef4444;
                font-size: 0.85rem;
                margin-top: 18px;
                font-weight: 600;
                background-color: #fee2e2;
                padding: 12px;
                border-radius: 10px;
                border: 1px solid rgba(239, 68, 68, 0.1);
            }
        </style>
    </head>
    <body>
        <div class="background-glow-1"></div>
        <div class="background-glow-2"></div>
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-sidebar: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #e0e7ff;
            
            --success: #10b981;
            --success-light: #d1fae5;
            --success-dark: #065f46;
            
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --warning-dark: #92400e;
            
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --danger-dark: #991b1b;
            
            --info: #0ea5e9;
            --info-light: #e0f2fe;
            --info-dark: #075985;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 280px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 28px 24px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 28px;
        }

        .sidebar-brand .logo {
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -1px;
            color: #1e293b;
        }

        .sidebar-brand .logo span {
            color: var(--primary);
        }

        .sidebar-brand .logo-sub {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 18px;
            color: var(--text-muted);
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .nav-item:hover {
            color: var(--text-main);
            background-color: #f1f5f9;
        }

        .nav-item.active {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        .nav-icon {
            font-size: 1.25rem;
            display: inline-block;
            width: 24px;
            text-align: center;
        }

        .sidebar-footer {
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .logout-link:hover {
            background-color: var(--danger-light);
            color: var(--danger);
        }

        /* Main Content wrapper */
        .main-wrapper {
            margin-left: 280px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top Header */
        .top-header {
            background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .current-time {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            background-color: #f1f5f9;
            padding: 8px 16px;
            border-radius: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-profile .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.12);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .profile-role {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Content Body */
        .content-body {
            padding: 40px;
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Tab panels */
        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Metrics grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .metric-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .metric-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .metric-icon.indigo { background-color: var(--primary-light); color: var(--primary); }
        .metric-icon.emerald { background-color: var(--success-light); color: var(--success); }
        .metric-icon.rose { background-color: var(--danger-light); color: var(--danger); }
        .metric-icon.amber { background-color: var(--warning-light); color: var(--warning); }

        .metric-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .metric-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
        }

        /* Split layout for dynamic status widget on Dashboard */
        .dashboard-row {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 28px;
            margin-bottom: 32px;
        }

        @media (max-width: 1024px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
        }

        /* Card Section styling */
        .card-section {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            margin-bottom: 28px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Forms Layout & Inputs */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .form-input, .form-select {
            padding: 12px 18px;
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .checkbox-container:hover {
            border-color: var(--primary);
            background-color: #f5f6ff;
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Buttons styling */
        .btn-primary {
            background-color: var(--primary);
            color: white;
            font-weight: 700;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1.5px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.25);
        }

        .btn-secondary {
            background-color: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.15);
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
            text-decoration: none;
        }

        .btn-danger:hover {
            background-color: var(--danger);
            color: white;
            transform: translateY(-1.5px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        /* Custom mini clear history button */
        .btn-clear-history {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.1);
            padding: 8px 16px;
            font-size: 0.8rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 700;
        }

        .btn-clear-history:hover {
            background-color: var(--danger);
            color: white;
        }

        /* Table & Lists */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .custom-table th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 16px 20px;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr:hover {
            background-color: #fcfdfe;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success { background-color: var(--success-light); color: var(--success-dark); }
        .badge-warning { background-color: var(--warning-light); color: var(--warning-dark); }
        .badge-danger { background-color: var(--danger-light); color: var(--danger-dark); }
        .badge-info { background-color: var(--info-light); color: var(--info-dark); }
        .badge-gray { background-color: #f1f5f9; color: #475569; }

        /* Quick Info card */
        .status-widget {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background-color: #f8fafc;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .status-row .label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .status-row .val {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Polaroid-style Film strips */
        .history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 28px;
        }

        .history-card {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px 12px 18px 12px;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .history-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 10px 10px -5px rgba(15, 23, 42, 0.03);
            border-color: var(--primary);
        }

        .history-img-wrapper {
            width: 100%;
            aspect-ratio: 0.45;
            border-radius: 10px;
            overflow: hidden;
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
        }

        .history-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .history-card:hover .history-img {
            transform: scale(1.05);
        }

        .history-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .history-id {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
            font-family: monospace;
            letter-spacing: -0.2px;
        }

        .history-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-frame {
            font-size: 0.65rem;
            font-weight: 700;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .history-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Success alerts styling */
        .alert-status {
            padding: 16px 20px;
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-saved { background-color: var(--success-light); color: var(--success-dark); border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-deleted { background-color: var(--danger-light); color: var(--danger-dark); border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-cleared { background-color: var(--warning-light); color: var(--warning-dark); border: 1px solid rgba(245, 158, 11, 0.2); }

        /* Modal frosted overlay */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.4);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 24px;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            max-width: 780px;
            width: 100%;
            padding: 32px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-close {
            position: absolute;
            top: 24px;
            right: 24px;
            background: #f1f5f9;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background-color: var(--danger-light); color: var(--danger); }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }

        .modal-split {
            display: flex;
            gap: 32px;
            height: 420px;
        }

        @media (max-width: 640px) {
            .modal-split {
                flex-direction: column;
                height: auto;
            }
        }

        .modal-preview {
            flex: 1;
            background-color: #0f172a;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .modal-preview img {
            height: 100%;
            width: auto;
            object-fit: contain;
        }

        .modal-actions {
            width: 250px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            justify-content: center;
        }

        @media (max-width: 640px) {
            .modal-actions {
                width: 100%;
            }
        }

        .qr-box {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 12px;
            width: 170px;
            height: 170px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .qr-box canvas { width: 100% !important; height: 100% !important; }
    </style>
    <!-- Include QRCode Generator Library for Web -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
</head>
<body>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="logo">Creative<span>Studio</span></div>
                <div class="logo-sub">Kiosk Controller</div>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-tab="dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> Dashboard
                </a>
                <a href="#" class="nav-item" data-tab="settings">
                    <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span> Kiosk Settings
                </a>
                <a href="#" class="nav-item" data-tab="queue">
                    <span class="nav-icon"><i class="fa-solid fa-hourglass-half"></i></span> Kiosk Queue
                </a>
                <a href="#" class="nav-item" data-tab="packages">
                    <span class="nav-icon"><i class="fa-solid fa-box-archive"></i></span> Manage Packages
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="admin.php?action=logout" class="nav-item logout-link">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout Portal
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="main-wrapper">
            <!-- Top Sticky Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1 class="page-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <span class="current-time"><i class="fa-regular fa-calendar-days"></i> &nbsp;<?php echo date('d M Y, H:i'); ?></span>
                    <div class="user-profile">
                        <div class="avatar">AD</div>
                        <div class="profile-info">
                            <div class="profile-name">Administrator</div>
                            <div class="profile-role">Super Admin</div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Scrollable Workspace Body -->
            <main class="content-body">
                
                <!-- Action Status Banners -->
                <?php if (isset($_GET['status'])): ?>
                    <?php if ($_GET['status'] === 'saved'): ?>
                        <div class="alert-status alert-saved">✓ Remote kiosk configuration successfully updated and synced!</div>
                    <?php elseif ($_GET['status'] === 'packages_saved'): ?>
                        <div class="alert-status alert-saved">✓ Package features and prices updated successfully!</div>
                    <?php elseif ($_GET['status'] === 'queue_reset'): ?>
                        <div class="alert-status alert-cleared">⚠ All kiosk queues cleared successfully!</div>
                    <?php elseif ($_GET['status'] === 'deleted'): ?>
                        <div class="alert-status alert-deleted">✕ Selected photo session permanently removed from disk!</div>
                    <?php elseif ($_GET['status'] === 'cleared'): ?>
                        <div class="alert-status alert-cleared">⚠ Entire photobooth session history cleared from disk!</div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Upper Metrics Row (Horizontal Cards) -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon indigo"><i class="fa-solid fa-camera"></i></div>
                        <div class="metric-info">
                            <div class="metric-label">Total Sesi Foto</div>
                            <div class="metric-value"><?php echo $photosCount; ?> Sesi</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon emerald"><i class="fa-solid fa-bolt"></i></div>
                        <div class="metric-info">
                            <div class="metric-label">Sesi Hari Ini</div>
                            <div class="metric-value"><?php echo $todayPhotosCount; ?> Sesi</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon rose"><i class="fa-solid fa-floppy-disk"></i></div>
                        <div class="metric-info">
                            <div class="metric-label">Memori Terpakai</div>
                            <div class="metric-value"><?php echo $formattedSize; ?></div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon amber"><i class="fa-solid fa-print"></i></div>
                        <div class="metric-info">
                            <div class="metric-label">Printer Aktif</div>
                            <div class="metric-value"><?php echo htmlspecialchars($settings['printer_type']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC TAB PANES -->

                <!-- TAB: Dashboard -->
                <div class="tab-pane active" id="tab-dashboard">
                    <div class="dashboard-row">
                        <!-- Chart Card -->
                        <div class="card-section" style="margin-bottom: 0;">
                            <div class="card-header" style="border: none; margin-bottom: 12px; padding-bottom: 0;">
                                <div class="card-title"><i class="fa-solid fa-chart-line"></i> &nbsp;Tren Aktivitas 7 Hari Terakhir</div>
                            </div>
                            <div style="height: 230px; width: 100%;">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                        </div>

                        <!-- Kiosk Quick Status Widget -->
                        <div class="card-section" style="margin-bottom: 0;">
                            <div class="card-header" style="border: none; margin-bottom: 12px; padding-bottom: 0;">
                                <div class="card-title"><i class="fa-solid fa-sliders"></i> &nbsp;Status Kiosk</div>
                            </div>
                            <div class="status-widget">
                                <div class="status-row">
                                    <span class="label">Antrean Aktif</span>
                                    <span class="val" style="color: var(--primary); font-weight: 800;">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Printer Active</span>
                                    <span class="val badge badge-info"><?php echo htmlspecialchars($settings['printer_type']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Sesi Aktif</span>
                                    <span class="val" style="font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars($queueState['active_session_id']) : 'None'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Photo Logs Gallery Card -->
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-images"></i> &nbsp;Log Hasil Foto Kiosk (<?php echo count($historyList); ?>)</div>
                            <?php if (!empty($historyList)): ?>
                                <form action="admin.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS SEMUA riwayat foto? Tindakan ini tidak dapat dibatalkan.');">
                                    <input type="hidden" name="action" value="clear_all">
                                    <button type="submit" class="btn-clear-history">Bersihkan Semua Riwayat</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($historyList)): ?>
                            <div style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 500; background-color: #f8fafc; border-radius: 16px; border: 1px dashed var(--border-color);">
                                Belum ada riwayat jepretan foto yang diunggah ke server.
                            </div>
                        <?php else: ?>
                            <div class="history-grid">
                                <?php foreach ($historyList as $item): ?>
                                    <div class="history-card" onclick="openDetails('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($item['photo']); ?>', '<?php echo htmlspecialchars($item['frame']); ?>', '<?php echo date('d M Y, H:i', $item['time']); ?>')">
                                        <div class="history-img-wrapper">
                                            <img src="<?php echo htmlspecialchars($item['photo']); ?>" class="history-img" alt="Photo strip">
                                        </div>
                                        <div class="history-info">
                                            <div class="history-id">ID: <?php echo htmlspecialchars($item['id']); ?></div>
                                            <div class="history-meta">
                                                <span class="history-frame"><?php echo htmlspecialchars($item['frame']); ?></span>
                                                <span class="history-time"><?php echo date('d M, H:i', $item['time']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB: Settings -->
                <div class="tab-pane" id="tab-settings">
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title">⚙️ Pengaturan Kontrol Kiosk</div>
                        </div>
                        <form action="admin.php" method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="admin_pin">PIN Keamanan Admin</label>
                                    <input type="text" id="admin_pin" name="admin_pin" class="form-input" value="<?php echo htmlspecialchars($settings['admin_pin']); ?>" pattern="[0-9]{4,8}" required placeholder="Contoh: 1234">
                                </div>

                                <div class="form-group">
                                    <label for="countdown_seconds">Durasi Hitung Mundur (Detik)</label>
                                    <input type="number" id="countdown_seconds" name="countdown_seconds" class="form-input" value="<?php echo intval($settings['countdown_seconds']); ?>" min="2" max="15" required>
                                </div>

                                <div class="form-group">
                                    <label for="total_shots">Jumlah Jepretan Sesi</label>
                                    <input type="number" id="total_shots" name="total_shots" class="form-input" value="<?php echo intval($settings['total_shots']); ?>" min="1" max="8" required>
                                </div>

                                <div class="form-group">
                                    <label for="printer_type">Mode Pencetakan</label>
                                    <select id="printer_type" name="printer_type" class="form-select">
                                        <option value="NONE" <?php echo $settings['printer_type'] === 'NONE' ? 'selected' : ''; ?>>NONE (Mode Digital Saja)</option>
                                        <option value="THERMAL" <?php echo $settings['printer_type'] === 'THERMAL' ? 'selected' : ''; ?>>THERMAL (Printer XP-420B)</option>
                                        <option value="COLOR" <?php echo $settings['printer_type'] === 'COLOR' ? 'selected' : ''; ?>>COLOR (Printer Warna Sistem)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="use_biometric">Autentikasi Biometrik Tablet</label>
                                    <select id="use_biometric" name="use_biometric" class="form-select">
                                        <option value="1" <?php echo $settings['use_biometric'] ? 'selected' : ''; ?>>Aktif (Sensor Sidik Jari)</option>
                                        <option value="0" <?php echo !$settings['use_biometric'] ? 'selected' : ''; ?>>Nonaktif (PIN Saja)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 12px; border-top: 1px solid var(--border-color); padding-top: 24px; text-align: right;">
                                <button type="submit" class="btn-primary">✓ SIMPAN & SINKRONISASI KIOSK</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB: Queue -->
                <div class="tab-pane" id="tab-queue">
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title">📊 Antrean Remote Kiosk</div>
                            <div>
                                <a href="admin.php?action=reset_queue" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin MERESET TOTAL antrean? Sesi remote aktif dan antrean berbayar akan dihapus.');">RESET ANTRIAN KIOSK</a>
                            </div>
                        </div>

                        <div class="dashboard-row" style="margin-bottom: 24px;">
                            <div class="status-widget" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; width: 100%;">
                                <div class="status-row">
                                    <span class="label">Antrean Aktif:</span>
                                    <span class="val" style="color: var(--warning); font-size: 1.1rem; font-weight: 800;">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Sesi Aktif saat ini:</span>
                                    <span class="val" style="font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars($queueState['active_session_id']) : 'Tidak ada'; ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Menunggu (Waiting):</span>
                                    <span class="val" style="color: var(--danger); font-size: 1.1rem; font-weight: 800;"><?php 
                                        $waitingNum = 0;
                                        foreach ($queueState['queue_list'] as $item) {
                                            if ($item['status'] === 'WAITING') $waitingNum++;
                                        }
                                        echo $waitingNum;
                                    ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Table of Queue Sessions -->
                        <div class="card-header" style="border: none; margin-bottom: 12px; padding-bottom: 0;">
                            <div class="card-title" style="font-size: 1.05rem;">📋 Daftar Sesi Antrean</div>
                        </div>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>No. Antrean</th>
                                        <th>ID Sesi</th>
                                        <th>Paket</th>
                                        <th>Waktu Masuk</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $packageNames = [];
                                    if (file_exists($packagesFile)) {
                                        $pkgs = json_decode(file_get_contents($packagesFile), true);
                                        foreach ($pkgs as $p) {
                                            $packageNames[$p['id']] = $p['name'];
                                        }
                                    }
                                    ?>
                                    <?php if (empty($queueState['queue_list'])): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px 0;">Tidak ada antrean terdaftar saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($queueState['queue_list'] as $item): ?>
                                            <tr>
                                                <td style="font-weight: 700; color: var(--primary);">#<?php echo htmlspecialchars($item['queue_number']); ?></td>
                                                <td style="font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars($item['session_id']); ?></td>
                                                <td><span class="badge badge-gray"><?php echo htmlspecialchars($packageNames[$item['package_id']] ?? $item['package_id']); ?></span></td>
                                                <td style="color: var(--text-muted);"><?php echo date('H:i:s (d M)', $item['timestamp']); ?></td>
                                                <td>
                                                    <?php if ($item['status'] === 'UNPAID'): ?>
                                                        <span class="badge badge-danger">Unpaid</span>
                                                    <?php elseif ($item['status'] === 'PAID'): ?>
                                                        <span class="badge badge-info">Paid</span>
                                                    <?php elseif ($item['status'] === 'WAITING'): ?>
                                                        <span class="badge badge-warning">Waiting</span>
                                                    <?php elseif ($item['status'] === 'ACTIVE'): ?>
                                                        <span class="badge badge-success" style="animation: pulse 1.5s infinite;">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray"><?php echo htmlspecialchars($item['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB: Packages -->
                <div class="tab-pane" id="tab-packages">
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title">🛍️ Manajemen Paket & Fitur Kiosk</div>
                        </div>
                        <form action="admin.php" method="POST">
                            <input type="hidden" name="action" value="update_packages">
                            
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
                                <?php foreach ($packagesList as $pkg): ?>
                                    <div class="card-section" style="border: 1px solid var(--border-color); background-color: #f8fafc; border-radius: 18px; margin-bottom: 0;">
                                        <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                            🎁 <?php echo htmlspecialchars($pkg['name']); ?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Harga Paket (Rp)</label>
                                            <input type="number" name="price_<?php echo $pkg['id']; ?>" class="form-input" value="<?php echo intval($pkg['price']); ?>" required style="background-color: white;">
                                        </div>

                                        <div class="form-group" style="margin-top: 14px; gap: 10px;">
                                            <label>Fitur Akses Layanan Kiosk:</label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_print_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['print'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label">🖨️ Cetak Struk Fisik</span>
                                            </label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_download_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['download'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label">📥 Download Foto Strip</span>
                                            </label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_gif_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['gif'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label">🎞️ Live Animated GIF</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="margin-top: 28px; border-top: 1px solid var(--border-color); padding-top: 24px; text-align: right;">
                                <button type="submit" class="btn-primary">✓ SIMPAN PRICING & AKSES FITUR</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Dialog Details -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeDetails()">&times;</button>
            <div class="modal-title">Sesi Foto: <span id="modalIdVal" style="color: var(--primary); font-family: monospace;"></span></div>
            
            <div class="modal-split">
                <div class="modal-preview">
                    <img id="modalImg" src="" alt="Preview jepretan">
                </div>
                
                <div class="modal-actions">
                    <div class="qr-box">
                        <canvas id="modalQr"></canvas>
                    </div>
                    <div style="text-align: center; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-bottom: 8px;">
                        Scan QR untuk Download
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a id="modalDownloadBtn" href="" download="" class="btn-primary">Unduh Gambar</a>
                        <button id="modalDeleteBtn" class="btn-danger">Hapus Sesi</button>
                        <button class="btn-secondary" onclick="closeDetails()">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching logic
        const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
        const tabPanes = document.querySelectorAll('.tab-pane');
        const pageTitle = document.querySelector('.page-title');

        function switchTab(tabId, updateHash = true) {
            navItems.forEach(item => item.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            const selectedNavItem = document.querySelector(`.sidebar-nav .nav-item[data-tab="${tabId}"]`);
            const selectedTabPane = document.getElementById(`tab-${tabId}`);
            
            if (selectedNavItem && selectedTabPane) {
                selectedNavItem.classList.add('active');
                selectedTabPane.classList.add('active');
                
                // Set Header title
                let titleText = selectedNavItem.textContent.trim();
                titleText = titleText.replace(/^[\p{Emoji}\s]+/u, '');
                pageTitle.textContent = titleText;
                
                localStorage.setItem('active_admin_tab', tabId);
                
                if (updateHash) {
                    window.location.hash = tabId;
                }
            }
        }

        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = item.getAttribute('data-tab');
                switchTab(tabId);
            });
        });

        // Chart.js render
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.85)'); // Indigo
        gradient.addColorStop(1, 'rgba(129, 140, 248, 0.2)');  // Soft Indigo

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Jumlah Sesi',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: gradient,
                    borderColor: '#4f46e5',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#64748b', font: { family: 'Outfit', size: 11 }, stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { family: 'Outfit', size: 11 } }
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
            
            const downloadBtn = document.getElementById('modalDownloadBtn');
            downloadBtn.href = photoPath;
            downloadBtn.download = `PhotoBooth_${id}.png`;
            
            const deleteBtn = document.getElementById('modalDeleteBtn');
            deleteBtn.onclick = function() {
                if (confirm(`Apakah Anda yakin ingin menghapus sesi ${id} secara permanen?`)) {
                    window.location.href = `admin.php?action=delete&id=${id}`;
                }
            };
            
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

        window.onclick = function(event) {
            if (event.target === modal) {
                closeDetails();
            }
        }

        // On Load active tab resolver
        window.addEventListener('DOMContentLoaded', () => {
            let activeTab = window.location.hash.replace('#', '');
            
            if (!activeTab) {
                const urlParams = new URLSearchParams(window.location.search);
                const status = urlParams.get('status');
                if (status === 'saved') {
                    activeTab = 'settings';
                } else if (status === 'packages_saved') {
                    activeTab = 'packages';
                } else if (status === 'queue_reset') {
                    activeTab = 'queue';
                } else {
                    activeTab = localStorage.getItem('active_admin_tab') || 'dashboard';
                }
            }
            
            switchTab(activeTab);
        });
    </script>
</body>
</html>
