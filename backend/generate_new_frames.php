<?php
// Set execution timeout to 60 seconds
set_time_limit(60);

$outputDir = __DIR__ . '/frames/';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// System fonts mapping (Windows paths)
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
    'malgun'    => $fontDir . 'malgun.ttf' // Korean font if available
];

// Helper: safe font resolver falling back to build-in fonts
function drawText($img, $size, $angle, $x, $y, $color, $fontKey, $text) {
    global $fonts;
    if (isset($fonts[$fontKey]) && file_exists($fonts[$fontKey])) {
        // Center text horizontally if $x is -1
        if ($x === -1) {
            $bbox = imagettfbbox($size, $angle, $fonts[$fontKey], $text);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $w = imagesx($img);
            $x = ($w - $textWidth) / 2;
        }
        imagettftext($img, $size, $angle, $x, $y, $color, $fonts[$fontKey], $text);
    } else {
        // Fallback to basic built-in font
        $font_fallback = 5; // internal large font
        $text_w = imagefontwidth($font_fallback) * strlen($text);
        if ($x === -1) {
            $w = imagesx($img);
            $x = ($w - $text_w) / 2;
        }
        imagestring($img, $font_fallback, $x, $y - 10, $text, $color);
    }
}

// Helper: draw a beautiful 4-point Y2K star (✦)
function drawFourPointStar($img, $cx, $cy, $radius, $color) {
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
    imagefilledpolygon($img, $points, 8, $color);
}

// Helper: draw a procedural barcode
function drawBarcode($img, $x, $y, $w, $h, $color) {
    $currentX = $x;
    srand(42); // deterministic for design consistency
    while ($currentX < $x + $w) {
        $lineWidth = rand(1, 4);
        $gap = rand(2, 5);
        if ($currentX + $lineWidth > $x + $w) {
            $lineWidth = $x + $w - $currentX;
        }
        imagefilledrectangle($img, $currentX, $y, $currentX + $lineWidth - 1, $y + $h - 1, $color);
        $currentX += $lineWidth + $gap;
    }
}

// Standard vertical strip slots
$slots = [
    ['x' => 50, 'y' => 50, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 455, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 860, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1265, 'w' => 500, 'h' => 375]
];

// ==========================================
// 1. SEOUL AESTHETIC FRAME (seoul_aesthetic.png)
// ==========================================
echo "Generating seoul_aesthetic.png...\n";
$imgSeoul = imagecreatetruecolor(600, 2000);
imagealphablending($imgSeoul, false);
imagesavealpha($imgSeoul, true);

// Opaque Soft Warm Beige Background (#F5F2EB)
$bgSeoul = imagecolorallocate($imgSeoul, 245, 242, 235);
imagefill($imgSeoul, 0, 0, $bgSeoul);

