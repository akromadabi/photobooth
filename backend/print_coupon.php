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

$cashier = isset($_GET['cashier']) ? htmlspecialchars($_GET['cashier']) : 'Staff-01';
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
            padding: 10px;
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
        }
        .receipt-container {
            text-align: center;
            width: 100%;
        }
        .header-line {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            margin: 6px 0;
            padding: 4px 0;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .double-divider {
            border-top: 1px double #000;
            margin: 10px 0;
        }
        .title {
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 11px;
            margin-top: 2px;
        }
        .meta-info {
            text-align: left;
            font-size: 11px;
            margin: 8px 0;
            line-height: 1.4;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
        }
        .coupon-title {
            font-weight: bold;
            font-size: 12px;
            margin: 10px 0 5px 0;
        }
        .coupon-box {
            border: 2px solid #000;
            padding: 8px 0;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 5px auto 10px auto;
            width: 80%;
            text-align: center;
        }
        .package-info {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .qr-container {
            margin: 12px 0;
            display: flex;
            justify-content: center;
        }
        .instructions {
            font-size: 11px;
            line-height: 1.4;
            margin: 10px 0;
        }
        .promo-section {
            font-size: 11px;
            line-height: 1.4;
            text-align: left;
            margin: 10px 0;
            padding: 6px;
            border: 1px dashed #000;
            white-space: pre-wrap; /* Preserves line breaks from admin text */
        }
        .footer {
            font-size: 11px;
            font-weight: bold;
            margin-top: 10px;
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
                padding: 12px;
                text-align: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: center;
                gap: 12px;
            }
            .control-bar button {
                padding: 8px 20px;
                font-weight: bold;
                border-radius: 4px;
                transition: background 0.2s;
            }
            .control-bar button:hover {
                background-color: #333;
            }
            body {
                padding-top: 70px;
            }
            .receipt-container {
                border: 1px dashed #aaa;
                padding: 20px;
                margin-bottom: 30px;
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
                padding: 10px 0;
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
        <div class="title">CREATIVE PHOTOBOOTH</div>
        <div class="subtitle">"Capture Your Best Moments"</div>
        
        <div class="header-line">
            <div class="meta-row">
                <span>Tanggal: <?php echo $dateStr; ?></span>
            </div>
            <div class="meta-row">
                <span>Kasir  : <?php echo $cashier; ?></span>
            </div>
        </div>

        <div class="package-info">
            Paket: <?php echo htmlspecialchars($packageName); ?>
        </div>

        <div class="coupon-title">KODE KUPON:</div>
        <div class="coupon-box">
            <?php echo htmlspecialchars($code); ?>
        </div>

        <div class="qr-container">
            <canvas id="qr-code-<?php echo $index; ?>"></canvas>
        </div>

        <div class="instructions">
            Masukkan kupon sebagai metode pembayaran Anda
        </div>

        <?php if (!empty($promoText)): ?>
        <div class="promo-section"><?php echo htmlspecialchars($promoText); ?></div>
        <?php endif; ?>

        <div class="double-divider"></div>
        <div class="footer">
            Terima kasih & Selamat Berfoto!
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
                    size: 130,
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
