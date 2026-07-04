<?php
// Script to generate vip_ticket_strip.png for 4-photo VIP Concert & Festival Ticket Pass theme
set_time_limit(90);

$outputDir = __DIR__ . '/frames/';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// System fonts mapping (Windows paths)
$fontDir = 'C:/Windows/Fonts/';
$fonts = [
    'arial'     => $fontDir . 'arial.ttf',
    'arialbd'   => $fontDir . 'arialbd.ttf',
    'segoe'     => $fontDir . 'segoeui.ttf',
    'segoebd'   => $fontDir . 'segoeuib.ttf',
    'georgia'   => $fontDir . 'georgia.ttf',
    'georgiab'  => $fontDir . 'georgiab.ttf',
    'cour'      => $fontDir . 'cour.ttf',
    'courbd'    => $fontDir . 'courbd.ttf',
];

function drawTextSafe($img, $size, $angle, $x, $y, $color, $fontKey, $text) {
    global $fonts;
    if (isset($fonts[$fontKey]) && file_exists($fonts[$fontKey])) {
        if ($x === -1) {
            $bbox = imagettfbbox($size, $angle, $fonts[$fontKey], $text);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $w = imagesx($img);
            $x = intval(($w - $textWidth) / 2);
        }
        imagettftext($img, $size, $angle, intval($x), intval($y), $color, $fonts[$fontKey], $text);
    } else {
        $font_fallback = 5;
        $text_w = imagefontwidth($font_fallback) * strlen($text);
        if ($x === -1) {
            $w = imagesx($img);
            $x = intval(($w - $text_w) / 2);
        }
        imagestring($img, $font_fallback, intval($x), intval($y - 10), $text, $color);
    }
}

