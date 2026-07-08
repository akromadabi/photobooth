<?php
session_start();

$settingsFile = __DIR__ . '/settings.json';
$uploadDir = __DIR__ . '/uploads/';
$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';

// Load coupon helper logic
require_once __DIR__ . '/coupon_helper.php';

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
        "midtrans_sandbox_server_key" => "",
        "midtrans_sandbox_client_key" => "",
        "midtrans_production_server_key" => "",
        "midtrans_production_client_key" => "",
        "midtrans_environment" => "sandbox",
        "siapp_pay_token" => "",
        "siapp_pay_merchant_name" => "",
        "fal_key" => "",
        "app_theme" => "NEON_RED",
        "thermal_contrast" => 1.2,
        "thermal_brightness" => 10.0,
        "thermal_sharpness" => 0.4,
        "thermal_denoise" => true
    ];
    if (file_exists($file)) {
        $loaded = json_decode(file_get_contents($file), true);
        if (is_array($loaded)) {
            $merged = array_merge($defaults, $loaded);
            
            // Dynamic routing & backward compatibility
            if ($merged['midtrans_environment'] === 'production') {
                if (empty($merged['midtrans_production_client_key']) && !empty($merged['midtrans_client_key'])) {
                    $merged['midtrans_production_client_key'] = $merged['midtrans_client_key'];
                }
                if (empty($merged['midtrans_production_server_key']) && !empty($merged['midtrans_server_key'])) {
                    $merged['midtrans_production_server_key'] = $merged['midtrans_server_key'];
                }
                $merged['midtrans_client_key'] = $merged['midtrans_production_client_key'];
                $merged['midtrans_server_key'] = $merged['midtrans_production_server_key'];
            } else {
                if (empty($merged['midtrans_sandbox_client_key']) && !empty($merged['midtrans_client_key'])) {
                    $merged['midtrans_sandbox_client_key'] = $merged['midtrans_client_key'];
                }
                if (empty($merged['midtrans_sandbox_server_key']) && !empty($merged['midtrans_server_key'])) {
                    $merged['midtrans_sandbox_server_key'] = $merged['midtrans_server_key'];
                }
                $merged['midtrans_client_key'] = $merged['midtrans_sandbox_client_key'];
                $merged['midtrans_server_key'] = $merged['midtrans_sandbox_server_key'];
            }
            return $merged;
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
    
    // Convert palette/indexed image to truecolor if it is not truecolor
    if (!imageistruecolor($img)) {
        $w = imagesx($img);
        $h = imagesy($img);
        $truecolorImg = imagecreatetruecolor($w, $h);
        
        imagealphablending($truecolorImg, false);
        imagesavealpha($truecolorImg, true);
        
        imagecopy($truecolorImg, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);
        $img = $truecolorImg;
    } else {
        // Enable alpha transparency blend mode
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    
    // Check if the image already contains transparent pixels (to preserve custom transparent areas/stickers)
    $w = imagesx($img);
    $h = imagesy($img);
    $hasTransparency = false;
    $transparentCount = 0;
    for ($x = 0; $x < $w; $x += 4) {
        for ($y = 0; $y < $h; $y += 4) {
            $colorIndex = imagecolorat($img, $x, $y);
            $alpha = ($colorIndex >> 24) & 0x7F;
            if ($alpha > 0) {
                $transparentCount++;
                if ($transparentCount > 100) {
                    $hasTransparency = true;
                    break 2;
                }
            }
        }
    }
    
    if ($hasTransparency) {
        // The image already contains transparency (pre-hollowed with overlapping ornaments).
        // Skip auto-hollowing to preserve overlapping elements.
        imagedestroy($img);
        return;
    }
    
    // Define transparent color
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    
    foreach ($slots as $slot) {
        $sx = intval($slot['x']);
        $sy = intval($slot['y']);
        $sw = intval($slot['width']);
        $sh = intval($slot['height']);
        
        if ($sw <= 0 || $sh <= 0) continue;
        
        // Check if the slot area contains a significant amount of white/near-white pixels (> 5% of slot area)
        $whiteCount = 0;
        $totalChecked = 0;
        for ($px = $sx; $px < $sx + $sw; $px += 5) {
            for ($py = $sy; $py < $sy + $sh; $py += 5) {
                $totalChecked++;
                $rgb = imagecolorat($img, $px, $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r >= 240 && $g >= 240 && $b >= 240) {
                    $whiteCount++;
                }
            }
        }
        
        $isWhiteSlot = ($totalChecked > 0 && ($whiteCount / $totalChecked) > 0.05);
        
        if ($isWhiteSlot) {
            // Carve ONLY the white/near-white pixels to preserve colorful overlapping stickers
            for ($px = $sx; $px < $sx + $sw; $px++) {
                for ($py = $sy; $py < $sy + $sh; $py++) {
                    $rgb = imagecolorat($img, $px, $py);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    if ($r >= 240 && $g >= 240 && $b >= 240) {
                        imagesetpixel($img, $px, $py, $transparent);
                    }
                }
            }
        } else {
            // Fallback: Carve the entire slot rectangle to transparent
            imagefilledrectangle($img, $sx, $sy, $sx + $sw - 1, $sy + $sh - 1, $transparent);
        }
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
                --bg-color: #f8f7f4;
                --card-bg: #ffffff;
                --primary: #4f46e5;
                --primary-hover: #4338ca;
                --text-main: #1a1a1a;
                --text-muted: #666666;
                --border-color: #e8e5e0;
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
                color: var(--text-main);
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
                background-color: #faf9f6;
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
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
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
            .btn-apk {
                width: 100%;
                padding: 16px;
                font-size: 0.95rem;
                font-weight: 700;
                border-radius: 16px;
                border: none;
                cursor: pointer;
                background-color: #10b981;
                color: white;
                box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15);
                font-family: inherit;
                transition: all 0.25s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                text-decoration: none;
                box-sizing: border-box;
                margin-top: 16px;
            }
            .btn-apk:hover {
                background-color: #059669;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2);
            }
            .btn-apk:active {
                transform: translateY(0);
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
            
            <?php if (file_exists(__DIR__ . '/app-debug.apk')): ?>
                <div style="margin-top: 16px; border-top: 1px dashed rgba(226, 232, 240, 0.8); padding-top: 16px;">
                    <a href="app-debug.apk" download class="btn-apk">
                        <i class="fa-brands fa-android"></i> DOWNLOAD APK ANDROID
                    </a>
                </div>
            <?php endif; ?>
            
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
    
    // Read new separated keys
    $midtransSandboxServerKey = isset($_POST['midtrans_sandbox_server_key']) ? trim($_POST['midtrans_sandbox_server_key']) : '';
    $midtransSandboxClientKey = isset($_POST['midtrans_sandbox_client_key']) ? trim($_POST['midtrans_sandbox_client_key']) : '';
    $midtransProductionServerKey = isset($_POST['midtrans_production_server_key']) ? trim($_POST['midtrans_production_server_key']) : '';
    $midtransProductionClientKey = isset($_POST['midtrans_production_client_key']) ? trim($_POST['midtrans_production_client_key']) : '';
    
    $midtransEnv = isset($_POST['midtrans_environment']) ? $_POST['midtrans_environment'] : 'sandbox';
    
    // Active keys for backwards compatibility fallback
    if ($midtransEnv === 'production') {
        $midtransClientKey = $midtransProductionClientKey;
        $midtransServerKey = $midtransProductionServerKey;
    } else {
        $midtransClientKey = $midtransSandboxClientKey;
        $midtransServerKey = $midtransSandboxServerKey;
    }
    
    $thermalContrast = isset($_POST['thermal_contrast']) ? floatval($_POST['thermal_contrast']) : 1.2;
    $thermalBrightness = isset($_POST['thermal_brightness']) ? floatval($_POST['thermal_brightness']) : 10.0;
    $thermalSharpness = isset($_POST['thermal_sharpness']) ? floatval($_POST['thermal_sharpness']) : 0.4;
    $thermalDenoise = isset($_POST['thermal_denoise']) && $_POST['thermal_denoise'] == '1';

    $falKey = isset($_POST['fal_key']) ? trim($_POST['fal_key']) : '';
    $appTheme = isset($_POST['app_theme']) ? $_POST['app_theme'] : 'NEON_RED';
    $couponPromoText = isset($_POST['coupon_promo_text']) ? $_POST['coupon_promo_text'] : '';
    
    $siappPayToken = isset($_POST['siapp_pay_token']) ? trim($_POST['siapp_pay_token']) : '';
    $siappPayMerchantName = isset($_POST['siapp_pay_merchant_name']) ? trim($_POST['siapp_pay_merchant_name']) : '';
    
    $settings = [
        "admin_pin" => $newPin ? $newPin : '1234',
        "countdown_seconds" => $countdown,
        "total_shots" => $shots,
        "printer_type" => $printer,
        "use_biometric" => $biometric,
        "payment_mode" => $paymentMode,
        "midtrans_server_key" => $midtransServerKey,
        "midtrans_client_key" => $midtransClientKey,
        "midtrans_sandbox_server_key" => $midtransSandboxServerKey,
        "midtrans_sandbox_client_key" => $midtransSandboxClientKey,
        "midtrans_production_server_key" => $midtransProductionServerKey,
        "midtrans_production_client_key" => $midtransProductionClientKey,
        "midtrans_environment" => $midtransEnv,
        "siapp_pay_token" => $siappPayToken,
        "siapp_pay_merchant_name" => $siappPayMerchantName,
        "fal_key" => $falKey,
        "app_theme" => $appTheme,
        "coupon_promo_text" => $couponPromoText,
        "thermal_contrast" => $thermalContrast,
        "thermal_brightness" => $thermalBrightness,
        "thermal_sharpness" => $thermalSharpness,
        "thermal_denoise" => $thermalDenoise
    ];
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    
    // Handle APK file upload
    $apkUploaded = false;
    $apkError = '';
    if (isset($_FILES['apk_file']) && $_FILES['apk_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['apk_file']['tmp_name'];
        $fileName = $_FILES['apk_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'apk') {
            $destPath = __DIR__ . '/app-debug.apk';
            
            // To ensure files don't accumulate/pile up, we overwrite the existing app-debug.apk
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $apkUploaded = true;
            } else {
                $apkError = 'Gagal menyimpan file APK ke server.';
            }
        } else {
            $apkError = 'Format file tidak didukung. Harap upload file .apk.';
        }
    } else if (isset($_FILES['apk_file']) && $_FILES['apk_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errCode = $_FILES['apk_file']['error'];
        if ($errCode === UPLOAD_ERR_INI_SIZE) {
            $apkError = 'Ukuran file APK melebihi batas maksimum upload server (upload_max_filesize di php.ini).';
        } else if ($errCode === UPLOAD_ERR_FORM_SIZE) {
            $apkError = 'Ukuran file APK melebihi batas maksimum form.';
        } else {
            $apkError = 'Terjadi kesalahan upload APK (Kode: ' . $errCode . ').';
        }
    }

    if ($apkError) {
        header('Location: admin.php?status=saved&apk_error=' . urlencode($apkError));
    } else if ($apkUploaded) {
        header('Location: admin.php?status=saved&apk_status=uploaded');
    } else {
        header('Location: admin.php?status=saved');
    }
    exit;
}

// Action: Create Coupon
if (isset($_POST['action']) && $_POST['action'] === 'create_coupon') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: admin.php');
        exit;
    }
    
    $packageId = isset($_POST['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['package_id']) : 'any';
    $customCode = !empty($_POST['custom_code']) ? trim($_POST['custom_code']) : null;
    $isBulk = isset($_POST['is_bulk']) && $_POST['is_bulk'] == '1';
    $bulkQty = isset($_POST['bulk_quantity']) ? intval($_POST['bulk_quantity']) : 5;
    
    // Load packages in case "any" is selected to print all packages
    $packagesFile = __DIR__ . '/packages.json';
    $pkgs = [];
    if (file_exists($packagesFile)) {
        $pkgs = json_decode(file_get_contents($packagesFile), true);
    }
    if (!is_array($pkgs)) {
        $pkgs = [];
    }

    $targetPackages = [];
    if ($packageId === 'any') {
        foreach ($pkgs as $p) {
            $targetPackages[] = $p['id'];
        }
    } else {
        $targetPackages[] = $packageId;
    }

    if (empty($targetPackages)) {
        header("Location: admin.php?status=coupon_error&msg=" . urlencode("Tidak ada paket yang terdaftar.") . "#coupons");
        exit;
    }

    if ($isBulk) {
        $successCount = 0;
        $createdCodes = [];
        foreach ($targetPackages as $tgtPkgId) {
            for ($i = 0; $i < $bulkQty; $i++) {
                $res = createCoupon($tgtPkgId);
                if ($res['success']) {
                    $successCount++;
                    $createdCodes[] = $res['coupon']['code'];
                }
            }
        }
        $printCodes = implode(',', $createdCodes);
        header("Location: admin.php?status=bulk_created&count=$successCount&print_codes=" . urlencode($printCodes) . "#coupons");
        exit;
    } else {
        $successCount = 0;
        $createdCodes = [];
        $lastError = '';
        foreach ($targetPackages as $tgtPkgId) {
            $res = createCoupon($tgtPkgId, $customCode);
            if ($res['success']) {
                $successCount++;
                $createdCodes[] = $res['coupon']['code'];
            } else {
                $lastError = $res['message'];
            }
        }
        
        if ($successCount > 0) {
            $printCodes = implode(',', $createdCodes);
            if ($packageId === 'any') {
                header("Location: admin.php?status=bulk_created&count=$successCount&print_codes=" . urlencode($printCodes) . "#coupons");
            } else {
                header("Location: admin.php?status=coupon_created&print_code=" . $createdCodes[0] . "#coupons");
            }
            exit;
        } else {
            $errorMsg = urlencode($lastError ? $lastError : "Gagal membuat kupon.");
            header("Location: admin.php?status=coupon_error&msg=$errorMsg#coupons");
            exit;
        }
    }
}

// Action: Delete Coupon
if (isset($_GET['action']) && $_GET['action'] === 'delete_coupon') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: admin.php');
        exit;
    }
    
    $code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
    if ($code) {
        $coupons = loadCoupons();
        $updatedCoupons = [];
        $deleted = false;
        foreach ($coupons as $c) {
            if (strtoupper($c['code']) === $code) {
                $deleted = true;
                continue;
            }
            $updatedCoupons[] = $c;
        }
        if ($deleted) {
            saveCoupons($updatedCoupons);
            header("Location: admin.php?status=coupon_deleted#coupons");
            exit;
        }
    }
    header("Location: admin.php#coupons");
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

// Action: Save Event Frames (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'save_event_frames') {
    header('Content-Type: application/json');
    $eventId = $_POST['event_id'] ?? '';
    $allowedFrames = $_POST['allowed_frames'] ?? [];
    
    $configPath = __DIR__ . '/frames/config.json';
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        $found = false;
        if (isset($config['events'])) {
            foreach ($config['events'] as &$evt) {
                if ($evt['id'] === $eventId) {
                    $evt['allowed_frames'] = $allowedFrames;
                    $found = true;
                    break;
                }
            }
        }
        if ($found) {
            $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Config file tidak ditemukan']);
    }
    exit;
}

// Action: Start Event Rental (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'start_event_rental') {
    header('Content-Type: application/json');
    $eventId = $_POST['event_id'] ?? '';
    
    $configPath = __DIR__ . '/frames/config.json';
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        $found = false;
        $activeEvt = null;
        if (isset($config['events'])) {
            foreach ($config['events'] as &$evt) {
                if ($evt['id'] === $eventId) {
                    if (empty($evt['rental_start_time'])) {
                        $durationHours = intval($evt['rental_duration_hours'] ?? 1);
                        $durationMinutes = intval($evt['rental_duration_minutes'] ?? 0);
                        $totalSeconds = ($durationHours * 3600) + ($durationMinutes * 60);
                        
                        $startTime = date('Y-m-d H:i:s');
                        $endTime = date('Y-m-d H:i:s', time() + $totalSeconds);
                        
                        $evt['rental_start_time'] = $startTime;
                        $evt['rental_end_time'] = $endTime;
                    }
                    $activeEvt = $evt;
                    $found = true;
                    break;
                }
            }
        }
        if ($found && $activeEvt) {
            $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
            echo json_encode([
                'success' => true,
                'rental_start_time' => $activeEvt['rental_start_time'],
                'rental_end_time' => $activeEvt['rental_end_time']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Config file tidak ditemukan']);
    }
    exit;
}

// Action: Save Event
if (isset($_POST['action']) && $_POST['action'] === 'save_event') {
    $eventName = trim($_POST['event_name']);
    $eventCode = strtoupper(trim($_POST['event_code']));
    $eventId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['event_id'] ?? '');
    $eventSubtitle = trim($_POST['event_subtitle'] ?? '');
    $eventHashtag = trim($_POST['event_hashtag'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $eventLocation = trim($_POST['event_location'] ?? '');
    $primaryColor = trim($_POST['primary_color'] ?? '#e63946');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#ffffff');
    
    $configPath = __DIR__ . '/frames/config.json';
    $config = ['events' => [], 'frames' => []];
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
    }
    
    // Auto-generate Event ID if empty (not editing)
    if (empty($eventId)) {
        // Generate slug from event_name
        $eventId = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '_', $eventName)));
        if (empty($eventId)) {
            $eventId = 'event_' . time();
        }
        
        // Collision check
        $baseId = $eventId;
        $counter = 1;
        $exists = true;
        while ($exists) {
            $exists = false;
            if (isset($config['events'])) {
                foreach ($config['events'] as $evt) {
                    if ($evt['id'] === $eventId) {
                        $exists = true;
                        break;
                    }
                }
            }
            if ($exists) {
                $eventId = $baseId . '_' . $counter;
                $counter++;
            }
        }
    }
    
    $eventIndex = -1;
    if (isset($config['events'])) {
        foreach ($config['events'] as $idx => $evt) {
            if ($evt['id'] === $eventId) {
                $eventIndex = $idx;
                break;
            }
        }
    } else {
        $config['events'] = [];
    }
    
    $logoUrl = "";
    if ($eventIndex !== -1) {
        $logoUrl = isset($config['events'][$eventIndex]['logo_url']) ? $config['events'][$eventIndex]['logo_url'] : "";
    }
    
    if (isset($_FILES['event_logo']) && $_FILES['event_logo']['error'] === UPLOAD_ERR_OK) {
        if (!file_exists(__DIR__ . '/frames/logos')) {
            mkdir(__DIR__ . '/frames/logos', 0777, true);
        }
        $fileTmpPath = $_FILES['event_logo']['tmp_name'];
        $fileName = $_FILES['event_logo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (in_array($fileExtension, ['png', 'jpg', 'jpeg'])) {
            $newFileName = 'logo_' . $eventId . '.' . $fileExtension;
            $destPath = __DIR__ . '/frames/logos/' . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $logoUrl = 'frames/logos/' . $newFileName;
            }
        }
    }
    
    $billingType = trim($_POST['billing_type'] ?? 'PAY_PER_SESSION');
    $rentalDurationHours = intval($_POST['rental_duration_hours'] ?? 1);
    $rentalDurationMinutes = intval($_POST['rental_duration_minutes'] ?? 0);
    $resetRentalTimer = isset($_POST['reset_rental_timer']) && $_POST['reset_rental_timer'] == '1';
    $limitPrintsPerSession = intval($_POST['limit_prints_per_session'] ?? 1);
    $allowedFrames = $_POST['allowed_frames'] ?? [];
    $allowedPackages = $_POST['allowed_packages'] ?? [];
    
    $rentalStartTime = '';
    $rentalEndTime = '';
    
    if ($eventIndex !== -1 && !$resetRentalTimer) {
        $rentalStartTime = $config['events'][$eventIndex]['rental_start_time'] ?? '';
        $rentalEndTime = $config['events'][$eventIndex]['rental_end_time'] ?? '';
    }

    $newEvent = [
        'id' => $eventId,
        'name' => $eventName,
        'code' => $eventCode,
        'subtitle' => $eventSubtitle,
        'hashtag' => $eventHashtag,
        'logo_url' => $logoUrl,
        'primary_color' => $primaryColor,
        'secondary_color' => $secondaryColor,
        'event_date' => $eventDate,
        'event_location' => $eventLocation,
        'billing_type' => $billingType,
        'rental_duration_hours' => $rentalDurationHours,
        'rental_duration_minutes' => $rentalDurationMinutes,
        'rental_start_time' => $rentalStartTime,
        'rental_end_time' => $rentalEndTime,
        'limit_prints_per_session' => $limitPrintsPerSession,
        'allowed_frames' => $allowedFrames,
        'allowed_packages' => $allowedPackages
    ];
    
    if ($eventIndex !== -1) {
        $config['events'][$eventIndex] = $newEvent;
    } else {
        $config['events'][] = $newEvent;
    }
    
    $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    
    header('Location: admin.php?status=event_saved#events');
    exit;
}

// Action: Delete Event
if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['id'])) {
    $eventId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    $configPath = __DIR__ . '/frames/config.json';
    if ($eventId && $eventId !== 'general') {
        $config = ['events' => [], 'frames' => []];
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
        }
        
        $updatedEvents = [];
        foreach ($config['events'] as $evt) {
            if ($evt['id'] === $eventId) {
                if (!empty($evt['logo_url'])) {
                    $logoFile = __DIR__ . '/' . $evt['logo_url'];
                    if (file_exists($logoFile)) {
                        unlink($logoFile);
                    }
                }
            } else {
                $updatedEvents[] = $evt;
            }
        }
        
        $config['events'] = $updatedEvents;
        $config['version'] = isset($config['version']) ? intval($config['version']) + 1 : 1;
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        
        header('Location: admin.php?status=event_deleted#events');
        exit;
    }
}

