<?php
// Script to generate upgraded receipt_tagihan_dynamic.png with rich background texture and CROSS-PHOTO OVERLAPPING elements
set_time_limit(90);

$outputDir = __DIR__ . '/frames/';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$fontDir = 'C:/Windows/Fonts/';
$fonts = [
    'georgia'   => $fontDir . 'georgia.ttf',
    'georgiab'  => $fontDir . 'georgiab.ttf',
    'georgiai'  => $fontDir . 'georgiai.ttf',
    'arial'     => $fontDir . 'arial.ttf',
    'arialbd'   => $fontDir . 'arialbd.ttf',
    'cour'      => $fontDir . 'cour.ttf',
    'courbd'    => $fontDir . 'courbd.ttf',
    'impact'    => $fontDir . 'impact.ttf',
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
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function drawTopRoundedRect($img, $x1, $y1, $x2, $y2, $radius, $color) {
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2, $color);
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y1 + $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
}

function drawFourPointStar($img, $cx, $cy, $radius, $color) {
    $points = [
        intval($cx), intval($cy - $radius),
        intval($cx + $radius/4), intval($cy - $radius/4),
        intval($cx + $radius), intval($cy),
        intval($cx + $radius/4), intval($cy + $radius/4),
        intval($cx), intval($cy + $radius),
        intval($cx - $radius/4), intval($cy + $radius/4),
        intval($cx - $radius), intval($cy),
        intval($cx - $radius/4), intval($cy - $radius/4)
    ];
    imagefilledpolygon($img, $points, $color);
}

function drawBarcodeLocal($img, $x, $y, $w, $h, $color) {
    $currentX = $x;
    srand(12345);
    while ($currentX < $x + $w) {
        $lineWidth = rand(2, 4);
        $gap = rand(2, 4);
        if ($currentX + $lineWidth > $x + $w) {
            $lineWidth = $x + $w - $currentX;
        }
        imagefilledrectangle($img, intval($currentX), intval($y), intval($currentX + $lineWidth - 1), intval($y + $h - 1), $color);
        $currentX += $lineWidth + $gap;
    }
}

echo "Generating receipt_tagihan_dynamic.png with cross-photo overlapping elements...\n";

$width = 600;
$height = 2400;
$img = imagecreatetruecolor($width, $height);

imagealphablending($img, false);
imagesavealpha($img, true);

// 1. Warm Paper Base Gradient
$paperBase = imagecolorallocate($img, 250, 246, 238);
imagefill($img, 0, 0, $paperBase);

for ($y = 0; $y < $height; $y++) {
    $ratio = $y / $height;
    $r = intval(250 - (10 * $ratio));
    $g = intval(246 - (12 * $ratio));
    $b = intval(238 - (15 * $ratio));
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $width, $y, $col);
}

imagealphablending($img, true);

// 2. Add Background Grid / Paper Texture Pattern
$gridColor = imagecolorallocatealpha($img, 200, 190, 175, 105);
for ($gx = 0; $gx < $width; $gx += 30) {
    imageline($img, $gx, 0, $gx, $height, $gridColor);
}
for ($gy = 0; $gy < $height; $gy += 30) {
    imageline($img, 0, $gy, $width, $gy, $gridColor);
}

// 3. Add Decorative Paper Stars (✦)
$accentStarColor = imagecolorallocatealpha($img, 220, 160, 30, 85);
drawFourPointStar($img, 530, 260, 16, $accentStarColor);
drawFourPointStar($img, 60, 2150, 18, $accentStarColor);
drawFourPointStar($img, 540, 2280, 14, $accentStarColor);

// Red Stamp "APPROVED" top right
$stampRedHeader = imagecolorallocatealpha($img, 210, 45, 45, 40);
imageellipse($img, 500, 100, 100, 100, $stampRedHeader);
imageellipse($img, 500, 100, 92, 92, $stampRedHeader);
drawTextSafe($img, 10, 15, 455, 105, $stampRedHeader, 'arialbd', 'APPROVED');
drawTextSafe($img, 7, 15, 465, 120, $stampRedHeader, 'arialbd', 'PHOTOBOOTH');

// Color Palette
$mustardYellow = imagecolorallocate($img, 230, 168, 37);
$darkCharcoal  = imagecolorallocate($img, 28, 28, 28);
$cardBgColor   = imagecolorallocate($img, 255, 255, 255);
$cardShadow    = imagecolorallocatealpha($img, 0, 0, 0, 115);
$cardBorder    = imagecolorallocate($img, 215, 210, 200);
$white         = imagecolorallocate($img, 255, 255, 255);
$mutedText     = imagecolorallocate($img, 110, 110, 110);
$subheadText   = imagecolorallocate($img, 65, 65, 65);

// Photo Slots (4 slots)
$slots = [
    ['x' => 50, 'y' => 420, 'w' => 500, 'h' => 360],
    ['x' => 50, 'y' => 830, 'w' => 500, 'h' => 360],
    ['x' => 50, 'y' => 1240, 'w' => 500, 'h' => 360],
    ['x' => 50, 'y' => 1650, 'w' => 500, 'h' => 360]
];