// Cut out transparent slots
$transparent = imagecolorallocatealpha($imgSeoul, 0, 0, 0, 127);
foreach ($slots as $slot) {
    imagefilledrectangle($imgSeoul, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}

// Re-enable alpha blending for overlay drawings (labels, text, lines)
imagealphablending($imgSeoul, true);

// Colors
$darkBrown = imagecolorallocate($imgSeoul, 74, 69, 63); // #4A453F
$lightBrown = imagecolorallocate($imgSeoul, 140, 130, 115); // #8C8273
$lineColor = imagecolorallocate($imgSeoul, 200, 192, 180); // Muted separation line

// Draw elegant thin borders around slot windows (1px outside the slot)
foreach ($slots as $slot) {
    imagerectangle($imgSeoul, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $lightBrown);
}

// Top Text: "Every Moment in Seoul" (Georgia Italic)
drawText($imgSeoul, 20, 0, -1, 40, $darkBrown, 'georgiai', 'Every Moment in Seoul');

// Bottom Section
// 1. Spaced S E O U L Title
drawText($imgSeoul, 32, 0, -1, 1750, $darkBrown, 'georgiab', 'S E O U L');

// 2. Korean Subtitle (서울에서의 모든 순간 - Every moment in Seoul)
if (file_exists($fonts['malgun'])) {
    drawText($imgSeoul, 12, 0, -1, 1795, $lightBrown, 'malgun', '서울에서의 semua 순간');
} else {
    drawText($imgSeoul, 10, 0, -1, 1795, $lightBrown, 'georgiab', '• MEMORY COLLECTOR •');
}

// 3. Details (Date & Location)
$dateStr = date('Y.m.d');
drawText($imgSeoul, 11, 0, 60, 1860, $darkBrown, 'courbd', "DATE:  $dateStr");
drawText($imgSeoul, 11, 0, 60, 1890, $darkBrown, 'courbd', 'LOC:   STUDIO KIOSK');

// 4. Little decorative barcode at bottom-right
drawBarcode($imgSeoul, 390, 1845, 150, 45, $darkBrown);
drawText($imgSeoul, 8, 0, 390, 1905, $darkBrown, 'cour', 'NO. 579D665F-SEOUL');

// 5. Aesthetic separator line
imageline($imgSeoul, 50, 1690, 550, 1690, $lineColor);
imageline($imgSeoul, 50, 1940, 550, 1940, $lineColor);

imagepng($imgSeoul, $outputDir . 'seoul_aesthetic.png');
imagedestroy($imgSeoul);


// ==========================================
// 2. LOVE FACTORY FRAME (love_factory.png)
// ==========================================
echo "Generating love_factory.png...\n";
$imgLove = imagecreatetruecolor(600, 2000);
imagealphablending($imgLove, false);
imagesavealpha($imgLove, true);

// Opaque Charcoal Dark Grey Background (#18181B)
$bgLove = imagecolorallocate($imgLove, 24, 24, 27);
imagefill($imgLove, 0, 0, $bgLove);

// Cut out transparent slots
foreach ($slots as $slot) {
    imagefilledrectangle($imgLove, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}

// Re-enable alpha blending
imagealphablending($imgLove, true);

// Colors
$zincGrey = imagecolorallocate($imgLove, 161, 161, 170); // #A1A1AA
$darkZinc = imagecolorallocate($imgLove, 63, 63, 70); // #3F3F46
$white = imagecolorallocate($imgLove, 244, 244, 245); // #F4F4F5
$redAccent = imagecolorallocate($imgLove, 230, 57, 70); // Y2K Red #E63946

// Draw thicker industrial borders around slots
foreach ($slots as $slot) {
    // 2px border
    imagerectangle($imgLove, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $darkZinc);
    imagerectangle($imgLove, $slot['x'] - 2, $slot['y'] - 2, $slot['x'] + $slot['w'] + 1, $slot['y'] + $slot['h'] + 1, $zincGrey);
}

// Star decorations between slots (Y2K style crosses)
drawFourPointStar($imgLove, 300, 440, 10, $zincGrey);
drawFourPointStar($imgLove, 300, 845, 10, $zincGrey);
drawFourPointStar($imgLove, 300, 1250, 10, $zincGrey);

// Stars at corners
drawFourPointStar($imgLove, 80, 1690, 8, $redAccent);
drawFourPointStar($imgLove, 520, 1690, 8, $redAccent);

// Bottom Section
// 1. Industrial Header
drawText($imgLove, 10, 0, -1, 1715, $zincGrey, 'arialbd', '✦ INDUSTRIAL LOVE CONCEPT ✦');

// 2. Bold title: "LOVE FACTORY" (Impact font)
drawText($imgLove, 36, 0, -1, 1785, $white, 'impact', 'LOVE FACTORY');

// 3. Technical parameters
drawText($imgLove, 9, 0, 50, 1850, $zincGrey, 'cour', 'SYS_BATCH: LF-99-2026');
drawText($imgLove, 9, 0, 50, 1870, $zincGrey, 'cour', 'HEART_RATE: 100% (STABLE)');
drawText($imgLove, 9, 0, 50, 1890, $zincGrey, 'cour', 'OPERATOR: ANTLR-90');

// 4. Large barcode
drawBarcode($imgLove, 320, 1840, 230, 55, $white);
drawText($imgLove, 8, 0, 320, 1910, $zincGrey, 'cour', 'CODE::*LF-404-STAY-COOL*');

// 5. Border framing for the entire strip (inset by 8px)
imagerectangle($imgLove, 8, 8, 600 - 9, 2000 - 9, $darkZinc);

imagepng($imgLove, $outputDir . 'love_factory.png');
imagedestroy($imgLove);


// ==========================================
// 3. CYBER NEON FRAME (cyber_neon.png)
// ==========================================
echo "Generating cyber_neon.png...\n";
$imgCyber = imagecreatetruecolor(600, 2000);
imagealphablending($imgCyber, false);
imagesavealpha($imgCyber, true);

// Opaque Deep Cyber Space Blue-Black Background (#0A0B10)
$bgCyber = imagecolorallocate($imgCyber, 10, 11, 16);
imagefill($imgCyber, 0, 0, $bgCyber);

// Cut out transparent slots
foreach ($slots as $slot) {
    imagefilledrectangle($imgCyber, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}

// Re-enable alpha blending
imagealphablending($imgCyber, true);

// Colors
$cyberCyan = imagecolorallocate($imgCyber, 0, 240, 255); // #00F0FF
$cyberPink = imagecolorallocate($imgCyber, 255, 46, 147); // #FF2E93
$neonDarkCyan = imagecolorallocatealpha($imgCyber, 0, 240, 255, 95); // glowing background border
$neonDarkPink = imagecolorallocatealpha($imgCyber, 255, 46, 147, 95); // glowing background border
$cyberText = imagecolorallocate($imgCyber, 220, 225, 250);

// Draw neon borders around slots (alternating cyan & pink glow)
for ($i = 0; $i < count($slots); $i++) {
    $slot = $slots[$i];
    $isCyan = ($i % 2 === 0);
    $glowCol = $isCyan ? $neonDarkCyan : $neonDarkPink;
    $solidCol = $isCyan ? $cyberCyan : $cyberPink;
    
    // Draw outer glow rectangle (3px width)
    for ($g = 1; $g <= 3; $g++) {
        imagerectangle($imgCyber, $slot['x'] - $g, $slot['y'] - $g, $slot['x'] + $slot['w'] + $g - 1, $slot['y'] + $slot['h'] + $g - 1, $glowCol);
    }
    // Draw solid inner border
    imagerectangle($imgCyber, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $solidCol);
    
    // Draw viewfinder crosshair corners inside each slot
    $cs = 12; // corner size
    // Top-left
    imageline($imgCyber, $slot['x'] + 5, $slot['y'] + 5, $slot['x'] + 5 + $cs, $slot['y'] + 5, $solidCol);
    imageline($imgCyber, $slot['x'] + 5, $slot['y'] + 5, $slot['x'] + 5, $slot['y'] + 5 + $cs, $solidCol);
    // Top-right
    imageline($imgCyber, $slot['x'] + $slot['w'] - 6, $slot['y'] + 5, $slot['x'] + $slot['w'] - 6 - $cs, $slot['y'] + 5, $solidCol);
    imageline($imgCyber, $slot['x'] + $slot['w'] - 6, $slot['y'] + 5, $slot['x'] + $slot['w'] - 6, $slot['y'] + 5 + $cs, $solidCol);
    // Bottom-left
    imageline($imgCyber, $slot['x'] + 5, $slot['y'] + $slot['h'] - 6, $slot['x'] + 5 + $cs, $slot['y'] + $slot['h'] - 6, $solidCol);
    imageline($imgCyber, $slot['x'] + 5, $slot['y'] + $slot['h'] - 6, $slot['x'] + 5, $slot['y'] + $slot['h'] - 6 - $cs, $solidCol);
    // Bottom-right
    imageline($imgCyber, $slot['x'] + $slot['w'] - 6, $slot['y'] + $slot['h'] - 6, $slot['x'] + $slot['w'] - 6 - $cs, $slot['y'] + $slot['h'] - 6, $solidCol);
    imageline($imgCyber, $slot['x'] + $slot['w'] - 6, $slot['y'] + $slot['h'] - 6, $slot['x'] + $slot['w'] - 6, $slot['y'] + $slot['h'] - 6 - $cs, $solidCol);
}

// Background digital wireframe lines in bottom margins
for ($l = 0; $l < 5; $l++) {
    $yLine = 1680 + $l * 12;
    $lineAlpha = imagecolorallocatealpha($imgCyber, 0, 240, 255, 115 - $l * 20);
    imageline($imgCyber, 50, $yLine, 550, $yLine, $lineAlpha);
}

// Bottom Section
// 1. Cyber Title "NEON DRIVE" (Arial Bold)
drawText($imgCyber, 28, 0, -1, 1785, $cyberCyan, 'arialbd', 'NEON DRIVE');

// 2. Neon digital clock widget
$curDate = date('Y/m/d');
drawText($imgCyber, 11, 0, 50, 1855, $cyberText, 'courbd', "SYS_CLOCK: [ $curDate ]");
drawText($imgCyber, 11, 0, 50, 1885, $cyberPink, 'courbd', "STATUS:    [ ONLINE_CYBER ]");

// 3. Tech circular layout simulator
$circleColor = imagecolorallocatealpha($imgCyber, 0, 240, 255, 60);
imagearc($imgCyber, 470, 1865, 80, 80, 0, 360, $circleColor);
imagearc($imgCyber, 470, 1865, 50, 50, 0, 360, $circleColor);
imageline($imgCyber, 430, 1865, 510, 1865, $circleColor);
imageline($imgCyber, 470, 1825, 470, 1905, $circleColor);
drawText($imgCyber, 7, 0, 442, 1870, $cyberCyan, 'cour', 'SYS_LOCK');

imagepng($imgCyber, $outputDir . 'cyber_neon.png');
imagedestroy($imgCyber);

echo "All frames generated successfully!\n";
?>