// Action: Save Frame Layout Config
$configPath = __DIR__ . '/frames/config.json';
if (isset($_POST['action']) && $_POST['action'] === 'save_frame') {
    $frameId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['frame_id'] ?? '');
    $frameName = trim($_POST['frame_name']);
    $layoutType = $_POST['layout_type']; // 'strip', 'grid', 'postcard'
    $eventId = $_POST['event_id'];
    $bgColor = trim($_POST['background_color']);
    $slotsJson = isset($_POST['slots_data']) ? $_POST['slots_data'] : '[]';
    $slots = json_decode($slotsJson, true);
    
    // Load config first to resolve ID and check collisions
    $config = ['events' => [], 'frames' => []];
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
    }
    
    if (empty($frameId)) {
        // Generate slug from frame_name
        $frameId = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '_', $frameName)));
        if (empty($frameId)) {
            $frameId = 'frame_' . time();
        }
        
        // Collision check
        $baseId = $frameId;
        $counter = 1;
        $exists = true;
        while ($exists) {
            $exists = false;
            foreach ($config['frames'] as $f) {
                if ($f['id'] === $frameId) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $frameId = $baseId . '_' . $counter;
                $counter++;
            }
        }
    }
    
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
        $category = isset($_POST['category']) ? trim($_POST['category']) : 'Classic';
        $isDynamic = isset($_POST['is_dynamic']) && $_POST['is_dynamic'] == '1' ? true : false;
        
        $dynamicElements = null;
        if ($isDynamic) {
            $dynamicElements = [
                'logo' => null,
                'texts' => []
            ];
            
            if (isset($_POST['dynamic_logo_enable']) && $_POST['dynamic_logo_enable'] == '1') {
                $dynamicElements['logo'] = [
                    'x' => intval($_POST['dynamic_logo_x'] ?? 300),
                    'y' => intval($_POST['dynamic_logo_y'] ?? 1720),
                    'width' => intval($_POST['dynamic_logo_w'] ?? 120),
                    'height' => intval($_POST['dynamic_logo_h'] ?? 120),
                    'align' => $_POST['dynamic_logo_align'] ?? 'center'
                ];
            }
            
            if (isset($_POST['dynamic_name_enable']) && $_POST['dynamic_name_enable'] == '1') {
                $dynamicElements['texts'][] = [
                    'type' => 'event_name',
                    'x' => intval($_POST['dynamic_name_x'] ?? 300),
                    'y' => intval($_POST['dynamic_name_y'] ?? 1860),
                    'font_size' => intval($_POST['dynamic_name_size'] ?? 28),
                    'font_style' => $_POST['dynamic_name_style'] ?? 'bold',
                    'color' => $_POST['dynamic_name_color'] ?? '#ffffff',
                    'align' => $_POST['dynamic_name_align'] ?? 'center'
                ];
            }
            
            if (isset($_POST['dynamic_subtitle_enable']) && $_POST['dynamic_subtitle_enable'] == '1') {
                $dynamicElements['texts'][] = [
                    'type' => 'event_subtitle',
                    'x' => intval($_POST['dynamic_subtitle_x'] ?? 300),
                    'y' => intval($_POST['dynamic_subtitle_y'] ?? 1900),
                    'font_size' => intval($_POST['dynamic_subtitle_size'] ?? 20),
                    'font_style' => $_POST['dynamic_subtitle_style'] ?? 'normal',
                    'color' => $_POST['dynamic_subtitle_color'] ?? '#cccccc',
                    'align' => $_POST['dynamic_subtitle_align'] ?? 'center'
                ];
            }
            
            if (isset($_POST['dynamic_hashtag_enable']) && $_POST['dynamic_hashtag_enable'] == '1') {
                $dynamicElements['texts'][] = [
                    'type' => 'event_hashtag',
                    'x' => intval($_POST['dynamic_hashtag_x'] ?? 300),
                    'y' => intval($_POST['dynamic_hashtag_y'] ?? 1930),
                    'font_size' => intval($_POST['dynamic_hashtag_size'] ?? 16),
                    'font_style' => $_POST['dynamic_hashtag_style'] ?? 'italic',
                    'color' => $_POST['dynamic_hashtag_color'] ?? '#aaaaaa',
                    'align' => $_POST['dynamic_hashtag_align'] ?? 'center'
                ];
            }
        }

        $printFlows = isset($_POST['print_flows']) && is_array($_POST['print_flows']) ? $_POST['print_flows'] : [];
        $printFlows = array_map(function($f) {
            return preg_replace('/[^A-Z0-9_-]/', '', $f);
        }, $printFlows);

        $newFrame = [
            "id" => $frameId,
            "name" => $frameName,
            "type" => $layoutType,
            "event_id" => $eventId,
            "width" => intval($imageWidth),
            "height" => intval($imageHeight),
            "background_color" => $bgColor ? $bgColor : "#ffffff",
            "image_url" => $targetFileUrl,
            "slots" => $slots,
            "category" => $category,
            "print_flows" => $printFlows,
            "is_dynamic" => $isDynamic,
            "dynamic_elements" => $dynamicElements
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
            --bg-main: #f8f7f4;
            --bg-card: #ffffff;
            --bg-sidebar: #ffffff;
            --border-color: #e8e5e0;
            --text-main: #1a1a1a;
            --text-muted: #666666;
            
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

        @keyframes grid-flow {
            0% { background-position: 0 0; }
            100% { background-position: 40px 40px; }
        }
        body {
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            background-color: var(--bg-main);
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(79, 70, 229, 0.015) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(79, 70, 229, 0.015) 1px, transparent 1px);
            animation: grid-flow 30s linear infinite;
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
            width: 240px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 32px;
        }

        .sidebar-brand .logo {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -1px;
            color: var(--text-main);
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
            background-color: #f5f3f0;
        }

        .nav-item.active {
            color: #ffffff;
            background-color: var(--primary);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.15);
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
            margin-left: 240px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top Header */
        .top-header {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .page-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: -0.2px;
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
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
            padding: clamp(16px, 2vw, 24px);
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Tab panels */
        .tab-pane {
            display: none;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-pane.active {
            display: block;
        }

        .tab-pane.active > * {
            opacity: 0;
            animation: fadeInUp 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .tab-pane.active > *:nth-child(1) { animation-delay: 0.04s; }
        .tab-pane.active > *:nth-child(2) { animation-delay: 0.08s; }
        .tab-pane.active > *:nth-child(3) { animation-delay: 0.12s; }
        .tab-pane.active > *:nth-child(4) { animation-delay: 0.16s; }
        .tab-pane.active > *:nth-child(5) { animation-delay: 0.20s; }
        .tab-pane.active > *:nth-child(6) { animation-delay: 0.24s; }
        .tab-pane.active > *:nth-child(7) { animation-delay: 0.28s; }

        /* Metrics grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }

        .metric-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.01), 0 1px 2px -1px rgba(0, 0, 0, 0.01);
            transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .metric-card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(79, 70, 229, 0.2);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        .metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .metric-icon.indigo { background-color: var(--primary-light); color: var(--primary); }
        .metric-icon.emerald { background-color: var(--success-light); color: var(--success-dark); }
        .metric-icon.rose { background-color: var(--danger-light); color: var(--danger-dark); }
        .metric-icon.amber { background-color: var(--warning-light); color: var(--warning-dark); }

        .metric-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .metric-card-bottom-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
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

        .card-section {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
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
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: normal;
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
            background-color: #faf9f6;
            border: 1px solid var(--border-color);
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
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: #faf9f6;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .checkbox-container:hover {
            border-color: var(--primary);
            background-color: #faf9f6;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
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
            background-color: #fbfbfa;
            border-color: #c3c0b9;
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
            border: 1px solid var(--border-color);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.8rem;
        }

        .custom-table th {
            background-color: #faf9f6;
            color: var(--text-muted);
            font-weight: 600;
            padding: 10px 14px;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr:hover {
            background-color: #fbfbfa;
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
            max-height: calc(100vh - 48px);
            overflow-y: auto;
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
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: normal;
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
        /* Events Management Styling */
        .events-grid-layout {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 16px;
        }
        .event-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .event-card:hover {
            box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.08);
            border-color: rgba(226, 232, 240, 0.8);
            transform: translateY(-2px);
        }
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #3b82f6);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .event-card:hover::before {
            opacity: 1;
        }
        .event-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .event-card-logo {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
        }
        .event-card-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .event-card-logo i {
            font-size: 1.5rem;
            color: #94a3b8;
        }
        .event-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
        }
        .event-card-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .event-detail-item i {
            color: #64748b;
            width: 16px;
            text-align: center;
        }
        .event-badge-code {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .event-colors-preview {
            display: flex;
            gap: 6px;
        }
        .event-color-pill {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .event-card-actions {
            display: flex;
            gap: 8px;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
            margin-top: auto;
        }
        .event-card-actions .btn-secondary,
        .event-card-actions .btn-danger {
            flex: 1;
            padding: 8px 12px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 8px;
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
        }
        .frame-card-preview-admin {
            width: 100%;
            height: 250px;
            background-color: transparent;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: visible;
            border: none;
            margin-bottom: 12px;
        }
        .frame-card-preview-admin img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.15));
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
        
        .frame-row-preview {
            width: 55px;
            height: 70px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: #faf9f6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            margin: 0 auto;
        }
        .frame-row-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.2s ease;
        }
        .frame-row-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .frame-row-preview:hover {
            transform: scale(1.05);
            border-color: var(--primary) !important;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }
        .frame-row-preview:hover .frame-row-preview-overlay {
            opacity: 1;
        }
        .frame-row-preview:hover img {
            transform: scale(1.08);
        }
        .color-palette-picker {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: none;
            cursor: pointer;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .color-palette-picker:hover {
            transform: scale(1.1);
            border-color: var(--primary);
        }
        .color-palette-picker::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        .color-palette-picker::-webkit-color-swatch {
            border: none;
            border-radius: 50%;
        }
        .color-palette-picker::-moz-color-swatch {
            border: none;
            border-radius: 50%;
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
            transition: all 0.15s ease-in-out;
        }
        .slot-rect-close:hover {
            background: #dc2626;
            transform: scale(1.18);
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
            transition: all 0.15s ease-in-out;
        }
        .slot-rect-resize:hover {
            transform: scale(1.22);
        }
        /* Dynamic Elements Dummy Widgets */
        .dyn-dummy-rect {
            position: absolute;
            border-style: dashed;
            border-width: 2px;
            box-sizing: border-box;
            user-select: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: move;
            border-radius: 6px;
            z-index: 15;
            padding: 4px;
            text-align: center;
        }
        .dyn-dummy-logo {
            border-color: #0ea5e9;
            background: rgba(14, 165, 233, 0.15);
            color: #0284c7;
        }
        .dyn-dummy-logo .slot-rect-resize {
            background: #0ea5e9 !important;
            border-color: white !important;
        }
        .dyn-dummy-text {
            /* Since X is the center coordinate, we want left to define the horizontal center,
               using transform to shift it horizontally by -50% */
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .dyn-dummy-name {
            border-color: #8b5cf6;
            background: rgba(139, 92, 246, 0.15);
            color: #6d28d9;
        }
        .dyn-dummy-subtitle {
            border-color: #ec4899;
            background: rgba(236, 72, 153, 0.15);
            color: #be185d;
        }
        .dyn-dummy-hashtag {
            border-color: #f97316;
            background: rgba(249, 115, 22, 0.15);
            color: #c2410c;
        }
        .dyn-dummy-meta-label {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 3px;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(15, 23, 42, 0.08);
            pointer-events: none;
        }
        .dyn-dummy-text-content {
            font-family: 'Outfit', sans-serif;
            line-height: 1.1;
            pointer-events: none;
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

        /* Zoom Frame Styling */
        .frame-card-preview-admin {
            position: relative;
            cursor: pointer;
        }
        .frame-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            opacity: 0;
            backdrop-filter: blur(4px);
            border-radius: 10px;
            z-index: 10;
        }
        .frame-card-preview-admin:hover .frame-preview-overlay {
            opacity: 1;
        }
        .frame-preview-overlay i {
            font-size: 1.6rem;
            color: #ffffff;
        }
        .frame-preview-overlay span {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .zoom-wrapper {
            position: relative;
            display: inline-block;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            overflow: hidden;
            background-color: transparent;
        }
        .zoom-bg-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }
        .zoom-bg-container.checkerboard {
            background-image: linear-gradient(45deg, #1e293b 25%, transparent 25%), 
                              linear-gradient(-45deg, #1e293b 25%, transparent 25%), 
                              linear-gradient(45deg, transparent 75%, #1e293b 75%), 
                              linear-gradient(-45deg, transparent 75%, #1e293b 75%);
            background-size: 16px 16px;
            background-position: 0 0, 0 8px, 8px -8px, -8px 0px;
            background-color: #0f172a;
        }
        .zoom-frame-img {
            position: relative;
            z-index: 2;
            height: 420px;
            width: auto;
            max-width: 100%;
            display: block;
            object-fit: contain;
            pointer-events: none;
            transition: height 0.1s ease;
        }
        .zoom-mockup-slot {
            position: absolute;
            transition: all 0.1s ease;
        }
        .zoom-overlay-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            overflow: hidden;
            pointer-events: none;
        }
        .zoom-dynamic-logo {
            position: absolute;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-family: 'Outfit', sans-serif;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .zoom-dynamic-text {
            position: absolute;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Ensure input fields fit container to prevent overflow */
        .form-input, .form-select {
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Bottom Navigation Bar for Mobile */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 70px;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-top: 1px solid #f1f5f9;
            z-index: 1000;
            justify-content: space-around;
            align-items: center;
            padding: 0 10px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.04);
            border-radius: 20px 20px 0 0;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 700;
            gap: 4px;
            flex: 1;
            height: 100%;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }

        .bottom-nav-item:hover, .bottom-nav-item.active {
            color: var(--primary);
        }

        .bottom-nav-item .icon {
            font-size: 1.25rem;
            transition: transform 0.2s ease;
        }
        
        .bottom-nav-item:active .icon {
            transform: scale(0.9);
        }

        /* Elevated Middle Button (More) */
        .bottom-nav-item.middle-btn {
            position: relative;
            top: -16px;
            background-color: var(--primary);
            color: white;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            flex: none;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
            border: 4px solid var(--bg-main);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .bottom-nav-item.middle-btn:hover, .bottom-nav-item.middle-btn.active {
            color: white;
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.45);
        }

        /* Bottom Sheet Drawer */
        .bottom-sheet-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1001;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .bottom-sheet-overlay.show {
            display: block;
            opacity: 1;
        }

        .bottom-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            border-radius: 28px 28px 0 0;
            padding: 24px 24px 40px 24px;
            z-index: 1002;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.08);
            max-width: 600px;
            margin: 0 auto;
        }

        .bottom-sheet.show {
            transform: translateY(0);
        }

        .bottom-sheet-handle {
            width: 44px;
            height: 5px;
            background-color: #cbd5e1;
            border-radius: 99px;
            margin: 0 auto 20px auto;
            cursor: pointer;
        }

        .bottom-sheet-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 10px;
        }

        .bottom-sheet-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 8px;
            border-radius: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
            user-select: none;
        }

        .bottom-sheet-item:hover, .bottom-sheet-item.active {
            background-color: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary);
        }

        .bottom-sheet-item .icon {
            font-size: 1.4rem;
        }

        .bottom-sheet-item.logout {
            border: 1px solid rgba(239, 68, 68, 0.1);
        }

        .bottom-sheet-item.logout:hover {
            background-color: var(--danger-light);
            color: var(--danger);
            border-color: var(--danger);
        }

        /* Responsive Layout Rules */
        @media (max-width: 1024px) {
            /* 1. Navigation & Main Containers */
            .sidebar {
                display: none !important;
            }

            .main-wrapper {
                margin-left: 0 !important;
                padding-bottom: 80px !important;
                width: 100%;
            }

            .bottom-nav {
                display: flex !important;
            }
            
            .top-header {
                padding: 10px 14px !important;
            }
            
            .content-body {
                padding: 10px 8px !important;
            }

            .card-section {
                padding: 14px 12px !important;
                border-radius: 12px !important;
                margin-bottom: 12px !important;
            }

            .card-header {
                margin-bottom: 12px !important;
                padding-bottom: 8px !important;
            }

            /* 2. Typography & Core Scaling */
            body {
                font-size: 12.5px !important;
            }

            .page-title {
                font-size: 1.05rem !important;
            }

            .current-time {
                display: none !important; /* Hide time on mobile to save space */
            }

            .profile-info {
                display: none !important; /* Hide profile name text on mobile */
            }

            .header-right {
                gap: 10px !important;
            }

            /* 3. Forms & Grid Stacking */
            .form-grid {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }

            .form-group {
                margin-bottom: 8px !important;
            }
            
            .form-group label {
                font-size: 0.65rem !important;
            }

            .form-group span, .form-group div {
                font-size: 0.75rem !important;
            }

            .form-input, .form-select {
                padding: 7px 12px !important;
                font-size: 0.85rem !important;
                border-radius: 8px !important;
            }

            .checkbox-container {
                padding: 7px 12px !important;
                border-radius: 8px !important;
                gap: 8px !important;
            }

            .checkbox-label {
                font-size: 0.75rem !important;
            }

            .btn-primary, .btn-secondary, .btn-danger {
                width: 100%;
                padding: 7px 12px !important;
                font-size: 0.8rem !important;
                border-radius: 6px !important;
                gap: 4px !important;
            }

            /* Card headers buttons and anchors overrides */
            .card-header a, .card-header button {
                width: auto !important;
                padding: 5px 10px !important;
                font-size: 0.7rem !important;
                border-radius: 6px !important;
                display: inline-flex !important;
            }

            .alert-status {
                padding: 8px 12px !important;
                font-size: 0.8rem !important;
                margin-bottom: 12px !important;
            }

            /* 4. Dashboard Metrics Grid */
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
                margin-bottom: 14px !important;
            }
            
            .metric-card {
                padding: 8px 10px !important;
                border-radius: 10px !important;
                gap: 8px !important;
            }
            
            .metric-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 0.9rem !important;
                border-radius: 6px !important;
            }
            
            .metric-value {
                font-size: 1rem !important;
            }
            
            .metric-label {
                font-size: 0.55rem !important;
                letter-spacing: 0.1px !important;
            }

            /* 5. Tables & Badges */
            .custom-table th, .custom-table td {
                padding: 6px 8px !important; /* Shrunk padding even more */
            }
            
            .custom-table td {
                font-size: 0.72rem !important;
                word-break: break-all !important; /* Force word wrap on long IDs */
            }
            
            .custom-table th {
                font-size: 0.65rem !important;
            }

            /* Compact Buttons inside Tables */
            .custom-table .btn-primary,
            .custom-table .btn-secondary,
            .custom-table .btn-danger,
            .custom-table a,
            .custom-table button {
                width: auto !important;
                display: inline-flex !important;
                padding: 4px 8px !important;
                font-size: 0.65rem !important;
                margin: 2px !important;
                border-radius: 4px !important;
                gap: 4px !important;
                box-shadow: none !important;
            }

            /* For action column text links if any */
            .custom-table td a {
                text-decoration: none !important;
            }

            /* Hide button text on mobile/tablet to keep tables ultra-compact */
            .custom-table .btn-text {
                display: none !important;
            }

            /* Avoid overflow in status row values */
            .status-row .val {
                word-break: break-all !important;
                max-width: 60%;
                text-align: right;
            }

            .badge {
                padding: 2px 5px !important;
                font-size: 0.6rem !important;
                border-radius: 4px !important;
                gap: 2px !important;
            }

            .card-title {
                font-size: 0.95rem !important;
                gap: 6px !important;
            }

            /* 6. Photo Logs Gallery Grid */
            .history-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)) !important;
                gap: 8px !important;
            }
            
            .history-card {
                padding: 6px 6px 8px 6px !important;
                border-radius: 8px !important;
                gap: 6px !important;
            }
            
            .history-id {
                font-size: 0.65rem !important;
            }
            
            .history-frame {
                font-size: 0.5rem !important;
                padding: 1px 3px !important;
            }
            
            .history-time {
                font-size: 0.55rem !important;
            }

            /* 7. Kiosk Frames Manager Grid */
            .frames-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
                gap: 10px !important;
            }
            
            .frame-card-admin {
                padding: 6px !important;
                border-radius: 8px !important;
            }
            
            .frame-card-preview-admin {
                height: 120px !important;
                margin-bottom: 6px !important;
            }
            
            .frame-card-title {
                font-size: 0.75rem !important;
            }
            
            .frame-card-tag {
                font-size: 0.6rem !important;
            }
            
            .frame-card-actions {
                margin-top: 4px !important;
                gap: 4px !important;
            }
            
            .frame-card-actions button, .frame-card-actions a {
                padding: 4px !important;
                font-size: 0.65rem !important;
                border-radius: 6px !important;
            }
        }

        /* Tier 2: Extremely Small Screen Overrides (max-width: 480px) */
        @media (max-width: 480px) {
            body {
                font-size: 11.5px !important;
            }

            .content-body {
                padding: 6px 4px !important;
            }

            .card-section {
                padding: 10px 8px !important;
                border-radius: 8px !important;
                margin-bottom: 8px !important;
            }

            .form-input, .form-select {
                padding: 6px 8px !important;
                font-size: 0.8rem !important;
            }

            .custom-table th, .custom-table td {
                padding: 6px 8px !important;
            }
            
            .custom-table td {
                font-size: 0.72rem !important;
            }
        }
    </style>
    <!-- Include QRCode Generator Library for Web -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