// ----------------------------------------------------
// HEADER SECTION
// ----------------------------------------------------
imagefilledrectangle($img, 0, 25, 250, 110, $mustardYellow);
imagefilledellipse($img, 250, 67, 85, 85, $mustardYellow);

imagefilledellipse($img, 55, 67, 65, 65, $white);
imageellipse($img, 55, 67, 65, 65, $cardBorder);

drawTextSafe($img, 26, 0, 315, 65, $darkCharcoal, 'arialbd', 'PHOTO RECEIPT');

$contactItems = [
    ['icon' => 'P', 'text' => '+123-456-7890', 'y' => 95],
    ['icon' => 'L', 'text' => 'Studio Photobooth', 'y' => 120],
    ['icon' => 'E', 'text' => 'hello@reallygreatsite.com', 'y' => 145]
];

foreach ($contactItems as $item) {
    $cy = $item['y'] - 5;
    imagefilledellipse($img, 335, $cy, 18, 18, $mustardYellow);
    if ($item['icon'] === 'P') {
        imagefilledellipse($img, 335, $cy, 7, 7, $white);
    } else if ($item['icon'] === 'L') {
        imagefilledellipse($img, 335, $cy - 1, 5, 5, $white);
        imagefilledrectangle($img, 334, $cy + 1, 336, $cy + 3, $white);
    } else {
        imagefilledrectangle($img, 331, $cy - 3, 339, $cy + 3, $white);
    }
    drawTextSafe($img, 9, 0, 352, $item['y'], $subheadText, 'arialbd', $item['text']);
}

imageline($img, 40, 175, 560, 175, $darkCharcoal);
imageline($img, 40, 177, 560, 177, $darkCharcoal);
imageline($img, 40, 182, 560, 182, $darkCharcoal);

drawTextSafe($img, 13, 0, 50, 215, $darkCharcoal, 'arialbd', 'Bukti Kenangan Session');
drawTextSafe($img, 10, 0, 50, 240, $subheadText, 'arial', 'Receipt No : #' . rand(100000, 999999));
drawTextSafe($img, 10, 0, 50, 262, $subheadText, 'arial', 'Date : ' . date('d/m/Y'));
drawTextSafe($img, 11, 0, 350, 215, $subheadText, 'arialbd', 'Kepada :');

// Drop Shadow under Card Container
drawFilledRoundedRect($img, 44, 304, 564, 2024, 20, $cardShadow);

// Table Body Background Card (White Card)
drawFilledRoundedRect($img, 40, 300, 560, 2020, 20, $cardBgColor);

