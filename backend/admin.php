<?php
session_start();

$settingsFile = __DIR__ . '/settings.json';
$uploadDir = __DIR__ . '/uploads/';
$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';

// Load settings from JSON
function loadSettings($file) {
    $defaults = [
        "admin_pin" => "1234",
        "countdown_seconds" => 5,
        "total_shots" => 4,
        "printer_type" => "NONE",
        "use_biometric" => true,
        "payment_mode" => "dummy",
        "midtrans_server_key" => "",
        "midtrans_client_key" => "",
        "midtrans_environment" => "sandbox",
        "fal_key" => ""
    ];
    if (file_exists($file)) {
        $loaded = json_decode(file_get_contents($file), true);
        if (is_array($loaded)) {
            return array_merge($defaults, $loaded);
        }
    }
    return $defaults;
}

// Function to dynamically punch transparent holes (slots) in the frame PNG file
function hollowOutFrame($imagePath, $slots) {
    if (!file_exists($imagePath) || empty($slots)) return;
    
    if (!function_exists('imagecreatefrompng')) return;
    
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo || $imageInfo['mime'] !== 'image/png') return;
    
    $img = imagecreatefrompng($imagePath);
    if (!$img) return;
    
    // Enable alpha transparency blend mode
    imagealphablending($img, false);
    imagesavealpha($img, true);
    
    foreach ($slots as $slot) {
        $x = intval($slot['x']);
        $y = intval($slot['y']);
        $w = intval($slot['width']);
        $h = intval($slot['height']);
        
        if ($w <= 0 || $h <= 0) continue;
        
        // Define transparent color
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        
        // Carve slot region to transparent
        imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $transparent);
    }
    
    // Save the PNG image back
    imagepng($img, $imagePath);
    imagedestroy($img);
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
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --bg-color: #f1f5f9;
                --card-bg: #ffffff;
                --primary: #4f46e5;
                --primary-hover: #4338ca;
                --text-main: #0f172a;
                --text-muted: #475569;
                --border-color: #e2e8f0;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Outfit', sans-serif;
                background: radial-gradient(circle at top left, #eef2f6 0%, #f8fafc 50%, #f1f5f9 100%);
                color: var(--text-main);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 24px;
                position: relative;
                overflow: hidden;
            }
            .background-glow-1 {
                position: absolute;
                width: 700px;
                height: 700px;
                background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(248, 250, 252, 0) 70%);
                z-index: 1;
                pointer-events: none;
                top: -15%;
                left: -15%;
            }
            .background-glow-2 {
                position: absolute;
                width: 700px;
                height: 700px;
                background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, rgba(248, 250, 252, 0) 70%);
                z-index: 1;
                pointer-events: none;
                bottom: -15%;
                right: -15%;
            }
            .login-card {
                width: 100%;
                max-width: 440px;
                background-color: var(--card-bg);
                border: 1px solid rgba(226, 232, 240, 0.8);
                border-radius: 28px;
                padding: 48px 40px;
                box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.08);
                text-align: center;
                z-index: 10;
                position: relative;
            }
            .logo-container {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                margin-bottom: 8px;
            }
            .logo-icon {
                font-size: 1.8rem;
                color: var(--primary);
            }
            .logo {
                font-weight: 800;
                font-size: 2.1rem;
                letter-spacing: -1px;
                color: #1e293b;
            }
            .logo span { color: var(--primary); }
            .subtitle {
                font-size: 0.75rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 2px;
                font-weight: 700;
                margin-bottom: 40px;
            }
            .form-group {
                margin-bottom: 28px;
                text-align: left;
            }
            label {
                display: block;
                font-size: 0.75rem;
                font-weight: 700;
                color: var(--text-muted);
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
            }
            .input-wrapper i {
                position: absolute;
                left: 18px;
                color: var(--text-muted);
                font-size: 1.1rem;
                pointer-events: none;
            }
            input[type="password"] {
                width: 100%;
                padding: 16px 16px 16px 48px;
                background-color: #f8fafc;
                border: 1px solid var(--border-color);
                border-radius: 16px;
                color: var(--text-main);
                font-size: 1.25rem;
                text-align: center;
                letter-spacing: 6px;
                font-family: inherit;
                outline: none;
                transition: all 0.2s ease;
            }
            input[type="password"]::placeholder {
                letter-spacing: 0;
            }
            input[type="password"]:focus {
                border-color: var(--primary);
                background-color: #ffffff;
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            }
            .btn {
                width: 100%;
                padding: 16px;
                font-size: 0.95rem;
                font-weight: 700;
                border-radius: 16px;
                border: none;
                cursor: pointer;
                background-color: var(--primary);
                color: white;
                box-shadow: 0 4px 15px rgba(79, 70, 229, 0.15);
                font-family: inherit;
                transition: all 0.25s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            .btn:hover {
                background-color: var(--primary-hover);
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(79, 70, 229, 0.2);
            }
            .btn:active {
                transform: translateY(0);
            }
            .error-message {
                color: #dc2626;
                font-size: 0.85rem;
                margin-top: 24px;
                font-weight: 600;
                background-color: #fef2f2;
                padding: 12px 16px;
                border-radius: 12px;
                border: 1px solid rgba(220, 38, 38, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
        </style>
    </head>
    <body>
        <div class="background-glow-1"></div>
        <div class="background-glow-2"></div>
        <div class="login-card">
            <div class="logo-container">
                <i class="fa-solid fa-camera-retro logo-icon"></i>
                <div class="logo">Creative<span>Studio</span></div>
            </div>
            <div class="subtitle">Kiosk Web Controller</div>
            
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="pin">Masukkan PIN Keamanan Admin</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="pin" name="pin" maxlength="8" required autofocus placeholder="••••">
                    </div>
                </div>
                <button type="submit" class="btn">
                    <i class="fa-solid fa-right-to-bracket"></i> MASUK DASHBOARD
                </button>
            </form>
            
            <?php if (isset($loginError)): ?>
                <div class="error-message">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?php echo htmlspecialchars($loginError); ?>
                </div>
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
    $existingSettings = loadSettings($settingsFile);
    $shots = isset($existingSettings['total_shots']) ? intval($existingSettings['total_shots']) : 4;
    $printer = isset($_POST['printer_type']) ? $_POST['printer_type'] : 'NONE';
    $biometric = isset($_POST['use_biometric']) && $_POST['use_biometric'] == '1';
    $paymentMode = isset($_POST['payment_mode']) ? $_POST['payment_mode'] : 'dummy';
    $midtransServerKey = isset($_POST['midtrans_server_key']) ? trim($_POST['midtrans_server_key']) : '';
    $midtransClientKey = isset($_POST['midtrans_client_key']) ? trim($_POST['midtrans_client_key']) : '';
    $midtransEnv = isset($_POST['midtrans_environment']) ? $_POST['midtrans_environment'] : 'sandbox';
    $falKey = isset($_POST['fal_key']) ? trim($_POST['fal_key']) : '';
    
    $settings = [
        "admin_pin" => $newPin ? $newPin : '1234',
        "countdown_seconds" => $countdown,
        "total_shots" => $shots,
        "printer_type" => $printer,
        "use_biometric" => $biometric,
        "payment_mode" => $paymentMode,
        "midtrans_server_key" => $midtransServerKey,
        "midtrans_client_key" => $midtransClientKey,
        "midtrans_environment" => $midtransEnv,
        "fal_key" => $falKey
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
        
        if (isset($_POST["print_flow_$id"])) {
            $pkg['print_flow'] = $_POST["print_flow_$id"];
        }
        if (isset($_POST["print_width_mm_$id"])) {
            $pkg['print_width_mm'] = intval($_POST["print_width_mm_$id"]);
        }
        if (isset($_POST["print_height_mm_$id"])) {
            $pkg['print_height_mm'] = intval($_POST["print_height_mm_$id"]);
        }
    }
    
    file_put_contents($packagesFile, json_encode($packages, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=packages_saved#packages');
    exit;
}

// Action: Save Frame Layout Config
$configPath = __DIR__ . '/frames/config.json';
if (isset($_POST['action']) && $_POST['action'] === 'save_frame') {
    $frameId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['frame_id']);
    $frameName = trim($_POST['frame_name']);
    $layoutType = $_POST['layout_type']; // 'strip', 'grid', 'postcard'
    $eventId = $_POST['event_id'];
    $bgColor = trim($_POST['background_color']);
    $slotsJson = isset($_POST['slots_data']) ? $_POST['slots_data'] : '[]';
    $slots = json_decode($slotsJson, true);
    
    $fileUploaded = false;
    $targetFileUrl = "";
    
    // Ensure frames directory exists
    if (!file_exists(__DIR__ . '/frames')) {
        mkdir(__DIR__ . '/frames', 0777, true);
    }
    
    if (isset($_FILES['frame_image']) && $_FILES['frame_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['frame_image']['tmp_name'];
        $fileName = $_FILES['frame_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'png') {
            $newFileName = $frameId . '.png';
            $origFileName = 'original_' . $frameId . '.png';
            
            $destPath = __DIR__ . '/frames/' . $newFileName;
            $origPath = __DIR__ . '/frames/' . $origFileName;
            
            // Move uploaded file to original path first
            if (move_uploaded_file($fileTmpPath, $origPath)) {
                // Copy original clean image to destination to be processed
                copy($origPath, $destPath);
                $fileUploaded = true;
                $targetFileUrl = 'frames/' . $newFileName;
            }
        }
    }
    
    // Load config
    $config = ['events' => [], 'frames' => []];
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
    }
    
    $frameIndex = -1;
    foreach ($config['frames'] as $idx => $f) {
        if ($f['id'] === $frameId) {
            $frameIndex = $idx;
            break;
        }
    }
    
    $imageWidth = 600;
    $imageHeight = 2000;
    if ($fileUploaded) {
        $imageInfo = getimagesize(__DIR__ . '/frames/' . $frameId . '.png');
        if ($imageInfo) {
            $imageWidth = $imageInfo[0];
            $imageHeight = $imageInfo[1];
        }
    } else if ($frameIndex !== -1) {
        $imageWidth = $config['frames'][$frameIndex]['width'];
        $imageHeight = $config['frames'][$frameIndex]['height'];
        $targetFileUrl = $config['frames'][$frameIndex]['image_url'];
    }
    
    if ($frameId && $frameName && ($fileUploaded || $frameIndex !== -1)) {
        $newFrame = [
            "id" => $frameId,
            "name" => $frameName,
            "type" => $layoutType,
            "event_id" => $eventId,
            "width" => intval($imageWidth),
            "height" => intval($imageHeight),
            "background_color" => $bgColor ? $bgColor : "#ffffff",
            "image_url" => $targetFileUrl,
            "slots" => $slots
        ];
        
        if ($frameIndex !== -1) {
            $config['frames'][$frameIndex] = $newFrame;
        } else {
            $config['frames'][] = $newFrame;
        }
        
        $destPath = __DIR__ . '/' . $targetFileUrl;
        $origPath = __DIR__ . '/frames/original_' . $frameId . '.png';
        
        // Restore destination from original un-hollowed image if available to clear previous holes
        if (file_exists($origPath)) {
            copy($origPath, $destPath);
        } else if (file_exists($destPath)) {
            // First time backup of current frame as original backup
            copy($destPath, $origPath);
        }
        
        // Carve transparent slot regions out of the destination frame image
        hollowOutFrame($destPath, $slots);
        
        $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
        
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        header('Location: admin.php?status=frame_saved#frames');
        exit;
    } else {
        header('Location: admin.php?status=frame_error#frames');
        exit;
    }
}

// Action: Delete Frame
if (isset($_GET['action']) && $_GET['action'] === 'delete_frame' && isset($_GET['id'])) {
    $frameId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    if ($frameId) {
        $config = ['events' => [], 'frames' => []];
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
        }
        
        $updatedFrames = [];
        foreach ($config['frames'] as $f) {
            if ($f['id'] === $frameId) {
                $imageFile = __DIR__ . '/' . $f['image_url'];
                if (file_exists($imageFile)) {
                    unlink($imageFile);
                }
                $origFile = __DIR__ . '/frames/original_' . $frameId . '.png';
                if (file_exists($origFile)) {
                    unlink($origFile);
                }
            } else {
                $updatedFrames[] = $f;
            }
        }
        
        $config['frames'] = $updatedFrames;
        $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
        
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        header('Location: admin.php?status=frame_deleted#frames');
        exit;
    }
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

// Load frames and events configuration
$configPath = __DIR__ . '/frames/config.json';
$configData = ['events' => [], 'frames' => []];
if (file_exists($configPath)) {
    $configData = json_decode(file_get_contents($configPath), true);
}
$framesList = isset($configData['frames']) ? $configData['frames'] : [];
$eventsList = isset($configData['events']) ? $configData['events'] : [];

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
            --border-color: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #475569;
            
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            
            --success: #10b981;
            --success-light: #ecfdf5;
            --success-dark: #047857;
            
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --warning-dark: #b45309;
            
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --danger-dark: #b91c1c;
            
            --info: #0ea5e9;
            --info-light: #e0f2fe;
            --info-dark: #0369a1;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        /* Custom Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
            border: 2px solid #f8fafc;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
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
            border-right: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            padding: 32px 24px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 32px;
        }

        .sidebar-brand .logo {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -1px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-brand .logo span {
            color: var(--primary);
        }

        .sidebar-brand .logo-sub {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            font-size: 0.9rem;
            position: relative;
        }

        .nav-item:hover {
            color: var(--text-main);
            background-color: #f8fafc;
        }

        .nav-item.active {
            color: var(--primary);
            background-color: var(--primary-light);
        }
        
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: var(--primary);
            border-radius: 0 4px 4px 0;
        }

        .nav-icon {
            font-size: 1.15rem;
            display: inline-flex;
            width: 20px;
            justify-content: center;
            align-items: center;
        }

        .sidebar-footer {
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
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
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #f1f5f9;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .current-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            background-color: #f1f5f9;
            padding: 8px 14px;
            border-radius: 99px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.08);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .profile-role {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
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
            animation: fadeIn 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Metrics grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .metric-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 20px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -4px rgba(0, 0, 0, 0.02);
            border-color: rgba(79, 70, 229, 0.15);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .metric-icon.indigo { background-color: var(--primary-light); color: var(--primary); }
        .metric-icon.emerald { background-color: var(--success-light); color: var(--success-dark); }
        .metric-icon.rose { background-color: var(--danger-light); color: var(--danger-dark); }
        .metric-icon.amber { background-color: var(--warning-light); color: var(--warning-dark); }

        .metric-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .metric-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .metric-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }

        /* Split layout for dynamic status widget on Dashboard */
        .dashboard-row {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        @media (max-width: 1024px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
        }

        /* Card Section styling */
        .card-section {
            background-color: var(--bg-card);
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Forms Layout & Inputs */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-group label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select {
            padding: 10px 14px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
        }

        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .checkbox-container:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .checkbox-container input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Buttons styling */
        .btn-primary {
            background-color: var(--primary);
            color: white;
            font-weight: 700;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.15);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: #ffffff;
            color: var(--text-main);
            border: 1px solid #e2e8f0;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-0.5px);
        }

        .btn-danger {
            background-color: var(--danger-light);
            color: var(--danger-dark);
            border: 1px solid rgba(239, 68, 68, 0.15);
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-danger:hover {
            background-color: var(--danger);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.15);
        }

        /* Custom mini clear history button */
        .btn-clear-history {
            background-color: var(--danger-light);
            color: var(--danger-dark);
            border: 1px solid rgba(239, 68, 68, 0.1);
            padding: 6px 12px;
            font-size: 0.75rem;
            border-radius: 8px;
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
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        .custom-table th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 14px 18px;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .custom-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
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
            gap: 12px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid #f1f5f9;
        }

        .status-row .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .status-row .val {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Polaroid-style Film strips */
        .history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 20px;
        }

        .history-card {
            background-color: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 10px 10px 14px 10px;
            cursor: pointer;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -8px rgba(15, 23, 42, 0.08);
            border-color: rgba(79, 70, 229, 0.2);
        }

        .history-img-wrapper {
            width: 100%;
            aspect-ratio: 0.45;
            border-radius: 8px;
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
            transform: scale(1.03);
        }

        .history-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .history-id {
            font-size: 0.75rem;
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
            font-size: 0.6rem;
            font-weight: 700;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .history-time {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Success alerts styling */
        .alert-status {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.01);
            animation: slideDown 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-saved { 
            background-color: var(--success-light); 
            color: var(--success-dark); 
            border: 1px solid rgba(16, 185, 129, 0.1); 
            border-left-color: var(--success);
        }
        .alert-deleted { 
            background-color: var(--danger-light); 
            color: var(--danger-dark); 
            border: 1px solid rgba(239, 68, 68, 0.1); 
            border-left-color: var(--danger);
        }
        .alert-cleared { 
            background-color: var(--warning-light); 
            color: var(--warning-dark); 
            border: 1px solid rgba(245, 158, 11, 0.1); 
            border-left-color: var(--warning);
        }

        /* Modal frosted overlay */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.3);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 24px;
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid #f1f5f9;
            border-radius: 24px;
            max-width: 780px;
            width: 100%;
            padding: 32px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
            animation: modalPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.97) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-close {
            position: absolute;
            top: 24px;
            right: 24px;
            background: #f1f5f9;
            border: none;
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background-color: var(--danger-light); color: var(--danger); }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }

        .modal-split {
            display: flex;
            gap: 24px;
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
            border-radius: 16px;
            border: 1px solid #1e293b;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .modal-preview img {
            height: 100%;
            width: auto;
            object-fit: contain;
        }

        .modal-actions {
            width: 240px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            justify-content: center;
        }

        @media (max-width: 640px) {
            .modal-actions {
                width: 100%;
            }
        }

        .qr-box {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 12px;
            width: 150px;
            height: 150px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .qr-box canvas { width: 100% !important; height: 100% !important; }

        /* Frame Manager Styles */
        .frames-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .frame-card-admin {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            transition: all 0.2s;
        }
        .frame-card-admin:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
            transform: translateY(-1.5px);
        }
        .frame-card-preview-admin {
            width: 100%;
            height: 180px;
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
            background-size: 12px 12px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            margin-bottom: 12px;
        }
        .frame-card-preview-admin img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        .frame-card-meta {
            width: 100%;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .frame-card-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .frame-card-tag {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .frame-card-actions {
            display: flex;
            gap: 8px;
            width: 100%;
            margin-top: 12px;
        }
        .frame-card-actions button, .frame-card-actions a {
            flex: 1;
            padding: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        
        /* Visual Editor Layout */
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            margin-top: 20px;
            min-height: 500px;
        }
        @media (max-width: 768px) {
            .editor-container {
                grid-template-columns: 1fr;
            }
        }
        .canvas-area {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
            background-size: 16px 16px;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 24px;
            overflow: auto;
            min-height: 480px;
        }
        .canvas-wrapper {
            position: relative;
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
            background-size: cover;
            border-radius: 8px;
            background-color: #ffffff;
        }
        .canvas-image {
            display: block;
            max-width: 100%;
            max-height: 480px;
            border-radius: 8px;
            user-select: none;
            -webkit-user-drag: none;
        }
        .slot-rect {
            position: absolute;
            border: 2px dashed #10b981;
            background: rgba(16, 185, 129, 0.15);
            color: #ffffff;
            font-weight: 800;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: move;
            box-sizing: border-box;
            user-select: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            border-radius: 4px;
        }
        /* Selection Highlight (Purple) */
        .slot-rect.selected {
            border: 2px solid #8b5cf6 !important;
            box-shadow: 0 0 12px rgba(139, 92, 246, 0.4);
            background: rgba(139, 92, 246, 0.15) !important;
            z-index: 10 !important;
        }
        /* Alignment Smart Snapping Highlight (Green Glow) */
        .slot-rect.align-highlight {
            border-color: #10b981 !important;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.2) !important;
        }
        /* Size Matching Smart Snapping Highlight (Blue Glow) */
        .slot-rect.size-highlight {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.4);
            background: rgba(59, 130, 246, 0.2) !important;
        }
        /* Gap measurement label & lines */
        .snap-gap-indicator {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 90;
        }
        .snap-gap-label {
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            font-family: monospace;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        /* Snapping / Coordinate Tooltip */
        .snap-tooltip {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            pointer-events: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15);
            z-index: 101;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        /* Layer Mode Styling */
        .btn-layer-toggle {
            flex: 1;
            font-size: 0.75rem;
            padding: 8px 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-layer-toggle.active {
            background: var(--primary) !important;
            color: white !important;
            border-color: var(--primary) !important;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.15);
        }
        /* Sandwiched Layer Mode: frame image on top, slots underneath */
        .canvas-wrapper.sandwiched-active {
            background-color: var(--bg-color); /* Reveal grid color through frame holes */
        }
        .canvas-wrapper.sandwiched-active .canvas-image {
            position: relative;
            z-index: 5;
            pointer-events: none;
            opacity: 0.65;
        }
        .canvas-wrapper.sandwiched-active .slot-rect {
            z-index: 1;
        }
        /* Keep selected slot visual indicator visible */
        .canvas-wrapper.sandwiched-active .slot-rect.selected {
            z-index: 10 !important;
        }
        .slot-rect-label {
            background: rgba(15, 23, 42, 0.85);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            pointer-events: none;
            font-weight: 700;
        }
        .slot-rect-close {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            border: 1px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-weight: bold;
            line-height: 1;
        }
        .slot-rect-resize {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: #10b981;
            cursor: se-resize;
            border-top-left-radius: 3px;
            border: 1px solid white;
        }
        .editor-help-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-top: 12px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }
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
                <a href="#" class="nav-item" data-tab="frames">
                    <span class="nav-icon"><i class="fa-solid fa-image"></i></span> Bingkai Kiosk
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
                        <div class="alert-status alert-saved">
                            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                            <span>Remote kiosk configuration successfully updated and synced!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'packages_saved'): ?>
                        <div class="alert-status alert-saved">
                            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                            <span>Package features and prices updated successfully!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'queue_reset'): ?>
                        <div class="alert-status alert-cleared">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.1rem;"></i>
                            <span>All kiosk queues cleared successfully!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'deleted'): ?>
                        <div class="alert-status alert-deleted">
                            <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                            <span>Selected photo session permanently removed from disk!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'frame_saved'): ?>
                        <div class="alert-status alert-saved">
                            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                            <span>Bingkai foto berhasil disimpan & disinkronisasikan!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'frame_deleted'): ?>
                        <div class="alert-status alert-deleted">
                            <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                            <span>Bingkai foto berhasil dihapus secara permanen dari disk!</span>
                        </div>
                    <?php elseif ($_GET['status'] === 'frame_error'): ?>
                        <div class="alert-status alert-cleared">
                            <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                            <span>Gagal menyimpan bingkai! Harap unggah gambar PNG transparan yang valid.</span>
                        </div>
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
                                <div class="card-title"><i class="fa-solid fa-chart-line"></i> Tren Aktivitas 7 Hari Terakhir</div>
                            </div>
                            <div style="height: 230px; width: 100%;">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                        </div>

                        <!-- Kiosk Quick Status Widget -->
                        <div class="card-section" style="margin-bottom: 0;">
                            <div class="card-header" style="border: none; margin-bottom: 12px; padding-bottom: 0;">
                                <div class="card-title"><i class="fa-solid fa-sliders"></i> Status Kiosk</div>
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
                            <div class="card-title"><i class="fa-solid fa-images"></i> Log Hasil Foto Kiosk (<?php echo count($historyList); ?>)</div>
                            <?php if (!empty($historyList)): ?>
                                <form action="admin.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS SEMUA riwayat foto? Tindakan ini tidak dapat dibatalkan.');">
                                    <input type="hidden" name="action" value="clear_all">
                                    <button type="submit" class="btn-clear-history">
                                        <i class="fa-solid fa-trash-can"></i> Bersihkan Semua Riwayat
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($historyList)): ?>
                            <div style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 500; background-color: #f8fafc; border-radius: 16px; border: 1px dashed var(--border-color);">
                                <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 10px; color: #cbd5e1; display: block;"></i>
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
                            <div class="card-title"><i class="fa-solid fa-sliders"></i> Pengaturan Kontrol Kiosk</div>
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
                                    <label for="printer_type">Mode Pencetakan</label>
                                    <select id="printer_type" name="printer_type" class="form-select">
                                        <option value="NONE" <?php echo $settings['printer_type'] === 'NONE' ? 'selected' : ''; ?>>NONE (Mode Digital Saja)</option>
                                        <option value="THERMAL" <?php echo $settings['printer_type'] === 'THERMAL' ? 'selected' : ''; ?>>THERMAL (Printer XP-420B)</option>
                                        <option value="COLOR" <?php echo $settings['printer_type'] === 'COLOR' ? 'selected' : ''; ?>>COLOR (Printer Warna Sistem)</option>
                                        <option value="AUTO" <?php echo $settings['printer_type'] === 'AUTO' ? 'selected' : ''; ?>>AUTO (Dynamic: Thermal & Color)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="use_biometric">Autentikasi Biometrik Tablet</label>
                                    <select id="use_biometric" name="use_biometric" class="form-select">
                                        <option value="1" <?php echo $settings['use_biometric'] ? 'selected' : ''; ?>>Aktif (Sensor Sidik Jari)</option>
                                        <option value="0" <?php echo !$settings['use_biometric'] ? 'selected' : ''; ?>>Nonaktif (PIN Saja)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="payment_mode">Mode Pembayaran</label>
                                    <select id="payment_mode" name="payment_mode" class="form-select" onchange="toggleMidtransFields(this.value)">
                                        <option value="dummy" <?php echo $settings['payment_mode'] === 'dummy' ? 'selected' : ''; ?>>Simulasi / Dummy (Tanpa Kunci API)</option>
                                        <option value="midtrans" <?php echo $settings['payment_mode'] === 'midtrans' ? 'selected' : ''; ?>>Midtrans (Real / Sandbox)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Midtrans Config Section -->
                            <div id="midtrans-settings-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px; <?php echo $settings['payment_mode'] === 'midtrans' ? '' : 'display: none;'; ?>">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-solid fa-credit-card"></i> Pengaturan Midtrans</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="midtrans_environment">Environment Midtrans</label>
                                        <select id="midtrans_environment" name="midtrans_environment" class="form-select">
                                            <option value="sandbox" <?php echo $settings['midtrans_environment'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Mode Uji Coba)</option>
                                            <option value="production" <?php echo $settings['midtrans_environment'] === 'production' ? 'selected' : ''; ?>>Production (Pembayaran Asli)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="midtrans_client_key">Midtrans Client Key</label>
                                        <input type="text" id="midtrans_client_key" name="midtrans_client_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_client_key']); ?>" placeholder="SB-Mid-client-...">
                                    </div>

                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="midtrans_server_key">Midtrans Server Key</label>
                                        <input type="password" id="midtrans_server_key" name="midtrans_server_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_server_key']); ?>" placeholder="SB-Mid-server-...">
                                    </div>
                                </div>
                            </div>
                            <script>
                                function toggleMidtransFields(mode) {
                                    const section = document.getElementById('midtrans-settings-section');
                                    const serverKey = document.getElementById('midtrans_server_key');
                                    const clientKey = document.getElementById('midtrans_client_key');
                                    if (mode === 'midtrans') {
                                        section.style.display = 'block';
                                        serverKey.setAttribute('required', 'required');
                                        clientKey.setAttribute('required', 'required');
                                    } else {
                                        section.style.display = 'none';
                                        serverKey.removeAttribute('required');
                                        clientKey.removeAttribute('required');
                                    }
                                }
                                // Set initial required attributes
                                document.addEventListener('DOMContentLoaded', () => {
                                    const selectEl = document.getElementById('payment_mode');
                                    if (selectEl) {
                                        toggleMidtransFields(selectEl.value);
                                    }
                                });
                            </script>

                            <!-- Fal.ai AI Configuration Section -->
                            <div id="fal-settings-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px;">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> Pengaturan AI Generation (Fal.ai)</h4>
                                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="fal_key">Fal.ai API Key</label>
                                        <input type="password" id="fal_key" name="fal_key" class="form-input" value="<?php echo htmlspecialchars(isset($settings['fal_key']) ? $settings['fal_key'] : ''); ?>" placeholder="fal_key_...">
                                        <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; display: block;">API Key dari fal.ai diperlukan untuk fitur face-swap katalog karakter.</small>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 24px; text-align: right;">
                                <button type="submit" class="btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan & Sinkronisasi Kiosk
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB: Queue -->
                <div class="tab-pane" id="tab-queue">
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Antrean Remote Kiosk</div>
                            <div>
                                <a href="admin.php?action=reset_queue" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin MERESET TOTAL antrean? Sesi remote aktif dan antrean berbayar akan dihapus.');">
                                    <i class="fa-solid fa-rotate-left"></i> Reset Antrean Kiosk
                                </a>
                            </div>
                        </div>

                        <div class="dashboard-row" style="margin-bottom: 24px;">
                            <div class="status-widget" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; width: 100%;">
                                <div class="status-row">
                                    <span class="label">Antrean Aktif:</span>
                                    <span class="val" style="color: var(--warning-dark); font-size: 1.1rem; font-weight: 800;">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Sesi Aktif saat ini:</span>
                                    <span class="val" style="font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars($queueState['active_session_id']) : 'Tidak ada'; ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Menunggu (Waiting):</span>
                                    <span class="val" style="color: var(--danger-dark); font-size: 1.1rem; font-weight: 800;"><?php 
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
                            <div class="card-title" style="font-size: 1.05rem;"><i class="fa-solid fa-list-ol"></i> Daftar Sesi Antrean</div>
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
                            <div class="card-title"><i class="fa-solid fa-box-archive"></i> Manajemen Paket & Fitur Kiosk</div>
                        </div>
                        <form action="admin.php" method="POST">
                            <input type="hidden" name="action" value="update_packages">
                            
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
                                <?php foreach ($packagesList as $pkg): ?>
                                    <div class="card-section" style="border: 1px solid var(--border-color); background-color: #f8fafc; border-radius: 18px; margin-bottom: 0;">
                                        <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                            <i class="fa-solid fa-gift" style="color: var(--primary);"></i> <?php echo htmlspecialchars($pkg['name']); ?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Harga Paket (Rp)</label>
                                            <input type="number" name="price_<?php echo $pkg['id']; ?>" class="form-input" value="<?php echo intval($pkg['price']); ?>" required style="background-color: white;">
                                        </div>

                                        <div class="form-group" style="margin-top: 14px; gap: 10px;">
                                            <label>Fitur Akses Layanan Kiosk:</label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_print_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['print'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label"><i class="fa-solid fa-print"></i> Cetak Struk Fisik</span>
                                            </label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_download_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['download'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label"><i class="fa-solid fa-download"></i> Download Foto Strip</span>
                                            </label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_gif_<?php echo $pkg['id']; ?>" value="1" <?php echo $pkg['features']['gif'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label"><i class="fa-solid fa-film"></i> Live Animated GIF</span>
                                            </label>
                                            
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="feature_sticker_<?php echo $pkg['id']; ?>" value="1" <?php echo isset($pkg['features']['sticker']) && $pkg['features']['sticker'] ? 'checked' : ''; ?>> 
                                                <span class="checkbox-label"><i class="fa-brands fa-whatsapp"></i> Koleksi Stiker WA</span>
                                            </label>
                                        </div>

                                        <div class="form-group" style="margin-top: 14px;">
                                            <label>Alur Pencetakan & Profil Ukuran</label>
                                            <select name="print_flow_<?php echo $pkg['id']; ?>" id="print_flow_<?php echo $pkg['id']; ?>" class="form-input" style="background-color: white;">
                                                <option value="RECEIPT" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'RECEIPT') ? 'selected' : ''; ?>>RECEIPT (Cetak Receipt Termal)</option>
                                                <option value="COLOR_PRINT" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'COLOR_PRINT') ? 'selected' : ''; ?>>COLOR_PRINT (Cetak Foto Warna)</option>
                                                <option value="ID_CARD" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'ID_CARD') ? 'selected' : ''; ?>>ID_CARD (Cetak ID Card Lisensi)</option>
                                            </select>
                                        </div>

                                        <div class="form-group" style="margin-top: 10px;">
                                            <label>Template Ukuran Cetak (Preset)</label>
                                            <select class="form-input" style="background-color: white;" onchange="applyPreset('<?php echo $pkg['id']; ?>', this.value)">
                                                <option value="">-- Pilih Template Ukuran --</option>
                                                <option value="cr80">ID Card Standar (54 x 86 mm)</option>
                                                <option value="r4">Cetak Foto Warna 4R (102 x 152 mm)</option>
                                                <option value="thermal58">Struk Termal 58mm (58 x 200 mm)</option>
                                                <option value="thermal80">Struk Termal 80mm (80 x 200 mm)</option>
                                            </select>
                                        </div>

                                        <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                            <div class="form-group">
                                                <label>Lebar Cetak (mm)</label>
                                                <input type="number" name="print_width_mm_<?php echo $pkg['id']; ?>" id="print_width_mm_<?php echo $pkg['id']; ?>" class="form-input" value="<?php echo isset($pkg['print_width_mm']) ? intval($pkg['print_width_mm']) : 58; ?>" required style="background-color: white;">
                                            </div>
                                            <div class="form-group">
                                                <label>Tinggi Cetak (mm)</label>
                                                <input type="number" name="print_height_mm_<?php echo $pkg['id']; ?>" id="print_height_mm_<?php echo $pkg['id']; ?>" class="form-input" value="<?php echo isset($pkg['print_height_mm']) ? intval($pkg['print_height_mm']) : 200; ?>" required style="background-color: white;">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="margin-top: 28px; border-top: 1px solid var(--border-color); padding-top: 24px; text-align: right;">
                                <button type="submit" class="btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pricing & Akses Fitur
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                    function applyPreset(pkgId, preset) {
                        const widthInput = document.getElementById('print_width_mm_' + pkgId);
                        const heightInput = document.getElementById('print_height_mm_' + pkgId);
                        const flowSelect = document.getElementById('print_flow_' + pkgId);
                        
                        if (preset === 'cr80') {
                            widthInput.value = 54;
                            heightInput.value = 86;
                            flowSelect.value = 'ID_CARD';
                        } else if (preset === 'r4') {
                            widthInput.value = 102;
                            heightInput.value = 152;
                            flowSelect.value = 'COLOR_PRINT';
                        } else if (preset === 'thermal58') {
                            widthInput.value = 58;
                            heightInput.value = 200;
                            flowSelect.value = 'RECEIPT';
                        } else if (preset === 'thermal80') {
                            widthInput.value = 80;
                            heightInput.value = 200;
                            flowSelect.value = 'RECEIPT';
                        }
                    }
                    </script>
                </div>

                <!-- TAB: Frames (Manajemen Bingkai & Visual Editor) -->
                <div class="tab-pane" id="tab-frames">
                    <!-- sub-view: LIST BINGKAI -->
                    <div id="framesListView">
                        <div class="card-section">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                                <div class="card-title"><i class="fa-solid fa-image"></i> Daftar Bingkai Kiosk</div>
                                <button class="btn-primary" onclick="showFrameEditor()">
                                    <i class="fa-solid fa-plus"></i> Tambah Bingkai Baru
                                </button>
                            </div>
                            
                            <div class="frames-grid">
                                <?php if (empty($framesList)): ?>
                                    <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 60px 40px;">
                                        <i class="fa-solid fa-image-portrait" style="font-size: 3rem; margin-bottom: 12px; color: #cbd5e1;"></i>
                                        <p style="font-weight: 600;">Belum ada bingkai kustom.</p>
                                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Klik tombol di atas untuk membuat bingkai pertamamu!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($framesList as $f): ?>
                                        <div class="frame-card-admin">
                                            <div class="frame-card-preview-admin">
                                                <img src="<?php echo htmlspecialchars($f['image_url']); ?>?v=<?php echo isset($configData['version'])?$configData['version']:'1'; ?>" alt="<?php echo htmlspecialchars($f['name']); ?>" onerror="this.src='https://placehold.co/150x180/121212/ffffff?text=No+Preview'">
                                            </div>
                                            <div class="frame-card-meta">
                                                <div class="frame-card-title"><?php echo htmlspecialchars($f['name']); ?></div>
                                                <div class="frame-card-tag">Tipe: <b><?php echo htmlspecialchars(ucfirst($f['type'])); ?></b></div>
                                                <div class="frame-card-tag">Sesi Event: <b>
                                                    <?php 
                                                        $evtName = "Umum (Default)";
                                                        foreach ($eventsList as $e) {
                                                            if ($e['id'] === $f['event_id']) {
                                                                $evtName = $e['name'];
                                                                break;
                                                            }
                                                        }
                                                        echo htmlspecialchars($evtName);
                                                    ?></b>
                                                </div>
                                                <div class="frame-card-tag">Jumlah Slot: <b><?php echo count($f['slots']); ?> Foto</b></div>
                                            </div>
                                            <div class="frame-card-actions">
                                                <button class="btn-secondary" onclick="editFrame(<?php echo htmlspecialchars(json_encode($f)); ?>)">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </button>
                                                <a href="admin.php?action=delete_frame&id=<?php echo urlencode($f['id']); ?>" class="btn-danger" style="background:#ef4444; color:white; display:flex; align-items:center; justify-content:center;" onclick="return confirm('Apakah Anda yakin ingin menghapus bingkai ini secara permanen?')">
                                                    <i class="fa-solid fa-trash-can"></i> Hapus
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- sub-view: VISUAL EDITOR FORM -->
                    <div id="frameEditorView" style="display: none;">
                        <div class="card-section">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                                <div class="card-title" id="editorTitle"><i class="fa-solid fa-image"></i> Buat Bingkai Kustom Baru</div>
                                <button class="btn-secondary" onclick="hideFrameEditor()">
                                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
                                </button>
                            </div>
                            
                            <form action="admin.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_frame">
                                <input type="hidden" id="slotsDataInput" name="slots_data" value="[]">
                                <input type="hidden" id="isEditingInput" name="existing_image" value="0">
                                
                                <div class="editor-container">
                                    <!-- Canvas Designer Area -->
                                    <div class="canvas-area">
                                        <div id="canvasEmptyPlaceholder" style="text-align: center; color: var(--text-muted);">
                                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 3.5rem; margin-bottom: 16px; color: #cbd5e1;"></i>
                                            <p style="font-weight: 600; font-size: 1rem; color: var(--text-main);">Harap unggah berkas bingkai (PNG transparan)</p>
                                            <p style="font-size: 0.8rem; margin-top: 4px;">Canvas area akan aktif otomatis agar Anda dapat mendesain slot.</p>
                                        </div>
                                        
                                        <div class="canvas-wrapper" id="canvasWrapper" style="display: none;">
                                            <img id="canvasImg" class="canvas-image" src="" alt="Frame canvas container">
                                            <!-- Alignment Guide Lines -->
                                            <div id="vGuide" style="position: absolute; border-left: 1.5px dashed #10b981; width: 0; top: 0; bottom: 0; display: none; pointer-events: none; z-index: 100;"></div>
                                            <div id="hGuide" style="position: absolute; border-top: 1.5px dashed #10b981; height: 0; left: 0; right: 0; display: none; pointer-events: none; z-index: 100;"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Editor Settings Sidebar -->
                                    <div style="display: flex; flex-direction: column; gap: 16px;">
                                        <div class="form-group">
                                            <label>ID Bingkai (Kode Unik, Alphanumeric)</label>
                                            <input type="text" id="editorFrameId" name="frame_id" class="form-input" placeholder="misal: wedding_rian_red" required style="background: white;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Nama Bingkai</label>
                                            <input type="text" id="editorFrameName" name="frame_name" class="form-input" placeholder="misal: Rustic Red Floral" required style="background: white;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Tipe Tata Letak (Layout Type)</label>
                                            <select id="editorLayoutType" name="layout_type" class="form-input" style="background: white;" onchange="onLayoutTypeChange()">
                                                <option value="strip">Strip (Vertical Strip - 4 Foto)</option>
                                                <option value="grid">Grid (2x2 Grid Collage - 4 Foto)</option>
                                                <option value="postcard">Card (Postcard / 1-Shot - 1 Foto)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Hubungkan ke Event Khusus</label>
                                            <select id="editorEventId" name="event_id" class="form-input" style="background: white;">
                                                <option value="general">Umum / Semua Sesi Kiosk</option>
                                                <?php foreach ($eventsList as $evt): ?>
                                                    <?php if ($evt['id'] !== 'general'): ?>
                                                        <option value="<?php echo htmlspecialchars($evt['id']); ?>"><?php echo htmlspecialchars($evt['name']); ?> [<?php echo htmlspecialchars($evt['code']); ?>]</option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Warna Latar Belakang Celah Bingkai</label>
                                            <div style="display: flex; gap: 8px;">
                                                <input type="color" id="editorBgColorPicker" class="form-input" style="width: 54px; padding: 4px; height:42px; background: white;" oninput="document.getElementById('editorBgColor').value = this.value">
                                                <input type="text" id="editorBgColor" name="background_color" class="form-input" placeholder="#ffffff" value="#ffffff" required style="background: white;" oninput="document.getElementById('editorBgColorPicker').value = this.value">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label id="fileInputLabel">File Gambar Bingkai (PNG Transparan)</label>
                                            <input type="file" id="editorFrameFile" name="frame_image" class="form-input" accept="image/png" style="background: white;" onchange="handleImageUpload(event)">
                                        </div>
                                        
                                        <!-- Layer Mode Control Toggle -->
                                        <div class="form-group" id="layerModeGroup" style="display: none; background: #f8fafc; border: 1px solid var(--border-color); padding: 12px; border-radius: 12px;">
                                            <label style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-weight:700;"><i class="fa-solid fa-layer-group" style="color: var(--primary);"></i> Mode Pratinjau Tumpukan</label>
                                            <div style="display: flex; gap: 8px;">
                                                <button type="button" id="btnLayerBack" class="btn-layer-toggle active" onclick="setLayerMode('back')" title="Memosisikan bingkai di latar belakang agar mudah mengedit letak kotak slot">
                                                    <i class="fa-solid fa-layer-group"></i> Desain Slot
                                                </button>
                                                <button type="button" id="btnLayerFront" class="btn-layer-toggle" onclick="setLayerMode('front')" title="Memosisikan bingkai di latar depan (sandwich) untuk meninjau elemen ornamen menimpa foto">
                                                    <i class="fa-solid fa-table-cells"></i> Hasil Jadi
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Auto Detect Holes Button -->
                                        <div id="autoDetectHolesGroup" style="display: none; margin-top: 12px; margin-bottom: 12px;">
                                            <button type="button" class="btn-primary" style="background: #10b981; border: 1px solid #10b981; color: white; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.8rem; height: 38px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s ease;" onclick="detectPngHoles()" title="Pindai gambar PNG dan buat kotak foto otomatis sesuai lubang transparan yang ada">
                                                <i class="fa-solid fa-wand-magic-sparkles"></i> Deteksi Lubang PNG Otomatis
                                            </button>
                                        </div>
                                        
                                        <div style="margin-top: 8px; display: flex; gap: 8px;">
                                            <button type="button" class="btn-secondary" style="flex: 1.5; display: flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid var(--border-color); font-size: 0.8rem;" onclick="addSlot()">
                                                <i class="fa-solid fa-plus"></i> Tambah Kotak
                                            </button>
                                            <button type="button" class="btn-danger" style="background: #64748b; color: white; font-size: 0.8rem;" onclick="clearSlots()">
                                                <i class="fa-solid fa-trash-can"></i> Reset
                                            </button>
                                        </div>
                                        
                                        <div style="margin-top: 8px; display: flex; gap: 8px;">
                                            <button type="button" id="btnUndo" class="btn-secondary" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px; padding: 8px; font-size: 0.75rem;" onclick="undo()" title="Undo (Ctrl+Z)" disabled>
                                                <i class="fa-solid fa-rotate-left"></i> Undo
                                            </button>
                                            <button type="button" id="btnRedo" class="btn-secondary" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px; padding: 8px; font-size: 0.75rem;" onclick="redo()" title="Redo (Ctrl+Y)" disabled>
                                                <i class="fa-solid fa-rotate-right"></i> Redo
                                            </button>
                                            <button type="button" id="btnDeleteSel" class="btn-danger" style="flex: 1.2; display: flex; align-items: center; justify-content: center; gap: 4px; padding: 8px; font-size: 0.75rem; background: #6b7280; color: white;" onclick="deleteSelected()" title="Hapus Terpilih (Del)" disabled>
                                                <i class="fa-solid fa-trash-can"></i> Hapus
                                            </button>
                                        </div>
                                        
                                        <div class="editor-help-text" style="line-height: 1.5;">
                                            <i class="fa-solid fa-circle-info"></i> <b>Petunjuk Canvas Editor:</b><br>
                                            1. <b>Pilih & Geser</b>: Klik kotak untuk memilih. Tahan <kbd style="background:#e2e8f0; padding:1px 3px; border-radius:3px;">Ctrl</kbd> atau <kbd style="background:#e2e8f0; padding:1px 3px; border-radius:3px;">Shift</kbd> untuk memilih banyak kotak.<br>
                                            2. <b>Shortcut Keyboard</b>:<br>
                                               &nbsp;&nbsp;• <kbd>Ctrl + Z</kbd> / <kbd>Ctrl + Y</kbd> : Undo / Redo<br>
                                               &nbsp;&nbsp;• <kbd>Del</kbd> / <kbd>Backspace</kbd> : Hapus kotak terpilih<br>
                                               &nbsp;&nbsp;• <kbd>Ctrl + A</kbd> : Pilih semua kotak<br>
                                               &nbsp;&nbsp;• <kbd>↑ ↓ ← →</kbd> : Geser kotak 1px (tahan <b>Shift</b> untuk 5px)<br>
                                            3. <b>Smart Snapping</b>: Geser kotak untuk auto-align dengan kotak lain atau pusat kanvas. Jarak antar kotak (gap) akan menunjukkan label ukuran (misal: `24px`) saat sama.<br>
                                            4. <b>Ubah Ukuran</b>: Tarik handle kanan bawah. Snap otomatis untuk menyamakan lebar/tinggi dengan kotak lain (highlight biru).
                                        </div>
                                        
                                        <div style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                                            <button type="submit" class="btn-primary" style="width: 100%;">
                                                <i class="fa-solid fa-circle-check"></i> Simpan & Rilis Bingkai
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
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
                        <a id="modalDownloadBtn" href="" download="" class="btn-primary">
                            <i class="fa-solid fa-download"></i> Unduh Gambar
                        </a>
                        <button id="modalDeleteBtn" class="btn-danger">
                            <i class="fa-solid fa-trash-can"></i> Hapus Sesi
                        </button>
                        <button class="btn-secondary" onclick="closeDetails()">
                            <i class="fa-solid fa-xmark"></i> Tutup
                        </button>
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

        // --- Visual Frame Editor JS Logic ---
        let slots = [];
        let editorImgWidth = 0;
        let editorImgHeight = 0;
        
        let selectedSlotIds = [];
        let history = [];
        let historyIndex = -1;
        let arrowKeyPressed = false;

        function showFrameEditor() {
            document.getElementById('framesListView').style.display = 'none';
            document.getElementById('frameEditorView').style.display = 'block';
            
            // Reset form fields
            document.getElementById('editorFrameId').value = '';
            document.getElementById('editorFrameId').readOnly = false;
            document.getElementById('editorFrameName').value = '';
            document.getElementById('editorLayoutType').value = 'strip';
            document.getElementById('editorEventId').value = 'general';
            document.getElementById('editorBgColor').value = '#ffffff';
            document.getElementById('editorBgColorPicker').value = '#ffffff';
            document.getElementById('editorFrameFile').value = '';
            document.getElementById('editorFrameFile').required = true;
            document.getElementById('fileInputLabel').innerText = "File Gambar Bingkai (Format PNG Transparan)";
            document.getElementById('isEditingInput').value = '0';
            document.getElementById('editorTitle').innerHTML = '<i class="fa-solid fa-image"></i> Buat Bingkai Kustom Baru';
            
            // Clear canvas wrapper
            document.getElementById('canvasWrapper').style.display = 'none';
            document.getElementById('canvasImg').src = '';
            document.getElementById('canvasEmptyPlaceholder').style.display = 'block';
            slots = [];
            selectedSlotIds = [];
            history = [];
            historyIndex = -1;
            renderSlots();
            updateSelectionButtons();
            updateUndoRedoButtons();
            
            document.getElementById('layerModeGroup').style.display = 'none';
            document.getElementById('autoDetectHolesGroup').style.display = 'none';
            setLayerMode('back');
        }

        function hideFrameEditor() {
            document.getElementById('frameEditorView').style.display = 'none';
            document.getElementById('framesListView').style.display = 'block';
        }

        function clearSlots() {
            if (confirm('Apakah Anda yakin ingin menghapus semua kotak foto?')) {
                slots = [];
                selectedSlotIds = [];
                renderSlots();
                updateSelectionButtons();
                saveHistoryState();
            }
        }

        function addSlot() {
            const previewImg = document.getElementById('canvasImg');
            if (!previewImg || !previewImg.src || previewImg.style.display === 'none' || document.getElementById('canvasWrapper').style.display === 'none') {
                alert("Harap unggah gambar bingkai terlebih dahulu!");
                return;
            }
            
            const max = document.getElementById('editorLayoutType').value === 'postcard' ? 1 : 4;
            if (slots.length >= max) {
                alert(`Maksimum slot untuk tata letak ini adalah ${max} foto!`);
                return;
            }
            
            const wrapper = document.getElementById('canvasWrapper');
            const wrapperRect = wrapper.getBoundingClientRect();
            
            const id = 'slot_' + Math.random().toString(36).substr(2, 9);
            const w = Math.round(wrapperRect.width * 0.4);
            const h = Math.round(w * 0.75);
            const x = Math.round((wrapperRect.width - w) / 2);
            const y = Math.round((wrapperRect.height - h) / 2 + (slots.length * 30));
            
            slots.push({
                id: id,
                index: slots.length,
                x: x,
                y: y > 0 ? y : 10,
                width: w > 20 ? w : 100,
                height: h > 20 ? h : 75
            });
            renderSlots();
            saveHistoryState();
        }

        function deleteSlot(id) {
            slots = slots.filter(s => s.id !== id);
            selectedSlotIds = selectedSlotIds.filter(selId => selId !== id);
            slots.forEach((s, idx) => s.index = idx);
            renderSlots();
            updateSelectionButtons();
            saveHistoryState();
        }

        function deleteSelected() {
            if (selectedSlotIds.length === 0) return;
            slots = slots.filter(s => !selectedSlotIds.includes(s.id));
            selectedSlotIds = [];
            slots.forEach((s, idx) => s.index = idx);
            renderSlots();
            updateSelectionButtons();
            saveHistoryState();
            showActionToast('Menghapus kotak terpilih');
        }

        function nudgeSelected(direction, step) {
            if (selectedSlotIds.length === 0) return;
            const wrapper = document.getElementById('canvasWrapper');
            if (!wrapper) return;
            const wrapperRect = wrapper.getBoundingClientRect();
            
            selectedSlotIds.forEach(id => {
                const s = slots.find(item => item.id === id);
                if (!s) return;
                
                if (direction === 'ArrowUp' || direction === 'arrowup') s.y = Math.max(0, s.y - step);
                else if (direction === 'ArrowDown' || direction === 'arrowdown') s.y = Math.min(wrapperRect.height - s.height, s.y + step);
                else if (direction === 'ArrowLeft' || direction === 'arrowleft') s.x = Math.max(0, s.x - step);
                else if (direction === 'ArrowRight' || direction === 'arrowright') s.x = Math.min(wrapperRect.width - s.width, s.x + step);
                
                const el = wrapper.querySelector(`.slot-rect[data-id="${id}"]`);
                if (el) {
                    el.style.left = s.x + 'px';
                    el.style.top = s.y + 'px';
                }
            });
            
            updateSlotsDataField();
        }

        function onLayoutTypeChange() {
            const layout = document.getElementById('editorLayoutType').value;
            
            // If slots exist, ask to regenerate default layout coordinates
            if (slots.length > 0) {
                if (confirm("Apakah Anda ingin mereset tata letak kotak foto ke default untuk tipe " + layout.toUpperCase() + "?")) {
                    generateDefaultSlots();
                    selectedSlotIds = [];
                    updateSelectionButtons();
                    saveHistoryState();
                    showActionToast(`Reset Tata Letak ${layout.toUpperCase()}`);
                } else {
                    // Fallback: just trim excess slots if they exceed layout maximum limit
                    const max = layout === 'postcard' ? 1 : 4;
                    if (slots.length > max) {
                        slots = slots.slice(0, max);
                        selectedSlotIds = selectedSlotIds.filter(id => slots.some(s => s.id === id));
                        renderSlots();
                        updateSelectionButtons();
                        saveHistoryState();
                    }
                }
            } else {
                generateDefaultSlots();
                saveHistoryState();
            }
        }

        function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('canvasImg');
                img.onload = function() {
                    document.getElementById('canvasEmptyPlaceholder').style.display = 'none';
                    document.getElementById('canvasWrapper').style.display = 'block';
                    document.getElementById('layerModeGroup').style.display = 'block';
                    document.getElementById('autoDetectHolesGroup').style.display = 'block';
                    setLayerMode('back');
                    
                    // Automatically detect PNG holes or fallback to defaults
                    detectPngHoles(true);
                    
                    // Initialize history stack
                    history = [JSON.stringify(slots)];
                    historyIndex = 0;
                    selectedSlotIds = [];
                    updateSelectionButtons();
                    updateUndoRedoButtons();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function generateDefaultSlots() {
            const previewImg = document.getElementById('canvasImg');
            const wrapper = document.getElementById('canvasWrapper');
            if (previewImg.naturalWidth === 0) return;
            
            const previewW = previewImg.clientWidth;
            const previewH = previewImg.clientHeight;
            
            slots = [];
            const layout = document.getElementById('editorLayoutType').value;
            
            if (layout === 'postcard') {
                // Card style layout (1 large centered slot)
                const w = Math.round(previewW * 0.85);
                const h = Math.round(previewH * 0.85);
                slots.push({
                    id: 'slot_' + Math.random().toString(36).substr(2, 9),
                    index: 0,
                    x: Math.round((previewW - w) / 2),
                    y: Math.round((previewH - h) / 2),
                    width: w,
                    height: h
                });
            } else if (layout === 'grid') {
                // 2x2 collage slots
                const w = Math.round(previewW * 0.43);
                const h = Math.round(previewH * 0.40);
                const gapX = Math.round(previewW * 0.05);
                const gapY = Math.round(previewH * 0.05);
                
                slots.push({ id: 's0', index: 0, x: gapX, y: gapY, width: w, height: h });
                slots.push({ id: 's1', index: 1, x: previewW - w - gapX, y: gapY, width: w, height: h });
                slots.push({ id: 's2', index: 2, x: gapX, y: previewH - h - gapY, width: w, height: h });
                slots.push({ id: 's3', index: 3, x: previewW - w - gapX, y: previewH - h - gapY, width: w, height: h });
            } else {
                // Strip (4 vertical slots)
                const w = Math.round(previewW * 0.82);
                const h = Math.round(previewH * 0.185);
                const gapX = Math.round((previewW - w) / 2);
                
                // Slots are spaced down vertically
                for (let i = 0; i < 4; i++) {
                    const y = Math.round(previewH * (0.03 + (i * 0.20)));
                    slots.push({
                        id: 's' + i,
                        index: i,
                        x: gapX,
                        y: y,
                        width: w,
                        height: h
                    });
                }
            }
            renderSlots();
        }

        function renderSlots() {
            const wrapper = document.getElementById('canvasWrapper');
            if (!wrapper) return;
            
            const oldRects = wrapper.querySelectorAll('.slot-rect');
            oldRects.forEach(r => r.remove());
            
            slots.forEach(slot => {
                const rect = document.createElement('div');
                rect.className = 'slot-rect' + (selectedSlotIds.includes(slot.id) ? ' selected' : '');
                rect.style.left = slot.x + 'px';
                rect.style.top = slot.y + 'px';
                rect.style.width = slot.width + 'px';
                rect.style.height = slot.height + 'px';
                rect.dataset.id = slot.id;
                
                rect.innerHTML = `
                    <span class="slot-rect-label">Foto ${slot.index + 1}</span>
                    <div class="slot-rect-close" onclick="deleteSlot('${slot.id}')">&times;</div>
                    <div class="slot-rect-resize"></div>
                `;
                
                setupInteract(rect, slot);
                wrapper.appendChild(rect);
            });
            
            updateSlotsDataField();
        }

        function updateSelectionDOM() {
            const wrapper = document.getElementById('canvasWrapper');
            if (!wrapper) return;
            wrapper.querySelectorAll('.slot-rect').forEach(rect => {
                const id = rect.dataset.id;
                if (selectedSlotIds.includes(id)) {
                    rect.classList.add('selected');
                } else {
                    rect.classList.remove('selected');
                }
            });
        }

        function updateSelectionButtons() {
            const btnDel = document.getElementById('btnDeleteSel');
            if (btnDel) {
                if (selectedSlotIds.length > 0) {
                    btnDel.disabled = false;
                    btnDel.style.background = '#ef4444';
                    btnDel.style.cursor = 'pointer';
                    btnDel.innerHTML = `<i class="fa-solid fa-trash-can"></i> Hapus (${selectedSlotIds.length})`;
                } else {
                    btnDel.disabled = true;
                    btnDel.style.background = '#6b7280';
                    btnDel.style.cursor = 'not-allowed';
                    btnDel.innerHTML = `<i class="fa-solid fa-trash-can"></i> Hapus`;
                }
            }
        }

        function updateUndoRedoButtons() {
            const btnUndo = document.getElementById('btnUndo');
            const btnRedo = document.getElementById('btnRedo');
            
            if (btnUndo) {
                if (historyIndex > 0) {
                    btnUndo.disabled = false;
                    btnUndo.style.opacity = '1';
                    btnUndo.style.cursor = 'pointer';
                } else {
                    btnUndo.disabled = true;
                    btnUndo.style.opacity = '0.5';
                    btnUndo.style.cursor = 'not-allowed';
                }
            }
            if (btnRedo) {
                if (historyIndex < history.length - 1 && history.length > 0) {
                    btnRedo.disabled = false;
                    btnRedo.style.opacity = '1';
                    btnRedo.style.cursor = 'pointer';
                } else {
                    btnRedo.disabled = true;
                    btnRedo.style.opacity = '0.5';
                    btnRedo.style.cursor = 'not-allowed';
                }
            }
        }

        function saveHistoryState() {
            if (historyIndex < history.length - 1) {
                history = history.slice(0, historyIndex + 1);
            }
            const state = JSON.stringify(slots);
            if (history.length > 0 && history[history.length - 1] === state) {
                return;
            }
            history.push(state);
            historyIndex = history.length - 1;
            updateUndoRedoButtons();
        }

        function undo() {
            if (historyIndex > 0) {
                historyIndex--;
                slots = JSON.parse(history[historyIndex]);
                // Keep selection valid
                selectedSlotIds = selectedSlotIds.filter(id => slots.some(s => s.id === id));
                renderSlots();
                updateSelectionButtons();
                updateUndoRedoButtons();
                showActionToast('<i class="fa-solid fa-rotate-left"></i> Undo');
            }
        }

        function redo() {
            if (historyIndex < history.length - 1) {
                historyIndex++;
                slots = JSON.parse(history[historyIndex]);
                selectedSlotIds = selectedSlotIds.filter(id => slots.some(s => s.id === id));
                renderSlots();
                updateSelectionButtons();
                updateUndoRedoButtons();
                showActionToast('<i class="fa-solid fa-rotate-right"></i> Redo');
            }
        }

        function showActionToast(message) {
            let toast = document.getElementById('editorToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'editorToast';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1e293b;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 9999px;
                    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
                    z-index: 1000;
                    font-size: 0.85rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    border: 1px solid rgba(255,255,255,0.15);
                    opacity: 0;
                    transition: opacity 0.2s ease, transform 0.2s ease;
                `;
                document.body.appendChild(toast);
            }
            toast.innerHTML = message;
            toast.style.display = 'flex';
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(-5px)';
            
            if (window.toastTimeout) clearTimeout(window.toastTimeout);
            window.toastTimeout = setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-50%) translateY(0)';
                setTimeout(() => { toast.style.display = 'none'; }, 200);
            }, 1500);
        }

        function setLayerMode(mode) {
            const wrapper = document.getElementById('canvasWrapper');
            const btnBack = document.getElementById('btnLayerBack');
            const btnFront = document.getElementById('btnLayerFront');
            
            if (!wrapper) return;
            
            if (mode === 'front') {
                wrapper.classList.add('sandwiched-active');
                if (btnBack) btnBack.classList.remove('active');
                if (btnFront) btnFront.classList.add('active');
                showActionToast('<i class="fa-solid fa-table-cells"></i> Hasil Jadi (Bingkai di Depan)');
            } else {
                wrapper.classList.remove('sandwiched-active');
                if (btnBack) btnBack.classList.add('active');
                if (btnFront) btnFront.classList.remove('active');
                showActionToast('<i class="fa-solid fa-layer-group"></i> Desain Slot (Bingkai di Belakang)');
            }
        }

        function detectPngHoles(fallbackOnFail = false) {
            const img = document.getElementById('canvasImg');
            if (!img || !img.src || img.naturalWidth === 0) {
                if (!fallbackOnFail) showActionToast('Harap unggah gambar terlebih dahulu!');
                return;
            }
            
            // Create offscreen canvas
            const canvas = document.createElement('canvas');
            const w = img.naturalWidth;
            const h = img.naturalHeight;
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            let imgData;
            try {
                imgData = ctx.getImageData(0, 0, w, h);
            } catch (err) {
                if (fallbackOnFail) {
                    generateDefaultSlots();
                } else {
                    showActionToast('Gagal memindai piksel: Gambar berasal dari domain lain.');
                }
                return;
            }
            
            const data = imgData.data;
            
            // 1. Analyze Row Profile (Vertical Segmentation)
            const rowTransparency = new Float32Array(h);
            for (let y = 0; y < h; y++) {
                let transCount = 0;
                for (let x = 0; x < w; x++) {
                    const alpha = data[(y * w + x) * 4 + 3];
                    if (alpha < 80) transCount++; // alpha < 80 threshold is very safe
                }
                rowTransparency[y] = transCount / w;
            }
            
            // Find continuous rows with high transparency (> 15% of row width)
            const ySegments = [];
            let inSegment = false;
            let startY = 0;
            
            for (let y = 0; y < h; y++) {
                const isTrans = rowTransparency[y] > 0.15;
                if (isTrans && !inSegment) {
                    startY = y;
                    inSegment = true;
                } else if (!isTrans && inSegment) {
                    const height = y - startY;
                    if (height >= 40) { // Min height of 40px
                        ySegments.push({ start: startY, end: y });
                    }
                    inSegment = false;
                }
            }
            if (inSegment) {
                const height = h - startY;
                if (height >= 40) ySegments.push({ start: startY, end: h });
            }
            
            if (ySegments.length === 0) {
                if (fallbackOnFail) {
                    generateDefaultSlots();
                    showActionToast('Gambar solid terdeteksi: Menggunakan tata letak default.');
                } else {
                    showActionToast('Tidak mendeteksi area transparan (lubang) pada gambar!');
                }
                return;
            }
            
            const detectedRects = [];
            
            // 2. For each vertical segment, analyze Column Profile to find slots
            ySegments.forEach(seg => {
                const yStart = seg.start;
                const yEnd = seg.end;
                const segHeight = yEnd - yStart;
                
                const colTransparency = new Float32Array(w);
                for (let x = 0; x < w; x++) {
                    let transCount = 0;
                    for (let y = yStart; y < yEnd; y++) {
                        const alpha = data[(y * w + x) * 4 + 3];
                        if (alpha < 80) transCount++;
                    }
                    colTransparency[x] = transCount / segHeight;
                }
                
                // Find continuous columns with high transparency (> 20% of segment height)
                let inColSeg = false;
                let startX = 0;
                
                for (let x = 0; x < w; x++) {
                    const isTrans = colTransparency[x] > 0.20;
                    if (isTrans && !inColSeg) {
                        startX = x;
                        inColSeg = true;
                    } else if (!isTrans && inColSeg) {
                        const width = x - startX;
                        if (width >= 40) { // Min width of 40px
                            detectedRects.push({
                                x: startX,
                                y: yStart,
                                width: width,
                                height: segHeight
                            });
                        }
                        inColSeg = false;
                    }
                }
                if (inColSeg) {
                    const width = w - startX;
                    if (width >= 40) {
                        detectedRects.push({
                            x: startX,
                            y: yStart,
                            width: width,
                            height: segHeight
                        });
                    }
                }
            });
            
            if (detectedRects.length === 0) {
                if (fallbackOnFail) {
                    generateDefaultSlots();
                    showActionToast('Gambar solid terdeteksi: Menggunakan tata letak default.');
                } else {
                    showActionToast('Gagal memisahkan kolom transparan pada gambar.');
                }
                return;
            }
            
            // Sort slots: top-to-bottom, then left-to-right
            detectedRects.sort((a, b) => {
                if (Math.abs(a.y - b.y) < 15) {
                    return a.x - b.x;
                }
                return a.y - b.y;
            });
            
            // Scale slots to the editor's preview coordinates
            const previewW = img.clientWidth;
            const previewH = img.clientHeight;
            const scaleX = w / previewW;
            const scaleY = h / previewH;
            
            slots = detectedRects.map((r, index) => {
                return {
                    id: 'slot_' + Math.random().toString(36).substr(2, 9),
                    index: index,
                    x: Math.round(r.x / scaleX),
                    y: Math.round(r.y / scaleY),
                    width: Math.round(r.width / scaleX),
                    height: Math.round(r.height / scaleY)
                };
            });
            
            renderSlots();
            saveHistoryState();
            showActionToast(`<i class="fa-solid fa-wand-magic-sparkles"></i> Deteksi otomatis: ${slots.length} lubang terdeteksi!`);
        }

        function clearSmartSnapVisuals() {
            document.querySelectorAll('.slot-rect').forEach(el => {
                el.classList.remove('align-highlight', 'size-highlight');
            });
            const vGuide = document.getElementById('vGuide');
            const hGuide = document.getElementById('hGuide');
            if (vGuide) vGuide.style.display = 'none';
            if (hGuide) hGuide.style.display = 'none';
            
            document.querySelectorAll('.snap-gap-indicator, .snap-gap-line, .snap-tooltip').forEach(el => el.remove());
        }

        function highlightSlot(id, type) {
            const el = document.querySelector(`.slot-rect[data-id="${id}"]`);
            if (el) {
                if (type === 'align') {
                    el.classList.add('align-highlight');
                } else if (type === 'size') {
                    el.classList.add('size-highlight');
                }
            }
        }

        function showTooltip(rect, text) {
            let tooltip = rect.querySelector('.snap-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'snap-tooltip';
                rect.appendChild(tooltip);
            }
            tooltip.innerHTML = text;
        }

        function showGapIndicator(type, box1, box2, gap) {
            const wrapper = document.getElementById('canvasWrapper');
            if (!wrapper) return;
            
            const gapVal = Math.round(gap);
            
            if (type === 'v') {
                const yStart = box1.y + box1.height;
                const yEnd = box2.y;
                const height = yEnd - yStart;
                if (height <= 0) return;
                
                const x = Math.min(box1.x + box1.width / 2, box2.x + box2.width / 2);
                
                const line = document.createElement('div');
                line.className = 'snap-gap-line';
                line.style.cssText = `
                    position: absolute;
                    left: ${x}px;
                    top: ${yStart}px;
                    width: 2px;
                    height: ${height}px;
                    border-left: 1.5px dashed #e11d48;
                    pointer-events: none;
                    z-index: 90;
                `;
                wrapper.appendChild(line);
                
                const labelContainer = document.createElement('div');
                labelContainer.className = 'snap-gap-indicator';
                labelContainer.style.cssText = `
                    left: ${x - 20}px;
                    top: ${yStart + height / 2 - 10}px;
                `;
                
                const label = document.createElement('span');
                label.className = 'snap-gap-label';
                label.innerText = `${gapVal}px`;
                
                labelContainer.appendChild(label);
                wrapper.appendChild(labelContainer);
            } else {
                const xStart = box1.x + box1.width;
                const xEnd = box2.x;
                const width = xEnd - xStart;
                if (width <= 0) return;
                
                const y = Math.min(box1.y + box1.height / 2, box2.y + box2.height / 2);
                
                const line = document.createElement('div');
                line.className = 'snap-gap-line';
                line.style.cssText = `
                    position: absolute;
                    left: ${xStart}px;
                    top: ${y}px;
                    width: ${width}px;
                    height: 2px;
                    border-top: 1.5px dashed #e11d48;
                    pointer-events: none;
                    z-index: 90;
                `;
                wrapper.appendChild(line);
                
                const labelContainer = document.createElement('div');
                labelContainer.className = 'snap-gap-indicator';
                labelContainer.style.cssText = `
                    left: ${xStart + width / 2 - 20}px;
                    top: ${y - 10}px;
                `;
                
                const label = document.createElement('span');
                label.className = 'snap-gap-label';
                label.innerText = `${gapVal}px`;
                
                labelContainer.appendChild(label);
                wrapper.appendChild(labelContainer);
            }
        }

        function setupInteract(rect, slot) {
            const wrapper = document.getElementById('canvasWrapper');
            const resizeHandle = rect.querySelector('.slot-rect-resize');
            const vGuide = document.getElementById('vGuide');
            const hGuide = document.getElementById('hGuide');
            
            rect.addEventListener('mousedown', function(e) {
                if (e.target.classList.contains('slot-rect-close') || e.target.classList.contains('slot-rect-resize')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                
                const isSelected = selectedSlotIds.includes(slot.id);
                
                if (e.ctrlKey || e.shiftKey) {
                    if (isSelected) {
                        selectedSlotIds = selectedSlotIds.filter(id => id !== slot.id);
                    } else {
                        selectedSlotIds.push(slot.id);
                    }
                } else {
                    if (!isSelected) {
                        selectedSlotIds = [slot.id];
                    }
                }
                
                updateSelectionDOM();
                updateSelectionButtons();
                
                const startX = e.clientX;
                const startY = e.clientY;
                
                const dragStartPositions = {};
                selectedSlotIds.forEach(id => {
                    const s = slots.find(item => item.id === id);
                    if (s) {
                        dragStartPositions[id] = { x: s.x, y: s.y };
                    }
                });
                
                const wrapperRect = wrapper.getBoundingClientRect();
                let hasDragged = false;
                
                function onMouseMove(moveEvent) {
                    hasDragged = true;
                    const dx = moveEvent.clientX - startX;
                    const dy = moveEvent.clientY - startY;
                    
                    let tempLeft = dragStartPositions[slot.id].x + dx;
                    let tempTop = dragStartPositions[slot.id].y + dy;
                    
                    tempLeft = Math.max(0, Math.min(tempLeft, wrapperRect.width - slot.width));
                    tempTop = Math.max(0, Math.min(tempTop, wrapperRect.height - slot.height));
                    
                    const SNAP_DIST = 8;
                    let finalLeft = tempLeft;
                    let finalTop = tempTop;
                    
                    clearSmartSnapVisuals();
                    let snappedX = false;
                    let snappedY = false;
                    
                    // Canvas Center Snapping
                    const canvasCenterX = wrapperRect.width / 2;
                    if (Math.abs((tempLeft + slot.width / 2) - canvasCenterX) < SNAP_DIST) {
                        finalLeft = canvasCenterX - slot.width / 2;
                        vGuide.style.left = canvasCenterX + 'px';
                        vGuide.style.display = 'block';
                        snappedX = true;
                        showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Tengah Kanvas (H)');
                    }
                    
                    const canvasCenterY = wrapperRect.height / 2;
                    if (Math.abs((tempTop + slot.height / 2) - canvasCenterY) < SNAP_DIST) {
                        finalTop = canvasCenterY - slot.height / 2;
                        hGuide.style.top = canvasCenterY + 'px';
                        hGuide.style.display = 'block';
                        snappedY = true;
                        showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Tengah Kanvas (V)');
                    }
                    
                    // Box to Box Snapping
                    slots.forEach(other => {
                        if (selectedSlotIds.includes(other.id)) return;
                        
                        if (!snappedX) {
                            if (Math.abs(tempLeft - other.x) < SNAP_DIST) {
                                finalLeft = other.x;
                                vGuide.style.left = other.x + 'px';
                                vGuide.style.display = 'block';
                                snappedX = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Kiri');
                            } else if (Math.abs((tempLeft + slot.width) - (other.x + other.width)) < SNAP_DIST) {
                                finalLeft = other.x + other.width - slot.width;
                                vGuide.style.left = (other.x + other.width) + 'px';
                                vGuide.style.display = 'block';
                                snappedX = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Kanan');
                            } else if (Math.abs((tempLeft + slot.width / 2) - (other.x + other.width / 2)) < SNAP_DIST) {
                                finalLeft = (other.x + other.width / 2) - slot.width / 2;
                                vGuide.style.left = (other.x + other.width / 2) + 'px';
                                vGuide.style.display = 'block';
                                snappedX = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tengah (H)');
                            } else if (Math.abs(tempLeft - (other.x + other.width)) < SNAP_DIST) {
                                finalLeft = other.x + other.width;
                                vGuide.style.left = (other.x + other.width) + 'px';
                                vGuide.style.display = 'block';
                                snappedX = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tepi Kanan');
                            } else if (Math.abs((tempLeft + slot.width) - other.x) < SNAP_DIST) {
                                finalLeft = other.x - slot.width;
                                vGuide.style.left = other.x + 'px';
                                vGuide.style.display = 'block';
                                snappedX = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tepi Kiri');
                            }
                        }
                        
                        if (!snappedY) {
                            if (Math.abs(tempTop - other.y) < SNAP_DIST) {
                                finalTop = other.y;
                                hGuide.style.top = other.y + 'px';
                                hGuide.style.display = 'block';
                                snappedY = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Atas');
                            } else if (Math.abs((tempTop + slot.height) - (other.y + other.height)) < SNAP_DIST) {
                                finalTop = other.y + other.height - slot.height;
                                hGuide.style.top = (other.y + other.height) + 'px';
                                hGuide.style.display = 'block';
                                snappedY = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Bawah');
                            } else if (Math.abs((tempTop + slot.height / 2) - (other.y + other.height / 2)) < SNAP_DIST) {
                                finalTop = (other.y + other.height / 2) - slot.height / 2;
                                hGuide.style.top = (other.y + other.height / 2) + 'px';
                                hGuide.style.display = 'block';
                                snappedY = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tengah (V)');
                            } else if (Math.abs(tempTop - (other.y + other.height)) < SNAP_DIST) {
                                finalTop = other.y + other.height;
                                hGuide.style.top = (other.y + other.height) + 'px';
                                hGuide.style.display = 'block';
                                snappedY = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tepi Bawah');
                            } else if (Math.abs((tempTop + slot.height) - other.y) < SNAP_DIST) {
                                finalTop = other.y - slot.height;
                                hGuide.style.top = other.y + 'px';
                                hGuide.style.display = 'block';
                                snappedY = true;
                                highlightSlot(other.id, 'align');
                                showTooltip(rect, '<i class="fa-solid fa-magnet"></i> Sejajar Tepi Atas');
                            }
                        }
                    });
                    
                    // Spacing Gap Snapping
                    const activeOthers = slots.filter(o => !selectedSlotIds.includes(o.id));
                    
                    if (!snappedY && activeOthers.length >= 2) {
                        const sortedV = [...activeOthers].sort((a, b) => a.y - b.y);
                        for (let i = 1; i < sortedV.length; i++) {
                            const prev = sortedV[i-1];
                            const curr = sortedV[i];
                            const gap = curr.y - (prev.y + prev.height);
                            if (gap <= 0) continue;
                            
                            const targetBelow = curr.y + curr.height + gap;
                            if (Math.abs(tempTop - targetBelow) < SNAP_DIST) {
                                finalTop = targetBelow;
                                hGuide.style.top = targetBelow + 'px';
                                hGuide.style.display = 'block';
                                highlightSlot(prev.id, 'align');
                                highlightSlot(curr.id, 'align');
                                showGapIndicator('v', prev, curr, gap);
                                showGapIndicator('v', curr, { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, gap);
                                showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(gap)}px`);
                                snappedY = true;
                                break;
                            }
                            
                            const targetAbove = prev.y - gap - slot.height;
                            if (Math.abs(tempTop - targetAbove) < SNAP_DIST) {
                                finalTop = targetAbove;
                                hGuide.style.top = (targetAbove + slot.height) + 'px';
                                hGuide.style.display = 'block';
                                highlightSlot(prev.id, 'align');
                                highlightSlot(curr.id, 'align');
                                showGapIndicator('v', prev, curr, gap);
                                showGapIndicator('v', { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, prev, gap);
                                showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(gap)}px`);
                                snappedY = true;
                                break;
                            }
                        }
                        
                        if (!snappedY) {
                            let prevV = null;
                            let nextV = null;
                            activeOthers.forEach(o => {
                                if (o.y + o.height <= tempTop) {
                                    if (!prevV || (o.y + o.height) > (prevV.y + prevV.height)) prevV = o;
                                }
                                if (o.y >= tempTop + slot.height) {
                                    if (!nextV || o.y < nextV.y) nextV = o;
                                }
                            });
                            if (prevV && nextV) {
                                const gapAbove = tempTop - (prevV.y + prevV.height);
                                const gapBelow = nextV.y - (tempTop + slot.height);
                                if (Math.abs(gapAbove - gapBelow) < SNAP_DIST) {
                                    const idealGap = (nextV.y - (prevV.y + prevV.height) - slot.height) / 2;
                                    if (idealGap > 0) {
                                        finalTop = prevV.y + prevV.height + idealGap;
                                        hGuide.style.top = finalTop + 'px';
                                        hGuide.style.display = 'block';
                                        highlightSlot(prevV.id, 'align');
                                        highlightSlot(nextV.id, 'align');
                                        showGapIndicator('v', prevV, { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, idealGap);
                                        showGapIndicator('v', { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, nextV, idealGap);
                                        showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(idealGap)}px`);
                                        snappedY = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!snappedX && activeOthers.length >= 2) {
                        const sortedH = [...activeOthers].sort((a, b) => a.x - b.x);
                        for (let i = 1; i < sortedH.length; i++) {
                            const prev = sortedH[i-1];
                            const curr = sortedH[i];
                            const gap = curr.x - (prev.x + prev.width);
                            if (gap <= 0) continue;
                            
                            const targetRight = curr.x + curr.width + gap;
                            if (Math.abs(tempLeft - targetRight) < SNAP_DIST) {
                                finalLeft = targetRight;
                                vGuide.style.left = targetRight + 'px';
                                vGuide.style.display = 'block';
                                highlightSlot(prev.id, 'align');
                                highlightSlot(curr.id, 'align');
                                showGapIndicator('h', prev, curr, gap);
                                showGapIndicator('h', curr, { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, gap);
                                showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(gap)}px`);
                                snappedX = true;
                                break;
                            }
                            
                            const targetLeft = prev.x - gap - slot.width;
                            if (Math.abs(tempLeft - targetLeft) < SNAP_DIST) {
                                finalLeft = targetLeft;
                                vGuide.style.left = (targetLeft + slot.width) + 'px';
                                vGuide.style.display = 'block';
                                highlightSlot(prev.id, 'align');
                                highlightSlot(curr.id, 'align');
                                showGapIndicator('h', prev, curr, gap);
                                showGapIndicator('h', { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, prev, gap);
                                showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(gap)}px`);
                                snappedX = true;
                                break;
                            }
                        }
                        
                        if (!snappedX) {
                            let prevH = null;
                            let nextH = null;
                            activeOthers.forEach(o => {
                                if (o.x + o.width <= tempLeft) {
                                    if (!prevH || (o.x + o.width) > (prevH.x + prevH.width)) prevH = o;
                                }
                                if (o.x >= tempLeft + slot.width) {
                                    if (!nextH || o.x < nextH.x) nextH = o;
                                }
                            });
                            if (prevH && nextH) {
                                const gapLeft = tempLeft - (prevH.x + prevH.width);
                                const gapRight = nextH.x - (tempLeft + slot.width);
                                if (Math.abs(gapLeft - gapRight) < SNAP_DIST) {
                                    const idealGap = (nextH.x - (prevH.x + prevH.width) - slot.width) / 2;
                                    if (idealGap > 0) {
                                        finalLeft = prevH.x + prevH.width + idealGap;
                                        vGuide.style.left = finalLeft + 'px';
                                        vGuide.style.display = 'block';
                                        highlightSlot(prevH.id, 'align');
                                        highlightSlot(nextH.id, 'align');
                                        showGapIndicator('h', prevH, { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, idealGap);
                                        showGapIndicator('h', { x: finalLeft, y: finalTop, width: slot.width, height: slot.height }, nextH, idealGap);
                                        showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Jarak Sama: ${Math.round(idealGap)}px`);
                                        snappedX = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!snappedX && !snappedY) {
                        showTooltip(rect, `<i class="fa-solid fa-location-crosshairs"></i> X: ${Math.round(finalLeft)}, Y: ${Math.round(finalTop)}`);
                    }
                    
                    const deltaX = finalLeft - dragStartPositions[slot.id].x;
                    const deltaY = finalTop - dragStartPositions[slot.id].y;
                    
                    selectedSlotIds.forEach(id => {
                        const s = slots.find(item => item.id === id);
                        if (!s || !dragStartPositions[id]) return;
                        
                        let sLeft = dragStartPositions[id].x + deltaX;
                        let sTop = dragStartPositions[id].y + deltaY;
                        
                        s.x = Math.max(0, Math.min(sLeft, wrapperRect.width - s.width));
                        s.y = Math.max(0, Math.min(sTop, wrapperRect.height - s.height));
                        
                        const el = wrapper.querySelector(`.slot-rect[data-id="${id}"]`);
                        if (el) {
                            el.style.left = s.x + 'px';
                            el.style.top = s.y + 'px';
                        }
                    });
                    
                    updateSlotsDataField();
                }
                
                function onMouseUp() {
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    clearSmartSnapVisuals();
                    
                    // If no dragging occurred and modifier keys weren't used, select only this clicked item
                    if (!hasDragged && !e.ctrlKey && !e.shiftKey && isSelected) {
                        selectedSlotIds = [slot.id];
                        updateSelectionDOM();
                        updateSelectionButtons();
                    }
                    
                    if (hasDragged) {
                        saveHistoryState();
                    }
                }
                
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
            
            resizeHandle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const startX = e.clientX;
                const startY = e.clientY;
                const startWidth = slot.width;
                const startHeight = slot.height;
                
                const wrapperRect = wrapper.getBoundingClientRect();
                let hasResized = false;
                
                function onMouseMove(moveEvent) {
                    hasResized = true;
                    const dx = moveEvent.clientX - startX;
                    const dy = moveEvent.clientY - startY;
                    
                    let newWidth = startWidth + dx;
                    let newHeight = startHeight + dy;
                    
                    newWidth = Math.max(20, Math.min(newWidth, wrapperRect.width - slot.x));
                    newHeight = Math.max(20, Math.min(newHeight, wrapperRect.height - slot.y));
                    
                    const SNAP_DIST = 8;
                    let finalWidth = newWidth;
                    let finalHeight = newHeight;
                    
                    clearSmartSnapVisuals();
                    let snappedWidth = false;
                    let snappedHeight = false;
                    
                    slots.forEach(other => {
                        if (other.id === slot.id) return;
                        
                        if (Math.abs(newWidth - other.width) < SNAP_DIST) {
                            finalWidth = other.width;
                            highlightSlot(other.id, 'size');
                            snappedWidth = true;
                        }
                        if (Math.abs(newHeight - other.height) < SNAP_DIST) {
                            finalHeight = other.height;
                            highlightSlot(other.id, 'size');
                            snappedHeight = true;
                        }
                    });
                    
                    if (snappedWidth && snappedHeight) {
                        showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Ukuran Sama (${Math.round(finalWidth)}x${Math.round(finalHeight)}px)`);
                    } else if (snappedWidth) {
                        showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Lebar Sama (${Math.round(finalWidth)}px)`);
                    } else if (snappedHeight) {
                        showTooltip(rect, `<i class="fa-solid fa-ruler-combined"></i> Tinggi Sama (${Math.round(finalHeight)}px)`);
                    }
                    
                    slots.forEach(other => {
                        if (other.id === slot.id) return;
                        
                        if (Math.abs((slot.x + finalWidth) - (other.x + other.width)) < SNAP_DIST) {
                            finalWidth = other.x + other.width - slot.x;
                            vGuide.style.left = (other.x + other.width) + 'px';
                            vGuide.style.display = 'block';
                            highlightSlot(other.id, 'align');
                        }
                        if (Math.abs((slot.y + finalHeight) - (other.y + other.height)) < SNAP_DIST) {
                            finalHeight = other.y + other.height - slot.y;
                            hGuide.style.top = (other.y + other.height) + 'px';
                            hGuide.style.display = 'block';
                            highlightSlot(other.id, 'align');
                        }
                    });
                    
                    if (!snappedWidth && !snappedHeight && vGuide.style.display === 'none' && hGuide.style.display === 'none') {
                        showTooltip(rect, `<i class="fa-solid fa-up-down-left-right"></i> W: ${Math.round(finalWidth)}px, H: ${Math.round(finalHeight)}px`);
                    }
                    
                    slot.width = finalWidth;
                    slot.height = finalHeight;
                    rect.style.width = finalWidth + 'px';
                    rect.style.height = finalHeight + 'px';
                    updateSlotsDataField();
                }
                
                function onMouseUp() {
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    clearSmartSnapVisuals();
                    if (hasResized) {
                        saveHistoryState();
                    }
                }
                
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        }

        function updateSlotsDataField() {
            const previewImg = document.getElementById('canvasImg');
            if (!previewImg || previewImg.naturalWidth === 0) return;
            
            const previewW = previewImg.clientWidth;
            const previewH = previewImg.clientHeight;
            
            const naturalW = previewImg.naturalWidth;
            const naturalH = previewImg.naturalHeight;
            
            const scaleX = naturalW / previewW;
            const scaleY = naturalH / previewH;
            
            const scaledSlots = slots.map(slot => {
                return {
                    index: parseInt(slot.index),
                    x: Math.round(slot.x * scaleX),
                    y: Math.round(slot.y * scaleY),
                    width: Math.round(slot.width * scaleX),
                    height: Math.round(slot.height * scaleY)
                };
            });
            
            document.getElementById('slotsDataInput').value = JSON.stringify(scaledSlots);
        }

        function editFrame(frame) {
            showFrameEditor();
            
            // Populate form
            document.getElementById('editorFrameId').value = frame.id;
            document.getElementById('editorFrameId').readOnly = true; // Protect key modification
            document.getElementById('editorFrameName').value = frame.name;
            document.getElementById('editorLayoutType').value = frame.type;
            document.getElementById('editorEventId').value = frame.event_id || 'general';
            document.getElementById('editorBgColor').value = frame.background_color || '#ffffff';
            document.getElementById('editorBgColorPicker').value = frame.background_color || '#ffffff';
            
            // Allow not uploading a file when editing
            document.getElementById('editorFrameFile').required = false;
            document.getElementById('fileInputLabel').innerText = "Ganti Gambar Bingkai (Opsional, format PNG)";
            document.getElementById('isEditingInput').value = '1';
            document.getElementById('editorTitle').innerHTML = '<i class="fa-solid fa-image"></i> Edit Tata Letak Bingkai: ' + frame.name;
            
            // Load image on canvas
            document.getElementById('canvasEmptyPlaceholder').style.display = 'none';
            document.getElementById('canvasWrapper').style.display = 'block';
            document.getElementById('layerModeGroup').style.display = 'block';
            document.getElementById('autoDetectHolesGroup').style.display = 'block';
            setLayerMode('back');
            
            const img = document.getElementById('canvasImg');
            img.onload = function() {
                // Load existing slots and scale them down to preview container size
                const previewW = img.clientWidth;
                const previewH = img.clientHeight;
                const naturalW = img.naturalWidth;
                const naturalH = img.naturalHeight;
                
                const scaleX = naturalW / previewW;
                const scaleY = naturalH / previewH;
                
                slots = frame.slots.map(s => {
                    return {
                        id: 'slot_' + Math.random().toString(36).substr(2, 9),
                        index: s.index,
                        x: Math.round(s.x / scaleX),
                        y: Math.round(s.y / scaleY),
                        width: Math.round(s.width / scaleX),
                        height: Math.round(s.height / scaleY)
                    };
                });
                renderSlots();
                
                // Clear selection and history
                selectedSlotIds = [];
                updateSelectionButtons();
                history = [JSON.stringify(slots)];
                historyIndex = 0;
                updateUndoRedoButtons();
            };
            // Load original un-hollowed image if available to prevent designing on top of transparent holes
            const origUrl = frame.image_url.replace(frame.id + '.png', 'original_' + frame.id + '.png');
            const checkImg = new Image();
            checkImg.onload = function() {
                img.src = origUrl;
            };
            checkImg.onerror = function() {
                img.src = frame.image_url;
            };
            checkImg.src = origUrl;
        }

        // Global Event Listeners for Editor
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper = document.getElementById('canvasWrapper');
            if (wrapper) {
                wrapper.addEventListener('mousedown', function(e) {
                    if (e.target === wrapper || e.target === document.getElementById('canvasImg')) {
                        selectedSlotIds = [];
                        updateSelectionDOM();
                        updateSelectionButtons();
                    }
                });
            }
        });

        document.addEventListener('keydown', function(e) {
            const editorView = document.getElementById('frameEditorView');
            if (!editorView || editorView.style.display === 'none') {
                return;
            }
            
            // Bypass shortcuts if text inputs are active
            if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'SELECT' || document.activeElement.tagName === 'TEXTAREA')) {
                return;
            }
            
            const key = e.key.toLowerCase();
            
            // Undo: Ctrl+Z
            if (e.ctrlKey && key === 'z') {
                e.preventDefault();
                undo();
            }
            // Redo: Ctrl+Y or Ctrl+Shift+Z
            else if ((e.ctrlKey && key === 'y') || (e.ctrlKey && e.shiftKey && key === 'z')) {
                e.preventDefault();
                redo();
            }
            // Select All: Ctrl+A
            else if (e.ctrlKey && key === 'a') {
                e.preventDefault();
                selectedSlotIds = slots.map(s => s.id);
                updateSelectionDOM();
                updateSelectionButtons();
                showActionToast('Terpilih Semua Kotak');
            }
            // Delete selected slots
            else if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                deleteSelected();
            }
            // Nudge arrow keys
            else if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key)) {
                e.preventDefault();
                arrowKeyPressed = true;
                const step = e.shiftKey ? 5 : 1;
                nudgeSelected(e.key, step);
            }
        });

        document.addEventListener('keyup', function(e) {
            if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(e.key.toLowerCase())) {
                if (arrowKeyPressed) {
                    arrowKeyPressed = false;
                    saveHistoryState();
                }
            }
        });
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
                } else if (status === 'frame_saved' || status === 'frame_deleted' || status === 'frame_error') {
                    activeTab = 'frames';
                } else {
                    activeTab = localStorage.getItem('active_admin_tab') || 'dashboard';
                }
            }
            
            switchTab(activeTab);
        });
    </script>
</body>
</html>
