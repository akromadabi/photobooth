<?php
// Set execution timeout to 90 seconds
set_time_limit(90);

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
    'malgun'    => $fontDir . 'malgun.ttf', // Korean font if available
    'oldengl'   => $fontDir . 'OLDENGL.TTF'
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
            $x = intval(($w - $textWidth) / 2);
        }
        imagettftext($img, $size, $angle, intval($x), intval($y), $color, $fonts[$fontKey], $text);
    } else {
        // Fallback to basic built-in font
        $font_fallback = 5; // internal large font
        $text_w = imagefontwidth($font_fallback) * strlen($text);
        if ($x === -1) {
            $w = imagesx($img);
            $x = intval(($w - $text_w) / 2);
        }
        imagestring($img, $font_fallback, intval($x), intval($y - 10), $text, $color);
    }
}

// Helper: draw a beautiful 4-point Y2K star (✦)
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

// Helper: draw a procedural barcode
function drawBarcode($img, $x, $y, $w, $h, $color) {
    $currentX = $x;
    srand(42); // deterministic for design consistency
    while ($currentX < $x + $w) {
        $lineWidth = rand(2, 4);
        $gap = rand(2, 5);
        if ($currentX + $lineWidth > $x + $w) {
            $lineWidth = $x + $w - $currentX;
        }
        imagefilledrectangle($img, intval($currentX), intval($y), intval($currentX + $lineWidth - 1), intval($y + $h - 1), $color);
        $currentX += $lineWidth + $gap;
    }
}

// Helper: draw a heart shape
function drawHeart($img, $cx, $cy, $size, $color) {
    $r = $size / 4;
    imagefilledellipse($img, intval($cx - $r), intval($cy - $r), intval($size/2), intval($size/2), $color);
    imagefilledellipse($img, intval($cx + $r), intval($cy - $r), intval($size/2), intval($size/2), $color);
    $points = [
        intval($cx - 2*$r), intval($cy - $r),
        intval($cx + 2*$r), intval($cy - $r),
        intval($cx), intval($cy + 1.5*$size/2)
    ];
    imagefilledpolygon($img, $points, $color);
}

// Helper: draw thick viewfinder line
function drawThickLine($img, $x1, $y1, $x2, $y2, $thickness, $color) {
    if ($x1 === $x2) { // vertical
        imagefilledrectangle($img, intval($x1 - $thickness/2), intval(min($y1, $y2)), intval($x1 + $thickness/2), intval(max($y1, $y2)), $color);
    } else { // horizontal
        imagefilledrectangle($img, intval(min($x1, $x2)), intval($y1 - $thickness/2), intval(max($x1, $x2)), intval($y1 + $thickness/2), $color);
    }
}

// Helper: draw pop-art explosion burst
function drawBurst($img, $cx, $cy, $numPoints, $minR, $maxR, $color, $borderColor) {
    $points = [];
    for ($i = 0; $i < $numPoints; $i++) {
        $angle = ($i * 2 * M_PI) / $numPoints;
        $r = ($i % 2 === 0) ? $maxR : $minR;
        $points[] = intval($cx + cos($angle) * $r);
        $points[] = intval($cy + sin($angle) * $r);
    }
    imagefilledpolygon($img, $points, $color);
    imagepolygon($img, $points, $borderColor);
}

// Helper: draw elegant botanical leaf branch
function drawBotanicalBranch($img, $startX, $startY, $endX, $endY, $color) {
    $steps = 20;
    $prevX = $startX;
    $prevY = $startY;
    for ($i = 1; $i <= $steps; $i++) {
        $t = $i / $steps;
        // Quadratic bezier curve bending slightly
        $ctrlX = ($startX + $endX) / 2 + 25;
        $ctrlY = ($startY + $endY) / 2 - 15;
        
        $x = (1 - $t) * (1 - $t) * $startX + 2 * (1 - $t) * $t * $ctrlX + $t * $t * $endX;
        $y = (1 - $t) * (1 - $t) * $startY + 2 * (1 - $t) * $t * $ctrlY + $t * $t * $endY;
        
        imageline($img, intval($prevX), intval($prevY), intval($x), intval($y), $color);
        
        // Leaves along stem
        if ($i % 5 === 0 && $i < $steps) {
            imagefilledellipse($img, intval($x), intval($y), 18, 9, $color);
        }
        $prevX = $x;
        $prevY = $y;
    }
    imagefilledellipse($img, intval($endX), intval($endY), 14, 7, $color);
}

// Standard vertical strip slots
$standardSlots = [
    ['x' => 50, 'y' => 50, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 455, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 860, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1265, 'w' => 500, 'h' => 375]
];

$dateStr = date('Y.m.d');


// ==========================================
// 1. CLASSIC BLACK (classic_strip_black.png)
// ==========================================
echo "Generating classic_strip_black.png...\n";
$imgClassic = imagecreatetruecolor(600, 2000);
imagealphablending($imgClassic, false);
imagesavealpha($imgClassic, true);

$bgClassic = imagecolorallocate($imgClassic, 18, 18, 18); // #121212
imagefill($imgClassic, 0, 0, $bgClassic);

$transparent = imagecolorallocatealpha($imgClassic, 0, 0, 0, 127);
foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgClassic, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgClassic, true);

$gold = imagecolorallocate($imgClassic, 212, 175, 55); // Premium Gold #D4AF37
$goldAlpha = imagecolorallocatealpha($imgClassic, 212, 175, 55, 40);
$white = imagecolorallocate($imgClassic, 240, 240, 240);

// Borders
foreach ($standardSlots as $slot) {
    for ($b = 1; $b <= 2; $b++) {
        imagerectangle($imgClassic, $slot['x'] - $b, $slot['y'] - $b, $slot['x'] + $slot['w'] + $b - 1, $slot['y'] + $slot['h'] + $b - 1, $gold);
    }
}

// Header
drawText($imgClassic, 26, 0, -1, 45, $gold, 'georgiai', 'MEMORIES');

// Bottom Titles
drawText($imgClassic, 40, 0, -1, 1770, $gold, 'georgiab', 'CLASSIC BLACK');
drawText($imgClassic, 15, 0, 60, 1850, $white, 'courbd', "COLLECTION: NO. 01");
drawText($imgClassic, 15, 0, 60, 1885, $white, 'courbd', "DATE:       $dateStr");

// Barcode
drawBarcode($imgClassic, 380, 1835, 160, 50, $gold);
drawText($imgClassic, 10, 0, 380, 1900, $gold, 'cour', '579D-CLASSIC');

// Overlapping 1: Gold seal stamp on Slot 2 bottom-right
imagefilledellipse($imgClassic, 510, 800, 80, 80, $goldAlpha);
imageellipse($imgClassic, 510, 800, 80, 80, $gold);
imageellipse($imgClassic, 510, 800, 74, 74, $gold);
drawText($imgClassic, 9, 0, 480, 796, $gold, 'arialbd', 'PREMIUM');
drawText($imgClassic, 9, 0, 482, 812, $gold, 'arialbd', 'QUALITY');

// Overlapping 2: Gold cursive signature on Slot 4 bottom-left
drawText($imgClassic, 20, -5, 65, 1625, $gold, 'georgiai', 'Studio Portrait');

imagepng($imgClassic, $outputDir . 'classic_strip_black.png');
imagedestroy($imgClassic);


// ==========================================
// 2. CREATIVE RED (creative_strip_red.png)
// ==========================================
echo "Generating creative_strip_red.png...\n";
$imgRed = imagecreatetruecolor(600, 2000);
imagealphablending($imgRed, false);
imagesavealpha($imgRed, true);

$bgRed = imagecolorallocate($imgRed, 230, 57, 70); // #E63946
imagefill($imgRed, 0, 0, $bgRed);

// Draw polka dots on red background
imagealphablending($imgRed, true);
$dotColor = imagecolorallocatealpha($imgRed, 255, 255, 255, 100);
for ($x = 20; $x < 600; $x += 40) {
    for ($y = 20; $y < 2000; $y += 40) {
        $inSlot = false;
        foreach ($standardSlots as $slot) {
            if ($x >= $slot['x'] && $x <= ($slot['x'] + $slot['w']) &&
                $y >= $slot['y'] && $y <= ($slot['y'] + $slot['h'])) {
                $inSlot = true;
                break;
            }
        }
        if (!$inSlot) {
            imagefilledellipse($imgRed, $x, $y, 6, 6, $dotColor);
        }
    }
}
imagealphablending($imgRed, false);

// Shadows behind borders
$black = imagecolorallocate($imgRed, 0, 0, 0);
foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgRed, $slot['x'] - 4 + 4, $slot['y'] - 4 + 4, $slot['x'] + $slot['w'] + 3 + 4, $slot['y'] + $slot['h'] + 3 + 4, $black);
}

// Cut out transparent slots
foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgRed, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgRed, true);

$white = imagecolorallocate($imgRed, 255, 255, 255);
$yellow = imagecolorallocate($imgRed, 255, 223, 0); // Pop Art Yellow

// Draw white borders
foreach ($standardSlots as $slot) {
    for ($b = 1; $b <= 4; $b++) {
        imagerectangle($imgRed, $slot['x'] - $b, $slot['y'] - $b, $slot['x'] + $slot['w'] + $b - 1, $slot['y'] + $slot['h'] + $b - 1, $white);
    }
}

// Titles
drawText($imgRed, 28, 0, -1, 45, $white, 'impact', 'C R E A T I V E');
drawText($imgRed, 50, 0, -1, 1775, $white, 'impact', 'SUPER RED');
drawText($imgRed, 15, 0, 60, 1850, $white, 'arialbd', "SERIES: POP-05");
drawText($imgRed, 15, 0, 60, 1885, $white, 'arialbd', "DATE:   $dateStr");

// Overlapping 1: Speech bubble "SMILE!" at top-right of Slot 1
imagefilledellipse($imgRed, 495, 75, 95, 70, $yellow);
imageellipse($imgRed, 495, 75, 95, 70, $black);
$tailPoints = [
    485, 100,
    505, 100,
    475, 120
];
imagefilledpolygon($imgRed, $tailPoints, $yellow);
imagepolygon($imgRed, $tailPoints, $black);
drawText($imgRed, 13, 12, 472, 81, $black, 'impact', 'SMILE!');

// Overlapping 2: Burst "POP!" at top-left of Slot 3
drawBurst($imgRed, 70, 880, 14, 25, 45, $yellow, $black);
drawText($imgRed, 15, -10, 48, 888, $black, 'impact', 'POP!');

imagepng($imgRed, $outputDir . 'creative_strip_red.png');
imagedestroy($imgRed);


// ==========================================
// 3. NEWSPAPER VINTAGE (newspaper_strip.png)
// ==========================================
echo "Generating newspaper_strip.png...\n";
$imgNews = imagecreatetruecolor(600, 2000);
imagealphablending($imgNews, false);
imagesavealpha($imgNews, true);

$bgNews = imagecolorallocate($imgNews, 232, 228, 219); // #E8E4DB
imagefill($imgNews, 0, 0, $bgNews);

foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgNews, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgNews, true);

$charcoal = imagecolorallocate($imgNews, 40, 40, 40);
$stampRed = imagecolorallocatealpha($imgNews, 190, 40, 40, 50); // semi-trans
$stampRedSolid = imagecolorallocate($imgNews, 190, 40, 40);

// Borders
foreach ($standardSlots as $slot) {
    imagerectangle($imgNews, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $charcoal);
}

// Newspaper Header
drawText($imgNews, 36, 0, -1, 45, $charcoal, 'georgiab', 'THE DAILY RETRO');

// Double rule below header
imageline($imgNews, 40, 80, 560, 80, $charcoal);
imageline($imgNews, 40, 84, 560, 84, $charcoal);
drawText($imgNews, 11, 0, 45, 100, $charcoal, 'courbd', 'VOL. XLV No. 345');
drawText($imgNews, 11, 0, -1, 100, $charcoal, 'courbd', 'ESTABLISHED 1990');
drawText($imgNews, 11, 0, 420, 100, $charcoal, 'courbd', 'PRICE: $0.10');
imageline($imgNews, 40, 110, 560, 110, $charcoal);
imageline($imgNews, 40, 114, 560, 114, $charcoal);