function drawFilledRoundedRect($img, $x1, $y1, $x2, $y2, $radius, $color) {
    $x1 = intval($x1); $y1 = intval($y1); $x2 = intval($x2); $y2 = intval($y2); $radius = intval($radius);
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function drawRoundedRectBorder($img, $x1, $y1, $x2, $y2, $radius, $thickness, $color) {
    $x1 = intval($x1); $y1 = intval($y1); $x2 = intval($x2); $y2 = intval($y2); $radius = intval($radius);
    for ($t = 0; $t < $thickness; $t++) {
        $rx1 = $x1 - $t;
        $ry1 = $y1 - $t;
        $rx2 = $x2 + $t;
        $ry2 = $y2 + $t;
        $r = $radius + $t;
        
        imageline($img, $rx1 + $r, $ry1, $rx2 - $r, $ry1, $color);
        imageline($img, $rx1 + $r, $ry2, $rx2 - $r, $ry2, $color);
        imageline($img, $rx1, $ry1 + $r, $rx1, $ry2 - $r, $color);
        imageline($img, $rx2, $ry1 + $r, $rx2, $ry2 - $r, $color);
        
        imagearc($img, $rx1 + $r, $ry1 + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($img, $rx2 - $r, $ry1 + $r, $r * 2, $r * 2, 270, 360, $color);
        imagearc($img, $rx1 + $r, $ry2 - $r, $r * 2, $r * 2, 90, 180, $color);
        imagearc($img, $rx2 - $r, $ry2 - $r, $r * 2, $r * 2, 0, 90, $color);
    }
}

function drawFourPointStar($img, $cx, $cy, $radius, $color) {
    $cx = intval($cx); $cy = intval($cy); $radius = intval($radius);
    $points = [
        $cx, $cy - $radius,
        $cx + intval($radius/4), $cy - intval($radius/4),
        $cx + $radius, $cy,
        $cx + intval($radius/4), $cy + intval($radius/4),
        $cx, $cy + $radius,
        $cx - intval($radius/4), $cy + intval($radius/4),
        $cx - $radius, $cy,
        $cx - intval($radius/4), $cy - intval($radius/4)
    ];
    imagefilledpolygon($img, $points, $color);
}

function drawBarcode($img, $x, $y, $w, $h, $color) {
    $currentX = $x;
    srand(8819);
    while ($currentX < $x + $w) {
        $lineWidth = rand(2, 5);
        $gap = rand(2, 4);
        if ($currentX + $lineWidth > $x + $w) {
            $lineWidth = $x + $w - $currentX;
        }
        imagefilledrectangle($img, intval($currentX), intval($y), intval($currentX + $lineWidth - 1), intval($y + $h - 1), $color);
        $currentX += $lineWidth + $gap;
    }
}

function drawTicketStamp($img, $cx, $cy, $color, $labelColor) {
    $cx = intval($cx); $cy = intval($cy);
    // Draw outer dotted circle
    for ($a = 0; $a < 360; $a += 12) {
        $x = $cx + 46 * cos(deg2rad($a));
        $y = $cy + 46 * sin(deg2rad($a));
        imagefilledellipse($img, intval($x), intval($y), 4, 4, $color);
    }
    imageellipse($img, $cx, $cy, 80, 80, $color);
    drawTextSafe($img, 8, 12, $cx - 24, $cy - 12, $color, 'courbd', 'ADMIT ONE');
    drawTextSafe($img, 11, 12, $cx - 30, $cy + 8, $labelColor, 'arialbd', 'VIP ACCESS');
    drawTextSafe($img, 8, 12, $cx - 22, $cy + 24, $color, 'courbd', '★ MUSIC ★');
}

echo "Generating vip_ticket_strip.png...\n";

$width = 600;
$height = 2400;
$img = imagecreatetruecolor($width, $height);

imagealphablending($img, false);
imagesavealpha($img, true);

// ----------------------------------------------------
// 1. BASE BACKGROUND (Sleek Concert Stage Dark Vibe)
// ----------------------------------------------------
// Base dark charcoal/purple gradient
for ($y = 0; $y < $height; $y++) {
    $ratio = $y / $height;
    // Radial glow emulation inside y loop
    $r = intval(14 * (1 - $ratio) + 9 * $ratio);
    $g = intval(10 * (1 - $ratio) + 7 * $ratio);
    $b = intval(24 * (1 - $ratio) + 16 * $ratio);
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $width - 1, $y, $col);
}

// Add smooth radial lighting glow behind music festival header and stub
imagealphablending($img, true);
for ($rad = 280; $rad > 0; $rad -= 8) {
    $alpha = intval(127 - (1 - ($rad / 280)) * 50);
    // Violet/Pink neon glows
    $glowHeader = imagecolorallocatealpha($img, 147, 51, 234, min(127, max(0, $alpha)));
    imagefilledellipse($img, 300, 150, intval($rad * 2), intval($rad * 1.5), $glowHeader);
    
    $glowFooter = imagecolorallocatealpha($img, 236, 72, 153, min(127, max(0, $alpha)));
    imagefilledellipse($img, 300, 2200, intval($rad * 2), intval($rad * 1.5), $glowFooter);
}

// Colors palette
$white       = imagecolorallocate($img, 255, 255, 255);
$neonPink    = imagecolorallocate($img, 236, 72, 153);    // #EC4899
$neonViolet  = imagecolorallocate($img, 168, 85, 247);    // #A855F7
$neonCyan    = imagecolorallocate($img, 6, 182, 212);     // #06B6D4
$goldYellow  = imagecolorallocate($img, 245, 158, 11);     // #F59E0B
$darkCharcoal= imagecolorallocate($img, 15, 12, 27);
$grayLabel   = imagecolorallocate($img, 156, 163, 175);   // #9CA3AF
$darkGrayBg  = imagecolorallocate($img, 24, 20, 37);

// 4 Photo Slots
$slots = [
    ['x' => 50, 'y' => 380, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 795, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1210, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1625, 'w' => 500, 'h' => 375]
];

// Cut transparent rectangles for photo slots
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagealphablending($img, false);
foreach ($slots as $slot) {
    imagefilledrectangle($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($img, true);

// Outer Ticket Frame Border
drawRoundedRectBorder($img, 20, 20, 580, 2380, 24, 4, $neonViolet);
drawRoundedRectBorder($img, 24, 24, 576, 2376, 20, 1, $neonPink);

// ----------------------------------------------------
// 2. HEADER SECTION (Concert & Festival Ticket Pass Banner)
// ----------------------------------------------------
// VIP Main Badge Pill
drawFilledRoundedRect($img, 35, 35, 565, 105, 16, $darkGrayBg);
drawRoundedRectBorder($img, 35, 35, 565, 105, 16, 2, $neonPink);

// Title texts
drawTextSafe($img, 18, 0, 55, 78, $white, 'segoebd', 'VIP ALL ACCESS PASS');
drawFilledRoundedRect($img, 410, 52, 545, 88, 10, $neonPink);
drawTextSafe($img, 10, 0, 425, 75, $white, 'arialbd', 'SOUNDCHECK');

// Equalizer/Soundwave Graphic in Header
$eqY = 160;
$eqX = 50;
$eqHeights = [10, 25, 45, 20, 35, 55, 30, 15, 25, 40, 18, 30, 50, 22, 12];
foreach ($eqHeights as $idx => $h) {
    $xPos = $eqX + ($idx * 9);
    imagefilledrectangle($img, $xPos, $eqY - intval($h/2), $xPos + 4, $eqY + intval($h/2), $neonCyan);
}

// Center Festival Code / Info
drawTextSafe($img, 26, 0, 210, 162, $goldYellow, 'arialbd', 'LIVE 2026');
drawTextSafe($img, 10, 0, 210, 185, $grayLabel, 'segoebd', 'FESTIVAL TOUR');

// Right Side Gate / Venue
drawTextSafe($img, 18, 0, 445, 155, $white, 'arialbd', 'GATE A');
drawTextSafe($img, 9, 0, 445, 178, $neonPink, 'segoebd', 'STAGE PASS');

// Double Separator line
imageline($img, 35, 210, 565, 210, $neonViolet);
imageline($img, 35, 213, 565, 213, $neonPink);

// ----------------------------------------------------
// 3. TICKET METADATA GRID (Above Photos)
// ----------------------------------------------------
$metaFields = [
    ['label' => 'TICKET NO.',  'val' => '#' . rand(1000, 9999) . '-VIP', 'x' => 55],
    ['label' => 'SECTION',     'val' => 'FRONTROW',                     'x' => 195],
    ['label' => 'ROW',         'val' => 'GA',                           'x' => 335],
    ['label' => 'TIME',        'val' => '19:00 WIB',                    'x' => 455],
];

foreach ($metaFields as $f) {
    drawTextSafe($img, 9, 0, $f['x'], 242, $grayLabel, 'segoebd', $f['label']);
    drawTextSafe($img, 12, 0, $f['x'], 270, $white, 'arialbd', $f['val']);
}

// Passenger / Holder label
drawTextSafe($img, 9, 0, 55, 305, $grayLabel, 'segoebd', 'PASSENGER NAME / VIP HOLDER:');
drawTextSafe($img, 12, 0, 55, 335, $goldYellow, 'courbd', 'VIP FESTIVAL GUEST');

drawTextSafe($img, 9, 0, 415, 305, $grayLabel, 'segoebd', 'DATE:');
$currentDateStr = date('d M Y');
drawTextSafe($img, 11, 0, 415, 335, $white, 'courbd', $currentDateStr);

// Ticket Left & Right side notches (Header section)
imagefilledellipse($img, 20, 210, 30, 30, $darkCharcoal);
imagearc($img, 20, 210, 30, 30, 270, 90, $neonPink);
imagefilledellipse($img, 580, 210, 30, 30, $darkCharcoal);
imagearc($img, 580, 210, 30, 30, 90, 270, $neonPink);

// ----------------------------------------------------
// 4. PHOTO SLOT BORDERS
// ----------------------------------------------------
foreach ($slots as $slot) {
    drawRoundedRectBorder($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], 14, 4, $neonViolet);
    drawRoundedRectBorder($img, $slot['x'] - 2, $slot['y'] - 2, $slot['x'] + $slot['w'] + 2, $slot['y'] + $slot['h'] + 2, 16, 1, $neonCyan);
}

// ----------------------------------------------------
// 5. CREATIVE OVERLAPPING ELEMENTS (standar_bingkai.md)
// ----------------------------------------------------
// Overlapping 1: Diagonal Gold Ribbon Sticker overlapping Top-Left of Slot #0
$ribbonBg = imagecolorallocate($img, 245, 158, 11);
$ribbonShadow = imagecolorallocatealpha($img, 0, 0, 0, 90);
imagefilledpolygon($img, [35, 435, 185, 435, 170, 470, 35, 470], $ribbonShadow);
imagefilledpolygon($img, [30, 430, 180, 430, 165, 465, 30, 465], $ribbonBg);
drawTextSafe($img, 9, 0, 42, 453, $white, 'arialbd', '★ VIP ACCESS ★');

// Overlapping 2: Retro circular "ADMIT ONE / SOUNDCHECK" stamp crossing Slot #1 bottom and Slot #2 top
drawTicketStamp($img, 520, 1200, $neonPink, $goldYellow);

// Overlapping 3: Perforated tear line between Slot #2 and Slot #3
$perfY = 1605;
// Left/Right ticket edge notches
imagefilledellipse($img, 20, $perfY, 26, 26, $darkCharcoal);
imagearc($img, 20, $perfY, 26, 26, 270, 90, $neonPink);
imagefilledellipse($img, 580, $perfY, 26, 26, $darkCharcoal);
imagearc($img, 580, $perfY, 26, 26, 90, 270, $neonPink);

// Tear Line dots
for ($px = 40; $px < 560; $px += 14) {
    imagefilledrectangle($img, $px, $perfY - 1, $px + 7, $perfY + 1, $neonViolet);
}

// Overlapping 4: PASS STUB Badge overlapping Top-Left of Slot #3
drawFilledRoundedRect($img, 35, 1618, 170, 1648, 8, $darkGrayBg);
drawRoundedRectBorder($img, 35, 1618, 170, 1648, 8, 1, $neonCyan);
drawTextSafe($img, 9, 0, 45, 1638, $neonCyan, 'courbd', 'TICKET STUB');

// Overlapping 5: FourPointStar sparkles (✦) overlapping margins
drawFourPointStar($img, 45, 780, 14, $neonCyan);
drawFourPointStar($img, 550, 1640, 12, $goldYellow);

// ----------------------------------------------------
// 6. FOOTER SECTION (Ticket Stub & Barcode)
// ----------------------------------------------------
// Divider above Footer
imageline($img, 35, 2015, 565, 2015, $neonViolet);
imageline($img, 35, 2018, 565, 2018, $neonPink);

// Creative Neon Card for VIP Stub Details
drawFilledRoundedRect($img, 40, 2032, 560, 2222, 12, $darkGrayBg);
drawRoundedRectBorder($img, 40, 2032, 560, 2222, 12, 1, $neonPink);

// Vertical neon divider separating details and logo
imageline($img, 430, 2042, 430, 2212, $neonViolet);

// Decorative labels for dynamic text
drawTextSafe($img, 8, 0, 60, 2055, $neonPink, 'segoebd', 'FESTIVAL / EVENT :');
drawTextSafe($img, 8, 0, 60, 2110, $neonCyan, 'segoebd', 'SPECIAL TOUR & ACCESS :');
drawTextSafe($img, 8, 0, 60, 2165, $goldYellow, 'segoebd', 'OFFICIAL HASHTAGS :');

// Circular design frame for dynamic Logo
imageellipse($img, 495, 2095, 76, 76, $neonCyan);
for ($a = 0; $a < 360; $a += 18) {
    $lx = 495 + 44 * cos(deg2rad($a));
    $ly = 2095 + 44 * sin(deg2rad($a));
    imagefilledellipse($img, intval($lx), intval($ly), 3, 3, $neonViolet);
}
drawTextSafe($img, 7, 0, 462, 2156, $grayLabel, 'segoebd', 'EVENT LOGO');

// Barcode section at bottom
drawBarcode($img, 55, 2250, 490, 60, $white);

// Barcode Serial No below
drawTextSafe($img, 10, 0, -1, 2335, $neonPink, 'courbd', 'NO. 7709-3829-2026-CONCERT-VIP');

// ----------------------------------------------------
// SAVE GENERATED PNG
// ----------------------------------------------------
$targetFile = $outputDir . 'vip_ticket_strip.png';
imagepng($img, $targetFile);
imagedestroy($img);

echo "Successfully generated: " . $targetFile . "\n";
?>
