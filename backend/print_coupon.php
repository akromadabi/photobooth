<?php
// print_coupon.php
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$rawCodes = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
if (empty($rawCodes)) {
    die("Error: Kode kupon tidak ditentukan.");
}

$codesList = array_filter(array_map('trim', explode(',', $rawCodes)));
if (empty($codesList)) {
    die("Error: Kode kupon tidak valid.");
}

require_once __DIR__ . '/coupon_helper.php';
$allCoupons = loadCoupons();

// Match coupons by code
$matchedCoupons = [];
foreach ($codesList as $targetCode) {
    foreach ($allCoupons as $c) {
        if (strtoupper($c['code']) === $targetCode) {
            $matchedCoupons[] = $c;
            break;
        }
    }
}

if (empty($matchedCoupons)) {
    die("Error: Kupon tidak ditemukan.");
}

// Load packages to find the package name (dynamic lookup per coupon)
$packagesFile = __DIR__ . '/packages.json';

// Load settings for static promo text
$settingsFile = __DIR__ . '/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}
$promoText = isset($settings['coupon_promo_text']) ? $settings['coupon_promo_text'] : '';

$cashier = isset($_GET['cashier']) ? htmlspecialchars($_GET['cashier']) : 'Kiosk Operator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kupon (<?php echo count($matchedCoupons); ?>)</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #ffffff;
            color: #000000;
            padding: 5px;
            width: 100%;
            max-width: 280px;
            margin: 0 auto;
        }
        .receipt-container {
            text-align: center;
            width: 100%;
            padding: 5px 0;
        }
        .title {
            font-weight: bold;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .subtitle {
            font-size: 11px;
            margin-bottom: 4px;
        }
        .dashed-line {
            font-size: 11px;
            margin: 4px 0;
            letter-spacing: -0.5px;
        }
        .package-info {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 6px 0;
        }
        .coupon-label {
            font-size: 11px;
            margin-bottom: 4px;
        }
        .coupon-box {
            border: 1.5px solid #000;
            padding: 5px 0;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 4px auto;
            width: 65%;
            text-align: center;
        }
        .qr-container {
            margin: 6px 0;
            display: flex;
            justify-content: center;
        }
        .qr-container canvas {
            width: 110px !important;
            height: 110px !important;
        }
        .instructions {
            font-size: 10.5px;
            line-height: 1.3;
            margin: 6px 0;
        }
        .meta-info {
            font-size: 10px;
            margin: 6px 0;
            line-height: 1.3;
            text-align: center;
        }
        .double-line {
            margin: 6px 0;
        }
        .double-line .line-1, .double-line .line-2 {
            border-top: 1px solid #000;
            margin-bottom: 2px;
        }
        .footer {
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }
        .no-print-btn {
            background-color: #000;
            color: #fff;
            border: none;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
        }

        /* Screen spacing bar styles */
        @media screen {
            .control-bar {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #f8f9fa;
                padding: 10px;
                text-align: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: center;
                gap: 12px;
            }
            .control-bar button {
                padding: 6px 16px;
                font-weight: bold;
                border-radius: 4px;
                cursor: pointer;
            }
            body {
                padding-top: 60px;
            }
            .receipt-container {
                border: 1px dashed #aaa;
                padding: 15px;
                margin-bottom: 15px;
                background: #fff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            }
        }
        
        /* Print rules */
        @media print {
            .no-print, .control-bar {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
                width: 100%;
                background-color: #fff;
            }
            @page {
                margin: 0;
                size: auto;
            }
            .receipt-container:not(:last-child) {
                page-break-after: always;
                break-after: page;
            }
            .receipt-container {
                page-break-inside: avoid;
                break-inside: avoid;
                padding: 5px 0;
            }
        }
    </style>
    <!-- Qrious library for offline & fast client-side QR generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
</head>
<body>

    <div class="control-bar no-print">
        <button class="no-print-btn" onclick="window.print()">Cetak Semua Kupon</button>
        <button class="no-print-btn" style="background-color: #6c757d;" onclick="window.close()">Tutup</button>
    </div>

    <?php foreach ($matchedCoupons as $index => $selectedCoupon): 
        $code = $selectedCoupon['code'];
        $packageName = 'Semua Paket';
        if ($selectedCoupon['package_id'] !== 'any') {
            if (file_exists($packagesFile)) {
                $packages = json_decode(file_get_contents($packagesFile), true);
                if (is_array($packages)) {
                    foreach ($packages as $p) {
                        if ($p['id'] === $selectedCoupon['package_id']) {
                            $packageName = $p['name'];
                            break;
                        }
                    }
                }
            }
        }
        $dateStr = date('d M Y H:i', $selectedCoupon['created_at']);
    ?>
    <div class="receipt-container">
        <div class="title">CREATIVE STUDIO</div>
        <div class="subtitle">"Capture Your Best Moments"</div>
        
        <div class="dashed-line">--------------------------------</div>

        <div class="package-info">
            PAKET: <?php echo htmlspecialchars(strtoupper($packageName)); ?>
        </div>

        <div class="coupon-label">KODE KUPON:</div>
        <div class="coupon-box">
            <?php echo htmlspecialchars($code); ?>
        </div>

        <div class="qr-container">
            <canvas id="qr-code-<?php echo $index; ?>"></canvas>
        </div>

        <div class="instructions">
            Pindai QR atau masukkan kode kupon<br>
            pada menu pembayaran Kiosk Anda.
        </div>

        <div class="meta-info">
            Tanggal: <?php echo $dateStr; ?><br>
            Kasir  : <?php echo $cashier; ?>
        </div>

        <div class="double-line">
            <div class="line-1"></div>
            <div class="line-2"></div>
        </div>

        <div class="footer">
            TERIMA KASIH & SELAMAT BERFOTO!
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        // Render QR codes and run auto print
        window.onload = function() {
            var coupons = <?php echo json_encode($matchedCoupons); ?>;
            coupons.forEach(function(coupon, index) {
                new QRious({
                    element: document.getElementById('qr-code-' + index),
                    value: coupon.code,
                    size: 110,
                    level: 'H'
                });
            });

            // Give a tiny delay for QRious to draw the QR code on canvas
            setTimeout(function() {
                window.print();
                
                // Once print dialog completes, register window close
                setTimeout(function() {
                    window.close();
                }, 500);
            }, 300);
        };
    </script>
</body>
</html>