// Bottom Section
drawText($imgNews, 42, 0, -1, 1765, $charcoal, 'impact', 'EXTRA! EXTRA!');
drawText($imgNews, 14, 0, 60, 1845, $charcoal, 'courbd', "ARCHIVE DATE:  $dateStr");
drawText($imgNews, 14, 0, 60, 1875, $charcoal, 'courbd', "LOCATION:      STUDIO RETRO");

// Overlapping 1: Red distressed APPROVED stamp overlapping bottom-left of Slot 3
$cx = 100; $cy = 1220;
$w2 = 60; $h2 = 25;
$angle = 15 * M_PI / 180;
$cos = cos($angle);
$sin = sin($angle);
$pts = [
    intval($cx - $w2*$cos + $h2*$sin), intval($cy - $w2*$sin - $h2*$cos),
    intval($cx + $w2*$cos + $h2*$sin), intval($cy + $w2*$sin - $h2*$cos),
    intval($cx + $w2*$cos - $h2*$sin), intval($cy + $w2*$sin + $h2*$cos),
    intval($cx - $w2*$cos - $h2*$sin), intval($cy - $w2*$sin + $h2*$cos)
];
imagefilledpolygon($imgNews, $pts, $stampRed);
for($t=0; $t<3; $t++) {
    $w2_t = $w2 - $t;
    $h2_t = $h2 - $t;
    $pts_t = [
        intval($cx - $w2_t*$cos + $h2_t*$sin), intval($cy - $w2_t*$sin - $h2_t*$cos),
        intval($cx + $w2_t*$cos + $h2_t*$sin), intval($cy + $w2_t*$sin - $h2_t*$cos),
        intval($cx + $w2_t*$cos - $h2_t*$sin), intval($cy + $w2_t*$sin + $h2_t*$cos),
        intval($cx - $w2_t*$cos - $h2_t*$sin), intval($cy - $w2_t*$sin + $h2_t*$cos)
    ];
    imagepolygon($imgNews, $pts_t, $stampRedSolid);
}
drawText($imgNews, 14, 15, 60, 1228, $stampRedSolid, 'arialbd', 'APPROVED');

// Overlapping 2: Vintage postage stamp overlapping top-right of Slot 1
$postageBg = imagecolorallocate($imgNews, 240, 235, 222);
imagefilledrectangle($imgNews, 480, 50, 540, 110, $postageBg);
imagerectangle($imgNews, 480, 50, 540, 110, $charcoal);
for ($x = 485; $x <= 535; $x += 10) {
    imagefilledellipse($imgNews, $x, 50, 4, 4, $bgNews);
    imagefilledellipse($imgNews, $x, 110, 4, 4, $bgNews);
}
for ($y = 55; $y <= 105; $y += 10) {
    imagefilledellipse($imgNews, 480, $y, 4, 4, $bgNews);
    imagefilledellipse($imgNews, 540, $y, 4, 4, $bgNews);
}
imageellipse($imgNews, 510, 80, 24, 24, $charcoal);
drawText($imgNews, 8, 0, 502, 102, $charcoal, 'courbd', '10c');

imagepng($imgNews, $outputDir . 'newspaper_strip.png');
imagedestroy($imgNews);


// ==========================================
// 3b. NEWSPAPER FOUR (newspaper_four.png)
// ==========================================
echo "Generating newspaper_four.png...\n";
$imgNewsFour = imagecreatetruecolor(600, 2200);
imagealphablending($imgNewsFour, false);
imagesavealpha($imgNewsFour, true);

$bgNewsFour = imagecolorallocate($imgNewsFour, 232, 228, 219); // #E8E4DB
imagefill($imgNewsFour, 0, 0, $bgNewsFour);

