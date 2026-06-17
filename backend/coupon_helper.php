<?php
// coupon_helper.php

function getCouponsFilePath() {
    return __DIR__ . '/coupons.json';
}

function loadCoupons() {
    $file = getCouponsFilePath();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function saveCoupons($coupons) {
    $file = getCouponsFilePath();
    return file_put_contents($file, json_encode($coupons, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Get the package index suffix (1-based, e.g., 1, 2, 3) from packages.json.
 * For "any", returns "0".
 */
function getPackageSuffix($packageId) {
    $suffix = '0';
    if ($packageId !== 'any') {
        $packagesFile = __DIR__ . '/packages.json';
        if (file_exists($packagesFile)) {
            $pkgs = json_decode(file_get_contents($packagesFile), true);
            if (is_array($pkgs)) {
                $index = 1;
                foreach ($pkgs as $p) {
                    if ($p['id'] === $packageId) {
                        $suffix = (string)$index;
                        break;
                    }
                    $index++;
                }
            }
        }
    }
    return $suffix;
}

/**
 * Generate a unique, user-friendly coupon code with a package suffix.
 * Excludes confusing characters like 0, O, 1, I, L.
 */
function generateCouponCode($packageId, $length = 3) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $charsLength = strlen($chars);
    $coupons = loadCoupons();
    
    // Build a map of existing codes for fast lookup
    $existingCodes = [];
    foreach ($coupons as $c) {
        $existingCodes[strtoupper($c['code'])] = true;
    }

    $suffix = getPackageSuffix($packageId);
    $attempts = 0;
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, $charsLength - 1)];
        }
        $fullCode = $code . $suffix;
        $attempts++;
        // Avoid infinite loop in case space is full (unlikely for 3 characters)
        if ($attempts > 1000) {
            $fullCode = $code . rand(10, 99) . $suffix;
            break;
        }
    } while (isset($existingCodes[$fullCode]));

    return $fullCode;
}

/**
 * Create a new coupon.
 * $packageId can be a specific package ID or "any".
 */
function createCoupon($packageId, $customCode = null) {
    $coupons = loadCoupons();
    
    if ($customCode !== null) {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $customCode));
        if (empty($base)) {
            return ['success' => false, 'message' => 'Kode kupon custom tidak valid.'];
        }
        $suffix = getPackageSuffix($packageId);
        $code = $base . $suffix;
        
        // Verify uniqueness
        foreach ($coupons as $c) {
            if (strtoupper($c['code']) === $code) {
                return ['success' => false, 'message' => 'Kode kupon sudah terdaftar.'];
            }
        }
    } else {
        $code = generateCouponCode($packageId, 3);
    }

    $newCoupon = [
        "code" => $code,
        "package_id" => $packageId,
        "status" => "ACTIVE",
        "created_at" => time(),
        "used_at" => null,
        "used_by_session" => null
    ];

    $coupons[] = $newCoupon;
    if (saveCoupons($coupons)) {
        return ['success' => true, 'coupon' => $newCoupon];
    }
    return ['success' => false, 'message' => 'Gagal menyimpan data kupon.'];
}

/**
 * Validate coupon code and mark as used if valid.
 */
function validateAndUseCoupon($code, $currentPackageId, $sessionId) {
    $code = strtoupper(trim($code));
    if (empty($code)) {
        return ['success' => false, 'message' => 'Kode kupon tidak boleh kosong.'];
    }

    $coupons = loadCoupons();
    $foundIndex = -1;

    for ($i = 0; $i < count($coupons); $i++) {
        if (strtoupper($coupons[$i]['code']) === $code) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex === -1) {
        return ['success' => false, 'message' => 'Kode kupon tidak valid atau tidak terdaftar.'];
    }

    $coupon = &$coupons[$foundIndex];

    // Check if coupon has expired (24 hours = 86400 seconds)
    if ($coupon['status'] === 'ACTIVE' && (time() - $coupon['created_at'] > 86400)) {
        $coupon['status'] = 'EXPIRED';
        saveCoupons($coupons);
    }

    if ($coupon['status'] !== 'ACTIVE') {
        if ($coupon['status'] === 'EXPIRED') {
            return ['success' => false, 'message' => 'Kupon ini sudah kedaluwarsa (berlaku maksimal 24 jam).'];
        }
        return ['success' => false, 'message' => 'Kupon ini sudah pernah digunakan atau kedaluwarsa.'];
    }

    // Verify package restriction
    if ($coupon['package_id'] !== 'any' && $coupon['package_id'] !== $currentPackageId) {
        // Load packages to display a descriptive error message
        $packagesFile = __DIR__ . '/packages.json';
        $packageName = $coupon['package_id'];
        if (file_exists($packagesFile)) {
            $pkgs = json_decode(file_get_contents($packagesFile), true);
            if (is_array($pkgs)) {
                foreach ($pkgs as $p) {
                    if ($p['id'] === $coupon['package_id']) {
                        $packageName = $p['name'];
                        break;
                    }
                }
            }
        }
        return [
            'success' => false, 
            'message' => 'Kupon ini hanya berlaku untuk "' . htmlspecialchars($packageName) . '". Silakan pilih paket yang sesuai.'
        ];
    }

    // Mark as used
    $coupon['status'] = 'USED';
    $coupon['used_at'] = time();
    $coupon['used_by_session'] = $sessionId;

    if (saveCoupons($coupons)) {
        return ['success' => true, 'coupon' => $coupon];
    }

    return ['success' => false, 'message' => 'Gagal memperbarui status kupon.'];
}
