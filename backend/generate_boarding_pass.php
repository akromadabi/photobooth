<?php
// Script to generate boarding_pass_strip.png for 4-photo Airline Boarding Pass theme
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

function drawAirplaneIcon($img, $cx, $cy, $size, $color) {
    $cx = intval($cx); $cy = intval($cy); $size = intval($size);
    // Fuselage
    imagefilledellipse($img, $cx, $cy, intval($size * 1.4), intval($size * 0.4), $color);
    // Main Wings
    $wingPts = [
        $cx - intval($size * 0.2), $cy - intval($size * 0.9),
        $cx + intval($size * 0.3), $cy,
        $cx - intval($size * 0.4), $cy
    ];
    imagefilledpolygon($img, $wingPts, $color);
    $wingPts2 = [
        $cx - intval($size * 0.2), $cy + intval($size * 0.9),
        $cx + intval($size * 0.3), $cy,
        $cx - intval($size * 0.4), $cy
    ];
    imagefilledpolygon($img, $wingPts2, $color);
    // Tail
    $tailPts = [
        $cx - intval($size * 0.6), $cy - intval($size * 0.4),
        $cx - intval($size * 0.4), $cy,
        $cx - intval($size * 0.7), $cy
    ];
    imagefilledpolygon($img, $tailPts, $color);
}

function drawPassportStamp($img, $cx, $cy, $color, $text) {
    $cx = intval($cx); $cy = intval($cy);
    imageellipse($img, $cx, $cy, 90, 90, $color);
    imageellipse($img, $cx, $cy, 82, 82, $color);
    drawTextSafe($img, 8, 15, $cx - 30, $cy - 12, $color, 'courbd', 'PASSPORT');
    drawTextSafe($img, 10, 15, $cx - 35, $cy + 6, $color, 'arialbd', $text);
    drawTextSafe($img, 8, 15, $cx - 28, $cy + 22, $color, 'courbd', 'CONTROL');
}

echo "Generating boarding_pass_strip.png...\n";

$width = 600;
$height = 2700;
$img = imagecreatetruecolor($width, $height);

imagealphablending($img, false);
imagesavealpha($img, true);

// ----------------------------------------------------
// 1. BASE BACKGROUND (Crisp Boarding Pass Paper)
// ----------------------------------------------------
$bgPaper = imagecolorallocate($img, 246, 246, 248); // #F6F6F8
imagefill($img, 0, 0, $bgPaper);

// Color Palette
$navyBlue   = imagecolorallocate($img, 14, 30, 60);     // #0E1E3C Airline Navy
$skyBlue    = imagecolorallocate($img, 37, 99, 235);    // #2563EB Sky Blue
$goldAccent = imagecolorallocate($img, 217, 119, 6);    // #D97706 Gold Badge
$redStamp   = imagecolorallocate($img, 220, 38, 38);    // #DC2626 Customs Red
$charcoal   = imagecolorallocate($img, 30, 35, 45);     // #1E232D
$white      = imagecolorallocate($img, 255, 255, 255);
$grayLight  = imagecolorallocate($img, 230, 232, 238);
$grayText   = imagecolorallocate($img, 100, 110, 125);

// Photo Slots (4 slots for 2400px height)
$slots = [
    ['x' => 50, 'y' => 350, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 765, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1180, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1595, 'w' => 500, 'h' => 375]
];