</head>
<body>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-brand" style="display: flex; align-items: center; gap: 12px; padding: 12px 0 24px 0;">
                <div class="brand-icon" style="width: 40px; height: 40px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);">CS</div>
                <div class="brand-text">
                    <div style="font-weight: 800; font-size: 1.15rem; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2;">Creative <span style="color: var(--primary);">Studio</span></div>
                    <div style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-top: 2px;">Kiosk Controller</div>
                </div>
            </div>
            <nav class="sidebar-nav" style="overflow-y: auto;">
                <div class="sidebar-nav-group-title" style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin: 15px 0 8px 12px;">Monitoring</div>
                <a href="#" class="nav-item active" data-tab="dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> Dashboard
                </a>
                <a href="#" class="nav-item" data-tab="queue">
                    <span class="nav-icon"><i class="fa-solid fa-hourglass-half"></i></span> Kiosk Queue
                </a>

                <div class="sidebar-nav-group-title" style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin: 20px 0 8px 12px;">Visual & Event</div>
                <a href="#" class="nav-item" data-tab="frames">
                    <span class="nav-icon"><i class="fa-solid fa-image"></i></span> Bingkai Kiosk
                </a>
                <a href="#" class="nav-item" data-tab="events">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Manajemen Event
                </a>

                <div class="sidebar-nav-group-title" style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin: 20px 0 8px 12px;">Konfigurasi Kiosk</div>
                <a href="#" class="nav-item" data-tab="settings">
                    <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span> Kiosk Settings
                </a>
                <a href="#" class="nav-item" data-tab="packages">
                    <span class="nav-icon"><i class="fa-solid fa-box-archive"></i></span> Manage Packages
                </a>
                <a href="#" class="nav-item" data-tab="coupons">
                    <span class="nav-icon"><i class="fa-solid fa-ticket"></i></span> Kelola Kupon
                </a>
            </nav>
            <div class="sidebar-footer" style="padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">AD</div>
                    <div style="display: flex; flex-direction: column;">
                        <div style="font-weight: 700; font-size: 0.8rem; color: var(--text-main); line-height: 1.2;">Administrator</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 500;">Super Admin</div>
                    </div>
                </div>
                <a href="admin.php?action=logout" style="color: var(--text-muted); font-size: 1.1rem; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.color='var(--danger)'; this.style.background='var(--danger-light)';" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent';">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="main-wrapper">
            <!-- Top Sticky Header -->
            <header class="top-header" style="background: transparent; border-bottom: none; backdrop-filter: none; padding: 16px 32px 8px 32px;">
                <div class="header-left">
                    <h1 class="page-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); letter-spacing: -0.5px;">Dashboard</h1>
                </div>
                <div class="header-right" style="gap: 16px; align-items: center;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(79, 70, 229, 0.2); background: rgba(79, 70, 229, 0.05); color: var(--primary); font-size: 0.75rem; font-weight: 600; padding: 6px 14px; border-radius: 20px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--primary); display: inline-block;"></span>
                        Role: Super Admin
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Admin: Administrator</span>
                    <a href="#" style="color: var(--text-muted); font-size: 1.15rem; transition: color 0.2s; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: #ffffff; border: 1px solid var(--border-color);"><i class="fa-regular fa-bell"></i></a>
                    <div class="avatar" style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);">AD</div>
                </div>
            </header>

            <!-- Scrollable Workspace Body -->
            <main class="content-body">
          
                <!-- DYNAMIC TAB PANES -->

                <!-- TAB: Dashboard -->
                <div class="tab-pane active" id="tab-dashboard">
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                        <div class="alert-status alert-deleted" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                            <span>Selected photo session permanently removed from disk!</span>
                        </div>
                    <?php endif; ?>
                    <!-- Premium Gradient Banner (Undangan Digital Style) -->
                    <div class="dashboard-banner" style="background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%); border-radius: 14px; padding: 16px 24px; color: white; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; box-shadow: 0 8px 16px rgba(79, 70, 229, 0.08); position: relative; overflow: hidden;">
                        <div style="position: absolute; right: -20px; bottom: -20px; font-size: 6rem; color: rgba(255,255,255,0.04); pointer-events: none;"><i class="fa-solid fa-chart-line"></i></div>
                        <div style="display: flex; align-items: center; gap: 16px; position: relative; z-index: 1;">
                            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; backdrop-filter: blur(10px);"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <div>
                                <h2 style="font-weight: 700; font-size: 1.15rem; letter-spacing: -0.3px; margin-bottom: 2px;">Dashboard Kiosk Controller</h2>
                                <p style="font-size: 0.78rem; color: rgba(255,255,255,0.85); font-weight: 500;">Sistem Monitoring & Kelola Visual Event Photo Booth Creative Studio</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; position: relative; z-index: 1;">
                            <span style="background: rgba(255,255,255,0.15); font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 14px; backdrop-filter: blur(5px); display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-camera"></i> <?php echo $photosCount; ?> Sesi</span>
                            <span style="background: rgba(255,255,255,0.15); font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 14px; backdrop-filter: blur(5px); display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-calendar-days"></i> <?php echo count($eventsList); ?> Event</span>
                            <span style="background: rgba(255,255,255,0.15); font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 14px; backdrop-filter: blur(5px); display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-image"></i> <?php echo count($framesList); ?> Bingkai</span>
                        </div>
                    </div>

                    <!-- Upper Metrics Row (Vertical Cards with Bottom Indicator Bars) -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                <span class="metric-label">Total Sesi Foto</span>
                                <div class="metric-icon indigo"><i class="fa-solid fa-camera"></i></div>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="metric-value"><?php echo $photosCount; ?> Sesi</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;">Semua sesi foto terdaftar</div>
                            </div>
                            <div class="metric-card-bottom-bar" style="background: var(--primary);"></div>
                        </div>
                        
                        <div class="metric-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                <span class="metric-label">Sesi Hari Ini</span>
                                <div class="metric-icon emerald"><i class="fa-solid fa-bolt"></i></div>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="metric-value"><?php echo $todayPhotosCount; ?> Sesi</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;">Sesi baru pada hari ini</div>
                            </div>
                            <div class="metric-card-bottom-bar" style="background: var(--success);"></div>
                        </div>

                        <div class="metric-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                <span class="metric-label">Memori Terpakai</span>
                                <div class="metric-icon rose"><i class="fa-solid fa-floppy-disk"></i></div>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="metric-value"><?php echo $formattedSize; ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;">Penyimpanan direktori foto</div>
                            </div>
                            <div class="metric-card-bottom-bar" style="background: var(--danger);"></div>
                        </div>

                        <div class="metric-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                <span class="metric-label">Printer Aktif</span>
                                <div class="metric-icon amber"><i class="fa-solid fa-print"></i></div>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="metric-value"><?php echo htmlspecialchars($settings['printer_type']); ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;">Perangkat printer kiosk aktif</div>
                            </div>
                            <div class="metric-card-bottom-bar" style="background: var(--warning);"></div>
                        </div>
                    </div>
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
                                    <span class="val" style="color: var(--primary); font-weight: 600;">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Printer Active</span>
                                    <span class="val badge badge-info"><?php echo htmlspecialchars($settings['printer_type']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Sesi Aktif</span>
                                    <span class="val" style="font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars(substr($queueState['active_session_id'], 0, 8)) . '...' : 'None'; ?></span>
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
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'saved'): ?>
                        <div class="alert-status alert-saved" style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 16px 20px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem; color: #047857;"></i>
                                <span style="font-weight: 600;">Remote kiosk configuration successfully updated and synced!</span>
                            </div>
                            <?php if (isset($_GET['apk_status']) && $_GET['apk_status'] === 'uploaded'): ?>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #047857; margin-left: 26px; margin-top: 4px; border-top: 1px dashed rgba(4, 120, 87, 0.2); padding-top: 6px; width: 100%;">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>File APK baru berhasil diupload dan memperbarui aplikasi Android!</span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_GET['apk_error']) && !empty($_GET['apk_error'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #b91c1c; margin-left: 26px; margin-top: 4px; border-top: 1px dashed rgba(185, 28, 28, 0.2); padding-top: 6px; width: 100%;">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span>Peringatan APK: <?php echo htmlspecialchars($_GET['apk_error']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-sliders"></i> Pengaturan Kontrol Kiosk</div>
                        </div>
                        <form action="admin.php" method="POST" enctype="multipart/form-data">
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
                                        <option value="NONE" <?php echo $settings['printer_type'] === 'NONE' ? 'selected' : ''; ?>>Digital (Tanpa Struk)</option>
                                        <option value="THERMAL" <?php echo $settings['printer_type'] === 'THERMAL' ? 'selected' : ''; ?>>Thermal (Struk/Kasir)</option>
                                        <option value="COLOR" <?php echo $settings['printer_type'] === 'COLOR' ? 'selected' : ''; ?>>Warna (Sistem)</option>
                                        <option value="AUTO" <?php echo $settings['printer_type'] === 'AUTO' ? 'selected' : ''; ?>>Auto (Warna & Thermal)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="use_biometric">Autentikasi Biometrik Tablet</label>
                                    <select id="use_biometric" name="use_biometric" class="form-select">
                                        <option value="1" <?php echo $settings['use_biometric'] ? 'selected' : ''; ?>>Aktif (Sidik Jari)</option>
                                        <option value="0" <?php echo !$settings['use_biometric'] ? 'selected' : ''; ?>>Nonaktif (PIN)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="app_theme">Tema Tampilan Kiosk</label>
                                    <select id="app_theme" name="app_theme" class="form-select">
                                        <option value="NEON_RED" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'NEON_RED') ? 'selected' : ''; ?>>Neon Red</option>
                                        <option value="CUTE_PASTEL" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'CUTE_PASTEL') ? 'selected' : ''; ?>>Cute Pastel</option>
                                        <option value="CUTE_NARA" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'CUTE_NARA') ? 'selected' : ''; ?>>Cute Nara (Pinky Flower)</option>
                                        <option value="LUXURY_GOLD" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'LUXURY_GOLD') ? 'selected' : ''; ?>>Luxury Gold</option>
                                        <option value="MINIMAL_MODERN" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'MINIMAL_MODERN') ? 'selected' : ''; ?>>Minimal Modern</option>
                                        <option value="COMIC_POP" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'COMIC_POP') ? 'selected' : ''; ?>>Comic Pop-Art</option>
                                        <option value="DAGO_ORANGE" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'DAGO_ORANGE') ? 'selected' : ''; ?>>Dago Orange Kiosk</option>
                                        <option value="CREATIVE_DYNAMIC" <?php echo (isset($settings['app_theme']) && $settings['app_theme'] === 'CREATIVE_DYNAMIC') ? 'selected' : ''; ?>>Creative Dynamic (Glowing)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                     <label for="payment_mode">Mode Pembayaran</label>
                                    <select id="payment_mode" name="payment_mode" class="form-select" onchange="toggleMidtransFields(this.value)">
                                        <option value="dummy" <?php echo $settings['payment_mode'] === 'dummy' ? 'selected' : ''; ?>>Simulasi (Dummy)</option>
                                        <option value="midtrans" <?php echo $settings['payment_mode'] === 'midtrans' ? 'selected' : ''; ?>>Midtrans (Gerbang Pembayaran)</option>
                                        <option value="siapp_pay" <?php echo $settings['payment_mode'] === 'siapp_pay' ? 'selected' : ''; ?>>SiappPay (Gerbang Pembayaran QRIS)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label for="coupon_promo_text">Teks Promosi Kupon (Thermal Struk)</label>
                                    <textarea id="coupon_promo_text" name="coupon_promo_text" class="form-input" rows="4" style="font-family: monospace; resize: vertical;"><?php echo htmlspecialchars($settings['coupon_promo_text'] ?? ''); ?></textarea>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">Teks promosi statis yang akan tercetak di bagian bawah struk kupon thermal.</span>
                                </div>
                            </div>

                            <!-- Midtrans Config Section -->
                            <div id="midtrans-settings-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px; <?php echo $settings['payment_mode'] === 'midtrans' ? '' : 'display: none;'; ?>">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-solid fa-credit-card"></i> Pengaturan Midtrans</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="midtrans_environment">Environment Midtrans</label>
                                        <select id="midtrans_environment" name="midtrans_environment" class="form-select" onchange="toggleMidtransEnvFields(this.value)">
                                            <option value="sandbox" <?php echo $settings['midtrans_environment'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Uji Coba)</option>
                                            <option value="production" <?php echo $settings['midtrans_environment'] === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Sandbox Key Fields -->
                                    <div class="form-group sandbox-group" style="<?php echo $settings['midtrans_environment'] === 'sandbox' ? '' : 'display: none;'; ?>">
                                        <label for="midtrans_sandbox_client_key">Sandbox Client Key</label>
                                        <input type="text" id="midtrans_sandbox_client_key" name="midtrans_sandbox_client_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_sandbox_client_key']); ?>" placeholder="SB-Mid-client-...">
                                    </div>

                                    <div class="form-group sandbox-group" style="grid-column: span 2; <?php echo $settings['midtrans_environment'] === 'sandbox' ? '' : 'display: none;'; ?>">
                                        <label for="midtrans_sandbox_server_key">Sandbox Server Key</label>
                                        <input type="password" id="midtrans_sandbox_server_key" name="midtrans_sandbox_server_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_sandbox_server_key']); ?>" placeholder="SB-Mid-server-...">
                                    </div>

                                    <!-- Production Key Fields -->
                                    <div class="form-group production-group" style="<?php echo $settings['midtrans_environment'] === 'production' ? '' : 'display: none;'; ?>">
                                        <label for="midtrans_production_client_key">Production Client Key</label>
                                        <input type="text" id="midtrans_production_client_key" name="midtrans_production_client_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_production_client_key']); ?>" placeholder="Mid-client-...">
                                    </div>

                                    <div class="form-group production-group" style="grid-column: span 2; <?php echo $settings['midtrans_environment'] === 'production' ? '' : 'display: none;'; ?>">
                                        <label for="midtrans_production_server_key">Production Server Key</label>
                                        <input type="password" id="midtrans_production_server_key" name="midtrans_production_server_key" class="form-input" value="<?php echo htmlspecialchars($settings['midtrans_production_server_key']); ?>" placeholder="Mid-server-...">
                                    </div>
                                </div>
                            </div>

                            <!-- SiappPay Config Section -->
                            <div id="siapppay-settings-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px; <?php echo $settings['payment_mode'] === 'siapp_pay' ? '' : 'display: none;'; ?>">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-solid fa-credit-card"></i> Pengaturan SiappPay</h4>
                                <div class="form-grid">
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="siapp_pay_token">API Token SiappPay</label>
                                        <input type="password" id="siapp_pay_token" name="siapp_pay_token" class="form-input" value="<?php echo htmlspecialchars($settings['siapp_pay_token'] ?? ''); ?>" placeholder="SiappPaySecretToken...">
                                    </div>
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="siapp_pay_merchant_name">Nama Merchant (Opsional)</label>
                                        <input type="text" id="siapp_pay_merchant_name" name="siapp_pay_merchant_name" class="form-input" value="<?php echo htmlspecialchars($settings['siapp_pay_merchant_name'] ?? ''); ?>" placeholder="Creative Studio">
                                    </div>
                                </div>
                            </div>

                            <script>
                                function toggleMidtransEnvFields(env) {
                                    const sandboxGroups = document.querySelectorAll('.sandbox-group');
                                    const productionGroups = document.querySelectorAll('.production-group');
                                    const paymentMode = document.getElementById('payment_mode').value;

                                    if (env === 'sandbox') {
                                        sandboxGroups.forEach(el => {
                                            el.style.display = ''; // Revert to stylesheet default display style
                                            const input = el.querySelector('input');
                                            if (paymentMode === 'midtrans') {
                                                input.setAttribute('required', 'required');
                                            }
                                        });
                                        productionGroups.forEach(el => {
                                            el.style.display = 'none';
                                            const input = el.querySelector('input');
                                            input.removeAttribute('required');
                                        });
                                    } else {
                                        sandboxGroups.forEach(el => {
                                            el.style.display = 'none';
                                            const input = el.querySelector('input');
                                            input.removeAttribute('required');
                                        });
                                        productionGroups.forEach(el => {
                                            el.style.display = ''; // Revert to stylesheet default display style
                                            const input = el.querySelector('input');
                                            if (paymentMode === 'midtrans') {
                                                input.setAttribute('required', 'required');
                                            }
                                        });
                                    }
                                }

                                function toggleMidtransFields(mode) {
                                    const midtransSection = document.getElementById('midtrans-settings-section');
                                    const siapppaySection = document.getElementById('siapppay-settings-section');
                                    
                                    if (mode === 'midtrans') {
                                        midtransSection.style.display = 'block';
                                        siapppaySection.style.display = 'none';
                                        const env = document.getElementById('midtrans_environment').value;
                                        toggleMidtransEnvFields(env);
                                        document.getElementById('siapp_pay_token').removeAttribute('required');
                                    } else if (mode === 'siapp_pay') {
                                        midtransSection.style.display = 'none';
                                        siapppaySection.style.display = 'block';
                                        document.getElementById('siapp_pay_token').setAttribute('required', 'required');
                                        document.querySelectorAll('.sandbox-group input, .production-group input').forEach(input => {
                                            input.removeAttribute('required');
                                        });
                                    } else {
                                        midtransSection.style.display = 'none';
                                        siapppaySection.style.display = 'none';
                                        document.getElementById('siapp_pay_token').removeAttribute('required');
                                        document.querySelectorAll('.sandbox-group input, .production-group input').forEach(input => {
                                            input.removeAttribute('required');
                                        });
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

                            <!-- Thermal Print Settings & Live Preview Section -->
                            <div id="thermal-print-settings-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px;">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-solid fa-print"></i> Pengaturan Kualitas Cetak Thermal (Struk)</h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                                    <!-- Controls -->
                                    <div class="form-grid" style="display: flex; flex-direction: column; gap: 16px; min-width: 280px;">
                                        <div class="form-group">
                                            <label for="thermal_contrast">Kontras Gambar: <span id="contrast_val">1.2</span></label>
                                            <input type="range" id="thermal_contrast" name="thermal_contrast" min="0.5" max="3.0" step="0.1" value="<?php echo floatval($settings['thermal_contrast'] ?? 1.2); ?>" class="form-input" style="width: 100%; height: auto; padding: 4px 0;" oninput="updateThermalPreview()">
                                            <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 4px;">Meningkatkan kontras gradasi. Nilai tinggi mempertegas gambar monokrom.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="thermal_brightness">Kecerahan (Brightness): <span id="brightness_val">+10.0</span></label>
                                            <input type="range" id="thermal_brightness" name="thermal_brightness" min="-50" max="50" step="1" value="<?php echo floatval($settings['thermal_brightness'] ?? 10.0); ?>" class="form-input" style="width: 100%; height: auto; padding: 4px 0;" oninput="updateThermalPreview()">
                                            <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 4px;">Mencerahkan bayangan gelap agar detail cetak tidak buram/hitam pekat.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="thermal_sharpness">Ketajaman (Sharpness): <span id="sharpness_val">0.4</span></label>
                                            <input type="range" id="thermal_sharpness" name="thermal_sharpness" min="0.0" max="2.0" step="0.1" value="<?php echo floatval($settings['thermal_sharpness'] ?? 0.4); ?>" class="form-input" style="width: 100%; height: auto; padding: 4px 0;" oninput="updateThermalPreview()">
                                            <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 4px;">Memperjelas tepi garis. Nilai tinggi memperjelas teks, namun menambah grain.</small>
                                        </div>
                                        
                                        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                                            <input type="checkbox" id="thermal_denoise" name="thermal_denoise" value="1" <?php echo (!isset($settings['thermal_denoise']) || $settings['thermal_denoise']) ? 'checked' : ''; ?> onchange="updateThermalPreview()" style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                                            <label for="thermal_denoise" style="margin-bottom: 0; cursor: pointer; font-weight: 500;">Aktifkan Pengurangan Noise (Median Filter)</label>
                                        </div>
                                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-left: 28px; margin-top: -6px;">Menyaring noise bintik halus (salt-and-pepper) dari tangkapan kamera.</small>
                                    </div>
                                    
                                    <!-- Live Preview Wrapper -->
                                    <div style="display: flex; flex-direction: column; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px; min-width: 250px;">
                                        <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 16px; color: var(--primary);"><i class="fa-solid fa-eye"></i> Simulasi Hasil Cetak Struk</div>
                                        
                                        <!-- Thermal Receipt Simulation Container -->
                                        <div style="background: #fdfdfb; border: 1px solid #d3d3d3; width: 176px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); display: flex; flex-direction: column; align-items: center; padding: 18px 12px 24px 12px; position: relative;">
                                            <!-- Jagged tear lines at bottom using SVG -->
                                            <div style="position: absolute; bottom: -10px; left: -1px; width: calc(100% + 2px); height: 10px; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 10\" preserveAspectRatio=\"none\"><polygon points=\"0,0 4,10 8,0 12,10 16,0 20,10 24,0 24,10 0,10\" fill=\"%23fdfdfb\"/><path d=\"M0,0 L4,10 L8,0 L12,10 L16,0 L20,10 L24,0\" stroke=\"%23d3d3d3\" stroke-width=\"1\" fill=\"none\"/></svg>'); background-size: 16px 10px; background-repeat: repeat-x;"></div>
                                            
                                            <!-- Simulated Dithered Preview Canvas -->
                                            <canvas id="ditheredCanvas" width="150" height="200" style="width: 150px; height: 200px; border: 1px solid #eaeaea; background: #fff; image-rendering: pixelated;"></canvas>
                                            
                                            <!-- Label/Receipt Footer Info -->
                                            <div style="font-family: monospace; font-size: 8px; color: #444; margin-top: 12px; text-align: center; line-height: 1.3; font-weight: bold; letter-spacing: 0.5px;">
                                                * DITHER PREVIEW *<br>
                                                Jeprat-jepret Kiosk App
                                            </div>
                                        </div>
                                        
                                        <!-- Image Source Selector -->
                                        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 10px; width: 100%;">
                                            <div style="display: flex; gap: 8px; justify-content: center; width: 100%;">
                                                <button type="button" class="btn-secondary" onclick="loadSamplePortrait()" style="font-size: 0.8rem; padding: 6px 12px; cursor: pointer; border: 1px solid var(--border-color);"><i class="fa-solid fa-face-smile"></i> Sampel</button>
                                                <?php
                                                // Find last photo in uploads to preview
                                                $uploadsPattern = __DIR__ . '/uploads/*_photo.png';
                                                $photos = glob($uploadsPattern);
                                                $lastPhotoUrl = '';
                                                if (!empty($photos)) {
                                                    usort($photos, function($a, $b) {
                                                        return filemtime($b) - filemtime($a);
                                                    });
                                                    $lastPhotoUrl = 'uploads/' . basename($photos[0]);
                                                }
                                                ?>
                                                <button type="button" class="btn-secondary" onclick="loadLastSessionPhoto('<?php echo $lastPhotoUrl; ?>')" style="font-size: 0.8rem; padding: 6px 12px; cursor: pointer; border: 1px solid var(--border-color); <?php echo empty($lastPhotoUrl) ? 'display:none;' : ''; ?>" id="btn-load-last-session"><i class="fa-solid fa-camera"></i> Foto Sesi</button>
                                            </div>
                                            <div style="text-align: center; font-size: 0.75rem; color: var(--text-muted); padding: 8px; border: 1px dashed var(--border-color); border-radius: 6px; background: rgba(0,0,0,0.1);">
                                                Drag & Drop foto di sini, atau:<br>
                                                <input type="file" id="preview_upload" accept="image/*" style="margin-top: 6px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer;" onchange="handlePreviewUpload(this.files[0])">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                                // Global reference for the source image
                                let sourceImgData = null; 

                                function updateThermalPreview() {
                                    const contrast = parseFloat(document.getElementById('thermal_contrast').value);
                                    const brightness = parseFloat(document.getElementById('thermal_brightness').value);
                                    const sharpness = parseFloat(document.getElementById('thermal_sharpness').value);
                                    const denoise = document.getElementById('thermal_denoise').checked;

                                    document.getElementById('contrast_val').innerText = contrast.toFixed(1);
                                    document.getElementById('brightness_val').innerText = (brightness >= 0 ? '+' : '') + brightness.toFixed(1);
                                    document.getElementById('sharpness_val').innerText = sharpness.toFixed(1);

                                    if (!sourceImgData) return;

                                    const canvas = document.getElementById('ditheredCanvas');
                                    const ctx = canvas.getContext('2d');
                                    
                                    // 1. Draw source image scaled to preview size
                                    ctx.drawImage(sourceImgData, 0, 0, canvas.width, canvas.height);
                                    
                                    // 2. Read pixels
                                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                    const data = imgData.data;
                                    const width = canvas.width;
                                    const height = canvas.height;

                                    // Create a grayscale buffer
                                    const grayData = new Int32Array(width * height);

                                    // Step 1: Grayscale + Contrast + Brightness
                                    for (let i = 0; i < data.length; i += 4) {
                                        const r = data[i];
                                        const g = data[i+1];
                                        const b = data[i+2];
                                        const luma = 0.299 * r + 0.587 * g + 0.114 * b;
                                        
                                        // Contrast & Brightness adjustment
                                        let adjusted = (luma - 128.0) * contrast + 128.0 + brightness;
                                        grayData[i/4] = Math.min(255, Math.max(0, Math.round(adjusted)));
                                    }

                                    // Step 2: Denoise via Median Filter 3x3
                                    const denoisedData = new Int32Array(width * height);
                                    if (denoise) {
                                        const neighbors = new Int32Array(9);
                                        for (let y = 0; y < height; y++) {
                                            for (let x = 0; x < width; x++) {
                                                const idx = y * width + x;
                                                if (y === 0 || y === height - 1 || x === 0 || x === width - 1) {
                                                    denoisedData[idx] = grayData[idx];
                                                    continue;
                                                }
                                                neighbors[0] = grayData[idx - width - 1];
                                                neighbors[1] = grayData[idx - width];
                                                neighbors[2] = grayData[idx - width + 1];
                                                neighbors[3] = grayData[idx - 1];
                                                neighbors[4] = grayData[idx];
                                                neighbors[5] = grayData[idx + 1];
                                                neighbors[6] = grayData[idx + width - 1];
                                                neighbors[7] = grayData[idx + width];
                                                neighbors[8] = grayData[idx + width + 1];
                                                
                                                // Sort
                                                neighbors.sort();
                                                denoisedData[idx] = neighbors[4];
                                            }
                                        }
                                    } else {
                                        denoisedData.set(grayData);
                                    }

                                    // Step 3: Sharpness filter
                                    const sharpenedData = new Int32Array(width * height);
                                    if (sharpness > 0) {
                                        for (let y = 0; y < height; y++) {
                                            for (let x = 0; x < width; x++) {
                                                const idx = y * width + x;
                                                if (y === 0 || y === height - 1 || x === 0 || x === width - 1) {
                                                    sharpenedData[idx] = denoisedData[idx];
                                                    continue;
                                                }
                                                const center = denoisedData[idx];
                                                const top = denoisedData[idx - width];
                                                const bottom = denoisedData[idx + width];
                                                const left = denoisedData[idx - 1];
                                                const right = denoisedData[idx + 1];

                                                const sharpVal = center + sharpness * (4 * center - top - bottom - left - right);
                                                sharpenedData[idx] = Math.min(255, Math.max(0, Math.round(sharpVal)));
                                            }
                                        }
                                    } else {
                                        sharpenedData.set(denoisedData);
                                    }

                                    // Step 4: Floyd-Steinberg Dithering
                                    const ditherBuffer = new Float32Array(sharpenedData);
                                    for (let y = 0; y < height; y++) {
                                        for (let x = 0; x < width; x++) {
                                            const idx = y * width + x;
                                            const oldPixel = ditherBuffer[idx];
                                            const newPixel = oldPixel < 128 ? 0 : 255;
                                            ditherBuffer[idx] = newPixel;
                                            
                                            const error = oldPixel - newPixel;
                                            
                                            // Diffuse error
                                            if (x + 1 < width) {
                                                ditherBuffer[idx + 1] += error * (7 / 16);
                                            }
                                            if (y + 1 < height) {
                                                if (x - 1 >= 0) {
                                                    ditherBuffer[idx + width - 1] += error * (3 / 16);
                                                }
                                                ditherBuffer[idx + width] += error * (5 / 16);
                                                if (x + 1 < width) {
                                                    ditherBuffer[idx + width + 1] += error * (1 / 16);
                                                }
                                            }
                                        }
                                    }

                                    // Write back to canvas image data
                                    for (let idx = 0; idx < ditherBuffer.length; idx++) {
                                        const val = ditherBuffer[idx] < 128 ? 0 : 255;
                                        const i = idx * 4;
                                        data[i] = val;     // R
                                        data[i+1] = val;   // G
                                        data[i+2] = val;   // B
                                        data[i+3] = 255;   // Alpha
                                    }

                                    ctx.putImageData(imgData, 0, 0);
                                }

                                function loadSamplePortrait() {
                                    const tempCanvas = document.createElement('canvas');
                                    tempCanvas.width = 150;
                                    tempCanvas.height = 200;
                                    const tCtx = tempCanvas.getContext('2d');
                                    
                                    const grad = tCtx.createRadialGradient(75, 80, 10, 75, 100, 110);
                                    grad.addColorStop(0, '#ffffff');
                                    grad.addColorStop(1, '#666666');
                                    tCtx.fillStyle = grad;
                                    tCtx.fillRect(0, 0, 150, 200);

                                    tCtx.fillStyle = '#333333';
                                    tCtx.beginPath();
                                    tCtx.ellipse(75, 200, 50, 40, 0, 0, Math.PI, true);
                                    tCtx.fill();

                                    tCtx.fillStyle = '#e8beac';
                                    tCtx.beginPath();
                                    tCtx.arc(75, 90, 42, 0, 2 * Math.PI);
                                    tCtx.fill();

                                    tCtx.fillStyle = '#333333';
                                    tCtx.beginPath();
                                    tCtx.arc(60, 85, 4, 0, 2 * Math.PI);
                                    tCtx.arc(90, 85, 4, 0, 2 * Math.PI);
                                    tCtx.fill();

                                    tCtx.strokeStyle = '#c49a88';
                                    tCtx.lineWidth = 2.5;
                                    tCtx.beginPath();
                                    tCtx.moveTo(75, 82);
                                    tCtx.lineTo(75, 96);
                                    tCtx.lineTo(79, 96);
                                    tCtx.stroke();

                                    tCtx.strokeStyle = '#d63031';
                                    tCtx.lineWidth = 3;
                                    tCtx.beginPath();
                                    tCtx.arc(75, 105, 10, 0, Math.PI, false);
                                    tCtx.stroke();

                                    tCtx.fillStyle = '#1e272e';
                                    tCtx.beginPath();
                                    tCtx.arc(75, 75, 42, Math.PI, 2*Math.PI);
                                    tCtx.fill();
                                    tCtx.beginPath();
                                    tCtx.moveTo(33, 75);
                                    tCtx.lineTo(33, 110);
                                    tCtx.lineTo(40, 110);
                                    tCtx.lineTo(40, 75);
                                    tCtx.moveTo(117, 75);
                                    tCtx.lineTo(117, 110);
                                    tCtx.lineTo(110, 110);
                                    tCtx.lineTo(110, 75);
                                    tCtx.fill();

                                    for (let n = 0; n < 350; n++) {
                                        const nx = Math.floor(Math.random() * 150);
                                        const ny = Math.floor(Math.random() * 200);
                                        tCtx.fillStyle = Math.random() > 0.5 ? '#000000' : '#ffffff';
                                        tCtx.fillRect(nx, ny, 1, 1);
                                    }

                                    sourceImgData = tempCanvas;
                                    updateThermalPreview();
                                }

                                function loadLastSessionPhoto(url) {
                                    if (!url) return;
                                    const img = new Image();
                                    img.crossOrigin = 'anonymous';
                                    img.onload = function() {
                                        sourceImgData = img;
                                        updateThermalPreview();
                                    };
                                    img.src = url;
                                }

                                function handlePreviewUpload(file) {
                                    if (!file) return;
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        const img = new Image();
                                        img.onload = function() {
                                            sourceImgData = img;
                                            updateThermalPreview();
                                        };
                                        img.src = e.target.result;
                                    };
                                    reader.readAsDataURL(file);
                                }

                                document.addEventListener('DOMContentLoaded', () => {
                                    const ditherSection = document.getElementById('thermal-print-settings-section');
                                    if (ditherSection) {
                                        ditherSection.addEventListener('dragover', (e) => {
                                            e.preventDefault();
                                        });
                                        ditherSection.addEventListener('drop', (e) => {
                                            e.preventDefault();
                                            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                                                handlePreviewUpload(e.dataTransfer.files[0]);
                                            }
                                        });
                                    }
                                    loadSamplePortrait();
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
                            
                            <!-- APK Upload Section -->
                            <div id="apk-upload-section" style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 24px;">
                                <h4 style="margin-bottom: 16px; color: var(--primary); font-size: 1rem;"><i class="fa-brands fa-android"></i> Hubungkan / Upload Aplikasi Android (APK)</h4>
                                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="apk_file">Upload APK Baru</label>
                                        <input type="file" id="apk_file" name="apk_file" class="form-input" accept=".apk" style="padding: 6px 12px; height: auto;">
                                        <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; display: block;">
                                            Upload file `.apk` baru ke server. File ini akan menggantikan file `app-debug.apk` yang lama tanpa menumpuk.
                                        </small>
                                        <?php 
                                        $apkPath = __DIR__ . '/app-debug.apk';
                                        if (file_exists($apkPath)): 
                                            $apkSizeMB = round(filesize($apkPath) / (1024 * 1024), 2);
                                            $apkTime = date("d M Y H:i:s", filemtime($apkPath));
                                        ?>
                                            <div style="margin-top: 10px; padding: 10px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; font-size: 0.85rem; color: #10b981; display: inline-flex; align-items: center; gap: 8px;">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <span>APK Aktif: <strong>app-debug.apk</strong> (<?php echo $apkSizeMB; ?> MB) - Diperbarui pada <?php echo $apkTime; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top: 10px; padding: 10px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; font-size: 0.85rem; color: #ef4444; display: inline-flex; align-items: center; gap: 8px;">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                <span>Belum ada file APK aktif di server (app-debug.apk tidak ditemukan).</span>
                                            </div>
                                        <?php endif; ?>
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
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'queue_reset'): ?>
                        <div class="alert-status alert-cleared" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.1rem;"></i>
                            <span>All kiosk queues cleared successfully!</span>
                        </div>
                    <?php endif; ?>
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
                                    <span class="val" style="color: var(--warning-dark); font-size: 1.05rem; font-weight: 600;">#<?php echo htmlspecialchars($queueState['active_queue_number']); ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Sesi Aktif saat ini:</span>
                                    <span class="val" style="font-family: monospace; font-size: 0.8rem;"><?php echo $queueState['active_session_id'] ? htmlspecialchars(substr($queueState['active_session_id'], 0, 8)) . '...' : 'Tidak ada'; ?></span>
                                </div>
                                <div class="status-row">
                                    <span class="label">Menunggu (Waiting):</span>
                                    <span class="val" style="color: var(--danger-dark); font-size: 1.05rem; font-weight: 600;"><?php 
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
                                        <th>#</th>
                                        <th>Sesi</th>
                                        <th>Paket</th>
                                        <th>Masuk</th>
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
                                                <td style="font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars(substr($item['session_id'], 0, 8)); ?>...</td>
                                                <td><span class="badge badge-gray"><?php echo htmlspecialchars($packageNames[$item['package_id']] ?? $item['package_id']); ?></span></td>
                                                <td style="color: var(--text-muted);"><?php echo date('H:i, d M', $item['timestamp']); ?></td>
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
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'packages_saved'): ?>
                        <div class="alert-status alert-saved" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                            <span>Package features and prices updated successfully!</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-section">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-box-archive"></i> Manajemen Paket & Fitur Kiosk</div>
                        </div>
                        <form action="admin.php" method="POST">
                            <input type="hidden" name="action" value="update_packages">
                            
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
                                <?php foreach ($packagesList as $pkg): ?>
                                    <div class="card-section" style="border: 1px solid var(--border-color); background-color: #f8fafc; border-radius: 18px; margin-bottom: 0;">
                                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-main); margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
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
                                                <option value="RECEIPT" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'RECEIPT') ? 'selected' : ''; ?>>Receipt Termal (Kertas Struk)</option>
                                                <option value="COLOR_PRINT" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'COLOR_PRINT') ? 'selected' : ''; ?>>Cetak Warna (Foto Strip)</option>
                                                <option value="ID_CARD" <?php echo (isset($pkg['print_flow']) && $pkg['print_flow'] === 'ID_CARD') ? 'selected' : ''; ?>>Cetak ID Card (Lisensi)</option>
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

                <!-- TAB: Coupons (Kelola Kupon & Voucher) -->
                <div class="tab-pane" id="tab-coupons">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] === 'bulk_created'): ?>
                            <div class="alert-status alert-saved" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                                <span>Berhasil men-generate <?php echo intval($_GET['count'] ?? 0); ?> kupon massal baru!</span>
                                <?php if (!empty($_GET['print_codes'])): ?>
                                <script>
                                    window.addEventListener('DOMContentLoaded', () => {
                                        window.open('print_coupon.php?code=<?php echo urlencode($_GET['print_codes']); ?>', '_blank');
                                    });
                                </script>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($_GET['status'] === 'coupon_created'): ?>
                            <div class="alert-status alert-saved" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                                <span>Kupon <strong><?php echo htmlspecialchars($_GET['print_code'] ?? ''); ?></strong> berhasil dibuat dan sedang dikirim ke printer!</span>
                                <script>
                                    window.addEventListener('DOMContentLoaded', () => {
                                        window.open('print_coupon.php?code=<?php echo urlencode($_GET['print_code'] ?? ''); ?>', '_blank');
                                    });
                                </script>
                            </div>
                        <?php elseif ($_GET['status'] === 'coupon_error'): ?>
                            <div class="alert-status alert-cleared" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                                <span>Gagal membuat kupon: <?php echo htmlspecialchars($_GET['msg'] ?? 'Error tidak diketahui'); ?></span>
                            </div>
                        <?php elseif ($_GET['status'] === 'coupon_deleted'): ?>
                            <div class="alert-status alert-deleted" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                                <span>Kupon berhasil dihapus secara permanen!</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="card-section" style="margin-bottom: 24px;">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                            <div class="card-title"><i class="fa-solid fa-ticket"></i> Buat Kupon Baru</div>
                        </div>
                        <form method="POST" action="admin.php">
                            <input type="hidden" name="action" value="create_coupon">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="coupon_package_id">Berlaku Untuk Paket</label>
                                    <select id="coupon_package_id" name="package_id" class="form-select">
                                        <option value="any">Semua Paket</option>
                                        <?php 
                                        if (file_exists($packagesFile)) {
                                            $pkgs = json_decode(file_get_contents($packagesFile), true);
                                            foreach ($pkgs as $p) {
                                                echo '<option value="' . htmlspecialchars($p['id']) . '">' . htmlspecialchars($p['name']) . ' (Rp ' . number_format($p['price'], 0, ',', '.') . ')</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group" id="group_custom_code">
                                    <label for="coupon_custom_code">Kode Kupon Kustom (Opsional)</label>
                                    <input type="text" id="coupon_custom_code" name="custom_code" class="form-input" placeholder="Misal: PROMO50 (Kosongkan untuk acak)">
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">Kosongkan untuk membuat kode acak unik secara otomatis.</span>
                                </div>
                                <div class="form-group">
                                    <label for="coupon_is_bulk">Mode Pembuatan</label>
                                    <select id="coupon_is_bulk" name="is_bulk" class="form-select" onchange="toggleBulkQty(this.value)">
                                        <option value="0">Kupon Tunggal</option>
                                        <option value="1">Kupon Massal</option>
                                    </select>
                                </div>
                                <div class="form-group" id="group_bulk_qty" style="display: none;">
                                    <label for="coupon_bulk_qty">Jumlah Kupon</label>
                                    <input type="number" id="coupon_bulk_qty" name="bulk_quantity" class="form-input" value="5" min="1" max="100">
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">Jumlah kupon massal yang ingin dibuat sekaligus (maks 100).</span>
                                </div>
                            </div>
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn-primary">
                                    <i class="fa-solid fa-plus-circle"></i> Buat Kupon
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-section">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                            <div class="card-title"><i class="fa-solid fa-list-check"></i> Daftar Kupon & Voucher</div>
                        </div>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Kode</th>
                                        <th>Paket</th>
                                        <th>Status</th>
                                        <th>Dibuat</th>
                                        <th>Dipakai</th>
                                        <th style="text-align: center; width: 140px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $allCoupons = loadCoupons();
                                    usort($allCoupons, function($a, $b) {
                                        return $b['created_at'] - $a['created_at'];
                                    });
                                    $pkgNames = ['any' => 'Semua Paket'];
                                    if (file_exists($packagesFile)) {
                                        $pkgs = json_decode(file_get_contents($packagesFile), true);
                                        foreach ($pkgs as $p) {
                                            $pkgNames[$p['id']] = $p['name'];
                                        }
                                    }
                                    ?>
                                    <?php if (empty($allCoupons)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px 0;">Belum ada kupon yang dibuat.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $counter = 1; foreach ($allCoupons as $coupon): ?>
                                            <tr>
                                                <td style="color: var(--text-muted);"><?php echo $counter++; ?>.</td>
                                                <td style="font-family: monospace; font-size: 0.9rem; font-weight: 600; color: var(--primary); letter-spacing: 0.5px;">
                                                    <?php echo htmlspecialchars($coupon['code']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-gray">
                                                        <?php echo htmlspecialchars($pkgNames[$coupon['package_id']] ?? $coupon['package_id']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($coupon['status'] === 'ACTIVE'): ?>
                                                        <?php if (time() - $coupon['created_at'] > 86400): ?>
                                                            <span class="badge badge-danger">Kedaluwarsa</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">Aktif</span>
                                                        <?php endif; ?>
                                                    <?php elseif ($coupon['status'] === 'EXPIRED'): ?>
                                                        <span class="badge badge-danger">Kedaluwarsa</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray">Terpakai</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: var(--text-muted); font-size: 0.85rem;">
                                                    <?php echo date('d M, H:i', $coupon['created_at']); ?>
                                                </td>
                                                <td style="color: var(--text-muted); font-size: 0.85rem;">
                                                    <?php 
                                                    if ($coupon['status'] === 'USED' && !empty($coupon['used_at'])) {
                                                        echo date('d M, H:i', $coupon['used_at']);
                                                        if (!empty($coupon['used_by_session'])) {
                                                            $shortSess = substr($coupon['used_by_session'], 0, 6);
                                                            echo '<span style="font-family: monospace; font-size: 0.7rem; display: block; color: var(--text-muted); margin-top: 2px;">(' . htmlspecialchars($shortSess) . '...)</span>';
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <a href="print_coupon.php?code=<?php echo urlencode($coupon['code']); ?>" target="_blank" class="btn-primary" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; background-color: var(--info-dark); border: none;">
                                                        <i class="fa-solid fa-print"></i> <span class="btn-text">Cetak</span>
                                                    </a>
                                                    <a href="admin.php?action=delete_coupon&code=<?php echo urlencode($coupon['code']); ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus kupon <?php echo htmlspecialchars($coupon['code']); ?>?');" class="btn-primary" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; background-color: var(--danger-dark); border: none; margin-left: 5px;">
                                                        <i class="fa-solid fa-trash"></i> <span class="btn-text">Hapus</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                function toggleBulkQty(val) {
                    const qtyGroup = document.getElementById('group_bulk_qty');
                    const codeGroup = document.getElementById('group_custom_code');
                    if (qtyGroup && codeGroup) {
                        if (val === '1') {
                            qtyGroup.style.display = 'block';
                            codeGroup.style.display = 'none';
                            document.getElementById('coupon_custom_code').value = '';
                        } else {
                            qtyGroup.style.display = 'none';
                            codeGroup.style.display = 'block';
                        }
                    }
                }
                </script>

                <!-- TAB: Frames (Manajemen Bingkai & Visual Editor) -->
                <div class="tab-pane" id="tab-frames">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] === 'frame_saved'): ?>
                            <div class="alert-status alert-saved" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                                <span>Bingkai foto berhasil disimpan & disinkronisasikan!</span>
                            </div>
                        <?php elseif ($_GET['status'] === 'frame_deleted'): ?>
                            <div class="alert-status alert-deleted" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                                <span>Bingkai foto berhasil dihapus secara permanen dari disk!</span>
                            </div>
                        <?php elseif ($_GET['status'] === 'frame_error'): ?>
                            <div class="alert-status alert-cleared" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                                <span>Gagal menyimpan bingkai! Harap unggah gambar PNG transparan yang valid.</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <!-- sub-view: LIST BINGKAI -->
                    <div id="framesListView">
                        <div class="card-section">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                                <div class="card-title"><i class="fa-solid fa-image"></i> Daftar Bingkai Kiosk</div>
                                <button class="btn-primary" onclick="showFrameEditor()">
                                    <i class="fa-solid fa-plus"></i> Tambah Bingkai Baru
                                </button>
                            </div>
                            
                            <?php if (!empty($framesList)): ?>
                            <?php
                            // Extract unique categories from framesList dynamically
                            $uniqueCategories = [];
                            foreach ($framesList as $f) {
                                $cat = isset($f['category']) ? trim($f['category']) : 'Classic';
                                if ($cat !== '' && !in_array($cat, $uniqueCategories)) {
                                    $uniqueCategories[] = $cat;
                                }
                            }
                            sort($uniqueCategories);
                            ?>
                            <!-- Filter Bar -->
                            <div class="filter-bar" style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; background: rgba(248, 250, 252, 0.6); padding: 18px; border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8); align-items: flex-end;">
                                <div style="flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 6px;">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Cari Bingkai</label>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; color: #94a3b8; font-size: 0.9rem;"></i>
                                        <input type="text" id="filterFrameSearch" class="form-input" placeholder="Cari berdasarkan nama..." style="width: 100%; padding-left: 38px; background: white;">
                                    </div>
                                </div>
                                <div style="width: 150px; display: flex; flex-direction: column; gap: 6px;">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Kategori</label>
                                    <select id="filterFrameCategory" class="form-select" style="background: white; width: 100%;">
                                        <option value="all">Semua Kategori</option>
                                        <?php foreach ($uniqueCategories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="width: 140px; display: flex; flex-direction: column; gap: 6px;">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Format Bingkai</label>
                                    <select id="filterFrameType" class="form-select" style="background: white; width: 100%;">
                                        <option value="all">Semua Format</option>
                                        <option value="strip">Vertical Strip</option>
                                        <option value="grid">Collage Grid</option>
                                        <option value="postcard">Postcard Card</option>
                                    </select>
                                </div>
                                <div style="width: 130px; display: flex; flex-direction: column; gap: 6px;">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sifat Bingkai</label>
                                    <select id="filterFrameDynamic" class="form-select" style="background: white; width: 100%;">
                                        <option value="all">Semua Sifat</option>
                                        <option value="dynamic">Dinamis</option>
                                        <option value="static">Statis</option>
                                    </select>
                                </div>
                                <div style="width: 180px; display: flex; flex-direction: column; gap: 6px;">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sesi Event</label>
                                    <select id="filterFrameEvent" class="form-select" style="background: white; width: 100%;">
                                        <option value="all">Semua Event</option>
                                        <option value="general">Umum (Default)</option>
                                        <?php foreach ($eventsList as $evt): ?>
                                            <?php if ($evt['id'] !== 'general'): ?>
                                                <option value="<?php echo htmlspecialchars($evt['id']); ?>"><?php echo htmlspecialchars($evt['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="height: 42px; display: flex; align-items: center;">
                                    <button class="btn-secondary" onclick="resetFrameFilters()" style="height: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 18px; font-weight: 600;">
                                        <i class="fa-solid fa-arrows-rotate" style="font-size: 0.85rem;"></i> Reset
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="frames-grid">
                                <?php if (empty($framesList)): ?>
                                    <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 60px 40px;">
                                        <i class="fa-regular fa-image" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                                        <span style="font-weight: 600; display: block;">Belum ada bingkai kustom yang terdaftar.</span>
                                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Klik tombol di atas untuk membuat bingkai pertamamu!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($framesList as $f): ?>
                                        <div class="frame-card-admin" data-frame="<?php echo htmlspecialchars(json_encode($f)); ?>">
                                            <div class="frame-card-preview-admin" onclick="openFrameZoom(<?php echo htmlspecialchars(json_encode($f)); ?>)">
                                                <img src="<?php echo htmlspecialchars($f['image_url']); ?>?v=<?php echo isset($configData['version'])?$configData['version']:'1'; ?>" alt="<?php echo htmlspecialchars($f['name']); ?>" onerror="this.src='https://placehold.co/150x180/121212/ffffff?text=No+Preview'">
                                                <div class="frame-preview-overlay">
                                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                                    <span>Zoom Detail</span>
                                                </div>
                                            </div>
                                            <div class="frame-card-meta">
                                                <div class="frame-card-title"><?php echo htmlspecialchars($f['name']); ?></div>
                                                <div class="frame-card-tag">Format: <b>
                                                    <?php 
                                                        if ($f['type'] === 'strip') echo 'Vertical Strip';
                                                        elseif ($f['type'] === 'grid') echo 'Collage Grid';
                                                        elseif ($f['type'] === 'postcard') echo 'Postcard Card';
                                                        else echo htmlspecialchars(ucfirst($f['type']));
                                                    ?></b>
                                                </div>
                                                <div class="frame-card-tag">Kategori: <b><?php echo htmlspecialchars($f['category'] ?? 'Classic'); ?></b></div>
                                                <div class="frame-card-tag">Cetak: <b>
                                                    <?php 
                                                        $pf = $f['print_flows'] ?? [];
                                                        if (empty($pf)) {
                                                            if ($f['type'] === 'strip') $pf = ['RECEIPT', 'COLOR_PRINT'];
                                                            else $pf = ['COLOR_PRINT'];
                                                        }
                                                        $pfNames = [];
                                                        foreach ($pf as $flow) {
                                                            if ($flow === 'RECEIPT') $pfNames[] = 'Struk';
                                                            elseif ($flow === 'COLOR_PRINT') $pfNames[] = 'Warna';
                                                            elseif ($flow === 'ID_CARD') $pfNames[] = 'ID Card';
                                                        }
                                                        echo htmlspecialchars(implode(', ', $pfNames));
                                                    ?></b>
                                                </div>
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
                                        <input type="hidden" id="editorFrameId" name="frame_id" value="">
                                        
                                        <div class="form-group">
                                            <label>Nama Bingkai</label>
                                            <input type="text" id="editorFrameName" name="frame_name" class="form-input" placeholder="misal: Rustic Red Floral" required style="background: white;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Format Bingkai (Frame Format)</label>
                                            <select id="editorLayoutType" name="layout_type" class="form-input" style="background: white;" onchange="onLayoutTypeChange()">
                                                <option value="strip">Vertical Strip</option>
                                                <option value="grid">Collage Grid</option>
                                                <option value="postcard">Postcard Card</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label style="font-weight: 600;">Jenis Cetak Didukung (Supported Print Flows)</label>
                                            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
                                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; color: var(--text-main);">
                                                    <input type="checkbox" name="print_flows[]" value="RECEIPT" id="flowReceiptCheckbox"> Receipt Termal (Kertas Struk)
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; color: var(--text-main);">
                                                    <input type="checkbox" name="print_flows[]" value="COLOR_PRINT" id="flowColorCheckbox"> Cetak Warna (Foto Strip/Collage)
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; color: var(--text-main);">
                                                    <input type="checkbox" name="print_flows[]" value="ID_CARD" id="flowIdCardCheckbox"> Cetak ID Card (Kartu PVC)
                                                </label>
                                            </div>
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
                                            <label>Kategori Bingkai</label>
                                            <select id="editorFrameCategorySelect" class="form-input" style="background: white;" onchange="if(this.value=='__custom__'){document.getElementById('editorFrameCategoryCustomGroup').style.display='block';document.getElementById('editorFrameCategory').value='';}else{document.getElementById('editorFrameCategoryCustomGroup').style.display='none';document.getElementById('editorFrameCategory').value=this.value;}">
                                                <option value="Classic">Classic</option>
                                                <option value="Creative">Creative</option>
                                                <option value="Aesthetic">Aesthetic</option>
                                                <option value="Y2K">Y2K</option>
                                                <option value="Magazine">Magazine</option>
                                                <option value="Receipt">Receipt</option>
                                                <option value="Dynamic">Dynamic</option>
                                                <option value="Funny">Funny</option>
                                                <option value="Newspaper">Newspaper</option>
                                                <option value="Cute">Cute</option>
                                                <option value="Romance">Romance</option>
                                                <option value="__custom__">-- Kustom Baru --</option>
                                            </select>
                                            <input type="hidden" id="editorFrameCategory" name="category" value="Classic">
                                        </div>
                                        <div class="form-group" id="editorFrameCategoryCustomGroup" style="display: none;">
                                            <label>Nama Kategori Kustom</label>
                                            <input type="text" id="editorFrameCategoryCustom" class="form-input" placeholder="misal: Ultah Anak" style="background: white;" oninput="document.getElementById('editorFrameCategory').value=this.value">
                                        </div>

                                        <!-- Opsi Bingkai Dinamis -->
                                        <div class="form-group" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 12px; border-radius: 12px; margin-top: 8px;">
                                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; user-select: none; margin-bottom: 0;">
                                                <input type="checkbox" id="editorFrameIsDynamic" name="is_dynamic" value="1" onchange="toggleDynamicFields(this.checked)" style="width: 18px; height: 18px; accent-color: var(--primary);">
                                                <span>Jadikan Bingkai Dinamis</span>
                                            </label>
                                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 4px; line-height: 1.3;">Jika aktif, teks & logo event akan digambar programmatik di atas koordinat yang disetting.</span>
                                            
                                            <!-- Dynamic fields container -->
                                            <div id="dynamicFieldsContainer" style="display: none; flex-direction: column; gap: 12px; margin-top: 12px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                                                <!-- LOGO PLACEMENT -->
                                                <div style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                                                    <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.8rem; margin-bottom: 0; cursor: pointer;">
                                                        <input type="checkbox" id="dyn_logo_enable" name="dynamic_logo_enable" value="1">
                                                        <span>Tampilkan Logo Event</span>
                                                    </label>
                                                    <!-- Hidden Logo Fields -->
                                                    <input type="hidden" name="dynamic_logo_x" id="dyn_logo_x" value="250">
                                                    <input type="hidden" name="dynamic_logo_y" id="dyn_logo_y" value="840">
                                                    <input type="hidden" name="dynamic_logo_w" id="dyn_logo_w" value="100">
                                                    <input type="hidden" name="dynamic_logo_h" id="dyn_logo_h" value="100">
                                                    <input type="hidden" name="dynamic_logo_align" value="center">
                                                </div>
                                                
                                                <!-- NAMA EVENT PLACEMENT -->
                                                <div style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px;">
                                                    <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.8rem; margin-bottom: 0; cursor: pointer;">
                                                        <input type="checkbox" id="dyn_name_enable" name="dynamic_name_enable" value="1">
                                                        <span>Tampilkan Nama Event</span>
                                                    </label>
                                                    
                                                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 6px; align-items: center;">
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Warna Teks</span>
                                                            <input type="color" id="dyn_name_color_picker" class="color-palette-picker" value="#000000" oninput="document.getElementById('dyn_name_color').value = this.value; renderDynamicDummies();">
                                                            <input type="hidden" name="dynamic_name_color" id="dyn_name_color" value="#000000">
                                                        </div>
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Gaya</span>
                                                            <select name="dynamic_name_style" id="dyn_name_style" class="form-input" style="padding: 4px 6px; font-size: 0.8rem; background: white; height: 32px;">
                                                                <option value="bold">Bold</option>
                                                                <option value="normal">Normal</option>
                                                                <option value="italic">Italic</option>
                                                                <option value="bold_italic">Bold Italic</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Hidden Nama Fields -->
                                                    <input type="hidden" name="dynamic_name_x" id="dyn_name_x" value="300">
                                                    <input type="hidden" name="dynamic_name_y" id="dyn_name_y" value="140">
                                                    <input type="hidden" name="dynamic_name_size" id="dyn_name_size" value="28">
                                                    <input type="hidden" name="dynamic_name_align" value="center">
                                                </div>
                                                
                                                <!-- SUBTITLE EVENT PLACEMENT -->
                                                <div style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px;">
                                                    <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.8rem; margin-bottom: 0; cursor: pointer;">
                                                        <input type="checkbox" id="dyn_subtitle_enable" name="dynamic_subtitle_enable" value="1">
                                                        <span>Tampilkan Subtitle/Tanggal</span>
                                                    </label>
                                                    
                                                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 6px; align-items: center;">
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Warna Teks</span>
                                                            <input type="color" id="dyn_subtitle_color_picker" class="color-palette-picker" value="#333333" oninput="document.getElementById('dyn_subtitle_color').value = this.value; renderDynamicDummies();">
                                                            <input type="hidden" name="dynamic_subtitle_color" id="dyn_subtitle_color" value="#333333">
                                                        </div>
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Gaya</span>
                                                            <select name="dynamic_subtitle_style" id="dyn_subtitle_style" class="form-input" style="padding: 4px 6px; font-size: 0.8rem; background: white; height: 32px;">
                                                                <option value="italic">Italic</option>
                                                                <option value="normal">Normal</option>
                                                                <option value="bold">Bold</option>
                                                                <option value="bold_italic">Bold Italic</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Hidden Subtitle Fields -->
                                                    <input type="hidden" name="dynamic_subtitle_x" id="dyn_subtitle_x" value="300">
                                                    <input type="hidden" name="dynamic_subtitle_y" id="dyn_subtitle_y" value="380">
                                                    <input type="hidden" name="dynamic_subtitle_size" id="dyn_subtitle_size" value="20">
                                                    <input type="hidden" name="dynamic_subtitle_align" value="center">
                                                </div>
                                                
                                                <!-- HASHTAG EVENT PLACEMENT -->
                                                <div style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px;">
                                                    <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.8rem; margin-bottom: 0; cursor: pointer;">
                                                        <input type="checkbox" id="dyn_hashtag_enable" name="dynamic_hashtag_enable" value="1">
                                                        <span>Tampilkan Hashtag Event</span>
                                                    </label>
                                                    
                                                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 6px; align-items: center;">
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Warna Teks</span>
                                                            <input type="color" id="dyn_hashtag_color_picker" class="color-palette-picker" value="#000000" oninput="document.getElementById('dyn_hashtag_color').value = this.value; renderDynamicDummies();">
                                                            <input type="hidden" name="dynamic_hashtag_color" id="dyn_hashtag_color" value="#000000">
                                                        </div>
                                                        <div>
                                                            <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Gaya</span>
                                                            <select name="dynamic_hashtag_style" id="dyn_hashtag_style" class="form-input" style="padding: 4px 6px; font-size: 0.8rem; background: white; height: 32px;">
                                                                <option value="bold">Bold</option>
                                                                <option value="normal">Normal</option>
                                                                <option value="italic">Italic</option>
                                                                <option value="bold_italic">Bold Italic</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Hidden Hashtag Fields -->
                                                    <input type="hidden" name="dynamic_hashtag_x" id="dyn_hashtag_x" value="300">
                                                    <input type="hidden" name="dynamic_hashtag_y" id="dyn_hashtag_y" value="1440">
                                                    <input type="hidden" name="dynamic_hashtag_size" id="dyn_hashtag_size" value="22">
                                                    <input type="hidden" name="dynamic_hashtag_align" value="center">
                                                </div>
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

                <!-- TAB: Events -->
                <div class="tab-pane" id="tab-events">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] === 'event_saved'): ?>
                            <div class="alert-status alert-saved" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                                <span>Event berhasil disimpan & disinkronisasikan!</span>
                            </div>
                        <?php elseif ($_GET['status'] === 'event_deleted'): ?>
                            <div class="alert-status alert-deleted" style="margin-bottom: 20px;">
                                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                                <span>Event berhasil dihapus secara permanen!</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="card-section" style="margin-bottom: 0; min-height: 500px;">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-title"><i class="fa-solid fa-calendar-days"></i> Daftar Event Aktif</div>
                            <button type="button" class="btn-primary" onclick="openEventModal()" style="font-size: 0.85rem; padding: 10px 18px;">
                                <i class="fa-solid fa-plus"></i> Tambah Event Baru
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px; text-align: center;">Logo</th>
                                        <th>Nama Event</th>
                                        <th style="width: 100px;">Kode</th>
                                        <th>Subtitle</th>
                                        <th>Hashtag</th>
                                        <th style="width: 80px;">Warna</th>
                                        <th style="width: 140px; text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($eventsList)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                                <i class="fa-regular fa-calendar-xmark" style="font-size: 2.5rem; margin-bottom: 12px; color: #cbd5e1; display: block;"></i>
                                                <span style="font-weight: 600; display: block;">Belum ada event kustom yang terdaftar.</span>
                                                <span style="font-size: 0.8rem; margin-top: 4px; display: block;">Klik tombol "Tambah Event Baru" di atas untuk membuat event.</span>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($eventsList as $evt): ?>
                                            <tr>
                                                <td style="text-align: center;">
                                                    <div style="width: 44px; height: 44px; border-radius: 8px; border: 1px solid var(--border-color); background: #f8fafc; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; vertical-align: middle;">
                                                        <?php if (!empty($evt['logo_url']) && file_exists(__DIR__ . '/' . $evt['logo_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($evt['logo_url']); ?>?v=<?php echo time(); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                                        <?php elseif ($evt['id'] === 'general'): ?>
                                                            <i class="fa-solid fa-store" style="color: #94a3b8; font-size: 1.2rem;"></i>
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-calendar-days" style="color: #cbd5e1; font-size: 1.2rem;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($evt['id'] === 'general'): ?>
                                                        <span style="font-weight: 700; color: var(--text-main); font-size: 0.85rem;"><?php echo htmlspecialchars($evt['name']); ?></span>
                                                        <span style="font-size: 0.68rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; padding: 1px 6px; border-radius: 10px; margin-left: 6px;">Profil Default</span>
                                                    <?php else: ?>
                                                        <span style="font-weight: 600; color: var(--text-main); font-size: 0.85rem;"><?php echo htmlspecialchars($evt['name']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($evt['id'] === 'general'): ?>
                                                        <span style="font-size: 0.72rem; color: #94a3b8; font-style: italic;">— (tidak pakai kode)</span>
                                                    <?php else: ?>
                                                        <span class="event-badge-code" style="font-size: 0.75rem;"><?php echo htmlspecialchars($evt['code']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($evt['subtitle'])): ?>
                                                        <span style="font-size: 0.75rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px;">
                                                            <i class="fa-solid fa-calendar" style="font-size: 0.7rem; color: #64748b;"></i>
                                                            <?php echo htmlspecialchars($evt['subtitle']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($evt['hashtag'])): ?>
                                                        <span style="color: #3b82f6; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fa-solid fa-hashtag" style="font-size: 0.7rem;"></i>
                                                            <?php echo htmlspecialchars($evt['hashtag']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 6px; align-items: center;">
                                                        <span class="event-color-pill" style="background: <?php echo htmlspecialchars($evt['primary_color'] ?? '#e63946'); ?>; width: 12px; height: 12px;" title="Warna Utama (<?php echo htmlspecialchars($evt['primary_color'] ?? '#e63946'); ?>)"></span>
                                                        <span class="event-color-pill" style="background: <?php echo htmlspecialchars($evt['secondary_color'] ?? '#ffffff'); ?>; width: 12px; height: 12px;" title="Warna Sekunder (<?php echo htmlspecialchars($evt['secondary_color'] ?? '#ffffff'); ?>)"></span>
                                                    </div>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                                         <?php 
                                                         $activeCount = isset($evt['allowed_frames']) ? count($evt['allowed_frames']) : count($framesList);
                                                         ?>
                                                         <button type="button" class="btn-secondary" onclick="openFramesModalFromRow(<?php echo htmlspecialchars(json_encode($evt)); ?>)" style="padding: 4.5px 8px; font-size: 0.75rem; border-radius: 6px; background: rgba(79, 70, 229, 0.1); color: var(--primary); border-color: rgba(79, 70, 229, 0.15); display: inline-flex; align-items: center; gap: 6px;">
                                                             <i class="fa-solid fa-images" style="font-size: 0.7rem;"></i> Bingkai
                                                             <span style="background: var(--primary); color: white; padding: 1px 5.5px; border-radius: 10px; font-size: 0.65rem; font-weight: 700; line-height: 1;">
                                                                 <?php echo $activeCount; ?>
                                                             </span>
                                                         </button>
                                                         <button type="button" class="btn-secondary" onclick="editEvent(<?php echo htmlspecialchars(json_encode($evt)); ?>)" style="padding: 4.5px 8px; font-size: 0.75rem; border-radius: 6px;">
                                                             <i class="fa-solid fa-pen" style="font-size: 0.7rem;"></i> Edit
                                                         </button>
                                                        <?php if ($evt['id'] !== 'general'): ?>
                                                            <a href="admin.php?action=delete_event&id=<?php echo urlencode($evt['id']); ?>" class="btn-danger" style="text-decoration: none; padding: 6px 12px; font-size: 0.8rem; border-radius: 8px;" onclick="return confirm('Apakah Anda yakin ingin menghapus event ini? Semua bingkai yang terhubung akan dialihkan ke Umum.')">
                                                                <i class="fa-solid fa-trash" style="font-size: 0.75rem;"></i> Hapus
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <style>
        .frames-selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        .frame-select-card {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 8px;
            background: #fff;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            transition: all 0.2s ease;
        }
        .frame-select-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        .frame-select-card.selected {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(230, 57, 70, 0.2);
            background: rgba(230, 57, 70, 0.02);
        }
        .frame-select-preview {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 8px;
            border: 1px solid var(--border-color);
        }
        .frame-select-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .frame-select-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            word-break: break-word;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .frame-select-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #64748b;
            font-weight: 500;
        }
        .frame-select-card.selected .frame-select-badge {
            background: rgba(230, 57, 70, 0.1);
            color: var(--primary);
        }
    </style>

    <!-- Modal Selection of Frames -->
    <div class="modal" id="eventFramesModal" style="z-index: 1100;">
        <div class="modal-content" style="max-width: 800px; width: 90%; max-height: 85vh; display: flex; flex-direction: column;">
            <button type="button" class="modal-close" onclick="cancelFramesModal()">&times;</button>
            <div class="modal-title" style="display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                <i class="fa-solid fa-images" style="color: var(--primary);"></i>
                <span>Pilih Bingkai Sesi Foto</span>
            </div>
            
            <!-- Filter & Quick Actions Bar -->
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 12px; margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border-color);">
                <!-- Search & Filters -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; width: 100%;">
                    <!-- Search Input -->
                    <div style="flex: 1; min-width: 200px; position: relative; display: flex; align-items: center;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; color: #94a3b8; font-size: 0.85rem;"></i>
                        <input type="text" id="modalFrameSearch" placeholder="Cari nama bingkai..." style="width: 100%; padding: 6px 10px 6px 30px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; background: white;" oninput="filterModalFrames()">
                    </div>
                    <!-- Type/Format Filter -->
                    <div style="width: 130px;">
                        <select id="modalFrameTypeFilter" style="width: 100%; padding: 6px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; background: white; cursor: pointer;" onchange="filterModalFrames()">
                            <option value="all">Semua Format</option>
                            <option value="strip">Vertical Strip</option>
                            <option value="grid">Collage Grid</option>
                            <option value="postcard">Postcard</option>
                        </select>
                    </div>
                    <!-- Category Filter -->
                    <div style="width: 130px;">
                        <select id="modalFrameCategoryFilter" style="width: 100%; padding: 6px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; background: white; cursor: pointer;" onchange="filterModalFrames()">
                            <option value="all">Semua Kategori</option>
                            <?php 
                            $uniqueCats = [];
                            foreach ($framesList as $f) {
                                $c = isset($f['category']) ? trim($f['category']) : 'Classic';
                                if ($c !== '' && !in_array($c, $uniqueCats)) {
                                    $uniqueCats[] = $c;
                                }
                            }
                            sort($uniqueCats);
                            foreach ($uniqueCats as $c): 
                            ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Selection State Filter -->
                    <div style="display: flex; align-items: center; gap: 6px; margin-left: 4px; font-size: 0.8rem; color: var(--text-main); font-weight: 600; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="modalFrameSelectedOnly" style="cursor: pointer; width: 14px; height: 14px;" onchange="filterModalFrames()">
                        <label for="modalFrameSelectedOnly" style="cursor: pointer; margin-bottom: 0;">Terpilih Saja</label>
                    </div>
                    <!-- Dynamic Nature Filter -->
                    <div style="display: flex; align-items: center; gap: 6px; margin-left: 4px; font-size: 0.8rem; color: var(--text-main); font-weight: 600; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="modalFrameDynamicOnly" style="cursor: pointer; width: 14px; height: 14px;" onchange="filterModalFrames()">
                        <label for="modalFrameDynamicOnly" style="cursor: pointer; margin-bottom: 0;">Dinamis Saja</label>
                    </div>
                </div>
                <!-- Quick Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 8px; margin-top: 4px;">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-secondary" onclick="selectAllFrames(true)" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 6px;">
                            <i class="fa-solid fa-check-double"></i> Pilih Semua
                        </button>
                        <button type="button" class="btn-secondary" onclick="selectAllFrames(false)" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 6px;">
                            <i class="fa-solid fa-square"></i> Kosongkan
                        </button>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;" id="modalFrameCountLabel">
                        Menampilkan semua bingkai
                    </div>
                </div>
            </div>
            
            <!-- Scrollable Frame Categories -->
            <div id="modalFramesScrollContainer" style="flex: 1; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 20px;">
                <!-- Empty Placeholder -->
                <div id="modalFramesEmptyPlaceholder" style="display: none; text-align: center; color: var(--text-muted); padding: 40px 20px;">
                    <i class="fa-regular fa-image" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <span style="font-weight: 600; display: block;">Tidak ada bingkai yang cocok dengan pencarian Anda.</span>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Coba ubah kata kunci atau filter Anda.</p>
                </div>

                <!-- Category: Dinamis -->
                <div id="dynamicCategorySection">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 10px;">
                        <span style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> Bingkai Dinamis
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn-secondary" onclick="selectCategoryFrames('dynamic', true)" style="padding: 2px 8px; font-size: 0.72rem; border-radius: 4px;">Pilih Semua</button>
                            <button type="button" class="btn-secondary" onclick="selectCategoryFrames('dynamic', false)" style="padding: 2px 8px; font-size: 0.72rem; border-radius: 4px;">Kosongkan</button>
                        </div>
                    </div>
                    <div class="frames-selector-grid" id="dynamicFramesGrid">
                        <?php 
                        $dynamicFrames = array_filter($framesList, function($f) {
                            return isset($f['is_dynamic']) && $f['is_dynamic'];
                        });
                        foreach ($dynamicFrames as $f): 
                        ?>
                            <div class="frame-select-card" data-frame-id="<?php echo htmlspecialchars($f['id']); ?>" data-category="dynamic" data-type="<?php echo htmlspecialchars(strtolower($f['type'])); ?>" data-category-name="<?php echo htmlspecialchars(isset($f['category']) ? trim($f['category']) : 'Classic'); ?>" onclick="toggleFrameCardSelection('<?php echo htmlspecialchars($f['id']); ?>')">
                                <input type="checkbox" class="rental-frame-checkbox" name="allowed_frames_checkbox[]" value="<?php echo htmlspecialchars($f['id']); ?>" onclick="event.stopPropagation(); updateFrameCardStyle('<?php echo htmlspecialchars($f['id']); ?>'); filterModalFrames();" style="position: absolute; top: 6px; right: 6px; accent-color: var(--primary); cursor: pointer; width: 16px; height: 16px;">
                                <div class="frame-select-preview">
                                    <img src="<?php echo htmlspecialchars($f['image_url']); ?>?v=<?php echo isset($configData['version'])?$configData['version']:'1'; ?>" onerror="this.src='https://placehold.co/100x120/121212/ffffff?text=No+Preview'">
                                </div>
                                <div class="frame-select-name"><?php echo htmlspecialchars($f['name']); ?></div>
                                <div class="frame-select-badge"><?php echo htmlspecialchars(ucfirst($f['type'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($dynamicFrames)): ?>
                            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 20px; font-size: 0.8rem;">
                                Tidak ada bingkai dinamis yang terdaftar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Category: Statis -->
                <div id="staticCategorySection">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 10px;">
                        <span style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-image" style="color: #64748b;"></i> Bingkai Statis
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn-secondary" onclick="selectCategoryFrames('static', true)" style="padding: 2px 8px; font-size: 0.72rem; border-radius: 4px;">Pilih Semua</button>
                            <button type="button" class="btn-secondary" onclick="selectCategoryFrames('static', false)" style="padding: 2px 8px; font-size: 0.72rem; border-radius: 4px;">Kosongkan</button>
                        </div>
                    </div>
                    <div class="frames-selector-grid" id="staticFramesGrid">
                        <?php 
                        $staticFrames = array_filter($framesList, function($f) {
                            return !isset($f['is_dynamic']) || !$f['is_dynamic'];
                        });
                        foreach ($staticFrames as $f): 
                        ?>
                            <div class="frame-select-card" data-frame-id="<?php echo htmlspecialchars($f['id']); ?>" data-category="static" data-type="<?php echo htmlspecialchars(strtolower($f['type'])); ?>" data-category-name="<?php echo htmlspecialchars(isset($f['category']) ? trim($f['category']) : 'Classic'); ?>" onclick="toggleFrameCardSelection('<?php echo htmlspecialchars($f['id']); ?>')">
                                <input type="checkbox" class="rental-frame-checkbox" name="allowed_frames_checkbox[]" value="<?php echo htmlspecialchars($f['id']); ?>" onclick="event.stopPropagation(); updateFrameCardStyle('<?php echo htmlspecialchars($f['id']); ?>'); filterModalFrames();" style="position: absolute; top: 6px; right: 6px; accent-color: var(--primary); cursor: pointer; width: 16px; height: 16px;">
                                <div class="frame-select-preview">
                                    <img src="<?php echo htmlspecialchars($f['image_url']); ?>?v=<?php echo isset($configData['version'])?$configData['version']:'1'; ?>" onerror="this.src='https://placehold.co/100x120/121212/ffffff?text=No+Preview'">
                                </div>
                                <div class="frame-select-name"><?php echo htmlspecialchars($f['name']); ?></div>
                                <div class="frame-select-badge"><?php echo htmlspecialchars(ucfirst($f['type'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($staticFrames)): ?>
                            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 20px; font-size: 0.8rem;">
                                Tidak ada bingkai statis yang terdaftar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Footer Actions -->
            <div style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 12px; display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" onclick="cancelFramesModal()" style="padding: 8px 16px; min-width: 100px;">
                    Batal
                </button>
                <button type="button" class="btn-primary" onclick="saveFramesModal()" style="padding: 8px 16px; min-width: 120px;">
                    Simpan Pilihan
                </button>
            </div>
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

    <!-- Modal Frame Zoom -->
    <div class="modal" id="frameZoomModal">
        <div class="modal-content" style="max-width: 900px;">
            <button class="modal-close" onclick="closeFrameZoom()">&times;</button>
            <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-magnifying-glass-plus" style="color: var(--primary);"></i>
                <span id="zoomFrameTitle">Detail Bingkai</span>
            </div>
            
            <div class="modal-split" style="height: 520px;">
                <div class="modal-preview" style="position: relative; overflow: auto; background-color: #0f172a; padding: 24px; display: flex; justify-content: center; align-items: center;">
                    <div id="zoomFrameContainer" style="position: relative; display: inline-block; margin: auto;">
                        <!-- Will be dynamically populated by JS -->
                    </div>
                </div>
                
                <div class="modal-actions" style="width: 280px; justify-content: flex-start; padding-top: 10px;">
                    <div class="card-section" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; margin-bottom: 0; background: #f8fafc; width: 100%;">
                        <h4 style="margin-bottom: 12px; color: var(--text-main); font-size: 0.9rem; font-weight: 700; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                            <i class="fa-solid fa-circle-info"></i> Informasi Bingkai
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.8rem;">
                            <div>ID Bingkai: <b id="zoomFrameId" style="font-family: monospace; color: var(--primary);"></b></div>
                            <div>Format: <b id="zoomFrameType"></b></div>
                            <div>Event: <b id="zoomFrameEvent"></b></div>
                            <div>Jumlah Slot: <b id="zoomFrameSlots"></b></div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>Warna Latar:</span>
                                <span id="zoomFrameBgColorText" style="font-family: monospace; font-weight: 700;"></span>
                                <span id="zoomFrameBgColorColor" style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; border: 1px solid #cbd5e1;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-section" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; margin-bottom: 0; background: #f8fafc; width: 100%; display: flex; flex-direction: column; gap: 12px;">
                        <h4 style="color: var(--text-main); font-size: 0.9rem; font-weight: 700;">
                            <i class="fa-solid fa-sliders"></i> Tampilan Pratinjau
                        </h4>
                        
                        <!-- Toggle Plain vs Mockup -->
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Mode Latar Belakang:</span>
                            <div style="display: flex; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: white;">
                                <button type="button" id="btnZoomModePlain" class="btn-layer-toggle active" style="flex: 1; border: none; padding: 8px; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-align: center;" onclick="setZoomMode('plain')">
                                    <i class="fa-solid fa-border-none"></i> Transparan
                                </button>
                                <button type="button" id="btnZoomModeMockup" class="btn-layer-toggle" style="flex: 1; border: none; padding: 8px; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-align: center;" onclick="setZoomMode('mockup')">
                                    <i class="fa-solid fa-user-astronaut"></i> Mockup
                                </button>
                            </div>
                        </div>

                        <!-- Zoom Slider -->
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Skala Perbesaran:</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-magnifying-glass-minus" style="font-size: 0.8rem; color: var(--text-muted);"></i>
                                <input type="range" id="zoomScaleRange" min="1" max="2" step="0.1" value="1" style="flex: 1; accent-color: var(--primary);" oninput="applyZoomScale(this.value)">
                                <i class="fa-solid fa-magnifying-glass-plus" style="font-size: 0.8rem; color: var(--primary);"></i>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px; width: 100%;">
                        <button id="zoomEditBtn" class="btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Bingkai Ini
                        </button>
                        <button class="btn-secondary" style="width: 100%; border: 1px solid var(--border-color);" onclick="closeFrameZoom()">
                            <i class="fa-solid fa-xmark"></i> Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Events -->
    <!-- Modal Event Editor -->
    <div class="modal" id="eventEditorModal">
        <div class="modal-content" style="max-width: 650px;">
            <button type="button" class="modal-close" onclick="closeEventModal()">&times;</button>
            <div class="modal-title" id="eventFormTitle" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-calendar-plus" style="color: var(--primary);"></i>
                <span>Tambah Event Baru</span>
            </div>
            
            <form method="POST" action="admin.php" enctype="multipart/form-data" id="eventEditorForm">
                <input type="hidden" name="action" value="save_event">
                <input type="hidden" id="eventIsEditing" value="0">
                
                <input type="hidden" id="event_id" name="event_id" value="">
                <span id="eventIdNote" style="display:none;"></span>
                
                <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 10px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="event_name">Nama Event</label>
                            <input type="text" id="event_name" name="event_name" class="form-input" placeholder="misal: Sweet Seventeen Budi" required style="background: white;">
                        </div>
                        <div class="form-group">
                            <label for="event_code">Kode Akses Kiosk (Event Code)</label>
                            <input type="text" id="event_code" name="event_code" class="form-input" placeholder="misal: BUDI17" required style="background: white; text-transform: uppercase;">
                            <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display: block;">Input di Kiosk Home Screen untuk masuk.</span>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="event_subtitle">Sub Judul / Tagline Jasa atau Event</label>
                            <input type="text" id="event_subtitle" name="event_subtitle" class="form-input" placeholder="misal: Weddingnya Budi & Cinta" style="background: white;">
                        </div>
                        <div class="form-group">
                            <label for="event_hashtag">Hashtag Event (Opsional)</label>
                            <input type="text" id="event_hashtag" name="event_hashtag" class="form-input" placeholder="misal: #BudiSweet17" style="background: white;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="event_date">Tanggal Event (Opsional)</label>
                            <input type="text" id="event_date" name="event_date" class="form-input" placeholder="misal: 20 Juni 2026" style="background: white;">
                        </div>
                        <div class="form-group">
                            <label for="event_location">Lokasi Event (Opsional)</label>
                            <input type="text" id="event_location" name="event_location" class="form-input" placeholder="misal: Hotel Hilton, Bandung" style="background: white;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 16px; align-items: start;">
                        <div class="form-group">
                            <label>Logo Event (PNG Transparan)</label>
                            <input type="file" name="event_logo" class="form-input" style="background: white;" accept="image/png, image/jpeg, image/jpg">
                            <div id="eventLogoPreviewContainer" style="display: none; margin-top: 10px; align-items: center; gap: 10px;">
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Logo saat ini:</span>
                                <img id="eventLogoPreviewImg" src="" style="height: 40px; object-fit: contain; border: 1px solid var(--border-color); padding: 2px; border-radius: 4px; background: #f8fafc;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Warna Tema Event (Untuk Custom Style)</label>
                            <div style="display: flex; gap: 12px; margin-top: 4px;">
                                <div style="flex: 1;">
                                    <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Utama</span>
                                    <div style="display: flex; gap: 6px;">
                                        <input type="color" id="primaryColorPicker" class="form-input" style="width: 40px; padding: 2px; height: 38px; background: white; border-radius: 6px;" value="#e63946" oninput="document.getElementById('primary_color').value = this.value">
                                        <input type="text" id="primary_color" name="primary_color" class="form-input" value="#e63946" required style="background: white; flex: 1; padding: 8px 10px; font-size: 0.85rem;" oninput="document.getElementById('primaryColorPicker').value = this.value">
                                    </div>
                                </div>
                                <div style="flex: 1;">
                                    <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Sekunder</span>
                                    <div style="display: flex; gap: 6px;">
                                        <input type="color" id="secondaryColorPicker" class="form-input" style="width: 40px; padding: 2px; height: 38px; background: white; border-radius: 6px;" value="#ffffff" oninput="document.getElementById('secondary_color').value = this.value">
                                        <input type="text" id="secondary_color" name="secondary_color" class="form-input" value="#ffffff" required style="background: white; flex: 1; padding: 8px 10px; font-size: 0.85rem;" oninput="document.getElementById('secondaryColorPicker').value = this.value">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="event_billing_type">Tipe Billing Event</label>
                            <select id="event_billing_type" name="billing_type" class="form-input" style="background: white;" onchange="toggleBillingFields()">
                                <option value="PAY_PER_SESSION">Bayar Mandiri per Sesi</option>
                                <option value="RENTAL_DURATION">Sewa Durasi (Free Play / Unlimited)</option>
                            </select>
                        </div>
                    </div>

                    <?php if (!empty($packagesList)): ?>
                    <div id="payPerSessionFields" style="display: none; flex-direction: column; gap: 12px; border: 1px dashed var(--border-color); padding: 16px; border-radius: 12px; background: rgba(0,0,0,0.02); margin-bottom: 16px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 6px;"><i class="fa-solid fa-box" style="color: var(--primary);"></i> Paket yang Ditampilkan</label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($packagesList as $pkg): ?>
                                <label style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem; cursor: pointer; color: var(--text-dark); user-select: none;">
                                    <input type="checkbox" class="event-package-checkbox" name="allowed_packages[]" value="<?php echo htmlspecialchars($pkg['id']); ?>" checked style="width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer;">
                                    <span><strong><?php echo htmlspecialchars($pkg['name']); ?></strong> (Rp <?php echo number_format($pkg['price'], 0, ',', '.'); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">Centang paket yang ingin ditampilkan di Kiosk / Halaman Pemesanan untuk event ini. Jika tidak dicentang semua, maka otomatis semua paket akan ditampilkan.</span>
                    </div>
                    <?php endif; ?>

                    <div id="rentalDurationFields" style="display: none; flex-direction: column; gap: 16px; border: 1px dashed var(--border-color); padding: 16px; border-radius: 12px; background: rgba(0,0,0,0.02);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label for="event_rental_duration_hours">Durasi Sewa (Jam)</label>
                                <select id="event_rental_duration_hours" name="rental_duration_hours" class="form-input" style="background: white;">
                                    <?php for($i=0; $i<=23; $i++) {
                                        echo "<option value='$i'>$i Jam</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="event_rental_duration_minutes">Durasi Sewa (Menit)</label>
                                <select id="event_rental_duration_minutes" name="rental_duration_minutes" class="form-input" style="background: white;">
                                    <?php for($i=0; $i<=59; $i++) {
                                        echo "<option value='$i'>$i Menit</option>";
                                    } ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="event_reset_rental_timer" name="reset_rental_timer" value="1" style="width: 16px; height: 16px; cursor: pointer;">
                            <label for="event_reset_rental_timer" style="margin-bottom: 0; cursor: pointer; font-size: 0.85rem; font-weight: 600;">Reset / Mulai Ulang Timer Sewa (Timer berjalan saat di-unlock di Kiosk)</label>
                        </div>
                        <div id="activeRentalTimerStatus" style="font-size: 0.75rem; color: var(--primary-accent); font-weight: bold; display: none; margin-top: -8px;"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: end; margin-top: 16px;">
                        <div class="form-group">
                            <label for="event_limit_prints_per_session">Batas Cetak per Sesi Foto</label>
                            <input type="number" id="event_limit_prints_per_session" name="limit_prints_per_session" class="form-input" value="1" min="1" style="background: white;">
                            <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display: block;">Mencegah spam print per sesi tamu.</span>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 6px;">Bingkai Sesi Foto</label>
                            <button type="button" class="btn-secondary" onclick="openFramesModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; font-weight: 600; border-radius: 8px; width: 100%; background: white; border: 1px solid var(--border-color); justify-content: center; cursor: pointer; transition: all 0.2s ease;">
                                <i class="fa-solid fa-images" style="color: var(--primary);"></i>
                                <span id="selectedFramesCountLabel">Kelola Bingkai Sesi (Semua Terpilih)</span>
                            </button>
                        </div>
                    </div>
                    
                    <div id="eventEditorAllowedFramesHiddenContainer"></div>
                    <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <button type="button" class="btn-secondary" onclick="closeEventModal()" style="padding: 10px 20px;">
                            Batal
                        </button>
                        <button type="submit" class="btn-primary" style="padding: 10px 20px;">
                            <i class="fa-solid fa-save"></i> Simpan Event
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>



    <script>
        // Tab switching logic (Supports Desktop sidebar, Mobile bottom-nav, and Bottom Sheet)
        const tabPanes = document.querySelectorAll('.tab-pane');
        const pageTitle = document.querySelector('.page-title');

        function switchTab(tabId, updateHash = true) {
            // Remove active classes from all nav sources
            document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.bottom-nav-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.bottom-sheet-item').forEach(item => item.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            const selectedNavItem = document.querySelector(`.sidebar-nav .nav-item[data-tab="${tabId}"]`);
            const selectedBottomItem = document.querySelector(`.bottom-nav-item[data-tab="${tabId}"]`);
            const selectedSheetItem = document.querySelector(`.bottom-sheet-item[data-tab="${tabId}"]`);
            const selectedTabPane = document.getElementById(`tab-${tabId}`);
            
            if (selectedTabPane) {
                if (selectedNavItem) selectedNavItem.classList.add('active');
                if (selectedBottomItem) selectedBottomItem.classList.add('active');
                if (selectedSheetItem) selectedSheetItem.classList.add('active');
                selectedTabPane.classList.add('active');
                
                if (tabId === 'frames') {
                    requestAnimationFrame(() => {
                        initListFrameOverlays();
                    });
                }
                
                // Set Header title
                let titleText = "";
                if (selectedNavItem) {
                    titleText = selectedNavItem.textContent.trim();
                } else if (selectedBottomItem) {
                    titleText = selectedBottomItem.textContent.trim();
                } else if (selectedSheetItem) {
                    titleText = selectedSheetItem.textContent.trim();
                }
                titleText = titleText.replace(/^[\p{Emoji}\s]+/u, '');
                pageTitle.textContent = titleText;
                
                localStorage.setItem('active_admin_tab', tabId);
                
                if (updateHash) {
                    window.location.hash = tabId;
                }
            }
        }

        // Bind event listeners to all navigation items
        function setupNavigationEvents() {
            // Sidebar Nav Items
            document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab(item.getAttribute('data-tab'));
                });
            });

            // Bottom Nav Items (excluding middle button)
            document.querySelectorAll('.bottom-nav-item:not(.middle-btn)').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab(item.getAttribute('data-tab'));
                });
            });

            // Bottom Sheet Menu Items
            document.querySelectorAll('.bottom-sheet-item:not(.logout)').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab(item.getAttribute('data-tab'));
                    closeBottomSheet(); // Close bottom sheet when a menu item is clicked
                });
            });
        }

        // Bottom Sheet Toggle Logic variables
        let bottomSheet, bottomSheetOverlay, bottomSheetToggle, bottomSheetHandle;

        function openBottomSheet() {
            if (bottomSheet && bottomSheetOverlay) {
                bottomSheet.classList.add('show');
                bottomSheetOverlay.classList.add('show');
            }
        }

        function closeBottomSheet() {
            if (bottomSheet && bottomSheetOverlay) {
                bottomSheet.classList.remove('show');
                bottomSheetOverlay.classList.remove('show');
            }
        }

        function initMobileNavigation() {
            bottomSheet = document.getElementById('bottomSheet');
            bottomSheetOverlay = document.getElementById('bottomSheetOverlay');
            bottomSheetToggle = document.getElementById('bottomSheetToggle');
            bottomSheetHandle = document.getElementById('bottomSheetHandle');

            if (bottomSheetToggle) {
                bottomSheetToggle.addEventListener('click', openBottomSheet);
            }
            if (bottomSheetOverlay) {
                bottomSheetOverlay.addEventListener('click', closeBottomSheet);
            }
            if (bottomSheetHandle) {
                bottomSheetHandle.addEventListener('click', closeBottomSheet);
            }

            // Run navigation setup
            setupNavigationEvents();
        }

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

        // List Frame Overlays (draw dynamic overlays on list view card previews)
        function renderListFrameOverlay(cardEl) {
            const frameDataStr = cardEl.getAttribute('data-frame');
            if (!frameDataStr) return;
            let frame;
            try {
                frame = JSON.parse(frameDataStr);
            } catch(e) {
                return;
            }
            if (!frame.is_dynamic || !frame.dynamic_elements) return;

            const imgEl = cardEl.querySelector('.frame-card-preview-admin img');
            const previewContainer = cardEl.querySelector('.frame-card-preview-admin');
            if (!imgEl || !previewContainer) return;

            const naturalW = imgEl.naturalWidth;
            const naturalH = imgEl.naturalHeight;

            // Get exact layout bounding boxes
            const imgRect = imgEl.getBoundingClientRect();
            const containerRect = previewContainer.getBoundingClientRect();

            const renderedW = imgRect.width;
            const renderedH = imgRect.height;
            const imgLeft = imgRect.left - containerRect.left;
            const imgTop = imgRect.top - containerRect.top;

            if (!naturalW || !naturalH || !renderedW || !renderedH) return;

            const scaleX = renderedW / naturalW;
            const scaleY = renderedH / naturalH;

            let overlayContainer = previewContainer.querySelector('.card-overlay-container');
            if (!overlayContainer) {
                overlayContainer = document.createElement('div');
                overlayContainer.className = 'card-overlay-container';
                overlayContainer.style.position = 'absolute';
                previewContainer.appendChild(overlayContainer);
            }
            
            overlayContainer.style.left = imgLeft + 'px';
            overlayContainer.style.top = imgTop + 'px';
            overlayContainer.style.width = renderedW + 'px';
            overlayContainer.style.height = renderedH + 'px';
            overlayContainer.style.pointerEvents = 'none';
            overlayContainer.style.zIndex = 5;
            overlayContainer.innerHTML = '';

            const de = frame.dynamic_elements;

            // 1. Logo Dummy
            if (de.logo) {
                const logoX = parseFloat(de.logo.x) || 0;
                const logoY = parseFloat(de.logo.y) || 0;
                const logoW = parseFloat(de.logo.width) || 100;
                const logoH = parseFloat(de.logo.height) || 100;

                const logoDiv = document.createElement('div');
                logoDiv.style.position = 'absolute';
                logoDiv.style.left = Math.round(logoX * scaleX) + 'px';
                logoDiv.style.top = Math.round(logoY * scaleY) + 'px';
                logoDiv.style.width = Math.round(logoW * scaleX) + 'px';
                logoDiv.style.height = Math.round(logoH * scaleY) + 'px';
                logoDiv.style.border = '1px dashed #0ea5e9';
                logoDiv.style.background = 'rgba(14, 165, 233, 0.12)';
                logoDiv.style.color = '#0284c7';
                logoDiv.style.display = 'flex';
                logoDiv.style.alignItems = 'center';
                logoDiv.style.justifyContent = 'center';
                logoDiv.style.boxSizing = 'border-box';
                
                const fontSize = Math.max(4, Math.round(7 * scaleY));
                logoDiv.innerHTML = `<div style="font-size: ${fontSize}px; font-weight: 800; text-transform: uppercase;">Logo</div>`;
                overlayContainer.appendChild(logoDiv);
            }

            // 2. Text Dummies
            if (de.texts && de.texts.length > 0) {
                de.texts.forEach(text => {
                    const textX = parseFloat(text.x) || 0;
                    const textY = parseFloat(text.y) || 0;
                    const textSize = parseFloat(text.font_size) || 20;
                    const textColor = text.color || '#000000';
                    const textStyle = text.font_style || 'normal';

                    let label = 'Teks';
                    let dummyText = '[TEKS]';
                    let accentColor = '#6d28d9';
                    let bgColor = 'rgba(139, 92, 246, 0.12)';
                    let borderColor = '#8b5cf6';

                    if (text.type === 'event_name') {
                        label = 'Nama';
                        dummyText = '[NAMA]';
                        accentColor = '#6d28d9';
                        bgColor = 'rgba(139, 92, 246, 0.12)';
                        borderColor = '#8b5cf6';
                    } else if (text.type === 'event_subtitle') {
                        label = 'Sub';
                        dummyText = '[SUBTITLE]';
                        accentColor = '#be185d';
                        bgColor = 'rgba(236, 72, 153, 0.12)';
                        borderColor = '#ec4899';
                    } else if (text.type === 'event_hashtag') {
                        label = 'Hash';
                        dummyText = '[HASH]';
                        accentColor = '#c2410c';
                        bgColor = 'rgba(249, 115, 22, 0.12)';
                        borderColor = '#f97316';
                    }

                    const textDiv = document.createElement('div');
                    textDiv.style.position = 'absolute';
                    textDiv.style.left = Math.round(textX * scaleX) + 'px';
                    textDiv.style.top = Math.round(textY * scaleY) + 'px';
                    textDiv.style.transform = 'translateX(-50%)';
                    textDiv.style.border = `1px dashed ${borderColor}`;
                    textDiv.style.background = bgColor;
                    textDiv.style.padding = '1px 3px';
                    textDiv.style.borderRadius = '3px';
                    textDiv.style.whiteSpace = 'nowrap';
                    textDiv.style.textAlign = 'center';
                    textDiv.style.boxSizing = 'border-box';

                    let fontStyleStr = '';
                    let fontWeightStr = 'normal';
                    if (textStyle === 'bold' || textStyle === 'bold_italic') fontWeightStr = 'bold';
                    if (textStyle === 'italic' || textStyle === 'bold_italic') fontStyleStr = 'italic';

                    const previewFontSize = Math.max(4, Math.round(textSize * scaleY * 0.8));
                    const labelFontSize = Math.max(3, Math.round(5 * scaleY));

                    textDiv.innerHTML = `
                        <div style="font-size: ${labelFontSize}px; font-weight: 800; color: ${accentColor}; text-transform: uppercase; margin-bottom: 0px; line-height: 1;">${label}</div>
                        <div style="font-size: ${previewFontSize}px; color: ${textColor}; font-weight: ${fontWeightStr}; font-style: ${fontStyleStr}; font-family: 'Outfit', sans-serif; line-height: 1;">${dummyText}</div>
                    `;
                    overlayContainer.appendChild(textDiv);
                });
            }
        }

        function initListFrameOverlays() {
            const cards = document.querySelectorAll('.frame-card-admin');
            cards.forEach(card => {
                const img = card.querySelector('.frame-card-preview-admin img');
                if (img) {
                    if (img.complete) {
                        renderListFrameOverlay(card);
                    } else {
                        img.onload = function() {
                            renderListFrameOverlay(card);
                        };
                    }
                }
            });
        }

        function applyFrameFilters() {
            const searchInput = document.getElementById('filterFrameSearch');
            const categorySelect = document.getElementById('filterFrameCategory');
            const typeSelect = document.getElementById('filterFrameType');
            const dynamicSelect = document.getElementById('filterFrameDynamic');
            const eventSelect = document.getElementById('filterFrameEvent');
            if (!searchInput || !categorySelect || !typeSelect || !dynamicSelect || !eventSelect) return;

            const searchVal = searchInput.value.toLowerCase().trim();
            const categoryVal = categorySelect.value;
            const typeVal = typeSelect.value;
            const dynamicVal = dynamicSelect.value;
            const eventVal = eventSelect.value;

            const cards = document.querySelectorAll('.frame-card-admin');
            let visibleCount = 0;

            cards.forEach(card => {
                const frameDataStr = card.getAttribute('data-frame');
                if (!frameDataStr) return;
                
                let frame;
                try {
                    frame = JSON.parse(frameDataStr);
                } catch(e) {
                    return;
                }

                // Match search query
                const matchSearch = !searchVal || frame.name.toLowerCase().includes(searchVal);

                // Match category
                const frameCategory = frame.category || 'Classic';
                const matchCategory = categoryVal === 'all' || frameCategory === categoryVal;
                
                // Match layout type
                const matchType = typeVal === 'all' || frame.type === typeVal;

                // Match dynamic/static nature
                const isFrameDynamic = frame.is_dynamic ? true : false;
                let matchDynamic = true;
                if (dynamicVal === 'dynamic') {
                    matchDynamic = isFrameDynamic === true;
                } else if (dynamicVal === 'static') {
                    matchDynamic = isFrameDynamic === false;
                }

                // Match event
                const frameEventId = frame.event_id || 'general';
                const matchEvent = eventVal === 'all' || frameEventId === eventVal;

                if (matchSearch && matchCategory && matchType && matchDynamic && matchEvent) {
                    card.style.display = 'flex';
                    visibleCount++;
                    // Re-render overlay when shown to ensure layout calculations are accurate
                    if (typeof renderListFrameOverlay === 'function') {
                        renderListFrameOverlay(card);
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // Handle "no frames match" placeholder
            let emptyPlaceholder = document.getElementById('frameFilterEmptyPlaceholder');
            if (visibleCount === 0) {
                if (!emptyPlaceholder) {
                    emptyPlaceholder = document.createElement('div');
                    emptyPlaceholder.id = 'frameFilterEmptyPlaceholder';
                    emptyPlaceholder.style.cssText = 'grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 60px 40px;';
                    emptyPlaceholder.innerHTML = `
                        <i class="fa-regular fa-image" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                        <span style="font-weight: 600; display: block;">Tidak ada bingkai yang cocok dengan filter Anda.</span>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Coba ubah kata kunci atau pilihan filter Anda.</p>
                    `;
                    const grid = document.querySelector('.frames-grid');
                    if (grid) {
                        grid.appendChild(emptyPlaceholder);
                    }
                } else {
                    emptyPlaceholder.style.display = 'block';
                }
            } else {
                if (emptyPlaceholder) {
                    emptyPlaceholder.style.display = 'none';
                }
            }
        }

        function resetFrameFilters() {
            const searchInput = document.getElementById('filterFrameSearch');
            const categorySelect = document.getElementById('filterFrameCategory');
            const typeSelect = document.getElementById('filterFrameType');
            const dynamicSelect = document.getElementById('filterFrameDynamic');
            const eventSelect = document.getElementById('filterFrameEvent');
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = 'all';
            if (typeSelect) typeSelect.value = 'all';
            if (dynamicSelect) dynamicSelect.value = 'all';
            if (eventSelect) eventSelect.value = 'all';
            applyFrameFilters();
        }

        document.addEventListener('DOMContentLoaded', () => {
            initListFrameOverlays();
            
            // Set up filter event listeners
            const searchInput = document.getElementById('filterFrameSearch');
            const categorySelect = document.getElementById('filterFrameCategory');
            const typeSelect = document.getElementById('filterFrameType');
            const dynamicSelect = document.getElementById('filterFrameDynamic');
            const eventSelect = document.getElementById('filterFrameEvent');

            if (searchInput) searchInput.addEventListener('input', applyFrameFilters);
            if (categorySelect) categorySelect.addEventListener('change', applyFrameFilters);
            if (typeSelect) typeSelect.addEventListener('change', applyFrameFilters);
            if (dynamicSelect) dynamicSelect.addEventListener('change', applyFrameFilters);
            if (eventSelect) eventSelect.addEventListener('change', applyFrameFilters);
        });

        window.addEventListener('resize', () => {
            const cards = document.querySelectorAll('.frame-card-admin');
            cards.forEach(card => {
                if (card.style.display !== 'none') {
                    renderListFrameOverlay(card);
                }
            });
        });

        // Zoom Frame Modal Handlers
        let currentFrame = null;
        let currentZoomImg = null;
        let currentBgContainer = null;
        let zoomObserver = null;

        function openFrameZoom(frame) {
            currentFrame = frame;
            
            // Set title
            document.getElementById('zoomFrameTitle').innerText = 'Detail Bingkai: ' + frame.name;
            
            // Set metadata
            document.getElementById('zoomFrameId').innerText = frame.id;
            let fmtName = frame.type.toUpperCase();
            if (frame.type === 'strip') fmtName = 'VERTICAL STRIP';
            else if (frame.type === 'grid') fmtName = 'COLLAGE GRID';
            else if (frame.type === 'postcard') fmtName = 'POSTCARD CARD';
            document.getElementById('zoomFrameType').innerText = fmtName;
            
            // Event Name
            let evtName = "Umum (Default)";
            if (typeof eventsList !== 'undefined') {
                const evt = eventsList.find(e => e.id === frame.event_id);
                if (evt) evtName = evt.name;
            }
            document.getElementById('zoomFrameEvent').innerText = evtName;
            document.getElementById('zoomFrameSlots').innerText = frame.slots.length + ' Foto';
            
            const bgColor = frame.background_color || '#ffffff';
            document.getElementById('zoomFrameBgColorText').innerText = bgColor;
            document.getElementById('zoomFrameBgColorColor').style.backgroundColor = bgColor;
            
            // Set Edit button click handler
            document.getElementById('zoomEditBtn').onclick = function() {
                closeFrameZoom();
                editFrame(frame);
            };
            
            // Create preview layout
            const container = document.getElementById('zoomFrameContainer');
            container.innerHTML = '';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'zoom-wrapper';
            
            const bgContainer = document.createElement('div');
            bgContainer.className = 'zoom-bg-container checkerboard';
            currentBgContainer = bgContainer;
            
            const img = document.createElement('img');
            img.src = frame.image_url + '?v=' + Date.now();
            img.className = 'zoom-frame-img';
            currentZoomImg = img;
            
            wrapper.appendChild(bgContainer);
            wrapper.appendChild(img);
            container.appendChild(wrapper);
            
            // Reset zoom slider to 1.0 and active button states
            document.getElementById('zoomScaleRange').value = 1.0;
            img.style.height = '420px'; // Initial base height
            
            const btnPlain = document.getElementById('btnZoomModePlain');
            const btnMockup = document.getElementById('btnZoomModeMockup');
            btnPlain.classList.add('active');
            btnMockup.classList.remove('active');
            
            // Listen for image load to initialize observer and render slots
            img.onload = function() {
                renderMockupSlots(frame, img, bgContainer);
                
                // Setup ResizeObserver
                if (zoomObserver) {
                    zoomObserver.disconnect();
                }
                zoomObserver = new ResizeObserver(() => {
                    renderMockupSlots(frame, img, bgContainer);
                });
                zoomObserver.observe(img);
            };
            
            // Show Modal
            document.getElementById('frameZoomModal').classList.add('active');
        }

        function closeFrameZoom() {
            document.getElementById('frameZoomModal').classList.remove('active');
            if (zoomObserver) {
                zoomObserver.disconnect();
                zoomObserver = null;
            }
            currentFrame = null;
            currentZoomImg = null;
            currentBgContainer = null;
        }

        function setZoomMode(mode) {
            const btnPlain = document.getElementById('btnZoomModePlain');
            const btnMockup = document.getElementById('btnZoomModeMockup');
            
            if (mode === 'plain') {
                btnPlain.classList.add('active');
                btnMockup.classList.remove('active');
            } else {
                btnPlain.classList.remove('active');
                btnMockup.classList.add('active');
            }
            
            if (currentFrame && currentZoomImg && currentBgContainer) {
                renderMockupSlots(currentFrame, currentZoomImg, currentBgContainer);
            }
        }

        function applyZoomScale(scale) {
            if (currentZoomImg) {
                // base height is 420px, we multiply it by scale
                currentZoomImg.style.height = (420 * scale) + 'px';
            }
        }

        function renderMockupSlots(frame, imgEl, bgContainer) {
            bgContainer.innerHTML = '';
            const naturalW = imgEl.naturalWidth;
            const naturalH = imgEl.naturalHeight;
            const renderedW = imgEl.width;
            const renderedH = imgEl.height;
            
            if (!naturalW || !naturalH || !renderedW || !renderedH) return;
            
            const scaleX = renderedW / naturalW;
            const scaleY = renderedH / naturalH;
            
            // Check if mockup mode is active
            const isMockup = document.getElementById('btnZoomModeMockup').classList.contains('active');
            
            if (isMockup) {
                // Place beautiful sample photos (using Unsplash high-quality portrait URLs)
                const dummyImages = [
                    'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=400'
                ];
                
                frame.slots.forEach((slot, index) => {
                    const slotDiv = document.createElement('div');
                    slotDiv.className = 'zoom-mockup-slot';
                    slotDiv.style.position = 'absolute';
                    slotDiv.style.left = (slot.x * scaleX) + 'px';
                    slotDiv.style.top = (slot.y * scaleY) + 'px';
                    slotDiv.style.width = (slot.width * scaleX) + 'px';
                    slotDiv.style.height = (slot.height * scaleY) + 'px';
                    slotDiv.style.backgroundImage = `url('${dummyImages[index % dummyImages.length]}')`;
                    slotDiv.style.backgroundSize = 'cover';
                    slotDiv.style.backgroundPosition = 'center';
                    slotDiv.style.zIndex = 1;
                    bgContainer.appendChild(slotDiv);
                });
                bgContainer.classList.remove('checkerboard');
            } else {
                bgContainer.classList.add('checkerboard');
            }

            // Draw dynamic overlay elements on top of the frame
            if (bgContainer.parentElement) {
                let overlayContainer = bgContainer.parentElement.querySelector('.zoom-overlay-container');
                if (!overlayContainer) {
                    overlayContainer = document.createElement('div');
                    overlayContainer.className = 'zoom-overlay-container';
                    bgContainer.parentElement.appendChild(overlayContainer);
                }
                overlayContainer.innerHTML = '';

                if (frame.is_dynamic && frame.dynamic_elements) {
                    const de = frame.dynamic_elements;

                    // 1. Logo Dummy
                    if (de.logo) {
                        const logoX = parseFloat(de.logo.x) || 0;
                        const logoY = parseFloat(de.logo.y) || 0;
                        const logoW = parseFloat(de.logo.width) || 100;
                        const logoH = parseFloat(de.logo.height) || 100;

                        const logoDiv = document.createElement('div');
                        logoDiv.className = 'zoom-dynamic-logo';
                        logoDiv.style.left = Math.round(logoX * scaleX) + 'px';
                        logoDiv.style.top = Math.round(logoY * scaleY) + 'px';
                        logoDiv.style.width = Math.round(logoW * scaleX) + 'px';
                        logoDiv.style.height = Math.round(logoH * scaleY) + 'px';
                        logoDiv.style.border = '1.5px dashed #0ea5e9';
                        logoDiv.style.background = 'rgba(14, 165, 233, 0.12)';
                        logoDiv.style.color = '#0284c7';
                        
                        const fontSize = Math.max(6, Math.round(9 * scaleY));
                        logoDiv.innerHTML = `
                            <div style="font-size: ${fontSize}px; font-weight: 800; text-transform: uppercase;">Logo</div>
                        `;
                        overlayContainer.appendChild(logoDiv);
                    }

                    // 2. Text Dummies
                    if (de.texts && de.texts.length > 0) {
                        de.texts.forEach(text => {
                            const textX = parseFloat(text.x) || 0;
                            const textY = parseFloat(text.y) || 0;
                            const textSize = parseFloat(text.font_size) || 20;
                            const textColor = text.color || '#000000';
                            const textStyle = text.font_style || 'normal';

                            let label = 'Teks';
                            let dummyText = '[TEKS EVENT]';
                            let accentColor = '#6d28d9';
                            let bgColor = 'rgba(139, 92, 246, 0.12)';
                            let borderColor = '#8b5cf6';

                            if (text.type === 'event_name') {
                                label = 'Nama Event';
                                dummyText = '[NAMA EVENT]';
                                accentColor = '#6d28d9';
                                bgColor = 'rgba(139, 92, 246, 0.12)';
                                borderColor = '#8b5cf6';
                            } else if (text.type === 'event_subtitle') {
                                label = 'Subtitle';
                                dummyText = '[SUBTITLE / TANGGAL]';
                                accentColor = '#be185d';
                                bgColor = 'rgba(236, 72, 153, 0.12)';
                                borderColor = '#ec4899';
                            } else if (text.type === 'event_hashtag') {
                                label = 'Hashtag';
                                dummyText = '[HASHTAG EVENT]';
                                accentColor = '#c2410c';
                                bgColor = 'rgba(249, 115, 22, 0.12)';
                                borderColor = '#f97316';
                            }

                            const textDiv = document.createElement('div');
                            textDiv.className = 'zoom-dynamic-text';
                            textDiv.style.left = Math.round(textX * scaleX) + 'px';
                            textDiv.style.top = Math.round(textY * scaleY) + 'px';
                            textDiv.style.transform = 'translateX(-50%)';
                            textDiv.style.border = `1.5px dashed ${borderColor}`;
                            textDiv.style.background = bgColor;
                            textDiv.style.padding = '2px 6px';
                            textDiv.style.borderRadius = '4px';
                            textDiv.style.whiteSpace = 'nowrap';
                            textDiv.style.textAlign = 'center';

                            let fontStyleStr = '';
                            let fontWeightStr = 'normal';
                            if (textStyle === 'bold' || textStyle === 'bold_italic') fontWeightStr = 'bold';
                            if (textStyle === 'italic' || textStyle === 'bold_italic') fontStyleStr = 'italic';

                            const previewFontSize = Math.round(textSize * scaleY);
                            const labelFontSize = Math.max(5, Math.round(7 * scaleY));

                            textDiv.innerHTML = `
                                <div style="font-size: ${labelFontSize}px; font-weight: 800; color: ${accentColor}; text-transform: uppercase; margin-bottom: 1px; pointer-events: none;">${label}</div>
                                <div style="font-size: ${previewFontSize}px; color: ${textColor}; font-weight: ${fontWeightStr}; font-style: ${fontStyleStr}; font-family: 'Outfit', sans-serif; line-height: 1.1; pointer-events: none;">${dummyText}</div>
                            `;
                            overlayContainer.appendChild(textDiv);
                        });
                    }
                }
            }
        }

        window.onclick = function(event) {
            if (event.target === modal) {
                closeDetails();
            }
            const zoomModal = document.getElementById('frameZoomModal');
            if (event.target === zoomModal) {
                closeFrameZoom();
            }
            const eventModal = document.getElementById('eventEditorModal');
            if (event.target === eventModal) {
                closeEventModal();
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
            
            // Reset Category & Dynamic Frame fields
            document.getElementById('editorFrameCategorySelect').value = 'Classic';
            if (document.getElementById('editorFrameCategoryCustomGroup')) {
                document.getElementById('editorFrameCategoryCustomGroup').style.display = 'none';
            }
            document.getElementById('editorFrameCategory').value = 'Classic';
            
            // Reset Print Flow checkboxes
            document.getElementById('flowReceiptCheckbox').checked = true;
            document.getElementById('flowColorCheckbox').checked = true;
            document.getElementById('flowIdCardCheckbox').checked = false;
            document.getElementById('editorFrameIsDynamic').checked = false;
            if (typeof toggleDynamicFields === 'function') {
                toggleDynamicFields(false);
            }
            
            document.getElementById('dyn_logo_enable').checked = false;
            document.getElementById('dyn_name_enable').checked = false;
            document.getElementById('dyn_name_color').value = '#000000';
            if (document.getElementById('dyn_name_color_picker')) {
                document.getElementById('dyn_name_color_picker').value = '#000000';
            }
            document.getElementById('dyn_subtitle_enable').checked = false;
            document.getElementById('dyn_subtitle_color').value = '#333333';
            if (document.getElementById('dyn_subtitle_color_picker')) {
                document.getElementById('dyn_subtitle_color_picker').value = '#333333';
            }
            document.getElementById('dyn_hashtag_enable').checked = false;
            document.getElementById('dyn_hashtag_color').value = '#000000';
            if (document.getElementById('dyn_hashtag_color_picker')) {
                document.getElementById('dyn_hashtag_color_picker').value = '#000000';
            }

            document.getElementById('layerModeGroup').style.display = 'none';
            document.getElementById('autoDetectHolesGroup').style.display = 'none';
            setLayerMode('back');
        }

        function hideFrameEditor() {
            document.getElementById('frameEditorView').style.display = 'none';
            document.getElementById('framesListView').style.display = 'block';
            applyFrameFilters();
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
            renderDynamicDummies();
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
                    const r = data[(y * w + x) * 4];
                    const g = data[(y * w + x) * 4 + 1];
                    const b = data[(y * w + x) * 4 + 2];
                    const alpha = data[(y * w + x) * 4 + 3];
                    // Detect transparent OR white/near-white pixels as slot area
                    if (alpha < 80 || (r >= 240 && g >= 240 && b >= 240)) {
                        transCount++;
                    }
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
                        const r = data[(y * w + x) * 4];
                        const g = data[(y * w + x) * 4 + 1];
                        const b = data[(y * w + x) * 4 + 2];
                        const alpha = data[(y * w + x) * 4 + 3];
                        // Detect transparent OR white/near-white pixels as slot area
                        if (alpha < 80 || (r >= 240 && g >= 240 && b >= 240)) {
                            transCount++;
                        }
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
            
            // Populate category
            var cat = frame.category || 'Classic';
            var selectVal = cat;
            var customGroupDisplay = 'none';
            if (cat !== 'Classic' && cat !== 'Creative' && cat !== 'Aesthetic' && cat !== 'Y2K' && cat !== 'Magazine' && cat !== 'Receipt' && cat !== 'Dynamic') {
                selectVal = '__custom__';
                customGroupDisplay = 'block';
                document.getElementById('editorFrameCategoryCustom').value = cat;
            }
            document.getElementById('editorFrameCategorySelect').value = selectVal;
            document.getElementById('editorFrameCategoryCustomGroup').style.display = customGroupDisplay;
            document.getElementById('editorFrameCategory').value = cat;
            
            // Populate print flows
            var printFlows = frame.print_flows || [];
            if (printFlows.length === 0) {
                // Fallback mapping based on legacy frame type
                if (frame.type === 'strip') {
                    printFlows = ['RECEIPT', 'COLOR_PRINT'];
                } else if (frame.type === 'grid' || frame.type === 'postcard') {
                    printFlows = ['COLOR_PRINT'];
                }
            }
            document.getElementById('flowReceiptCheckbox').checked = printFlows.includes('RECEIPT');
            document.getElementById('flowColorCheckbox').checked = printFlows.includes('COLOR_PRINT');
            document.getElementById('flowIdCardCheckbox').checked = printFlows.includes('ID_CARD');
            
            // Populate dynamic elements
            var isDyn = frame.is_dynamic ? true : false;
            document.getElementById('editorFrameIsDynamic').checked = isDyn;
            toggleDynamicFields(isDyn);
            
            if (isDyn && frame.dynamic_elements) {
                var de = frame.dynamic_elements;
                
                // Logo
                if (de.logo) {
                    document.getElementById('dyn_logo_enable').checked = true;
                    document.getElementById('dyn_logo_x').value = de.logo.x || 300;
                    document.getElementById('dyn_logo_y').value = de.logo.y || 1720;
                    document.getElementById('dyn_logo_w').value = de.logo.width || 120;
                    document.getElementById('dyn_logo_h').value = de.logo.height || 120;
                } else {
                    document.getElementById('dyn_logo_enable').checked = false;
                }
                
                // Texts mapping
                var nameEl = de.texts ? de.texts.find(t => t.type === 'event_name') : null;
                var subEl = de.texts ? de.texts.find(t => t.type === 'event_subtitle') : null;
                var hashEl = de.texts ? de.texts.find(t => t.type === 'event_hashtag') : null;
                
                if (nameEl) {
                    document.getElementById('dyn_name_enable').checked = true;
                    document.getElementById('dyn_name_x').value = nameEl.x || 300;
                    document.getElementById('dyn_name_y').value = nameEl.y || 1860;
                    document.getElementById('dyn_name_size').value = nameEl.font_size || 28;
                    document.getElementById('dyn_name_color').value = nameEl.color || '#000000';
                    if (document.getElementById('dyn_name_color_picker')) {
                        document.getElementById('dyn_name_color_picker').value = nameEl.color || '#000000';
                    }
                    document.getElementById('dyn_name_style').value = nameEl.font_style || 'bold';
                } else {
                    document.getElementById('dyn_name_enable').checked = false;
                }
                
                if (subEl) {
                    document.getElementById('dyn_subtitle_enable').checked = true;
                    document.getElementById('dyn_subtitle_x').value = subEl.x || 300;
                    document.getElementById('dyn_subtitle_y').value = subEl.y || 1900;
                    document.getElementById('dyn_subtitle_size').value = subEl.font_size || 20;
                    document.getElementById('dyn_subtitle_color').value = subEl.color || '#333333';
                    if (document.getElementById('dyn_subtitle_color_picker')) {
                        document.getElementById('dyn_subtitle_color_picker').value = subEl.color || '#333333';
                    }
                    document.getElementById('dyn_subtitle_style').value = subEl.font_style || 'normal';
                } else {
                    document.getElementById('dyn_subtitle_enable').checked = false;
                }
                
                if (hashEl) {
                    document.getElementById('dyn_hashtag_enable').checked = true;
                    document.getElementById('dyn_hashtag_x').value = hashEl.x || 300;
                    document.getElementById('dyn_hashtag_y').value = hashEl.y || 1930;
                    document.getElementById('dyn_hashtag_size').value = hashEl.font_size || 16;
                    document.getElementById('dyn_hashtag_color').value = hashEl.color || '#000000';
                    if (document.getElementById('dyn_hashtag_color_picker')) {
                        document.getElementById('dyn_hashtag_color_picker').value = hashEl.color || '#000000';
                    }
                    document.getElementById('dyn_hashtag_style').value = hashEl.font_style || 'italic';
                } else {
                    document.getElementById('dyn_hashtag_enable').checked = false;
                }
            } else {
                // Reset all dynamic fields inputs
                document.getElementById('dyn_logo_enable').checked = false;
                document.getElementById('dyn_name_enable').checked = false;
                document.getElementById('dyn_name_color').value = '#000000';
                if (document.getElementById('dyn_name_color_picker')) {
                    document.getElementById('dyn_name_color_picker').value = '#000000';
                }
                document.getElementById('dyn_subtitle_enable').checked = false;
                document.getElementById('dyn_subtitle_color').value = '#333333';
                if (document.getElementById('dyn_subtitle_color_picker')) {
                    document.getElementById('dyn_subtitle_color_picker').value = '#333333';
                }
                document.getElementById('dyn_hashtag_enable').checked = false;
                document.getElementById('dyn_hashtag_color').value = '#000000';
                if (document.getElementById('dyn_hashtag_color_picker')) {
                    document.getElementById('dyn_hashtag_color_picker').value = '#000000';
                }
            }

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

        function editEvent(evt) {
            const isGeneral = evt.id === 'general';

            document.getElementById('eventFormTitle').innerHTML = isGeneral
                ? '<i class="fa-solid fa-store" style="color: var(--primary);"></i> <span>Edit Profil Jasa / Layanan Umum</span>'
                : '<i class="fa-solid fa-calendar-minus" style="color: var(--primary);"></i> <span>Edit Event: ' + evt.name + '</span>';

            document.getElementById('event_id').value = evt.id;
            document.getElementById('event_id').readOnly = true; // Protect key modification
            document.getElementById('eventIdNote').innerText = isGeneral
                ? "Profil jasa default (ditampilkan saat tidak ada event aktif)."
                : "ID Event tidak dapat diubah setelah dibuat.";

            document.getElementById('event_name').value = evt.name;
            document.getElementById('event_code').value = evt.code || '';
            document.getElementById('event_subtitle').value = evt.subtitle || '';
            document.getElementById('event_hashtag').value = evt.hashtag || '';
            document.getElementById('event_date').value = evt.event_date || '';
            document.getElementById('event_location').value = evt.event_location || '';
            
            // Hide event_code field for 'general' — it doesn't use an access code
            const codeGroup = document.getElementById('event_code').closest('.form-group');
            if (isGeneral) {
                codeGroup.style.display = 'none';
                document.getElementById('event_code').removeAttribute('required');
                document.getElementById('event_code').value = 'UMUM'; // keep placeholder value
            } else {
                codeGroup.style.display = '';
                document.getElementById('event_code').setAttribute('required', 'required');
            }
            
            document.getElementById('primary_color').value = evt.primary_color || '#e63946';
            document.getElementById('primaryColorPicker').value = evt.primary_color || '#e63946';
            document.getElementById('secondary_color').value = evt.secondary_color || '#ffffff';
            document.getElementById('secondaryColorPicker').value = evt.secondary_color || '#ffffff';
            
            if (evt.logo_url) {
                document.getElementById('eventLogoPreviewImg').src = evt.logo_url + '?v=' + Date.now();
                document.getElementById('eventLogoPreviewContainer').style.display = 'flex';
            } else {
                document.getElementById('eventLogoPreviewContainer').style.display = 'none';
            }
            
            document.getElementById('event_billing_type').value = evt.billing_type || 'PAY_PER_SESSION';
            
            // Populate duration
            let hours = 1;
            let minutes = 0;
            if (evt.rental_duration_hours !== undefined) {
                hours = evt.rental_duration_hours;
                minutes = evt.rental_duration_minutes || 0;
            } else if (evt.rental_start_time && evt.rental_end_time) {
                let start = new Date(evt.rental_start_time.replace(' ', 'T'));
                let end = new Date(evt.rental_end_time.replace(' ', 'T'));
                let diffMs = end - start;
                if (diffMs > 0) {
                    let totalMins = Math.floor(diffMs / 60000);
                    hours = Math.floor(totalMins / 60);
                    minutes = totalMins % 60;
                }
            }
            document.getElementById('event_rental_duration_hours').value = hours;
            document.getElementById('event_rental_duration_minutes').value = minutes;
            document.getElementById('event_reset_rental_timer').checked = false; // default unchecked
            
            // Show active timer status
            const statusEl = document.getElementById('activeRentalTimerStatus');
            if (evt.rental_start_time && evt.rental_end_time) {
                statusEl.innerText = "Sewa Sedang Berjalan: " + evt.rental_start_time + " s.d " + evt.rental_end_time;
                statusEl.style.display = "block";
            } else {
                statusEl.innerText = "Status Sewa: Belum Dimulai (Akan berjalan saat di-unlock di Kiosk)";
                statusEl.style.display = "block";
            }
            
            document.getElementById('event_limit_prints_per_session').value = evt.limit_prints_per_session !== undefined ? evt.limit_prints_per_session : 1;
            toggleBillingFields();

            // Load allowed frames into checkboxes and card selection states
            document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                cb.checked = false;
                updateFrameCardStyle(cb.value);
            });
            if (evt.allowed_frames && Array.isArray(evt.allowed_frames)) {
                evt.allowed_frames.forEach(frameId => {
                    const cb = document.querySelector(`.rental-frame-checkbox[value="${frameId}"]`);
                    if (cb) {
                        cb.checked = true;
                        updateFrameCardStyle(frameId);
                    }
                });
            } else {
                // If allowed_frames is not set (e.g. legacy events), check all by default
                document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                    cb.checked = true;
                    updateFrameCardStyle(cb.value);
                });
            }
            updateSelectedFramesCount();
            syncHiddenAllowedFrames();

            // Load allowed packages
            document.querySelectorAll('.event-package-checkbox').forEach(cb => {
                cb.checked = false;
            });
            if (evt.allowed_packages && Array.isArray(evt.allowed_packages)) {
                evt.allowed_packages.forEach(pkgId => {
                    const cb = document.querySelector(`.event-package-checkbox[value="${pkgId}"]`);
                    if (cb) {
                        cb.checked = true;
                    }
                });
            } else {
                // If allowed_packages is not set (e.g. legacy/new events), check all by default
                document.querySelectorAll('.event-package-checkbox').forEach(cb => {
                    cb.checked = true;
                });
            }

            document.getElementById('eventIsEditing').value = '1';
            
            // Show modal
            document.getElementById('eventEditorModal').classList.add('active');
            document.getElementById('event_name').focus();
        }

        function resetEventForm() {
            document.getElementById('eventFormTitle').innerHTML = '<i class="fa-solid fa-calendar-plus" style="color: var(--primary);"></i> <span>Tambah Event Baru</span>';
            document.getElementById('event_id').value = '';
            document.getElementById('event_id').readOnly = false;
            document.getElementById('eventIdNote').innerText = "ID unik sistem, tidak boleh mengandung spasi.";
            document.getElementById('event_name').value = '';
            document.getElementById('event_code').value = '';
            document.getElementById('event_subtitle').value = '';
            document.getElementById('event_hashtag').value = '';
            document.getElementById('event_date').value = '';
            document.getElementById('event_location').value = '';
            
            // Always restore code field when resetting
            const codeGroup = document.getElementById('event_code').closest('.form-group');
            codeGroup.style.display = '';
            document.getElementById('event_code').setAttribute('required', 'required');
            
            document.getElementById('primary_color').value = '#e63946';
            document.getElementById('primaryColorPicker').value = '#e63946';
            document.getElementById('secondary_color').value = '#ffffff';
            document.getElementById('secondaryColorPicker').value = '#ffffff';
            
            document.getElementById('event_billing_type').value = 'PAY_PER_SESSION';
            document.getElementById('event_rental_duration_hours').value = '1';
            document.getElementById('event_rental_duration_minutes').value = '0';
            document.getElementById('event_reset_rental_timer').checked = false;
            document.getElementById('activeRentalTimerStatus').style.display = 'none';
            document.getElementById('event_limit_prints_per_session').value = '1';
            
            // Check all packages by default when resetting
            document.querySelectorAll('.event-package-checkbox').forEach(cb => {
                cb.checked = true;
            });
            
            toggleBillingFields();

            // Check all frames by default when resetting/creating a new event
            document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                cb.checked = true;
                updateFrameCardStyle(cb.value);
            });
            updateSelectedFramesCount();
            syncHiddenAllowedFrames();

            document.getElementById('eventLogoPreviewContainer').style.display = 'none';
            document.getElementById('eventIsEditing').value = '0';
        }

        function toggleBillingFields() {
            const billingType = document.getElementById('event_billing_type').value;
            const fieldsContainer = document.getElementById('rentalDurationFields');
            const payPerSessionContainer = document.getElementById('payPerSessionFields');
            if (billingType === 'RENTAL_DURATION') {
                fieldsContainer.style.display = 'flex';
                if (payPerSessionContainer) payPerSessionContainer.style.display = 'none';
            } else {
                fieldsContainer.style.display = 'none';
                if (payPerSessionContainer) payPerSessionContainer.style.display = 'flex';
            }
        }

        function openEventModal() {
            resetEventForm();
            document.getElementById('eventEditorModal').classList.add('active');
            document.getElementById('event_id').focus();
        }

        function closeEventModal() {
            document.getElementById('eventEditorModal').classList.remove('active');
        }

        // Functions for managing eventFramesModal
        let activeEditFramesEventId = null;
        let isOpenedFromRow = false;
        let originalCheckedStates = {};

        function openFramesModal() {
            isOpenedFromRow = false;
            activeEditFramesEventId = null;
            backupCheckedStates();
            
            // Reset filters
            const searchInput = document.getElementById('modalFrameSearch');
            const typeSelect = document.getElementById('modalFrameTypeFilter');
            const categorySelect = document.getElementById('modalFrameCategoryFilter');
            const selectedOnlyCheckbox = document.getElementById('modalFrameSelectedOnly');
            const dynamicOnlyCheckbox = document.getElementById('modalFrameDynamicOnly');
            if (searchInput) searchInput.value = '';
            if (typeSelect) typeSelect.value = 'all';
            if (categorySelect) categorySelect.value = 'all';
            if (selectedOnlyCheckbox) selectedOnlyCheckbox.checked = false;
            if (dynamicOnlyCheckbox) dynamicOnlyCheckbox.checked = false;
            filterModalFrames();

            document.getElementById('eventFramesModal').classList.add('active');
        }

        function openFramesModalFromRow(evt) {
            isOpenedFromRow = true;
            activeEditFramesEventId = evt.id;
            backupCheckedStates();
            
            // Uncheck all first
            document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                cb.checked = false;
                updateFrameCardStyle(cb.value);
            });
            
            // Check allowed ones
            const allowedFrames = evt.allowed_frames;
            if (allowedFrames && Array.isArray(allowedFrames)) {
                allowedFrames.forEach(frameId => {
                    const cb = document.querySelector(`.rental-frame-checkbox[value="${frameId}"]`);
                    if (cb) {
                        cb.checked = true;
                        updateFrameCardStyle(frameId);
                    }
                });
            } else {
                // If not set, check all by default
                document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                    cb.checked = true;
                    updateFrameCardStyle(cb.value);
                });
            }
            updateSelectedFramesCount();
            
            // Reset filters
            const searchInput = document.getElementById('modalFrameSearch');
            const typeSelect = document.getElementById('modalFrameTypeFilter');
            const categorySelect = document.getElementById('modalFrameCategoryFilter');
            const selectedOnlyCheckbox = document.getElementById('modalFrameSelectedOnly');
            const dynamicOnlyCheckbox = document.getElementById('modalFrameDynamicOnly');
            if (searchInput) searchInput.value = '';
            if (typeSelect) typeSelect.value = 'all';
            if (categorySelect) categorySelect.value = 'all';
            if (selectedOnlyCheckbox) selectedOnlyCheckbox.checked = false;
            if (dynamicOnlyCheckbox) dynamicOnlyCheckbox.checked = false;
            filterModalFrames();

            document.getElementById('eventFramesModal').classList.add('active');
        }

        function backupCheckedStates() {
            originalCheckedStates = {};
            document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                originalCheckedStates[cb.value] = cb.checked;
            });
        }

        function restoreCheckedStates() {
            document.querySelectorAll('.rental-frame-checkbox').forEach(cb => {
                if (originalCheckedStates[cb.value] !== undefined) {
                    cb.checked = originalCheckedStates[cb.value];
                    updateFrameCardStyle(cb.value);
                }
            });
            updateSelectedFramesCount();
        }

        function cancelFramesModal() {
            restoreCheckedStates();
            document.getElementById('eventFramesModal').classList.remove('active');
        }

        function saveFramesModal() {
            document.getElementById('eventFramesModal').classList.remove('active');
            updateSelectedFramesCount();
            
            if (isOpenedFromRow && activeEditFramesEventId) {
                const allowed = [];
                document.querySelectorAll('.rental-frame-checkbox:checked').forEach(cb => {
                    allowed.push(cb.value);
                });
                
                const formData = new FormData();
                formData.append('action', 'save_event_frames');
                formData.append('event_id', activeEditFramesEventId);
                allowed.forEach(f => formData.append('allowed_frames[]', f));
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Gagal menyimpan bingkai event: ' + (data.message || 'unknown error'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Gagal mengirim data bingkai.');
                });
            } else {
                syncHiddenAllowedFrames();
            }
        }

        function syncHiddenAllowedFrames() {
            const container = document.getElementById('eventEditorAllowedFramesHiddenContainer');
            if (!container) return;
            container.innerHTML = '';
            
            document.querySelectorAll('.rental-frame-checkbox:checked').forEach(cb => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'allowed_frames[]';
                hiddenInput.value = cb.value;
                container.appendChild(hiddenInput);
            });
        }

        function updateFrameCardStyle(frameId) {
            const cb = document.querySelector(`.rental-frame-checkbox[value="${frameId}"]`);
            const card = document.querySelector(`.frame-select-card[data-frame-id="${frameId}"]`);
            if (cb && card) {
                if (cb.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            }
        }

        function toggleFrameCardSelection(frameId) {
            const cb = document.querySelector(`.rental-frame-checkbox[value="${frameId}"]`);
            if (cb) {
                cb.checked = !cb.checked;
                updateFrameCardStyle(frameId);
                filterModalFrames();
            }
        }

        function selectAllFrames(state) {
            document.querySelectorAll('.frame-select-card').forEach(card => {
                if (card.style.display !== 'none') {
                    const cb = card.querySelector('.rental-frame-checkbox');
                    if (cb) {
                        cb.checked = state;
                        updateFrameCardStyle(cb.value);
                    }
                }
            });
            updateSelectedFramesCount();
            filterModalFrames();
        }

        // category: 'dynamic' or 'static'
        function selectCategoryFrames(category, state) {
            document.querySelectorAll(`.frame-select-card[data-category="${category}"]`).forEach(card => {
                if (card.style.display !== 'none') {
                    const cb = card.querySelector('.rental-frame-checkbox');
                    if (cb) {
                        cb.checked = state;
                        updateFrameCardStyle(cb.value);
                    }
                }
            });
            updateSelectedFramesCount();
            filterModalFrames();
        }

        function updateSelectedFramesCount() {
            const total = document.querySelectorAll('.rental-frame-checkbox').length;
            const checked = document.querySelectorAll('.rental-frame-checkbox:checked').length;
            const label = document.getElementById('selectedFramesCountLabel');
            if (label) {
                if (checked === total) {
                    label.innerText = `Kelola Bingkai Sesi (Semua Terpilih)`;
                } else if (checked === 0) {
                    label.innerText = `Kelola Bingkai Sesi (Belum Ada Terpilih)`;
                } else {
                    label.innerText = `Kelola Bingkai Sesi (${checked} Terpilih)`;
                }
            }
        }

        function filterModalFrames() {
            const searchInput = document.getElementById('modalFrameSearch');
            const typeSelect = document.getElementById('modalFrameTypeFilter');
            const selectedOnlyCheckbox = document.getElementById('modalFrameSelectedOnly');
            const categorySelect = document.getElementById('modalFrameCategoryFilter');
            const dynamicOnlyCheckbox = document.getElementById('modalFrameDynamicOnly');
            if (!searchInput || !typeSelect || !selectedOnlyCheckbox) return;

            const searchVal = searchInput.value.toLowerCase().trim();
            const typeVal = typeSelect.value;
            const selectedOnlyVal = selectedOnlyCheckbox.checked;
            const categoryVal = categorySelect ? categorySelect.value : 'all';
            const dynamicOnlyVal = dynamicOnlyCheckbox ? dynamicOnlyCheckbox.checked : false;

            const cards = document.querySelectorAll('.frame-select-card');
            let visibleDynamic = 0;
            let visibleStatic = 0;
            let totalVisible = 0;

            cards.forEach(card => {
                const nameEl = card.querySelector('.frame-select-name');
                const name = nameEl ? nameEl.innerText.toLowerCase() : '';
                const type = card.getAttribute('data-type') || '';
                const category = card.getAttribute('data-category') || '';
                const categoryName = card.getAttribute('data-category-name') || 'Classic';
                
                const cb = card.querySelector('.rental-frame-checkbox');
                const isChecked = cb ? cb.checked : false;

                // Match Search Name
                const matchSearch = !searchVal || name.includes(searchVal);

                // Match Type
                const matchType = typeVal === 'all' || type === typeVal;

                // Match Checked
                const matchChecked = !selectedOnlyVal || isChecked;

                // Match Category Name
                const matchCategoryName = categoryVal === 'all' || categoryName === categoryVal;

                // Match Dynamic Only
                const matchDynamicOnly = !dynamicOnlyVal || category === 'dynamic';

                if (matchSearch && matchType && matchChecked && matchCategoryName && matchDynamicOnly) {
                    card.style.display = 'flex';
                    totalVisible++;
                    if (category === 'dynamic') {
                        visibleDynamic++;
                    } else if (category === 'static') {
                        visibleStatic++;
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // Toggle category section wrappers
            const dynSection = document.getElementById('dynamicCategorySection');
            if (dynSection) {
                dynSection.style.display = visibleDynamic > 0 ? 'block' : 'none';
            }

            const statSection = document.getElementById('staticCategorySection');
            if (statSection) {
                statSection.style.display = visibleStatic > 0 ? 'block' : 'none';
            }

            // Global placeholder if everything is hidden
            const placeholder = document.getElementById('modalFramesEmptyPlaceholder');
            if (placeholder) {
                placeholder.style.display = totalVisible === 0 ? 'block' : 'none';
            }

            // Update label
            const label = document.getElementById('modalFrameCountLabel');
            if (label) {
                const totalCards = cards.length;
                if (totalVisible === totalCards) {
                    label.innerText = `Menampilkan semua ${totalCards} bingkai`;
                } else {
                    label.innerText = `Menampilkan ${totalVisible} dari ${totalCards} bingkai`;
                }
            }
        }

        function toggleDynamicFields(show) {
            var container = document.getElementById('dynamicFieldsContainer');
            if (container) {
                container.style.display = show ? 'flex' : 'none';
            }
            renderDynamicDummies();
        }

        function renderDynamicDummies() {
            const wrapper = document.getElementById('canvasWrapper');
            if (!wrapper) return;
            
            // Clear old dynamic dummies
            const oldDummies = wrapper.querySelectorAll('.dyn-dummy-rect');
            oldDummies.forEach(d => d.remove());
            
            // Check if dynamic mode is active
            const isDynamic = document.getElementById('editorFrameIsDynamic').checked;
            if (!isDynamic) return;
            
            const previewImg = document.getElementById('canvasImg');
            if (!previewImg || previewImg.naturalWidth === 0) return;
            
            const previewW = previewImg.clientWidth;
            const previewH = previewImg.clientHeight;
            
            const naturalW = previewImg.naturalWidth;
            const naturalH = previewImg.naturalHeight;
            
            const scaleX = naturalW / previewW;
            const scaleY = naturalH / previewH;
            
            // 1. Logo Dummy
            const logoEnable = document.getElementById('dyn_logo_enable').checked;
            if (logoEnable) {
                const logoX = parseFloat(document.getElementById('dyn_logo_x').value) || 0;
                const logoY = parseFloat(document.getElementById('dyn_logo_y').value) || 0;
                const logoW = parseFloat(document.getElementById('dyn_logo_w').value) || 100;
                const logoH = parseFloat(document.getElementById('dyn_logo_h').value) || 100;
                
                const dummy = document.createElement('div');
                dummy.className = 'dyn-dummy-rect dyn-dummy-logo';
                dummy.style.left = Math.round(logoX / scaleX) + 'px';
                dummy.style.top = Math.round(logoY / scaleY) + 'px';
                dummy.style.width = Math.round(logoW / scaleX) + 'px';
                dummy.style.height = Math.round(logoH / scaleY) + 'px';
                
                dummy.innerHTML = `
                    <div class="dyn-dummy-meta-label" style="color: #0284c7;">Logo</div>
                    <i class="fa-regular fa-image" style="font-size: 14px; margin-bottom: 2px;"></i>
                    <div class="slot-rect-close" onclick="deleteDynamicDummy('logo')">&times;</div>
                    <div class="slot-rect-resize"></div>
                `;
                
                setupDynamicDummyInteract(dummy, 'logo', scaleX, scaleY);
                wrapper.appendChild(dummy);
            }
            
            // 2. Name Dummy
            const nameEnable = document.getElementById('dyn_name_enable').checked;
            if (nameEnable) {
                const nameX = parseFloat(document.getElementById('dyn_name_x').value) || 0;
                const nameY = parseFloat(document.getElementById('dyn_name_y').value) || 0;
                const nameSize = parseFloat(document.getElementById('dyn_name_size').value) || 24;
                const nameColor = document.getElementById('dyn_name_color').value || '#000000';
                const nameStyle = document.getElementById('dyn_name_style').value || 'normal';
                
                const dummy = document.createElement('div');
                dummy.className = 'dyn-dummy-rect dyn-dummy-text dyn-dummy-name';
                dummy.style.left = Math.round(nameX / scaleX) + 'px';
                dummy.style.top = Math.round(nameY / scaleY) + 'px';
                
                let fontStyleStr = '';
                let fontWeightStr = 'normal';
                if (nameStyle === 'bold' || nameStyle === 'bold_italic') fontWeightStr = 'bold';
                if (nameStyle === 'italic' || nameStyle === 'bold_italic') fontStyleStr = 'italic';
                
                const previewFontSize = Math.round(nameSize / scaleY);
                
                dummy.innerHTML = `
                    <div class="dyn-dummy-meta-label" style="color: #6d28d9;">Nama Event</div>
                    <div class="dyn-dummy-text-content" style="font-size: ${previewFontSize}px; color: ${nameColor}; font-weight: ${fontWeightStr}; font-style: ${fontStyleStr};">[NAMA EVENT]</div>
                    <div class="slot-rect-close" onclick="deleteDynamicDummy('name')">&times;</div>
                    <div class="slot-rect-resize"></div>
                `;
                
                setupDynamicDummyInteract(dummy, 'name', scaleX, scaleY);
                wrapper.appendChild(dummy);
            }
            
            // 3. Subtitle Dummy
            const subEnable = document.getElementById('dyn_subtitle_enable').checked;
            if (subEnable) {
                const subX = parseFloat(document.getElementById('dyn_subtitle_x').value) || 0;
                const subY = parseFloat(document.getElementById('dyn_subtitle_y').value) || 0;
                const subSize = parseFloat(document.getElementById('dyn_subtitle_size').value) || 20;
                const subColor = document.getElementById('dyn_subtitle_color').value || '#333333';
                const subStyle = document.getElementById('dyn_subtitle_style').value || 'normal';
                
                const dummy = document.createElement('div');
                dummy.className = 'dyn-dummy-rect dyn-dummy-text dyn-dummy-subtitle';
                dummy.style.left = Math.round(subX / scaleX) + 'px';
                dummy.style.top = Math.round(subY / scaleY) + 'px';
                
                let fontStyleStr = '';
                let fontWeightStr = 'normal';
                if (subStyle === 'bold' || subStyle === 'bold_italic') fontWeightStr = 'bold';
                if (subStyle === 'italic' || subStyle === 'bold_italic') fontStyleStr = 'italic';
                
                const previewFontSize = Math.round(subSize / scaleY);
                
                dummy.innerHTML = `
                    <div class="dyn-dummy-meta-label" style="color: #be185d;">Subtitle</div>
                    <div class="dyn-dummy-text-content" style="font-size: ${previewFontSize}px; color: ${subColor}; font-weight: ${fontWeightStr}; font-style: ${fontStyleStr};">[SUBTITLE / TANGGAL]</div>
                    <div class="slot-rect-close" onclick="deleteDynamicDummy('subtitle')">&times;</div>
                    <div class="slot-rect-resize"></div>
                `;
                
                setupDynamicDummyInteract(dummy, 'subtitle', scaleX, scaleY);
                wrapper.appendChild(dummy);
            }
            
            // 4. Hashtag Dummy
            const hashEnable = document.getElementById('dyn_hashtag_enable').checked;
            if (hashEnable) {
                const hashX = parseFloat(document.getElementById('dyn_hashtag_x').value) || 0;
                const hashY = parseFloat(document.getElementById('dyn_hashtag_y').value) || 0;
                const hashSize = parseFloat(document.getElementById('dyn_hashtag_size').value) || 16;
                const hashColor = document.getElementById('dyn_hashtag_color').value || '#666666';
                const hashStyle = document.getElementById('dyn_hashtag_style').value || 'normal';
                
                const dummy = document.createElement('div');
                dummy.className = 'dyn-dummy-rect dyn-dummy-text dyn-dummy-hashtag';
                dummy.style.left = Math.round(hashX / scaleX) + 'px';
                dummy.style.top = Math.round(hashY / scaleY) + 'px';
                
                let fontStyleStr = '';
                let fontWeightStr = 'normal';
                if (hashStyle === 'bold' || hashStyle === 'bold_italic') fontWeightStr = 'bold';
                if (hashStyle === 'italic' || hashStyle === 'bold_italic') fontStyleStr = 'italic';
                
                const previewFontSize = Math.round(hashSize / scaleY);
                
                dummy.innerHTML = `
                    <div class="dyn-dummy-meta-label" style="color: #c2410c;">Hashtag</div>
                    <div class="dyn-dummy-text-content" style="font-size: ${previewFontSize}px; color: ${hashColor}; font-weight: ${fontWeightStr}; font-style: ${fontStyleStr};">[HASHTAG EVENT]</div>
                    <div class="slot-rect-close" onclick="deleteDynamicDummy('hashtag')">&times;</div>
                    <div class="slot-rect-resize"></div>
                `;
                
                setupDynamicDummyInteract(dummy, 'hashtag', scaleX, scaleY);
                wrapper.appendChild(dummy);
            }
        }

        function setupDynamicDummyInteract(el, type, scaleX, scaleY) {
            const wrapper = document.getElementById('canvasWrapper');
            const resizeHandle = el.querySelector('.slot-rect-resize');
            
            el.addEventListener('mousedown', function(e) {
                if (e.target.classList.contains('slot-rect-resize') || e.target.classList.contains('slot-rect-close')) return;
                
                e.preventDefault();
                e.stopPropagation();
                
                const startX = e.clientX;
                const startY = e.clientY;
                
                let startLeft = 0;
                let startTop = 0;
                
                if (type === 'logo') {
                    startLeft = parseFloat(document.getElementById('dyn_logo_x').value) || 0;
                    startTop = parseFloat(document.getElementById('dyn_logo_y').value) || 0;
                } else if (type === 'name') {
                    startLeft = parseFloat(document.getElementById('dyn_name_x').value) || 0;
                    startTop = parseFloat(document.getElementById('dyn_name_y').value) || 0;
                } else if (type === 'subtitle') {
                    startLeft = parseFloat(document.getElementById('dyn_subtitle_x').value) || 0;
                    startTop = parseFloat(document.getElementById('dyn_subtitle_y').value) || 0;
                } else if (type === 'hashtag') {
                    startLeft = parseFloat(document.getElementById('dyn_hashtag_x').value) || 0;
                    startTop = parseFloat(document.getElementById('dyn_hashtag_y').value) || 0;
                }
                
                function onMouseMove(moveEvent) {
                    const dx = moveEvent.clientX - startX;
                    const dy = moveEvent.clientY - startY;
                    
                    const naturalDx = Math.round(dx * scaleX);
                    const naturalDy = Math.round(dy * scaleY);
                    
                    const newLeft = startLeft + naturalDx;
                    const newTop = startTop + naturalDy;
                    
                    if (type === 'logo') {
                        document.getElementById('dyn_logo_x').value = newLeft;
                        document.getElementById('dyn_logo_y').value = newTop;
                        el.style.left = Math.round(newLeft / scaleX) + 'px';
                        el.style.top = Math.round(newTop / scaleY) + 'px';
                    } else if (type === 'name') {
                        document.getElementById('dyn_name_x').value = newLeft;
                        document.getElementById('dyn_name_y').value = newTop;
                        el.style.left = Math.round(newLeft / scaleX) + 'px';
                        el.style.top = Math.round(newTop / scaleY) + 'px';
                    } else if (type === 'subtitle') {
                        document.getElementById('dyn_subtitle_x').value = newLeft;
                        document.getElementById('dyn_subtitle_y').value = newTop;
                        el.style.left = Math.round(newLeft / scaleX) + 'px';
                        el.style.top = Math.round(newTop / scaleY) + 'px';
                    } else if (type === 'hashtag') {
                        document.getElementById('dyn_hashtag_x').value = newLeft;
                        document.getElementById('dyn_hashtag_y').value = newTop;
                        el.style.left = Math.round(newLeft / scaleX) + 'px';
                        el.style.top = Math.round(newTop / scaleY) + 'px';
                    }
                }
                
                function onMouseUp() {
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    saveHistoryState();
                }
                
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
            
            if (resizeHandle) {
                if (type === 'logo') {
                    resizeHandle.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const startX = e.clientX;
                        const startY = e.clientY;
                        
                        const startW = parseFloat(document.getElementById('dyn_logo_w').value) || 100;
                        const startH = parseFloat(document.getElementById('dyn_logo_h').value) || 100;
                        
                        function onMouseMove(moveEvent) {
                            const dx = moveEvent.clientX - startX;
                            const dy = moveEvent.clientY - startY;
                            
                            const naturalDx = Math.round(dx * scaleX);
                            const naturalDy = Math.round(dy * scaleY);
                            
                            const newW = Math.max(10, startW + naturalDx);
                            const newH = Math.max(10, startH + naturalDy);
                            
                            document.getElementById('dyn_logo_w').value = newW;
                            document.getElementById('dyn_logo_h').value = newH;
                            
                            el.style.width = Math.round(newW / scaleX) + 'px';
                            el.style.height = Math.round(newH / scaleY) + 'px';
                        }
                        
                        function onMouseUp() {
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                            saveHistoryState();
                        }
                        
                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });
                } else {
                    // Resizing text (name, subtitle, hashtag) scales the event font size
                    resizeHandle.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const startY = e.clientY;
                        const startSize = parseFloat(document.getElementById('dyn_' + type + '_size').value) || 20;
                        
                        function onMouseMove(moveEvent) {
                            const dy = moveEvent.clientY - startY;
                            const naturalDy = Math.round(dy * scaleY);
                            
                            const newSize = Math.max(6, Math.min(150, startSize + Math.round(naturalDy)));
                            
                            document.getElementById('dyn_' + type + '_size').value = newSize;
                            
                            const textEl = el.querySelector('.dyn-dummy-text-content');
                            if (textEl) {
                                textEl.style.fontSize = Math.round(newSize / scaleY) + 'px';
                            }
                        }
                        
                        function onMouseUp() {
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                            saveHistoryState();
                            renderDynamicDummies();
                        }
                        
                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });
                }
            }
        }

        function deleteDynamicDummy(type) {
            const checkbox = document.getElementById('dyn_' + type + '_enable');
            if (checkbox) {
                checkbox.checked = false;
                renderDynamicDummies();
                saveHistoryState();
            }
        }

        function bindDynamicFieldChangeListeners() {
            const ids = [
                'editorFrameIsDynamic',
                'dyn_logo_enable', 'dyn_logo_x', 'dyn_logo_y', 'dyn_logo_w', 'dyn_logo_h',
                'dyn_name_enable', 'dyn_name_x', 'dyn_name_y', 'dyn_name_size', 'dyn_name_color', 'dyn_name_color_picker', 'dyn_name_style',
                'dyn_subtitle_enable', 'dyn_subtitle_x', 'dyn_subtitle_y', 'dyn_subtitle_size', 'dyn_subtitle_color', 'dyn_subtitle_color_picker', 'dyn_subtitle_style',
                'dyn_hashtag_enable', 'dyn_hashtag_x', 'dyn_hashtag_y', 'dyn_hashtag_size', 'dyn_hashtag_color', 'dyn_hashtag_color_picker', 'dyn_hashtag_style'
            ];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', renderDynamicDummies);
                    el.addEventListener('change', renderDynamicDummies);
                }
            });
        }

        // Global Event Listeners for Editor
        document.addEventListener('DOMContentLoaded', () => {
            bindDynamicFieldChangeListeners();
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
            initMobileNavigation();

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
                } else if (status === 'coupon_created' || status === 'bulk_created' || status === 'coupon_error' || status === 'coupon_deleted') {
                    activeTab = 'coupons';
                } else if (status === 'event_saved' || status === 'event_deleted') {
                    activeTab = 'events';
                } else {
                    activeTab = localStorage.getItem('active_admin_tab') || 'dashboard';
                }
            }
            
            switchTab(activeTab);

            // Clear status query parameter from URL to prevent showing alert again on refresh
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                if (url.searchParams.has('status')) {
                    url.searchParams.delete('status');
                    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
                }
            }

            // Auto close alerts after 4 seconds
            setTimeout(() => {
                document.querySelectorAll('.alert-status').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin 0.5s ease, padding 0.5s ease, height 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 4000);
        });
    </script>

    <!-- Bottom Navigation Bar (Mobile) -->
    <div class="bottom-nav">
        <div class="bottom-nav-item active" data-tab="dashboard">
            <span class="icon"><i class="fa-solid fa-chart-simple"></i></span>
            <span>Dashboard</span>
        </div>
        <div class="bottom-nav-item" data-tab="settings">
            <span class="icon"><i class="fa-solid fa-sliders"></i></span>
            <span>Settings</span>
        </div>
        <div class="bottom-nav-item middle-btn" id="bottomSheetToggle">
            <span class="icon" style="font-size: 1.4rem; display: flex; align-items: center; justify-content: center; height: 100%;"><i class="fa-solid fa-ellipsis"></i></span>
        </div>
        <div class="bottom-nav-item" data-tab="queue">
            <span class="icon"><i class="fa-solid fa-hourglass-half"></i></span>
            <span>Queue</span>
        </div>
        <div class="bottom-nav-item" data-tab="packages">
            <span class="icon"><i class="fa-solid fa-box-archive"></i></span>
            <span>Packages</span>
        </div>
    </div>

    <!-- Bottom Sheet Overlay -->
    <div class="bottom-sheet-overlay" id="bottomSheetOverlay"></div>

    <!-- Bottom Sheet Drawer -->
    <div class="bottom-sheet" id="bottomSheet">
        <div class="bottom-sheet-handle" id="bottomSheetHandle"></div>
        <div style="font-weight: 800; font-size: 1.05rem; margin-bottom: 18px; color: var(--text-main); text-align: center; font-family: 'Outfit', sans-serif;">
            Menu Lainnya
        </div>
        <div class="bottom-sheet-grid">
            <div class="bottom-sheet-item" data-tab="coupons">
                <span class="icon" style="color: var(--warning);"><i class="fa-solid fa-ticket"></i></span>
                <span style="margin-top: 4px;">Kelola Kupon</span>
            </div>
            <div class="bottom-sheet-item" data-tab="frames">
                <span class="icon" style="color: var(--info);"><i class="fa-solid fa-image"></i></span>
                <span style="margin-top: 4px;">Bingkai Kiosk</span>
            </div>
            <div class="bottom-sheet-item" data-tab="events">
                <span class="icon" style="color: var(--primary);"><i class="fa-solid fa-calendar-days"></i></span>
                <span style="margin-top: 4px;">Manajemen Event</span>
            </div>
            <a href="admin.php?action=logout" class="bottom-sheet-item logout">
                <span class="icon" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span style="margin-top: 4px;">Logout</span>
            </a>
        </div>
    </div>
</body>
</html>