$fourSlots = [
    ['x' => 50, 'y' => 330, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 725, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1120, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1515, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgNewsFour, 0, 0, 0, 127);
foreach ($fourSlots as $slot) {
    imagefilledrectangle($imgNewsFour, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgNewsFour, true);

$charcoal = imagecolorallocate($imgNewsFour, 40, 40, 40);
$white = imagecolorallocate($imgNewsFour, 255, 255, 255);

// Outer card borders
imagerectangle($imgNewsFour, 20, 20, 600 - 21, 2200 - 21, $charcoal);
imagerectangle($imgNewsFour, 24, 24, 600 - 25, 2200 - 25, $charcoal);

// Borders around slots
foreach ($fourSlots as $slot) {
    imagerectangle($imgNewsFour, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $charcoal);
}

// Newspaper Header (Masthead)
// Special Edition line
imageline($imgNewsFour, 40, 55, 180, 55, $charcoal);
imageline($imgNewsFour, 420, 55, 560, 55, $charcoal);
drawText($imgNewsFour, 11, 0, -1, 60, $charcoal, 'georgiai', 'Special Edition');

// Left meta column
drawText($imgNewsFour, 9, 0, 50, 115, $charcoal, 'georgiai', '@reallygreatsite');
drawText($imgNewsFour, 9, 0, 50, 135, $charcoal, 'georgiai', '+123-456-7890');

// Right meta column
drawText($imgNewsFour, 9, 0, 420, 115, $charcoal, 'georgiai', '123 Anywhere');
drawText($imgNewsFour, 9, 0, 420, 135, $charcoal, 'georgiai', 'St., Any City');

// Title: Our Memories in Old English
drawText($imgNewsFour, 36, 0, -1, 135, $charcoal, 'oldengl', 'Our Memories');

// Stars on left and right of title (procedural drawing based on text width)
$bbox = imagettfbbox(36, 0, $fonts['oldengl'], 'Our Memories');
$textW = abs($bbox[2] - $bbox[0]);
$centerX = 300;
$leftStarX = $centerX - ($textW / 2) - 40;
$rightStarX = $centerX + ($textW / 2) + 40;
drawFourPointStar($imgNewsFour, $leftStarX, 120, 12, $charcoal);
drawFourPointStar($imgNewsFour, $rightStarX, 120, 12, $charcoal);

// Subtitle
drawText($imgNewsFour, 18, 0, -1, 200, $charcoal, 'georgiab', 'Best Friend Forever');

// Double underline below header
drawThickLine($imgNewsFour, 40, 240, 560, 240, 4, $charcoal);
imageline($imgNewsFour, 40, 248, 560, 248, $charcoal);


// Newspaper Footer
// Double underline above footer
imageline($imgNewsFour, 40, 1920, 560, 1920, $charcoal);
imageline($imgNewsFour, 40, 1926, 560, 1926, $charcoal);

// Brand box (black rectangle on left)
imagefilledrectangle($imgNewsFour, 50, 1950, 280, 2130, $charcoal);

// Centered white Old English text "Groovy Studio" inside brand box
$brandText = 'Groovy Studio';
$brandBbox = imagettfbbox(20, 0, $fonts['oldengl'], $brandText);
$brandW = abs($brandBbox[2] - $brandBbox[0]);
$brandH = abs($brandBbox[5] - $brandBbox[1]);
$brandX = 165 - ($brandW / 2);
$brandY = 2040 + ($brandH / 2);
imagettftext($imgNewsFour, 20, 0, $brandX, $brandY, $white, $fonts['oldengl'], $brandText);

// Quote/story box (framed rectangle on right)
imagerectangle($imgNewsFour, 310, 1950, 550, 2130, $charcoal);
drawText($imgNewsFour, 12, 0, 325, 1980, $charcoal, 'georgiab', 'On This Page of Ours');
drawText($imgNewsFour, 9, 0, 325, 2010, $charcoal, 'georgiai', 'Best stories came from random laughs,');
drawText($imgNewsFour, 9, 0, 325, 2035, $charcoal, 'georgiai', 'long chats, and friends who were always');
drawText($imgNewsFour, 9, 0, 325, 2060, $charcoal, 'georgiai', 'there through every part of life.');

// Footer metadata (barcode & weather/date)
drawBarcode($imgNewsFour, 370, 2150, 180, 30, $charcoal);
drawText($imgNewsFour, 7, 0, 370, 2190, $charcoal, 'cour', '9940172-88295-001');

drawText($imgNewsFour, 8, 0, 50, 2160, $charcoal, 'courbd', 'ISSUE DATE: ' . $dateStr);
drawText($imgNewsFour, 8, 0, 50, 2180, $charcoal, 'courbd', 'PRICE:      PRICELESS');

imagepng($imgNewsFour, $outputDir . 'newspaper_four.png');
imagedestroy($imgNewsFour);


// ==========================================
// 3c. NEWSPAPER BIRTHDAY (newspaper_birthday.png)
// ==========================================
echo "Generating newspaper_birthday.png...\n";
$imgBirth = imagecreatetruecolor(600, 2200);
imagealphablending($imgBirth, false);
imagesavealpha($imgBirth, true);

$bgBirth = imagecolorallocate($imgBirth, 234, 230, 223); // #eae6df
imagefill($imgBirth, 0, 0, $bgBirth);

$birthSlots = [
    ['x' => 50, 'y' => 330, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 725, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1120, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1515, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgBirth, 0, 0, 0, 127);
foreach ($birthSlots as $slot) {
    imagefilledrectangle($imgBirth, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgBirth, true);

$charcoal = imagecolorallocate($imgBirth, 40, 40, 40);
$white = imagecolorallocate($imgBirth, 255, 255, 255);
$red = imagecolorallocate($imgBirth, 220, 50, 50);

// Outer card borders
imagerectangle($imgBirth, 20, 20, 600 - 21, 2200 - 21, $charcoal);
imagerectangle($imgBirth, 24, 24, 600 - 25, 2200 - 25, $charcoal);

// Borders around slots
foreach ($birthSlots as $slot) {
    imagerectangle($imgBirth, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $charcoal);
}

// Newspaper Header (Masthead)
// Special Edition line
imageline($imgBirth, 40, 55, 180, 55, $charcoal);
imageline($imgBirth, 420, 55, 560, 55, $charcoal);
drawText($imgBirth, 11, 0, -1, 60, $charcoal, 'georgiai', 'Special Edition');

// Date top right
$currentDateStr = strtoupper(date('F d, Y'));
drawText($imgBirth, 10, 0, 430, 60, $charcoal, 'georgiab', $currentDateStr);

// Headline text: BREAKING NEWS
drawText($imgBirth, 30, 0, -1, 105, $charcoal, 'georgiab', 'BREAKING NEWS');

// Double underline below headline
imageline($imgBirth, 40, 115, 560, 115, $charcoal);
imageline($imgBirth, 40, 119, 560, 119, $charcoal);

// Subhead texts (with generic fallbacks for dynamic replacement)
drawText($imgBirth, 26, 0, -1, 175, $charcoal, 'georgiai', "It's My");
drawText($imgBirth, 52, 0, -1, 245, $charcoal, 'impact', 'BIRTHDAY!');

// Double underline below header
drawThickLine($imgBirth, 40, 275, 560, 275, 4, $charcoal);
imageline($imgBirth, 40, 283, 560, 283, $charcoal);


// =========================================================================
// Overlapping Elements (Cross-photo content) - Pedoman standar_bingkai.md
// =========================================================================

// Element 1: Red slanted rectangle badge "HOT NEWS" overlapping top-left of Slot 0
$cx1 = 75; $cy1 = 325;
$w2 = 60; $h2 = 16;
$angle1 = -15 * M_PI / 180;
$cos1 = cos($angle1);
$sin1 = sin($angle1);
$pts1 = [
    intval($cx1 - $w2*$cos1 + $h2*$sin1), intval($cy1 - $w2*$sin1 - $h2*$cos1),
    intval($cx1 + $w2*$cos1 + $h2*$sin1), intval($cy1 + $w2*$sin1 - $h2*$cos1),
    intval($cx1 + $w2*$cos1 - $h2*$sin1), intval($cy1 + $w2*$sin1 + $h2*$cos1),
    intval($cx1 - $w2*$cos1 - $h2*$sin1), intval($cy1 - $w2*$sin1 + $h2*$cos1)
];
imagefilledpolygon($imgBirth, $pts1, $red);
imagepolygon($imgBirth, $pts1, $charcoal);
imagettftext($imgBirth, 9, 15, 45, 332, $white, $fonts['arialbd'], 'HOT NEWS');

// Element 2: Semi-transparent circular "APPROVED" stamp overlapping bottom-right of Slot 1
$stampColor = imagecolorallocatealpha($imgBirth, 40, 40, 40, 60);
$stampSolid = imagecolorallocate($imgBirth, 40, 40, 40);
imagefilledellipse($imgBirth, 520, 1100, 75, 75, $stampColor);
imageellipse($imgBirth, 520, 1100, 75, 75, $stampSolid);
imageellipse($imgBirth, 520, 1100, 67, 67, $stampSolid);
drawText($imgBirth, 9, 0, 492, 1105, $stampSolid, 'arialbd', 'APPROVED');

// Element 3: Black filled "LIVE REPORT" banner overlapping border between Slot 2 & Slot 3
imagefilledrectangle($imgBirth, 210, 1490, 390, 1520, $charcoal);
$badgeBbox = imagettfbbox(9, 0, $fonts['arialbd'], 'LIVE REPORT');
$badgeW = abs($badgeBbox[2] - $badgeBbox[0]);
$badgeX = 300 - ($badgeW / 2);
imagettftext($imgBirth, 9, 0, $badgeX, 1511, $white, $fonts['arialbd'], 'LIVE REPORT');


// Newspaper Footer
// Double underline above footer
imageline($imgBirth, 40, 1920, 560, 1920, $charcoal);
imageline($imgBirth, 40, 1926, 560, 1926, $charcoal);

// Left Column: Mock Newspaper Article (Birthday Special Report)
drawText($imgBirth, 10, 0, 50, 1960, $charcoal, 'georgiab', 'BIRTHDAY SPECIAL');
drawText($imgBirth, 9, 0, 50, 1980, $charcoal, 'georgiai', 'In today\'s special edition, we are');
drawText($imgBirth, 9, 0, 50, 2000, $charcoal, 'georgiai', 'thrilled to report a fantastic new');
drawText($imgBirth, 9, 0, 50, 2020, $charcoal, 'georgiai', 'milestone. Today marks a day of');
drawText($imgBirth, 9, 0, 50, 2040, $charcoal, 'georgiai', 'joy, laughter, and celebration.');
drawText($imgBirth, 9, 0, 50, 2065, $charcoal, 'georgiai', 'Sources reveal that the star of');
drawText($imgBirth, 9, 0, 50, 2085, $charcoal, 'georgiai', 'the show continues to be an');
drawText($imgBirth, 9, 0, 50, 2105, $charcoal, 'georgiai', 'inspiration, spreading positivity');
drawText($imgBirth, 9, 0, 50, 2125, $charcoal, 'georgiai', 'and light wherever they go.');

// Right Column: Highlighted Editorial Birthday Quote
drawText($imgBirth, 11, 0, 310, 1965, $charcoal, 'georgiab', '"Wishing you a fantastic');
drawText($imgBirth, 11, 0, 310, 1985, $charcoal, 'georgiab', 'year ahead! May your days');
drawText($imgBirth, 11, 0, 310, 2005, $charcoal, 'georgiab', 'be as bright & wonderful');
drawText($imgBirth, 11, 0, 310, 2025, $charcoal, 'georgiab', 'as you are!"');

drawText($imgBirth, 9, 0, 310, 2055, $charcoal, 'georgiai', 'Today\'s forecast calls for cake,');
drawText($imgBirth, 9, 0, 310, 2075, $charcoal, 'georgiai', 'laughter, and sweet memories');
drawText($imgBirth, 9, 0, 310, 2095, $charcoal, 'georgiai', 'with loved ones. Experts suggest');
drawText($imgBirth, 9, 0, 310, 2115, $charcoal, 'georgiai', 'lots of smiles and wishes.');

// Footer metadata (barcode & weather/date)
drawBarcode($imgBirth, 370, 2150, 180, 30, $charcoal);
drawText($imgBirth, 7, 0, 370, 2190, $charcoal, 'cour', '9940172-88295-002');

drawText($imgBirth, 8, 0, 50, 2160, $charcoal, 'courbd', 'ISSUE DATE: ' . $dateStr);
drawText($imgBirth, 8, 0, 50, 2180, $charcoal, 'courbd', 'BY:         GROOVY STUDIO');

imagepng($imgBirth, $outputDir . 'newspaper_birthday.png');
imagedestroy($imgBirth);


// ==========================================
// 3d. NEWSPAPER POST (newspaper_post.png)
// ==========================================
echo "Generating newspaper_post.png...\n";
$imgPost = imagecreatetruecolor(600, 2200);
imagealphablending($imgPost, false);
imagesavealpha($imgPost, true);

$bgPost = imagecolorallocate($imgPost, 234, 230, 223); // #eae6df
imagefill($imgPost, 0, 0, $bgPost);

$postSlots = [
    ['x' => 50, 'y' => 310, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 705, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1100, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1495, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgPost, 0, 0, 0, 127);
foreach ($postSlots as $slot) {
    imagefilledrectangle($imgPost, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgPost, true);

$charcoal = imagecolorallocate($imgPost, 40, 40, 40);
$white = imagecolorallocate($imgPost, 255, 255, 255);
$red = imagecolorallocate($imgPost, 220, 50, 50);

// Outer card borders
imagerectangle($imgPost, 20, 20, 600 - 21, 2200 - 21, $charcoal);
imagerectangle($imgPost, 24, 24, 600 - 25, 2200 - 25, $charcoal);

// Borders around slots
foreach ($postSlots as $slot) {
    imagerectangle($imgPost, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $charcoal);
}

// Newspaper Header (Masthead) - Boxed Headline style
imagerectangle($imgPost, 40, 40, 560, 100, $charcoal);
imageline($imgPost, 180, 40, 180, 100, $charcoal);

// BREAKING NEWS on left
drawText($imgPost, 10, 0, 52, 75, $charcoal, 'arialbd', 'BREAKING');
drawText($imgPost, 10, 0, 118, 75, $charcoal, 'arialbd', 'NEWS');

// Gothic title on right
$postTitle = 'The Graduation Post';
$postBbox = imagettfbbox(20, 0, $fonts['oldengl'], $postTitle);
$postW = abs($postBbox[2] - $postBbox[0]);
$postX = 370 - ($postW / 2);
imagettftext($imgPost, 20, 0, intval($postX), 82, $charcoal, $fonts['oldengl'], $postTitle);

// Under masthead box
imageline($imgPost, 40, 115, 560, 115, $charcoal);
drawText($imgPost, 9, 0, 50, 128, $charcoal, 'georgia', 'NEW YORK');
$currentPostDate = date('l, F j, Y'); // e.g. Saturday, June 9, 2030
$currentPostDateUpper = strtoupper($currentPostDate);
// Right align date
$dateBbox = imagettfbbox(8, 0, $fonts['georgia'], $currentPostDateUpper);
$dateW = abs($dateBbox[2] - $dateBbox[0]);
drawText($imgPost, 8, 0, 550 - $dateW, 128, $charcoal, 'georgia', $currentPostDateUpper);

// Double underlines below header
imageline($imgPost, 40, 138, 560, 138, $charcoal);
imageline($imgPost, 40, 142, 560, 142, $charcoal);

// Note: Main text (Headline) "SHE DID IT!" and subhead "Samira Hadid is Graduating"
// are drawn dynamically by the kiosk app to allow customizable "COMING SOON" headlines!
// So we do not write them statically on the background image.

// Double underline below headline area
drawThickLine($imgPost, 40, 260, 560, 260, 4, $charcoal);
imageline($imgPost, 40, 268, 560, 268, $charcoal);


// =========================================================================
// Overlapping Elements (Cross-photo content) - Pedoman standar_bingkai.md
// =========================================================================

// Element 1: Red slanted rectangle badge "EXCLUSIVE" overlapping top-left of Slot 0
$cx2 = 75; $cy2 = 305;
$w2 = 60; $h2 = 16;
$angle2 = -15 * M_PI / 180;
$cos2 = cos($angle2);
$sin2 = sin($angle2);
$pts2 = [
    intval($cx2 - $w2*$cos2 + $h2*$sin2), intval($cy2 - $w2*$sin2 - $h2*$cos2),
    intval($cx2 + $w2*$cos2 + $h2*$sin2), intval($cy2 + $w2*$sin2 - $h2*$cos2),
    intval($cx2 + $w2*$cos2 - $h2*$sin2), intval($cy2 + $w2*$sin2 + $h2*$cos2),
    intval($cx2 - $w2*$cos2 - $h2*$sin2), intval($cy2 - $w2*$sin2 + $h2*$cos2)
];
imagefilledpolygon($imgPost, $pts2, $red);
imagepolygon($imgPost, $pts2, $charcoal);
imagettftext($imgPost, 9, 15, 45, 312, $white, $fonts['arialbd'], 'EXCLUSIVE');

// Element 2: Black 4-point star ornaments on left and right of dynamic hashtag
drawFourPointStar($imgPost, 80, 1980, 12, $charcoal);
drawFourPointStar($imgPost, 520, 1980, 12, $charcoal);


// Newspaper Footer
// Double underline above footer
imageline($imgPost, 40, 1910, 560, 1910, $charcoal);
imageline($imgPost, 40, 1916, 560, 1916, $charcoal);

// Footer metadata (barcode & weather/date)
drawBarcode($imgPost, 370, 2140, 180, 30, $charcoal);
drawText($imgPost, 7, 0, 370, 2180, $charcoal, 'cour', '9940172-88295-003');

drawText($imgPost, 8, 0, 50, 2150, $charcoal, 'courbd', 'ISSUE DATE: ' . $dateStr);
drawText($imgPost, 8, 0, 50, 2170, $charcoal, 'courbd', 'BY:         GROOVY STUDIO');

imagepng($imgPost, $outputDir . 'newspaper_post.png');
imagedestroy($imgPost);


// ==========================================
// 3e. INVOICE PREMIUM (receipt_invoice.png)
// ==========================================
echo "Generating receipt_invoice.png...\n";
$imgInv = imagecreatetruecolor(600, 2400);
imagealphablending($imgInv, false);
imagesavealpha($imgInv, true);

$bgInv = imagecolorallocate($imgInv, 245, 245, 245); // #f5f5f5
imagefill($imgInv, 0, 0, $bgInv);

$invSlots = [
    ['x' => 50, 'y' => 360, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 775, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1190, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1605, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgInv, 0, 0, 0, 127);
foreach ($invSlots as $slot) {
    imagefilledrectangle($imgInv, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgInv, true);

$charcoal = imagecolorallocate($imgInv, 40, 40, 40);
$white = imagecolorallocate($imgInv, 255, 255, 255);

// Outer card borders
imagerectangle($imgInv, 20, 20, 600 - 21, 2400 - 21, $charcoal);
imagerectangle($imgInv, 24, 24, 600 - 25, 2400 - 25, $charcoal);

// Borders around slots
foreach ($invSlots as $slot) {
    imagerectangle($imgInv, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $charcoal);
}

// 1. Eagle Logo
// central body
imagefilledpolygon($imgInv, [75, 45, 70, 70, 75, 75, 80, 70], $charcoal);
// left wing
imagefilledpolygon($imgInv, [75, 55, 45, 35, 55, 55, 48, 68, 65, 62], $charcoal);
// right wing
imagefilledpolygon($imgInv, [75, 55, 105, 35, 95, 55, 102, 68, 85, 62], $charcoal);

// Company name - shifted slightly right to x = 145 to prevent overlapping with logo overlay
drawText($imgInv, 10, 0, 145, 55, $charcoal, 'arialbd', 'Ginyard');
drawText($imgInv, 10, 0, 145, 75, $charcoal, 'arialbd', 'International Co.');

// Right side header info
drawText($imgInv, 9, 0, 400, 55, $charcoal, 'cour', 'reallygreatsite.com');
drawText($imgInv, 9, 0, 400, 75, $charcoal, 'cour', '+123-456-7890');
drawText($imgInv, 9, 0, 400, 95, $charcoal, 'cour', '@reallygreatsite');

// Large Invoice Title
drawText($imgInv, 40, 0, 50, 160, $charcoal, 'georgiab', 'Invoice');

// Divider line
imageline($imgInv, 40, 185, 560, 185, $charcoal);

// Invoice Meta fields - corrected spacing
drawText($imgInv, 10, 0, 50, 205, $charcoal, 'courbd', 'No: invc-90823');
drawText($imgInv, 10, 0, 50, 235, $charcoal, 'cour', 'To:'); // 'To: Helene Paquet' will overlay at y = 235

$currentInvDate = date('F d, Y'); 
$dueInvDate = date('F d, Y', strtotime('+7 days'));
drawText($imgInv, 9, 0, 360, 205, $charcoal, 'cour', 'Date: ' . $currentInvDate);
drawText($imgInv, 9, 0, 360, 235, $charcoal, 'cour', 'Due Date: ' . $dueInvDate);

// Table Header lines
imageline($imgInv, 40, 280, 560, 280, $charcoal);
drawThickLine($imgInv, 40, 320, 560, 320, 3, $charcoal);

// Column Headers
drawText($imgInv, 10, 0, 50, 305, $charcoal, 'courbd', 'No');
drawText($imgInv, 10, 0, 95, 305, $charcoal, 'courbd', 'Description');
drawText($imgInv, 10, 0, 350, 305, $charcoal, 'courbd', 'Price');
drawText($imgInv, 10, 0, 450, 305, $charcoal, 'courbd', 'Qty');
drawText($imgInv, 10, 0, 505, 295, $charcoal, 'courbd', 'Total');

// Draw Line Items above each slot
drawText($imgInv, 9, 0, 50, 350, $charcoal, 'cour', '1.  Memory Capture #1     $100    1    $100');
drawText($imgInv, 9, 0, 50, 765, $charcoal, 'cour', '2.  Memory Capture #2     $100    1    $100');
drawText($imgInv, 9, 0, 50, 1180, $charcoal, 'cour', '3.  Memory Capture #3     $100    1    $100');
drawText($imgInv, 9, 0, 50, 1595, $charcoal, 'cour', '4.  Memory Capture #4     $100    1    $100');

// Dotted separator lines between items
for ($dx = 40; $dx < 560; $dx += 8) {
    imageline($imgInv, $dx, 752, $dx + 4, 752, $charcoal);
    imageline($imgInv, $dx, 1167, $dx + 4, 1167, $charcoal);
    imageline($imgInv, $dx, 1582, $dx + 4, 1582, $charcoal);
}

// Table Footer Divider
imageline($imgInv, 40, 2000, 560, 2000, $charcoal);

// Footer left info
drawText($imgInv, 10, 0, 50, 2040, $charcoal, 'georgiai', 'The invoice amount must be paid');
drawText($imgInv, 10, 0, 50, 2060, $charcoal, 'georgiai', 'no later than 7 business days');
drawText($imgInv, 10, 0, 50, 2080, $charcoal, 'georgiai', 'after invoice issuance.');

// Footer right totals
drawText($imgInv, 9, 0, 360, 2040, $charcoal, 'cour', 'SUBTOTAL : $400.00');
drawText($imgInv, 9, 0, 360, 2065, $charcoal, 'cour', 'TAX (0%) : $0.00');
drawText($imgInv, 9, 0, 360, 2090, $charcoal, 'cour', 'DISCOUNT : $0.00');
drawText($imgInv, 11, 0, 360, 2120, $charcoal, 'courbd', 'TOTAL    : $400.00');

// Bottom Divider
imageline($imgInv, 40, 2155, 560, 2155, $charcoal);

// Payment Information
drawText($imgInv, 11, 0, 50, 2195, $charcoal, 'arialbd', 'Payment Information:');
drawText($imgInv, 9, 0, 50, 2220, $charcoal, 'cour', 'Payment Method: Online Payment');
drawText($imgInv, 9, 0, 50, 2245, $charcoal, 'cour', 'Account Name:'); // dynamic event_subtitle overlay at y = 2245
drawText($imgInv, 9, 0, 50, 2270, $charcoal, 'cour', 'Account Number: 123-456-7890');

// Thank You in script font
drawText($imgInv, 22, 0, 370, 2250, $charcoal, 'georgiai', 'Thank You');

// Barcode at bottom (shifted down, leaves y = 2320 clean for dynamic event_hashtag)
drawBarcode($imgInv, 200, 2350, 200, 20, $charcoal);

imagepng($imgInv, $outputDir . 'receipt_invoice.png');
imagedestroy($imgInv);


// ==========================================
// 4. VOGUE MAGAZINE (magazine_strip.png)
// ==========================================
echo "Generating magazine_strip.png...\n";
$imgMag = imagecreatetruecolor(600, 2000);
imagealphablending($imgMag, false);
imagesavealpha($imgMag, true);

$bgMag = imagecolorallocate($imgMag, 255, 255, 255);
imagefill($imgMag, 0, 0, $bgMag);

// Magazine specific slots
$magSlots = [
    ['x' => 50, 'y' => 50, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 454, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 858, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1267, 'w' => 500, 'h' => 375]
];

foreach ($magSlots as $slot) {
    imagefilledrectangle($imgMag, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgMag, true);

$black = imagecolorallocate($imgMag, 0, 0, 0);

// Minimalist Borders
foreach ($magSlots as $slot) {
    imagerectangle($imgMag, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $black);
}

// Vogue Top Header
drawText($imgMag, 48, 0, -1, 45, $black, 'georgiab', 'V O G U E');

// Bottom Titles
drawText($imgMag, 32, 0, -1, 1780, $black, 'georgiab', 'THE FASHION ISSUE');
drawText($imgMag, 15, 0, 60, 1850, $black, 'arialbd', "EDITION:  2026.FALL");
drawText($imgMag, 15, 0, 60, 1885, $black, 'arialbd', "LOC:      MILAN STUDIO");

// Barcode & Price
drawBarcode($imgMag, 390, 1835, 150, 48, $black);
drawText($imgMag, 11, 0, 390, 1900, $black, 'courbd', "PRICE: $4.99");

// Overlapping 1: Vertical V O G U E text on left side
$vogueLetters = ['V', 'O', 'G', 'U', 'E'];
$yPositions = [350, 470, 590, 710, 830];
for ($i = 0; $i < 5; $i++) {
    // Left edge of slot is X: 50. Drawing at X: 18 with size 45 overlaps slot by ~20px
    drawText($imgMag, 45, 0, 18, $yPositions[$i], $black, 'georgiab', $vogueLetters[$i]);
}

// Overlapping 2: Black banner "FALL / WINTER" overlapping bottom of Slot 3
$blockBg = imagecolorallocate($imgMag, 0, 0, 0);
$blockTxt = imagecolorallocate($imgMag, 255, 255, 255);
imagefilledrectangle($imgMag, 360, 1210, 535, 1240, $blockBg);
drawText($imgMag, 12, 0, 372, 1230, $blockTxt, 'arialbd', 'FALL / WINTER');

imagepng($imgMag, $outputDir . 'magazine_strip.png');
imagedestroy($imgMag);


// ==========================================
// 5. 35mm ANALOG FILM (retro_film_strip.png)
// ==========================================
echo "Generating retro_film_strip.png...\n";
$imgFilm = imagecreatetruecolor(600, 2000);
imagealphablending($imgFilm, false);
imagesavealpha($imgFilm, true);

$bgFilm = imagecolorallocate($imgFilm, 28, 25, 23); // #1C1917
imagefill($imgFilm, 0, 0, $bgFilm);

// Film specific slots
$filmSlots = [
    ['x' => 80, 'y' => 80, 'w' => 440, 'h' => 330],
    ['x' => 80, 'y' => 475, 'w' => 440, 'h' => 330],
    ['x' => 80, 'y' => 870, 'w' => 440, 'h' => 330],
    ['x' => 80, 'y' => 1265, 'w' => 440, 'h' => 330]
];

foreach ($filmSlots as $slot) {
    imagefilledrectangle($imgFilm, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgFilm, true);

$sprocketColor = imagecolorallocate($imgFilm, 10, 10, 10);
$orangeText = imagecolorallocate($imgFilm, 220, 100, 0);
$orangeLed = imagecolorallocate($imgFilm, 255, 110, 0);
$lightGrey = imagecolorallocate($imgFilm, 90, 85, 80);

// Film Borders
foreach ($filmSlots as $slot) {
    imagerectangle($imgFilm, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $lightGrey);
}

// Draw sprocket holes
for ($y = 20; $y < 2000; $y += 65) {
    // Left sprocket
    imagefilledrectangle($imgFilm, 22, $y, 48, $y + 35, $sprocketColor);
    // Right sprocket
    imagefilledrectangle($imgFilm, 552, $y, 578, $y + 35, $sprocketColor);
}

// Film Markings (Headers)
drawText($imgFilm, 13, 0, 80, 65, $orangeText, 'courbd', 'KODAK 400TX');

// Frame numbers overlapping margins
drawText($imgFilm, 11, 0, 52, 240, $orangeText, 'courbd', '24');
drawText($imgFilm, 11, 0, 52, 635, $orangeText, 'courbd', '25');
drawText($imgFilm, 11, 0, 52, 1030, $orangeText, 'courbd', '26');
drawText($imgFilm, 11, 0, 52, 1425, $orangeText, 'courbd', '27');

// Overlapping: Classic Orange Date stamp on Slot 4 bottom-right
$dateLedStr = date("'y  m  d"); // e.g. '26  06  17
drawText($imgFilm, 22, 0, 360, 1565, $orangeLed, 'courbd', $dateLedStr);

imagepng($imgFilm, $outputDir . 'retro_film_strip.png');
imagedestroy($imgFilm);


// ==========================================
// 6. SEOUL AESTHETIC (seoul_aesthetic.png)
// ==========================================
echo "Generating seoul_aesthetic.png...\n";
$imgSeoul = imagecreatetruecolor(600, 2000);
imagealphablending($imgSeoul, false);
imagesavealpha($imgSeoul, true);

$bgSeoul = imagecolorallocate($imgSeoul, 245, 242, 235); // #F5F2EB
imagefill($imgSeoul, 0, 0, $bgSeoul);

foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgSeoul, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgSeoul, true);

$darkBrown = imagecolorallocate($imgSeoul, 74, 69, 63); // #4A453F
$lightBrown = imagecolorallocate($imgSeoul, 140, 130, 115); // #8C8273
$lineColor = imagecolorallocate($imgSeoul, 200, 192, 180);
$stampColor = imagecolorallocatealpha($imgSeoul, 140, 130, 115, 40);

// Borders
foreach ($standardSlots as $slot) {
    imagerectangle($imgSeoul, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $lightBrown);
}

// Texts
drawText($imgSeoul, 28, 0, -1, 45, $darkBrown, 'georgiai', 'Every Moment in Seoul');
drawText($imgSeoul, 44, 0, -1, 1750, $darkBrown, 'georgiab', 'S E O U L');

if (file_exists($fonts['malgun'])) {
    drawText($imgSeoul, 18, 0, -1, 1800, $lightBrown, 'malgun', '서울에서의 semua 순간');
} else {
    drawText($imgSeoul, 14, 0, -1, 1800, $lightBrown, 'georgiab', '• MEMORY COLLECTOR •');
}

drawText($imgSeoul, 15, 0, 60, 1860, $darkBrown, 'courbd', "DATE:  $dateStr");
drawText($imgSeoul, 15, 0, 60, 1895, $darkBrown, 'courbd', 'LOC:   STUDIO KIOSK');

// Barcode
drawBarcode($imgSeoul, 370, 1845, 180, 50, $darkBrown);
drawText($imgSeoul, 11, 0, 370, 1910, $darkBrown, 'cour', 'NO. 579D665F-SEOUL');

// Separators
imageline($imgSeoul, 50, 1690, 550, 1690, $lineColor);
imageline($imgSeoul, 50, 1940, 550, 1940, $lineColor);

// Overlapping 1: Botanical branch overlapping Slot 1 bottom-left
drawBotanicalBranch($imgSeoul, 35, 435, 95, 375, $lightBrown);

// Overlapping 2: Botanical branch overlapping Slot 3 top-right
drawBotanicalBranch($imgSeoul, 565, 845, 500, 895, $lightBrown);

// Overlapping 3: Semi-transparent round stamp overlapping Slot 2 bottom-right
imagefilledellipse($imgSeoul, 520, 810, 80, 80, $stampColor);
imageellipse($imgSeoul, 520, 810, 80, 80, $lightBrown);
imageellipse($imgSeoul, 520, 810, 72, 72, $lightBrown);
drawText($imgSeoul, 11, 0, 495, 815, $lightBrown, 'georgiab', 'SEOUL');

imagepng($imgSeoul, $outputDir . 'seoul_aesthetic.png');
imagedestroy($imgSeoul);


// ==========================================
// 7. LOVE FACTORY (love_factory.png)
// ==========================================
echo "Generating love_factory.png...\n";
$imgLove = imagecreatetruecolor(600, 2000);
imagealphablending($imgLove, false);
imagesavealpha($imgLove, true);

$bgLove = imagecolorallocate($imgLove, 24, 24, 27); // #18181B
imagefill($imgLove, 0, 0, $bgLove);

foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgLove, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgLove, true);

$zincGrey = imagecolorallocate($imgLove, 161, 161, 170); // #A1A1AA
$darkZinc = imagecolorallocate($imgLove, 63, 63, 70); // #3F3F46
$white = imagecolorallocate($imgLove, 244, 244, 245);
$redAccent = imagecolorallocate($imgLove, 230, 57, 70);

// Double Industrial Borders
foreach ($standardSlots as $slot) {
    imagerectangle($imgLove, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $darkZinc);
    imagerectangle($imgLove, $slot['x'] - 2, $slot['y'] - 2, $slot['x'] + $slot['w'] + 1, $slot['y'] + $slot['h'] + 1, $zincGrey);
}

// Stars between slots
drawFourPointStar($imgLove, 300, 440, 10, $zincGrey);
drawFourPointStar($imgLove, 300, 845, 10, $zincGrey);
drawFourPointStar($imgLove, 300, 1250, 10, $zincGrey);
drawFourPointStar($imgLove, 80, 1690, 8, $redAccent);
drawFourPointStar($imgLove, 520, 1690, 8, $redAccent);

// Titles & Info
drawText($imgLove, 14, 0, -1, 1715, $zincGrey, 'arialbd', '✦ INDUSTRIAL LOVE CONCEPT ✦');
drawText($imgLove, 48, 0, -1, 1785, $white, 'impact', 'LOVE FACTORY');
drawText($imgLove, 14, 0, 50, 1850, $zincGrey, 'cour', 'SYS_BATCH: LF-99-2026');
drawText($imgLove, 14, 0, 50, 1875, $zincGrey, 'cour', 'HEART_RATE: 100% (STABLE)');
drawText($imgLove, 14, 0, 50, 1900, $zincGrey, 'cour', 'OPERATOR: ANTLR-90');

// Barcode
drawBarcode($imgLove, 350, 1840, 200, 60, $white);
drawText($imgLove, 11, 0, 350, 1915, $zincGrey, 'cour', 'CODE::*LF-404-STAY-COOL*');

// Framing border
imagerectangle($imgLove, 8, 8, 600 - 9, 2000 - 9, $darkZinc);

// Overlapping 1: Red bubble-gum heart shape overlapping top-right of Slot 3
drawHeart($imgLove, 525, 890, 50, $redAccent);

// Overlapping 2: White Y2K 4-point star overlapping bottom-left of Slot 1
drawFourPointStar($imgLove, 75, 400, 20, $white);

// Overlapping 3: WARNING banner overlapping bottom of Slot 2
$warningBg = imagecolorallocate($imgLove, 230, 57, 70);
$warningTxt = imagecolorallocate($imgLove, 244, 244, 245);
imagefilledrectangle($imgLove, 120, 815, 480, 845, $warningBg);
drawText($imgLove, 12, 0, -1, 835, $warningTxt, 'arialbd', 'WARNING: EXTREME CUTENESS');

imagepng($imgLove, $outputDir . 'love_factory.png');
imagedestroy($imgLove);


// ==========================================
// 8. CYBER NEON (cyber_neon.png)
// ==========================================
echo "Generating cyber_neon.png...\n";
$imgCyber = imagecreatetruecolor(600, 2000);
imagealphablending($imgCyber, false);
imagesavealpha($imgCyber, true);

$bgCyber = imagecolorallocate($imgCyber, 10, 11, 16); // #0A0B10
imagefill($imgCyber, 0, 0, $bgCyber);

foreach ($standardSlots as $slot) {
    imagefilledrectangle($imgCyber, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgCyber, true);

$cyberCyan = imagecolorallocate($imgCyber, 0, 240, 255); // #00F0FF
$cyberPink = imagecolorallocate($imgCyber, 255, 46, 147); // #FF2E93
$neonDarkCyan = imagecolorallocatealpha($imgCyber, 0, 240, 255, 95);
$neonDarkPink = imagecolorallocatealpha($imgCyber, 255, 46, 147, 95);
$cyberText = imagecolorallocate($imgCyber, 220, 225, 250);

// Neon Glow Borders
for ($i = 0; $i < count($standardSlots); $i++) {
    $slot = $standardSlots[$i];
    $isCyan = ($i % 2 === 0);
    $glowCol = $isCyan ? $neonDarkCyan : $neonDarkPink;
    $solidCol = $isCyan ? $cyberCyan : $cyberPink;
    
    for ($g = 1; $g <= 3; $g++) {
        imagerectangle($imgCyber, $slot['x'] - $g, $slot['y'] - $g, $slot['x'] + $slot['w'] + $g - 1, $slot['y'] + $slot['h'] + $g - 1, $glowCol);
    }
    imagerectangle($imgCyber, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $solidCol);
}

// Background wireframe lines
for ($l = 0; $l < 5; $l++) {
    $yLine = 1680 + $l * 12;
    $lineAlpha = imagecolorallocatealpha($imgCyber, 0, 240, 255, 115 - $l * 20);
    imageline($imgCyber, 50, $yLine, 550, $yLine, $lineAlpha);
}

// Titles & clock
drawText($imgCyber, 38, 0, -1, 1785, $cyberCyan, 'arialbd', 'NEON DRIVE');
drawText($imgCyber, 14, 0, 50, 1855, $cyberText, 'courbd', "SYS_CLOCK: [ $dateStr ]");
drawText($imgCyber, 14, 0, 50, 1885, $cyberPink, 'courbd', "STATUS:    [ ONLINE_CYBER ]");

// Circular simulator
$circleColor = imagecolorallocatealpha($imgCyber, 0, 240, 255, 60);
imagearc($imgCyber, 470, 1865, 80, 80, 0, 360, $circleColor);
imagearc($imgCyber, 470, 1865, 50, 50, 0, 360, $circleColor);
imageline($imgCyber, 430, 1865, 510, 1865, $circleColor);
imageline($imgCyber, 470, 1825, 470, 1905, $circleColor);
drawText($imgCyber, 7, 0, 442, 1870, $cyberCyan, 'cour', 'SYS_LOCK');

// Overlapping 1: Thick viewfinder corners inside each slot
for ($i = 0; $i < count($standardSlots); $i++) {
    $slot = $standardSlots[$i];
    $isCyan = ($i % 2 === 0);
    $solidCol = $isCyan ? $cyberCyan : $cyberPink;
    $cs = 25; // larger viewfinder corners
    $t = 3;   // thicker lines
    
    // Top-left
    drawThickLine($imgCyber, $slot['x'] + 5, $slot['y'] + 5, $slot['x'] + 5 + $cs, $slot['y'] + 5, $t, $solidCol);
    drawThickLine($imgCyber, $slot['x'] + 5, $slot['y'] + 5, $slot['x'] + 5, $slot['y'] + 5 + $cs, $t, $solidCol);
    // Top-right
    drawThickLine($imgCyber, $slot['x'] + $slot['w'] - 5, $slot['y'] + 5, $slot['x'] + $slot['w'] - 5 - $cs, $slot['y'] + 5, $t, $solidCol);
    drawThickLine($imgCyber, $slot['x'] + $slot['w'] - 5, $slot['y'] + 5, $slot['x'] + $slot['w'] - 5, $slot['y'] + 5 + $cs, $t, $solidCol);
    // Bottom-left
    drawThickLine($imgCyber, $slot['x'] + 5, $slot['y'] + $slot['h'] - 5, $slot['x'] + 5 + $cs, $slot['y'] + $slot['h'] - 5, $t, $solidCol);
    drawThickLine($imgCyber, $slot['x'] + 5, $slot['y'] + $slot['h'] - 5, $slot['x'] + 5, $slot['y'] + $slot['h'] - 5 - $cs, $t, $solidCol);
    // Bottom-right
    drawThickLine($imgCyber, $slot['x'] + $slot['w'] - 5, $slot['y'] + $slot['h'] - 5, $slot['x'] + $slot['w'] - 5 - $cs, $slot['y'] + $slot['h'] - 5, $t, $solidCol);
    drawThickLine($imgCyber, $slot['x'] + $slot['w'] - 5, $slot['y'] + $slot['h'] - 5, $slot['x'] + $slot['w'] - 5, $slot['y'] + $slot['h'] - 5 - $cs, $t, $solidCol);
    
    // Middle ticks along borders
    imageline($imgCyber, $slot['x'] - 5, $slot['y'] + intval($slot['h']/2), $slot['x'] + 5, $slot['y'] + intval($slot['h']/2), $cyberCyan);
    imageline($imgCyber, $slot['x'] + $slot['w'] - 5, $slot['y'] + intval($slot['h']/2), $slot['x'] + $slot['w'] + 5, $slot['y'] + intval($slot['h']/2), $cyberCyan);
}

// Overlapping 2: Pink tag sticker overlapping top edge of Slot 2
$tagBg = imagecolorallocate($imgCyber, 255, 46, 147);
$tagTxt = imagecolorallocate($imgCyber, 10, 11, 16);
imagefilledrectangle($imgCyber, 70, 440, 250, 470, $tagBg);
drawText($imgCyber, 12, 0, 85, 462, $tagTxt, 'courbd', 'ACCESS GRANTED');

imagepng($imgCyber, $outputDir . 'cyber_neon.png');
imagedestroy($imgCyber);


// ==========================================
// 9. SUPERMARKET STRUK (receipt_supermarket.png)
// ==========================================
echo "Generating receipt_supermarket.png...\n";
$imgSuper = imagecreatetruecolor(600, 2000);
imagealphablending($imgSuper, false);
imagesavealpha($imgSuper, true);

$bgSuper = imagecolorallocate($imgSuper, 248, 247, 242); // #f8f7f2
imagefill($imgSuper, 0, 0, $bgSuper);

$receiptSlots = [
    ['x' => 50, 'y' => 100, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 505, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 910, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1315, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgSuper, 0, 0, 0, 127);
foreach ($receiptSlots as $slot) {
    imagefilledrectangle($imgSuper, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgSuper, true);

$charcoal = imagecolorallocate($imgSuper, 40, 40, 40);
$grey = imagecolorallocate($imgSuper, 120, 120, 120);

// Jagged tear line at y=40 and y=1960
for ($x = 10; $x < 600; $x += 16) {
    imagefilledrectangle($imgSuper, $x, 35, $x + 8, 38, $grey);
    imagefilledrectangle($imgSuper, $x, 1962, $x + 8, 1965, $grey);
}

// Dashed slot borders
foreach ($receiptSlots as $slot) {
    for ($dx = 0; $dx < $slot['w']; $dx += 10) {
        imageline($imgSuper, $slot['x'] + $dx, $slot['y'] - 1, $slot['x'] + $dx + 5, $slot['y'] - 1, $grey);
        imageline($imgSuper, $slot['x'] + $dx, $slot['y'] + $slot['h'], $slot['x'] + $dx + 5, $slot['y'] + $slot['h'], $grey);
    }
    for ($dy = 0; $dy < $slot['h']; $dy += 10) {
        imageline($imgSuper, $slot['x'] - 1, $slot['y'] + $dy, $slot['x'] - 1, $slot['y'] + $dy + 5, $grey);
        imageline($imgSuper, $slot['x'] + $slot['w'], $slot['y'] + $dy, $slot['x'] + $slot['w'], $slot['y'] + $dy + 5, $grey);
    }
}

// Headers
drawText($imgSuper, 20, 0, -1, 75, $charcoal, 'courbd', '** SNAP & SAVE **');
drawText($imgSuper, 11, 0, -1, 93, $charcoal, 'cour', 'STORE #4492 - CASHIER: CHIP');

// Date & Time
drawText($imgSuper, 10, 0, 50, 495, $charcoal, 'cour', "DATE: $dateStr");
drawText($imgSuper, 10, 0, 380, 495, $charcoal, 'cour', "TIME: " . date('H:i:s'));

// Totals
drawText($imgSuper, 11, 0, 50, 900, $charcoal, 'cour', "ITEM: 4x MEMORY CAPTURES");
drawText($imgSuper, 11, 0, 50, 1305, $charcoal, 'cour', "TAX (0%):                     $0.00");
drawText($imgSuper, 14, 0, 50, 1725, $charcoal, 'courbd', "TOTAL:                        PRICELESS");

// Barcode & Footer
drawBarcode($imgSuper, 120, 1765, 360, 65, $charcoal);
drawText($imgSuper, 10, 0, -1, 1855, $charcoal, 'cour', "9940172-88295-001");
drawText($imgSuper, 11, 0, -1, 1910, $charcoal, 'courbd', "THANK YOU FOR SHOPPING!");

// Overlapping 1: Red/Yellow Promo Sticker on bottom-right of Slot 1
$stickerYellow = imagecolorallocate($imgSuper, 255, 223, 0);
$stickerRed = imagecolorallocate($imgSuper, 230, 57, 70);
drawBurst($imgSuper, 510, 440, 12, 30, 45, $stickerYellow, $stickerRed);
drawText($imgSuper, 11, -5, 485, 445, $stickerRed, 'impact', '50% OFF');

// Overlapping 2: Charcoal stamp "BEST VALUE" on top-left of Slot 3
$stampColor = imagecolorallocatealpha($imgSuper, 40, 40, 40, 40); // semi-transparent
$stampSolid = imagecolorallocate($imgSuper, 40, 40, 40);
imagefilledellipse($imgSuper, 120, 950, 70, 70, $stampColor);
imageellipse($imgSuper, 120, 950, 70, 70, $stampSolid);
drawText($imgSuper, 10, 15, 95, 955, $stampSolid, 'courbd', 'VALUED');

imagepng($imgSuper, $outputDir . 'receipt_supermarket.png');
imagedestroy($imgSuper);


// ==========================================
// 10. COFFEE SHOP INVOICE (receipt_coffee.png)
// ==========================================
echo "Generating receipt_coffee.png...\n";
$imgCoffee = imagecreatetruecolor(600, 2000);
imagealphablending($imgCoffee, false);
imagesavealpha($imgCoffee, true);

$bgCoffee = imagecolorallocate($imgCoffee, 251, 248, 243); // #fbf8f3
imagefill($imgCoffee, 0, 0, $bgCoffee);

foreach ($receiptSlots as $slot) {
    imagefilledrectangle($imgCoffee, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgCoffee, true);

$coffeeBrown = imagecolorallocate($imgCoffee, 92, 64, 51); // #5C4033
$coffeeLight = imagecolorallocate($imgCoffee, 143, 93, 58); // #8F5D38
$paidRed = imagecolorallocatealpha($imgCoffee, 230, 57, 70, 20); // semi-trans paid stamp
$paidRedBorder = imagecolorallocate($imgCoffee, 230, 57, 70);

// Double border lines
foreach ($receiptSlots as $slot) {
    imagerectangle($imgCoffee, $slot['x'] - 2, $slot['y'] - 2, $slot['x'] + $slot['w'] + 1, $slot['y'] + $slot['h'] + 1, $coffeeLight);
}

// Headers
drawText($imgCoffee, 22, 0, -1, 75, $coffeeBrown, 'georgiab', 'ROAST & REEL COFFEE');
drawText($imgCoffee, 11, 0, -1, 92, $coffeeLight, 'georgiai', 'Sip. Snap. Repeat.');

// Details
drawText($imgCoffee, 11, 0, 50, 495, $coffeeBrown, 'cour', "ORDER: #551-COFFEE");
drawText($imgCoffee, 11, 0, 50, 900, $coffeeBrown, 'cour', "BARISTA: BOT-BREW");
drawText($imgCoffee, 12, 0, 50, 1305, $coffeeBrown, 'courbd', "1x ESPRESSO BLEND    $0.00");
drawText($imgCoffee, 12, 0, 50, 1720, $coffeeBrown, 'courbd', "TOTAL PAYMENT:       $0.00");

// Overlapping 1: Coffee stain ring on top-left of Slot 2
$stainColor = imagecolorallocatealpha($imgCoffee, 143, 93, 58, 80); // semi-transparent coffee brown
imageellipse($imgCoffee, 60, 520, 60, 60, $stainColor);
imageellipse($imgCoffee, 60, 520, 59, 59, $stainColor);
imageellipse($imgCoffee, 60, 520, 58, 58, $stainColor);
imagefilledellipse($imgCoffee, 100, 540, 6, 6, $stainColor);
imagefilledellipse($imgCoffee, 90, 555, 4, 4, $stainColor);

// Overlapping 2: PAID Stamp overlapping bottom-right of Slot 4
imagefilledellipse($imgCoffee, 510, 1630, 110, 110, $paidRed);
imageellipse($imgCoffee, 510, 1630, 110, 110, $paidRedBorder);
imageellipse($imgCoffee, 510, 1630, 102, 102, $paidRedBorder);
drawText($imgCoffee, 16, 15, 475, 1638, $paidRedBorder, 'georgiab', 'PAID');

// Barcode & Footer
drawBarcode($imgCoffee, 50, 1765, 260, 60, $coffeeBrown);
drawText($imgCoffee, 11, 0, 50, 1850, $coffeeLight, 'cour', "INV-99201-COFFEE");
drawText($imgCoffee, 12, 0, -1, 1910, $coffeeBrown, 'georgiab', 'HAVE A WONDERFUL DAY!');

imagepng($imgCoffee, $outputDir . 'receipt_coffee.png');
imagedestroy($imgCoffee);


// ==========================================
// 11. RETRO CINEMA TICKET (receipt_cinema.png)
// ==========================================
echo "Generating receipt_cinema.png...\n";
$imgCinema = imagecreatetruecolor(600, 2000);
imagealphablending($imgCinema, false);
imagesavealpha($imgCinema, true);

$bgCinema = imagecolorallocate($imgCinema, 250, 244, 225); // #faf4e1
imagefill($imgCinema, 0, 0, $bgCinema);

$cinemaSlots = [
    ['x' => 55, 'y' => 100, 'w' => 490, 'h' => 365],
    ['x' => 55, 'y' => 495, 'w' => 490, 'h' => 365],
    ['x' => 55, 'y' => 890, 'w' => 490, 'h' => 365],
    ['x' => 55, 'y' => 1285, 'w' => 490, 'h' => 365]
];

foreach ($cinemaSlots as $slot) {
    imagefilledrectangle($imgCinema, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgCinema, true);

// Cinema punches along left & right edges
for ($y = 200; $y < 2000; $y += 300) {
    imagefilledellipse($imgCinema, 0, $y, 50, 50, $transparent);
    imagefilledellipse($imgCinema, 600, $y, 50, 50, $transparent);
}

$darkBlue = imagecolorallocate($imgCinema, 34, 37, 42); // #22252a
$cinemaRed = imagecolorallocate($imgCinema, 217, 4, 41); // ticket red
$yellowGold = imagecolorallocate($imgCinema, 255, 186, 8); // ticket stars

// Borders around cinema slots
foreach ($cinemaSlots as $slot) {
    imagerectangle($imgCinema, $slot['x'] - 2, $slot['y'] - 2, $slot['x'] + $slot['w'] + 1, $slot['y'] + $slot['h'] + 1, $cinemaRed);
}

// Headers
drawText($imgCinema, 24, 0, -1, 72, $cinemaRed, 'impact', 'RETRO CINEMA HALL');
drawText($imgCinema, 11, 0, -1, 90, $darkBlue, 'arialbd', 'ADMIT ONE  •  NO REFUNDS');

// Ticket Info
drawText($imgCinema, 11, 0, 60, 488, $darkBlue, 'courbd', "TICKET:   #4492-CINEMA");
drawText($imgCinema, 11, 0, 360, 488, $darkBlue, 'courbd', "ROW: LOVE  SEAT: 12");
drawText($imgCinema, 12, 0, -1, 883, $cinemaRed, 'impact', '★ ★ ★ BLOCKBUSTER ★ ★ ★');

// Details
drawText($imgCinema, 11, 0, 60, 1278, $darkBlue, 'cour', "THEATRE STATIONS");
drawText($imgCinema, 13, 0, 60, 1720, $cinemaRed, 'impact', "DATE: $dateStr");
drawText($imgCinema, 13, 0, 380, 1720, $cinemaRed, 'impact', "SHOWTIME: NOW");

// Overlapping 1: Star burst sticker on Slot 2 top-right
$ticketGold = imagecolorallocate($imgCinema, 255, 186, 8);
$ticketRed = imagecolorallocate($imgCinema, 217, 4, 41);
drawFourPointStar($imgCinema, 510, 515, 25, $ticketGold);
drawText($imgCinema, 10, 0, 498, 520, $ticketRed, 'impact', 'VIP');

// Overlapping 2: Slanted "TICKET ENTRY" stamp on Slot 3 bottom-left
$stampBlue = imagecolorallocatealpha($imgCinema, 34, 37, 42, 60);
$stampBlueSolid = imagecolorallocate($imgCinema, 34, 37, 42);
imagefilledrectangle($imgCinema, 45, 1215, 165, 1245, $stampBlue);
imagerectangle($imgCinema, 45, 1215, 165, 1245, $stampBlueSolid);
drawText($imgCinema, 9, -5, 52, 1234, $stampBlueSolid, 'arialbd', 'TICKET ENTRY');

// Barcode & stars
drawBarcode($imgCinema, 120, 1755, 360, 65, $darkBlue);
drawText($imgCinema, 9, 0, -1, 1845, $darkBlue, 'cour', "00044928812");
drawFourPointStar($imgCinema, 80, 1885, 20, $yellowGold);
drawFourPointStar($imgCinema, 520, 1885, 20, $yellowGold);
drawText($imgCinema, 14, 0, -1, 1900, $cinemaRed, 'impact', 'ENJOY YOUR MOVIE!');

imagepng($imgCinema, $outputDir . 'receipt_cinema.png');
imagedestroy($imgCinema);


// ==========================================
// 12. MINIMALIST BANK SLIP (receipt_bank.png)
// ==========================================
echo "Generating receipt_bank.png...\n";
$imgBank = imagecreatetruecolor(600, 2000);
imagealphablending($imgBank, false);
imagesavealpha($imgBank, true);

$bgBank = imagecolorallocate($imgBank, 244, 246, 250); // #f4f6fa
imagefill($imgBank, 0, 0, $bgBank);

foreach ($receiptSlots as $slot) {
    imagefilledrectangle($imgBank, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgBank, true);

$navy = imagecolorallocate($imgBank, 29, 53, 87); // #1D3557
$blueLine = imagecolorallocate($imgBank, 69, 123, 157); // #457B9D
$lightBlueText = imagecolorallocate($imgBank, 69, 123, 157);

// Slot outline
foreach ($receiptSlots as $slot) {
    imagerectangle($imgBank, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $blueLine);
}

// Header
drawText($imgBank, 22, 0, -1, 75, $navy, 'arialbd', 'MEMORIES BANK CO.');
drawText($imgBank, 11, 0, -1, 92, $lightBlueText, 'arial', 'SECURE MEMORY TRANSFER');

// Transaction Details
drawText($imgBank, 11, 0, 50, 495, $navy, 'courbd', "STATUS:    SUCCESSFUL");
drawText($imgBank, 11, 0, 50, 900, $navy, 'cour', "FROM:      KIOSK CAMERA");
drawText($imgBank, 11, 0, 50, 1305, $navy, 'cour', "TO:        LOCAL ARCHIVE");
drawText($imgBank, 11, 0, 50, 1720, $navy, 'cour', "REF NO:    TXN-" . rand(100000, 999999));

// Overlapping 1: Green security stamp on Slot 1 bottom-right
$mintBg = imagecolorallocatealpha($imgBank, 42, 157, 143, 40);
$mintSolid = imagecolorallocate($imgBank, 42, 157, 143);
imagefilledellipse($imgBank, 515, 445, 60, 60, $mintBg);
imageellipse($imgBank, 515, 445, 60, 60, $mintSolid);
imageellipse($imgBank, 515, 445, 54, 54, $mintSolid);
drawText($imgBank, 8, 10, 495, 450, $mintSolid, 'arialbd', 'SECURE');

// Overlapping 2: Bank stamp overlapping Slot 4 top-left
$bankBlueAlpha = imagecolorallocatealpha($imgBank, 29, 53, 87, 50);
$bankBlueSolid = imagecolorallocate($imgBank, 29, 53, 87);
imagefilledellipse($imgBank, 90, 1345, 70, 70, $bankBlueAlpha);
imageellipse($imgBank, 90, 1345, 70, 70, $bankBlueSolid);
drawText($imgBank, 9, 0, 70, 1348, $bankBlueSolid, 'courbd', 'BANK');

// Footer & Barcode
drawBarcode($imgBank, 150, 1765, 300, 60, $navy);
drawText($imgBank, 12, 0, -1, 1910, $navy, 'arialbd', 'TRANSACTION COMPLETE');

imagepng($imgBank, $outputDir . 'receipt_bank.png');
imagedestroy($imgBank);


// ==========================================
// 13. CLINIC PRESCRIPTION (receipt_clinic.png)
// ==========================================
echo "Generating receipt_clinic.png...\n";
$imgClinic = imagecreatetruecolor(600, 2000);
imagealphablending($imgClinic, false);
imagesavealpha($imgClinic, true);

$bgClinic = imagecolorallocate($imgClinic, 238, 242, 246); // #eef2f6
imagefill($imgClinic, 0, 0, $bgClinic);

foreach ($receiptSlots as $slot) {
    imagefilledrectangle($imgClinic, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgClinic, true);

$medicalTeal = imagecolorallocate($imgClinic, 38, 70, 83); // #264653
$freshMint = imagecolorallocate($imgClinic, 42, 157, 143); // #2A9D8F
$rxWatermark = imagecolorallocatealpha($imgClinic, 42, 157, 143, 110); // transparent watermark

// Big Rx Watermark behind
drawText($imgClinic, 140, 0, 70, 1050, $rxWatermark, 'georgiab', 'Rx');

// Borders
foreach ($receiptSlots as $slot) {
    imagerectangle($imgClinic, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $freshMint);
}

// Headers
drawText($imgClinic, 22, 0, -1, 75, $medicalTeal, 'georgiab', 'CLINIC OF HAPPINESS');
drawText($imgClinic, 11, 0, -1, 92, $freshMint, 'arialbd', 'PRESCRIPTION CARD');

// Details
drawText($imgClinic, 11, 0, 50, 495, $medicalTeal, 'cour', "PATIENT:   YOUR SMILE");
drawText($imgClinic, 11, 0, 50, 900, $medicalTeal, 'cour', "DOSAGE:    SMILE 4x DAILY");
drawText($imgClinic, 11, 0, 50, 1305, $medicalTeal, 'cour', "REFILLS:   UNLIMITED");
drawText($imgClinic, 11, 0, 50, 1720, $medicalTeal, 'cour', "PRESCRIPTION NO: 994-LOVE");

// Overlapping 1: Red/White capsule sticker on Slot 2 top-right
$medRed = imagecolorallocate($imgClinic, 230, 57, 70);
$medWhite = imagecolorallocate($imgClinic, 255, 255, 255);
$medDark = imagecolorallocate($imgClinic, 38, 70, 83);
imagefilledellipse($imgClinic, 485, 520, 20, 20, $medRed);
imagefilledellipse($imgClinic, 515, 520, 20, 20, $medWhite);
imagefilledrectangle($imgClinic, 485, 510, 515, 530, $medRed);
imagefilledrectangle($imgClinic, 500, 510, 515, 530, $medWhite);
imageellipse($imgClinic, 485, 520, 20, 20, $medDark);
imageellipse($imgClinic, 515, 520, 20, 20, $medDark);
imageline($imgClinic, 485, 510, 515, 510, $medDark);
imageline($imgClinic, 485, 530, 515, 530, $medDark);
drawText($imgClinic, 10, -5, 465, 555, $medRed, 'impact', 'TAKE PILL');

// Overlapping 2: Mint green cross (+) on Slot 1 top-left
$mintCross = imagecolorallocate($imgClinic, 42, 157, 143);
imagefilledrectangle($imgClinic, 65, 110, 75, 130, $mintCross);
imagefilledrectangle($imgClinic, 55, 120, 85, 130, $mintCross);

// Overlapping 3: Signature line crossing Slot 4 bottom-right
drawText($imgClinic, 14, -8, 380, 1695, $medicalTeal, 'georgiai', 'Dr. Snapshot M.D.');

// Signature & crosses
imageline($imgClinic, 360, 1795, 540, 1795, $medicalTeal);
drawText($imgClinic, 10, 0, 360, 1815, $freshMint, 'cour', 'AUTHORIZED SIGNATURE');

// Plus signs (+) on corners
drawText($imgClinic, 18, 0, 35, 1890, $freshMint, 'arialbd', '+');
drawText($imgClinic, 18, 0, 545, 1890, $freshMint, 'arialbd', '+');
drawText($imgClinic, 11, 0, -1, 1910, $medicalTeal, 'georgiab', 'TAKE CARE OF YOURSELF!');

imagepng($imgClinic, $outputDir . 'receipt_clinic.png');
imagedestroy($imgClinic);


// ==========================================
// 14. ACTIVE SPORTS MAGAZINE (magazine_sports.png)
// ==========================================
echo "Generating magazine_sports.png...\n";
$imgSports = imagecreatetruecolor(600, 2000);
imagealphablending($imgSports, false);
imagesavealpha($imgSports, true);

$bgSports = imagecolorallocate($imgSports, 15, 23, 42); // #0F172A
imagefill($imgSports, 0, 0, $bgSports);

$magSlotsLocal = [
    ['x' => 50, 'y' => 50, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 454, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 858, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1267, 'w' => 500, 'h' => 375]
];

foreach ($magSlotsLocal as $slot) {
    imagefilledrectangle($imgSports, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgSports, true);

$white = imagecolorallocate($imgSports, 255, 255, 255);
$neonGreen = imagecolorallocate($imgSports, 204, 255, 0); // #CCFF00
$darkGrey = imagecolorallocate($imgSports, 50, 50, 50);

// Borders
foreach ($magSlotsLocal as $slot) {
    imagerectangle($imgSports, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $white);
}

// Headers
drawText($imgSports, 38, 0, -1, 45, $neonGreen, 'impact', 'A C T I V E');
drawText($imgSports, 11, 0, -1, 93, $white, 'arialbd', 'CHALLENGE YOUR LIMITS');

// Bottom Titles
drawText($imgSports, 36, 0, -1, 1770, $neonGreen, 'impact', 'SPORT ACTIVE');
drawText($imgSports, 12, 0, 60, 1830, $white, 'arialbd', "ISSUE #42 - RUNNING EXTRA");
drawText($imgSports, 11, 0, 60, 1865, $white, 'courbd', "HEART: 140BPM | PACE: 4'15\"");

// Barcode
drawBarcode($imgSports, 390, 1820, 150, 48, $white);
drawText($imgSports, 10, 0, 390, 1885, $white, 'cour', "ACTIVE-8829");

// Overlapping 1: Slanted tag/label "LIMITLESS" on bottom-right of Slot 1
$limitlessBg = imagecolorallocate($imgSports, 204, 255, 0);
$limitlessTxt = imagecolorallocate($imgSports, 15, 23, 42);
imagefilledrectangle($imgSports, 380, 410, 530, 440, $limitlessBg);
imagerectangle($imgSports, 380, 410, 530, 440, $white);
drawText($imgSports, 11, 0, 395, 432, $limitlessTxt, 'impact', 'LIMITLESS');

// Overlapping 2: Circular "MVP" badge on top-left of Slot 3
imagefilledellipse($imgSports, 90, 880, 70, 70, $neonGreen);
imageellipse($imgSports, 90, 880, 70, 70, $white);
drawText($imgSports, 14, 0, 70, 890, $limitlessTxt, 'impact', 'MVP');

imagepng($imgSports, $outputDir . 'magazine_sports.png');
imagedestroy($imgSports);


// ==========================================
// 15. ROCK & RHYTHM MAGAZINE (magazine_music.png)
// ==========================================
echo "Generating magazine_music.png...\n";
$imgMusic = imagecreatetruecolor(600, 2000);
imagealphablending($imgMusic, false);
imagesavealpha($imgMusic, true);

$bgMusic = imagecolorallocate($imgMusic, 17, 17, 17); // #111111
imagefill($imgMusic, 0, 0, $bgMusic);

foreach ($magSlotsLocal as $slot) {
    imagefilledrectangle($imgMusic, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgMusic, true);

$musicRed = imagecolorallocate($imgMusic, 230, 57, 70); // #E63946
$musicGrey = imagecolorallocate($imgMusic, 150, 150, 150);

// Borders
foreach ($magSlotsLocal as $slot) {
    imagerectangle($imgMusic, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $musicRed);
}

// Headers
drawText($imgMusic, 26, 0, -1, 45, $musicRed, 'impact', 'R O C K  S O U N D');
drawText($imgMusic, 10, 0, -1, 93, $white, 'courbd', 'THE SOUND OF THE UNDERGROUND');

// Bottom Titles
drawText($imgMusic, 34, 0, -1, 1770, $musicRed, 'impact', 'GUITAR HEROES');
drawText($imgMusic, 12, 0, 60, 1830, $white, 'arialbd', "INTERVIEW: THE BEAT");
drawText($imgMusic, 11, 0, 60, 1860, $musicGrey, 'courbd', "VOL. 99 | LIVE AT ARENA");

// Barcode
drawBarcode($imgMusic, 390, 1815, 150, 48, $musicRed);
drawText($imgMusic, 10, 0, 390, 1880, $musicRed, 'cour', "ROCK-9902");

// Overlapping 1: Red Live Pass tag on Slot 2 top-left
$livePassBg = imagecolorallocate($imgMusic, 230, 57, 70);
$livePassTxt = imagecolorallocate($imgMusic, 255, 255, 255);
imagefilledrectangle($imgMusic, 30, 480, 160, 510, $livePassBg);
imagerectangle($imgMusic, 30, 480, 160, 510, $livePassTxt);
drawText($imgMusic, 10, 5, 45, 502, $livePassTxt, 'impact', 'LIVE PASS');

// Overlapping 2: White/Red slanted tape strip "BACKSTAGE" on Slot 3 bottom
$tapeBg = imagecolorallocate($imgMusic, 240, 240, 240);
imagefilledrectangle($imgMusic, 120, 1205, 280, 1235, $tapeBg);
imagerectangle($imgMusic, 120, 1205, 280, 1235, $musicRed);
drawText($imgMusic, 11, -3, 140, 1228, $livePassBg, 'impact', 'BACKSTAGE');

imagepng($imgMusic, $outputDir . 'magazine_music.png');
imagedestroy($imgMusic);


// ==========================================
// 16. PIXEL GAME MAGAZINE (magazine_gaming.png)
// ==========================================
echo "Generating magazine_gaming.png...\n";
$imgGaming = imagecreatetruecolor(600, 2000);
imagealphablending($imgGaming, false);
imagesavealpha($imgGaming, true);

$bgGaming = imagecolorallocate($imgGaming, 30, 27, 75); // #1E1B4B
imagefill($imgGaming, 0, 0, $bgGaming);

foreach ($magSlotsLocal as $slot) {
    imagefilledrectangle($imgGaming, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgGaming, true);

$neonCyan = imagecolorallocate($imgGaming, 0, 240, 255); // #00F0FF
$neonPink = imagecolorallocate($imgGaming, 255, 0, 127); // #FF007F

// Borders
foreach ($magSlotsLocal as $slot) {
    imagerectangle($imgGaming, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $neonCyan);
}

// Headers
drawText($imgGaming, 24, 0, -1, 45, $neonCyan, 'impact', 'P I X E L  P L A Y');
drawText($imgGaming, 10, 0, -1, 93, $white, 'courbd', 'RETRO & NEXT-GEN REVIEW');

// Bottom Titles
drawText($imgGaming, 36, 0, -1, 1770, $neonPink, 'impact', 'GAME STAR');
drawText($imgGaming, 12, 0, 60, 1830, $white, 'courbd', "SCORE: 99.9% | RANK: SSS");
drawText($imgGaming, 11, 0, 60, 1865, $neonCyan, 'courbd', "ARCADE EDITION: #88");

// Barcode
drawBarcode($imgGaming, 390, 1820, 150, 48, $white);
drawText($imgGaming, 10, 0, 390, 1885, $white, 'cour', "PLAY-1992");

// Overlapping 1: Cyan pixelated "LEVEL UP" bubble on Slot 2 top-right
imagefilledrectangle($imgGaming, 390, 470, 520, 500, $neonCyan);
drawText($imgGaming, 10, 0, 405, 492, $bgGaming, 'impact', 'LEVEL UP!');

// Overlapping 2: Pink pixel heart on Slot 3 bottom-left
drawHeart($imgGaming, 80, 1210, 35, $neonPink);

imagepng($imgGaming, $outputDir . 'magazine_gaming.png');
imagedestroy($imgGaming);


// ==========================================
// 17. WANDERLUST EXPLORER MAGAZINE (magazine_travel.png)
// ==========================================
echo "Generating magazine_travel.png...\n";
$imgTravel = imagecreatetruecolor(600, 2000);
imagealphablending($imgTravel, false);
imagesavealpha($imgTravel, true);

$bgTravel = imagecolorallocate($imgTravel, 242, 239, 233); // #f2efe9
imagefill($imgTravel, 0, 0, $bgTravel);

foreach ($magSlotsLocal as $slot) {
    imagefilledrectangle($imgTravel, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgTravel, true);

$oliveGreen = imagecolorallocate($imgTravel, 79, 119, 45); // #4F772D
$darkCharcoal = imagecolorallocate($imgTravel, 47, 62, 70); // #2F3E46

// Borders
foreach ($magSlotsLocal as $slot) {
    imagerectangle($imgTravel, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $oliveGreen);
}

// Headers
drawText($imgTravel, 26, 0, -1, 45, $oliveGreen, 'georgiab', 'W A N D E R L U S T');
drawText($imgTravel, 10, 0, -1, 93, $darkCharcoal, 'georgiai', 'EXPLORE THE UNKNOWN WILD');

// Bottom Titles
drawText($imgTravel, 36, 0, -1, 1770, $oliveGreen, 'georgiab', 'LOST IN WILD');
drawText($imgTravel, 11, 0, 60, 1830, $darkCharcoal, 'courbd', "GPS: 8.4095 S, 115.1889 E");
drawText($imgTravel, 11, 0, 60, 1860, $darkCharcoal, 'courbd', "CAMPING JOURNAL // VOL 4");

// Barcode
drawBarcode($imgTravel, 390, 1815, 150, 48, $darkCharcoal);
drawText($imgTravel, 10, 0, 390, 1880, $darkCharcoal, 'cour', "WILD-004");

// Overlapping 1: Green botanical leaf branch overlapping bottom-left of Slot 1
drawBotanicalBranch($imgTravel, 35, 415, 95, 365, $oliveGreen);

// Overlapping 2: Compass/travel stamp overlapping bottom-right of Slot 3
$stampColor = imagecolorallocatealpha($imgTravel, 79, 119, 45, 40);
imagefilledellipse($imgTravel, 520, 1200, 70, 70, $stampColor);
imageellipse($imgTravel, 520, 1200, 70, 70, $oliveGreen);
drawText($imgTravel, 8, 15, 498, 1206, $oliveGreen, 'georgiab', 'WILD');

imagepng($imgTravel, $outputDir . 'magazine_travel.png');
imagedestroy($imgTravel);


// ==========================================
// 18. MANGA WEEKLY MAGAZINE (magazine_manga.png)
// ==========================================
echo "Generating magazine_manga.png...\n";
$imgManga = imagecreatetruecolor(600, 2000);
imagealphablending($imgManga, false);
imagesavealpha($imgManga, true);

$bgManga = imagecolorallocate($imgManga, 255, 255, 255); // #ffffff
imagefill($imgManga, 0, 0, $bgManga);

foreach ($magSlotsLocal as $slot) {
    imagefilledrectangle($imgManga, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgManga, true);

$mangaBlack = imagecolorallocate($imgManga, 0, 0, 0);
$mangaRed = imagecolorallocate($imgManga, 217, 4, 41); // #d90429

// Borders
foreach ($magSlotsLocal as $slot) {
    imagerectangle($imgManga, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $mangaBlack);
}

// Headers
drawText($imgManga, 32, 0, -1, 45, $mangaBlack, 'impact', 'MANGA WEEKLY');
drawText($imgManga, 11, 0, -1, 93, $mangaRed, 'arialbd', 'NEW CHAPTER OUT NOW!');

// Bottom Titles
drawText($imgManga, 38, 0, -1, 1770, $mangaBlack, 'impact', 'HERO ACADEMY');
drawText($imgManga, 12, 0, 60, 1830, $mangaBlack, 'arialbd', "VOLUME #18 - SENSATIONAL!");
drawText($imgManga, 11, 0, 60, 1860, $mangaRed, 'courbd', "PAGE: 4x STORIES");

// Barcode
drawBarcode($imgManga, 390, 1820, 150, 48, $mangaBlack);
drawText($imgManga, 10, 0, 390, 1885, $mangaBlack, 'cour', "MANGA-18");

// Overlapping 1: Black action speech bubble "BAM!" overlapping top-right of Slot 1
$bubbleBg = imagecolorallocate($imgManga, 255, 255, 255);
imagefilledellipse($imgManga, 520, 85, 75, 55, $bubbleBg);
imageellipse($imgManga, 520, 85, 75, 55, $mangaBlack);
$tailPoints = [
    510, 110,
    530, 110,
    495, 125
];
imagefilledpolygon($imgManga, $tailPoints, $bubbleBg);
imagepolygon($imgManga, $tailPoints, $mangaBlack);
drawText($imgManga, 13, 12, 498, 93, $mangaBlack, 'impact', 'BAM!');

// Overlapping 2: Black screentone star overlapping bottom-left of Slot 3
drawFourPointStar($imgManga, 80, 1215, 25, $mangaBlack);
drawFourPointStar($imgManga, 80, 1215, 12, $bubbleBg);

imagepng($imgManga, $outputDir . 'magazine_manga.png');
imagedestroy($imgManga);


// ==========================================
// 19. DYNAMIC EVENT STRIP (dynamic_event_strip.png)
// ==========================================
echo "Generating dynamic_event_strip.png...\n";
$imgDyn = imagecreatetruecolor(600, 2000);
imagealphablending($imgDyn, false);
imagesavealpha($imgDyn, true);

$bgDyn = imagecolorallocate($imgDyn, 255, 255, 255); // Opaque White
imagefill($imgDyn, 0, 0, $bgDyn);

$dynSlots = [
    ['x' => 50, 'y' => 480, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 920, 'w' => 500, 'h' => 375]
];

$transparent = imagecolorallocatealpha($imgDyn, 0, 0, 0, 127);
foreach ($dynSlots as $slot) {
    imagefilledrectangle($imgDyn, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($imgDyn, true);

// Configure colors
$greyDark = imagecolorallocate($imgDyn, 40, 40, 40);
$greyLight = imagecolorallocate($imgDyn, 200, 192, 180);
$greyBorder = imagecolorallocate($imgDyn, 220, 220, 220); // Very light grey border for photo slots
$vfColor = imagecolorallocate($imgDyn, 100, 100, 100);
$badgeBg = imagecolorallocate($imgDyn, 255, 255, 255);
$badgeBorder = imagecolorallocate($imgDyn, 210, 205, 195);
$boxBg = imagecolorallocate($imgDyn, 250, 250, 248);
$boxBorder = imagecolorallocate($imgDyn, 225, 220, 210);

// 1. Draw Dual Outer Borders
imagerectangle($imgDyn, 15, 15, 584, 1984, $greyLight);
imagerectangle($imgDyn, 20, 20, 579, 1979, $greyDark);

// 2. Draw Slot Borders and Viewfinder Corners
$vfSize = 22;
$vfThickness = 3;
foreach ($dynSlots as $slot) {
    // Light border around slot
    imagerectangle($imgDyn, $slot['x'] - 1, $slot['y'] - 1, $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], $greyBorder);
    
    $sx = $slot['x'];
    $sy = $slot['y'];
    $sw = $slot['w'];
    $sh = $slot['h'];
    
    // Top-left corner bracket
    imagefilledrectangle($imgDyn, $sx - 6, $sy - 6, $sx - 6 + $vfSize, $sy - 6 + $vfThickness - 1, $vfColor);
    imagefilledrectangle($imgDyn, $sx - 6, $sy - 6, $sx - 6 + $vfThickness - 1, $sy - 6 + $vfSize, $vfColor);
    
    // Top-right corner bracket
    imagefilledrectangle($imgDyn, $sx + $sw + 6 - $vfSize, $sy - 6, $sx + $sw + 6, $sy - 6 + $vfThickness - 1, $vfColor);
    imagefilledrectangle($imgDyn, $sx + $sw + 6 - $vfThickness, $sy - 6, $sx + $sw + 6, $sy - 6 + $vfSize, $vfColor);
    
    // Bottom-left corner bracket
    imagefilledrectangle($imgDyn, $sx - 6, $sy + $sh + 6 - $vfThickness, $sx - 6 + $vfSize, $sy + $sh + 6, $vfColor);
    imagefilledrectangle($imgDyn, $sx - 6, $sy + $sh + 6 - $vfSize, $sx - 6 + $vfThickness - 1, $sy + $sh + 6, $vfColor);
    
    // Bottom-right corner bracket
    imagefilledrectangle($imgDyn, $sx + $sw + 6 - $vfSize, $sy + $sh + 6 - $vfThickness, $sx + $sw + 6, $sy + $sh + 6, $vfColor);
    imagefilledrectangle($imgDyn, $sx + $sw + 6 - $vfThickness, $sy + $sh + 6 - $vfSize, $sx + $sw + 6, $sy + $sh + 6, $vfColor);
}

// 3. Draw Logo Background Circle Badge (Centered at x=300, y=890)
imagefilledellipse($imgDyn, 300, 890, 130, 130, $badgeBg);
imageellipse($imgDyn, 300, 890, 130, 130, $badgeBorder);
imageellipse($imgDyn, 300, 890, 122, 122, $greyDark);

// 4. Draw Decorative Dividers with Y2K Stars
// Upper Divider (below Subtitle, y=435)
imageline($imgDyn, 50, 435, 260, 435, $greyLight);
imageline($imgDyn, 340, 435, 550, 435, $greyLight);
drawFourPointStar($imgDyn, 300, 435, 12, $greyDark);

// Lower Divider (above Hashtag, y=1380)
imageline($imgDyn, 50, 1380, 260, 1380, $greyLight);
imageline($imgDyn, 340, 1380, 550, 1380, $greyLight);
drawFourPointStar($imgDyn, 300, 1380, 12, $greyDark);

// 5. Draw Information Box (y=1580 - 1700)
imagefilledrectangle($imgDyn, 80, 1580, 520, 1700, $boxBg);
imagerectangle($imgDyn, 80, 1580, 520, 1700, $boxBorder);
imagerectangle($imgDyn, 84, 1584, 516, 1696, $greyLight);

// Content inside Info Box
drawText($imgDyn, 12, 0, 110, 1622, $greyDark, 'courbd', 'DATE: ' . date('Y.m.d'));
drawText($imgDyn, 12, 0, 110, 1662, $greyDark, 'courbd', 'LOC:  PHOTO KIOSK');
drawText($imgDyn, 12, 0, 310, 1622, $greyDark, 'courbd', 'VER:  V1.28');
drawText($imgDyn, 12, 0, 310, 1662, $greyDark, 'courbd', 'TYPE: DYNAMIC STRIP');

// 6. Draw Procedural Barcode stamp (y=1790)
drawBarcode($imgDyn, 180, 1790, 240, 55, $greyDark);
drawText($imgDyn, 10, 0, 180, 1865, $greyDark, 'cour', 'NO. EV-9948D-STRIP');

// Draw static branding at the very bottom
$grey = imagecolorallocate($imgDyn, 120, 120, 120);
drawText($imgDyn, 12, 0, -1, 1920, $grey, 'arial', 'photobooth by @polling.id');

imagepng($imgDyn, $outputDir . 'dynamic_event_strip.png');
imagedestroy($imgDyn);


echo "All 19 frames generated successfully!\n";
?>