// Cut transparent rectangles for photo slots
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
foreach ($slots as $slot) {
    imagefilledrectangle($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($img, true);

// Outer Ticket Frame Border
drawRoundedRectBorder($img, 20, 20, 580, 2680, 24, 4, $navyBlue);

// ----------------------------------------------------
// 2. HEADER SECTION (Airline Boarding Pass Banner)
// ----------------------------------------------------
// Main Navy Header Pill Bar
drawFilledRoundedRect($img, 35, 35, 565, 105, 16, $navyBlue);

// Airline Name & Class Badge inside Header Bar
drawTextSafe($img, 18, 0, 55, 76, $white, 'segoebd', 'GROOVY AIRWAYS');
drawFilledRoundedRect($img, 415, 52, 545, 88, 10, $goldAccent);
drawTextSafe($img, 10, 0, 430, 75, $white, 'arialbd', 'FIRST CLASS');

// Flight Route Section (JKT ✈️ BALI / CGK ➔ DPS)
// Left City Code: CGK
drawTextSafe($img, 32, 0, 55, 165, $navyBlue, 'arialbd', 'CGK');
drawTextSafe($img, 10, 0, 55, 185, $grayText, 'segoebd', 'JAKARTA');

// Flight Icon & Dotted Arc Line in Center
$planeX = 300; $planeY = 160;
for ($dx = 150; $dx <= 450; $dx += 12) {
    imagesetpixel($img, $dx, 160, $skyBlue);
    imagesetpixel($img, $dx, 161, $skyBlue);
}
drawAirplaneIcon($img, $planeX, $planeY, 22, $skyBlue);

// Right City Code: DPS
drawTextSafe($img, 32, 0, 465, 165, $navyBlue, 'arialbd', 'DPS');
drawTextSafe($img, 10, 0, 465, 185, $grayText, 'segoebd', 'BALI');

// Double Separator Line under City Codes
imageline($img, 35, 205, 565, 205, $grayLight);
imageline($img, 35, 208, 565, 208, $navyBlue);

// ----------------------------------------------------
// 3. FLIGHT METADATA GRID (Above Photos)
// ----------------------------------------------------
// 4 Columns: FLIGHT | GATE | SEAT | BOARDING
$metaFields = [
    ['label' => 'FLIGHT',   'val' => 'GA-2026', 'x' => 55],
    ['label' => 'GATE',     'val' => 'B07',     'x' => 195],
    ['label' => 'SEAT',     'val' => '01A',     'x' => 335],
    ['label' => 'BOARDING', 'val' => '12:30',   'x' => 455],
];

foreach ($metaFields as $f) {
    drawTextSafe($img, 9, 0, $f['x'], 235, $grayText, 'segoebd', $f['label']);
    drawTextSafe($img, 14, 0, $f['x'], 262, $navyBlue, 'arialbd', $f['val']);
}

// Subheader passenger label placeholder line
drawTextSafe($img, 9, 0, 55, 295, $grayText, 'segoebd', 'PASSENGER NAME:');
drawTextSafe($img, 11, 0, 55, 322, $charcoal, 'courbd', 'VIP GUEST / PASSENGER');

drawTextSafe($img, 9, 0, 415, 295, $grayText, 'segoebd', 'DATE:');
$currentDateStr = date('d M Y');
drawTextSafe($img, 11, 0, 415, 322, $navyBlue, 'courbd', $currentDateStr);


// ----------------------------------------------------
// 4. PHOTO SLOT BORDERS
// ----------------------------------------------------
foreach ($slots as $slot) {
    drawRoundedRectBorder($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], 12, 4, $navyBlue);
    drawRoundedRectBorder($img, $slot['x'] - 3, $slot['y'] - 3, $slot['x'] + $slot['w'] + 3, $slot['y'] + $slot['h'] + 3, 14, 1, $goldAccent);
}


// ----------------------------------------------------
// 5. CREATIVE OVERLAPPING ELEMENTS (standar_bingkai.md)
// ----------------------------------------------------

// Overlapping 1: Baggage PRIORITY PASS Tag Sticker overlapping top-left of Slot #0
$tagBg = imagecolorallocate($img, 220, 38, 38);
drawFilledRoundedRect($img, 35, 335, 185, 370, 8, $tagBg);
drawRoundedRectBorder($img, 35, 335, 185, 370, 8, 2, $white);
drawTextSafe($img, 9, 0, 45, 358, $white, 'arialbd', 'PRIORITY PASS');

// Overlapping 2: Red Customs Passport Control Stamp overlapping bottom-right of Slot #1
drawPassportStamp($img, 515, 1150, $redStamp, 'APPROVED');

// Overlapping 3: Ticket Stub Perforated Line between Slot #2 and Slot #3 (y = 1570)
$perfY = 1570;
// Left/Right ticket edge notches
imagefilledellipse($img, 20, $perfY, 24, 24, $bgPaper);
imageellipse($img, 20, $perfY, 24, 24, $navyBlue);
imagefilledellipse($img, 580, $perfY, 24, 24, $bgPaper);
imageellipse($img, 580, $perfY, 24, 24, $navyBlue);

// Dotted tear line across ticket
for ($px = 40; $px < 560; $px += 14) {
    imagefilledrectangle($img, $px, $perfY - 1, $px + 7, $perfY + 1, $navyBlue);
}

// Overlapping 4: Flight Clearance Badge on Slot #3 top-left
drawFilledRoundedRect($img, 35, 1580, 185, 1612, 8, $navyBlue);
drawTextSafe($img, 9, 0, 48, 1601, $goldAccent, 'courbd', 'PASSENGER STUB');


// ----------------------------------------------------
// 6. FOOTER: CREATIVE PASSENGER STUB DESIGN
// ----------------------------------------------------

// ── BAND 1: Navy Stub Header (y: 1988 → 2065) ──────
drawFilledRoundedRect($img, 35, 1988, 565, 2068, 0, $navyBlue);

// Small white airplane icon + "PASSENGER STUB" left
drawAirplaneIcon($img, 70, 2028, 18, $white);
drawTextSafe($img, 13, 0, 100, 2034, $white, 'segoebd', 'PASSENGER STUB');

// Gold divider pipe
imagefilledrectangle($img, 285, 2000, 287, 2055, $goldAccent);

// Right side: boarding label + flight code
drawTextSafe($img, 9, 0, 300, 2018, $grayLight, 'segoe', 'CONFIRMED FLIGHT');
drawTextSafe($img, 15, 0, 300, 2042, $goldAccent, 'arialbd', 'GA-2026  |  GATE B07');
drawTextSafe($img, 9, 0, 300, 2058, $grayLight, 'segoe', 'SEAT 01A  •  BOARDING 12:30');

// ── BAND 2: Event Name – Full-width Accent Field (y: 2068 → 2175) ──
// Paper background (already white)
// Gold left vertical accent bar
imagefilledrectangle($img, 35, 2068, 42, 2175, $goldAccent);

// Light grey tinted background for this section
$sectionGrey = imagecolorallocate($img, 240, 241, 246);
imagefilledrectangle($img, 43, 2068, 565, 2175, $sectionGrey);

// Label "FLIGHT DESTINATION / NAMA ACARA" small caps
drawTextSafe($img, 8, 0, 60, 2090, $grayText, 'segoebd', 'FLIGHT DESTINATION / NAMA ACARA');

// Decorative dotted route line (small, right side)
for ($dx = 400; $dx <= 545; $dx += 10) {
    imagesetpixel($img, $dx, 2088, $skyBlue);
}
imagefilledellipse($img, 550, 2088, 8, 8, $skyBlue);

// [event_name rendered by kiosk at x:55, y:2145 | font_size:24, bold, #0e1e3c]
// — draws in full width of this grey field —

// ── BAND 3: Keterangan/Subtitle + Seat Info Row (y: 2175 → 2280) ──
// Left box (x: 35→390) — holds event_subtitle
imagefilledrectangle($img, 35, 2175, 390, 2280, $bgPaper);
// top/bottom border lines for left box
imageline($img, 35, 2175, 390, 2175, $grayLight);
imageline($img, 35, 2280, 565, 2280, $grayLight);
// Left gold accent bar
imagefilledrectangle($img, 35, 2175, 42, 2280, $goldAccent);
// Label
drawTextSafe($img, 8, 0, 58, 2196, $grayText, 'segoebd', 'KETERANGAN / NOTE');
// [event_subtitle rendered by kiosk at x:55, y:2232 | font_size:14, normal, #1e232d]

// Right mini-box (x: 395→565) — static decorative info
$miniBoxBg = imagecolorallocate($img, 14, 30, 60); // navy
imagefilledrectangle($img, 395, 2175, 565, 2280, $miniBoxBg);
drawTextSafe($img, 8, 0, 412, 2200, $grayText, 'segoe', 'AIRLINE');
drawTextSafe($img, 16, 0, 412, 2228, $goldAccent, 'arialbd', 'GROOVY');
drawTextSafe($img, 16, 0, 412, 2252, $white, 'arialbd', 'AIRWAYS');
// Small gold star ornament
imagefilledellipse($img, 548, 2228, 12, 12, $goldAccent);
imagefilledellipse($img, 548, 2228, 6, 6, $miniBoxBg);

// ── BAND 4: Booking Reference / Hashtag (y: 2285 → 2365) ──
// Dashed separator top
for ($px = 42; $px < 560; $px += 12) {
    imagefilledrectangle($img, $px, 2283, $px + 6, 2285, $navyBlue);
}
// Light sky-blue tinted band
$skyBand = imagecolorallocate($img, 235, 243, 255);
imagefilledrectangle($img, 35, 2286, 565, 2368, $skyBand);
// Left accent bar (sky blue)
imagefilledrectangle($img, 35, 2286, 42, 2368, $skyBlue);
// '#' Large decorative character
drawTextSafe($img, 28, 0, 52, 2347, $skyBlue, 'arialbd', '#');
// Label
drawTextSafe($img, 8, 0, 90, 2304, $grayText, 'segoebd', 'BOOKING REFERENCE / HASHTAG');
// [event_hashtag rendered by kiosk at x:88, y:2345 | font_size:13, bold, #2563eb]
// Right decorative QR placeholder box
imagerectangle($img, 492, 2295, 550, 2360, $skyBlue);
// QR mini pattern inside box
for ($qx = 496; $qx <= 546; $qx += 8) {
    for ($qy = 2299; $qy <= 2356; $qy += 8) {
        if (($qx + $qy) % 16 === 0) {
            imagefilledrectangle($img, $qx, $qy, $qx + 5, $qy + 5, $skyBlue);
        }
    }
}
drawTextSafe($img, 6, 0, 496, 2365, $grayText, 'segoe', 'SCAN');

// ── BAND 5: Thick Separator ─────────────────────────
imageline($img, 35, 2375, 565, 2375, $grayLight);
imagefilledrectangle($img, 35, 2376, 565, 2379, $navyBlue);

// ── BAND 6: Barcode + Serial Number ─────────────────
drawBarcode($img, 55, 2400, 490, 65, $navyBlue);
drawTextSafe($img, 10, 0, -1, 2490, $navyBlue, 'courbd', 'NO. 0984-7719-2026-BOARDING');

// ── Bottom branding line ─────────────────────────────
drawTextSafe($img, 9, 0, -1, 2630, $grayText, 'segoe', 'LARANA PHOTOBOX  •  www.laranabox.com');


// ----------------------------------------------------
// SAVE GENERATED PNG
// ----------------------------------------------------
$targetFile = $outputDir . 'boarding_pass_strip.png';
imagepng($img, $targetFile);
imagedestroy($img);

echo "Successfully generated: " . $targetFile . "\n";