// Cut transparent rectangles for photo slots AFTER drawing card background
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagealphablending($img, false);
foreach ($slots as $slot) {
    imagefilledrectangle($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($img, true);

// Yellow Header Bar for Table
drawTopRoundedRect($img, 40, 300, 560, 355, 20, $mustardYellow);

drawTextSafe($img, 13, 0, 70, 335, $white, 'arialbd', 'Keterangan Momen');
drawTextSafe($img, 13, 0, 300, 335, $white, 'arialbd', 'Jumlah');
drawTextSafe($img, 13, 0, 455, 335, $white, 'arialbd', 'Status');

// Item Rows & Photo Slot Borders
$items = [
    ['name' => 'Capture #1 - Smile & Pose',  'qty' => '1', 'total' => 'PRICELESS', 'itemY' => 390, 'slot' => $slots[0]],
    ['name' => 'Capture #2 - Best Moment',   'qty' => '1', 'total' => 'PRICELESS', 'itemY' => 800, 'slot' => $slots[1]],
    ['name' => 'Capture #3 - Sweet Memories', 'qty' => '1', 'total' => 'PRICELESS', 'itemY' => 1210, 'slot' => $slots[2]],
    ['name' => 'Capture #4 - Fun Times',      'qty' => '1', 'total' => 'PRICELESS', 'itemY' => 1620, 'slot' => $slots[3]]
];

foreach ($items as $it) {
    drawTextSafe($img, 11, 0, 75, $it['itemY'], $darkCharcoal, 'arialbd', $it['name']);
    drawTextSafe($img, 11, 0, 320, $it['itemY'], $darkCharcoal, 'arialbd', $it['qty']);
    drawTextSafe($img, 11, 0, 435, $it['itemY'], $mustardYellow, 'arialbd', $it['total']);
    
    $s = $it['slot'];
    imagerectangle($img, $s['x'] - 1, $s['y'] - 1, $s['x'] + $s['w'], $s['y'] + $s['h'], $darkCharcoal);
}

// ----------------------------------------------------
// ✨ CROSS-PHOTO OVERLAPPING ELEMENTS (Tumpang Nindih)
// ----------------------------------------------------

// 1. OVERLAPPING ELEMENT #1: Diagonal Yellow Ribbon Label on Top-Left of Photo #1
$ribbonBg = imagecolorallocate($img, 230, 168, 37); // #E6A825
$ribbonShadow = imagecolorallocatealpha($img, 0, 0, 0, 80);
// Ribbon shape overlapping border of slot #1 (x: 35 to 165, y: 405 to 445)
imagefilledpolygon($img, [35, 410, 165, 410, 150, 445, 35, 445], $ribbonShadow);
imagefilledpolygon($img, [30, 405, 160, 405, 145, 440, 30, 440], $ribbonBg);
drawTextSafe($img, 10, 0, 42, 428, $white, 'arialbd', '★ BEST SHOT');

// 2. OVERLAPPING ELEMENT #2: Circular Seal Stamp "MEMORY APPROVED" crossing Photo #1 Bottom & Photo #2 Top
$stampRed = imagecolorallocatealpha($img, 215, 35, 35, 20); // semi-translucent bold red
$stampRedDark = imagecolorallocatealpha($img, 180, 20, 20, 20);
imagefilledellipse($img, 520, 805, 95, 95, $stampRed);
imageellipse($img, 520, 805, 95, 95, $stampRedDark);
imageellipse($img, 520, 805, 87, 87, $stampRedDark);
drawTextSafe($img, 9, -12, 480, 792, $stampRedDark, 'arialbd', '100% AUTHENTIC');
drawTextSafe($img, 8, -12, 488, 810, $stampRedDark, 'arialbd', '★ MEMORY ★');
drawTextSafe($img, 7, -12, 492, 825, $stampRedDark, 'arialbd', 'APPROVED');

// 3. OVERLAPPING ELEMENT #3: Washi Tape Sticker Tape crossing Left Edge of Photo #2 / Photo #3
$washiColor = imagecolorallocatealpha($img, 245, 220, 120, 45); // semi-translucent mustard washi tape
$washiLine  = imagecolorallocatealpha($img, 210, 170, 60, 60);
// Polygon tape strip overlapping left border (x: 20 to 110, y: 1225 to 1255)
imagefilledpolygon($img, [20, 1230, 110, 1225, 105, 1255, 15, 1260], $washiColor);
imageline($img, 20, 1230, 110, 1225, $washiLine);
imageline($img, 15, 1260, 105, 1255, $washiLine);
drawTextSafe($img, 8, -3, 28, 1247, $darkCharcoal, 'courbd', 'SWEET POSES');

// 4. OVERLAPPING ELEMENT #4: Decorative Handwritten Script Badge crossing Right Edge of Photo #3
$scriptTextCol = imagecolorallocate($img, 220, 40, 40);
drawTextSafe($img, 16, -8, 440, 1590, $scriptTextCol, 'georgiai', 'Keep Smiling!');
drawFourPointStar($img, 545, 1585, 10, $mustardYellow);

// 5. OVERLAPPING ELEMENT #5: Vintage Stamp Badge overlapping Bottom-Left of Photo #4
$badgeBg = imagecolorallocate($img, 28, 28, 28);
drawFilledRoundedRect($img, 35, 1985, 160, 2015, 8, $badgeBg);
drawTextSafe($img, 8, 0, 45, 2004, $white, 'courbd', 'AUTHENTIC SHOT');

// ----------------------------------------------------
// FOOTER SECTION
// ----------------------------------------------------
drawTextSafe($img, 9, 0, 50, 2050, $mutedText, 'arial', 'Catatan Kenangan :');
drawTextSafe($img, 9, 0, 50, 2070, $subheadText, 'arialbd', 'Simpan Struk Foto Ini Selamanya!');
drawTextSafe($img, 9, 0, 50, 2090, $mutedText, 'arial', 'Atas Nama : Photobooth Joy Session');

drawTextSafe($img, 10, 0, 320, 2050, $darkCharcoal, 'arialbd', 'TOTAL SHOTS');
drawTextSafe($img, 10, 0, 470, 2050, $subheadText, 'arialbd', '4 POSES');

drawTextSafe($img, 10, 0, 320, 2080, $darkCharcoal, 'arialbd', 'HAPPY RATE');
drawTextSafe($img, 10, 0, 470, 2080, $subheadText, 'arialbd', '100%');

drawTextSafe($img, 12, 0, 320, 2118, $darkCharcoal, 'arialbd', 'TOTAL JOY');
drawTextSafe($img, 12, 0, 435, 2118, $mustardYellow, 'arialbd', 'PRICELESS');

imageline($img, 40, 2155, 560, 2155, $darkCharcoal);
imageline($img, 40, 2158, 560, 2158, $darkCharcoal);

drawTextSafe($img, 32, 0, -1, 2225, $darkCharcoal, 'arialbd', 'THANK YOU!');

drawBarcodeLocal($img, 200, 2265, 200, 25, $darkCharcoal);

$targetFile = $outputDir . 'receipt_tagihan_dynamic.png';
imagepng($img, $targetFile);
imagedestroy($img);

echo "Successfully generated receipt_tagihan_dynamic.png WITH OVERLAPPING CROSS-PHOTO ELEMENTS!\n";
