<?php
// Script to generate music_player_strip.png with high visual quality GD graphics
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
        
        // Straight lines
        imageline($img, $rx1 + $r, $ry1, $rx2 - $r, $ry1, $color);
        imageline($img, $rx1 + $r, $ry2, $rx2 - $r, $ry2, $color);
        imageline($img, $rx1, $ry1 + $r, $rx1, $ry2 - $r, $color);
        imageline($img, $rx2, $ry1 + $r, $rx2, $ry2 - $r, $color);
        
        // Arc corners
        imagearc($img, $rx1 + $r, $ry1 + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($img, $rx2 - $r, $ry1 + $r, $r * 2, $r * 2, 270, 360, $color);
        imagearc($img, $rx1 + $r, $ry2 - $r, $r * 2, $r * 2, 90, 180, $color);
        imagearc($img, $rx2 - $r, $ry2 - $r, $r * 2, $r * 2, 0, 90, $color);
    }
}

function drawHeartIcon($img, $cx, $cy, $size, $color) {
    $cx = intval($cx); $cy = intval($cy); $size = intval($size);
    $r = $size / 4.2;
    imagefilledellipse($img, intval($cx - $r), intval($cy - $r*0.6), intval($size/1.9), intval($size/1.9), $color);
    imagefilledellipse($img, intval($cx + $r), intval($cy - $r*0.6), intval($size/1.9), intval($size/1.9), $color);
    $pts = [
        intval($cx - 2.1 * $r), intval($cy - $r*0.4),
        intval($cx + 2.1 * $r), intval($cy - $r*0.4),
        intval($cx), intval($cy + $size * 0.65)
    ];
    imagefilledpolygon($img, $pts, $color);
}

function drawVinylRecord($img, $cx, $cy, $radius, $darkColor, $labelColor, $whiteColor) {
    $cx = intval($cx); $cy = intval($cy); $radius = intval($radius);
    // Outer black disc
    imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $darkColor);
    
    // Concentric grooves
    $grooveColor = imagecolorallocatealpha($img, 255, 255, 255, 105);
    for ($r = intval($radius * 0.45); $r < $radius; $r += 8) {
        imageellipse($img, $cx, $cy, $r * 2, $r * 2, $grooveColor);
    }
    
    // Red Center Label
    imagefilledellipse($img, $cx, $cy, intval($radius * 0.65), intval($radius * 0.65), $labelColor);
    imageellipse($img, $cx, $cy, intval($radius * 0.65), intval($radius * 0.65), $whiteColor);
    
    // Center Spindle Hole
    imagefilledellipse($img, $cx, $cy, intval($radius * 0.18), intval($radius * 0.18), $whiteColor);
    imagefilledellipse($img, $cx, $cy, intval($radius * 0.12), intval($radius * 0.12), $darkColor);
}

function drawPlayerControls($img, $white, $darkGreen) {
    // 1. Shuffle Icon (x: 80, y: 1650)
    $sy = 1650;
    $sx = 80;
    imageline($img, $sx, $sy + 15, $sx + 15, $sy, $white);
    imageline($img, $sx, $sy, $sx + 15, $sy + 15, $white);
    imageline($img, $sx + 15, $sy, $sx + 25, $sy, $white);
    imageline($img, $sx + 15, $sy + 15, $sx + 25, $sy + 15, $white);
    imagefilledpolygon($img, [$sx + 25, $sy - 4, $sx + 32, $sy, $sx + 25, $sy + 4], $white);
    imagefilledpolygon($img, [$sx + 25, $sy + 11, $sx + 32, $sy + 15, $sx + 25, $sy + 19], $white);

    // 2. Previous Track Icon (x: 180, y: 1650)
    $px = 180;
    $py = 1650;
    imagefilledrectangle($img, $px, $py - 12, $px + 4, $py + 12, $white);
    imagefilledpolygon($img, [$px + 22, $py - 12, $px + 5, $py, $px + 22, $py + 12], $white);
    imagefilledpolygon($img, [$px + 38, $py - 12, $px + 21, $py, $px + 38, $py + 12], $white);

    // 3. Play / Pause Main Circle Button (x: 300, y: 1650) - Diameter 66px
    $cx = 300;
    $cy = 1650;
    imagefilledellipse($img, $cx, $cy, 66, 66, $white);
    $triPts = [
        $cx - 9, $cy - 16,
        $cx + 16, $cy,
        $cx - 9, $cy + 16
    ];
    imagefilledpolygon($img, $triPts, $darkGreen);

    // 4. Next Track Icon (x: 420, y: 1650)
    $nx = 420;
    $ny = 1650;
    imagefilledpolygon($img, [$nx, $ny - 12, $nx + 17, $ny, $nx, $ny + 12], $white);
    imagefilledpolygon($img, [$nx + 16, $ny - 12, $nx + 33, $ny, $nx + 16, $ny + 12], $white);
    imagefilledrectangle($img, $nx + 34, $ny - 12, $nx + 38, $ny + 12, $white);

    // 5. Repeat Icon (x: 510, y: 1650)
    $rx = 510;
    $ry = 1650;
    imagearc($img, $rx + 12, $ry + 5, 26, 20, 0, 270, $white);
    imagearc($img, $rx + 12, $ry + 5, 28, 22, 0, 270, $white);
    imagefilledpolygon($img, [$rx + 22, $ry - 8, $rx + 28, $ry + 2, $rx + 16, $ry + 2], $white);
}

echo "Generating music_player_strip.png...\n";

$width = 600;
$height = 2000;
$img = imagecreatetruecolor($width, $height);

imagealphablending($img, false);
imagesavealpha($img, true);

// ----------------------------------------------------
// 1. BASE BACKGROUND (Rich Dark Emerald Gradient)
// ----------------------------------------------------
for ($y = 0; $y < $height; $y++) {
    $ratio = $y / $height;
    $r = intval(15 * (1 - $ratio) + 6 * $ratio);
    $g = intval(56 * (1 - $ratio) + 26 * $ratio);
    $b = intval(44 * (1 - $ratio) + 20 * $ratio);
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $width - 1, $y, $col);
}

// Add smooth radial lighting glow behind music player controls at bottom
imagealphablending($img, true);
for ($rad = 280; $rad > 0; $rad -= 5) {
    $alpha = intval(127 - (1 - ($rad / 280)) * 45);
    $glowColor = imagecolorallocatealpha($img, 28, 135, 95, min(127, max(0, $alpha)));
    imagefilledellipse($img, 300, 1600, intval($rad * 2), intval($rad * 1.5), $glowColor);
}

// Colors palette
$white       = imagecolorallocate($img, 255, 255, 255);
$whiteSoft   = imagecolorallocate($img, 225, 240, 235);
$mutedText   = imagecolorallocate($img, 175, 205, 195);
$darkGreen   = imagecolorallocate($img, 13, 59, 46);     // #0D3B2E
$accentRed   = imagecolorallocate($img, 235, 45, 60);     // #EB2D3C Heart Red
$vinylDark   = imagecolorallocate($img, 18, 18, 18);
$vinylLabel  = imagecolorallocate($img, 215, 40, 50);

// Photo Slots (3 slots)
$slots = [
    ['x' => 50, 'y' => 200, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 605, 'w' => 500, 'h' => 375],
    ['x' => 50, 'y' => 1010, 'w' => 500, 'h' => 375]
];

// Cut transparent rectangles for photo slots
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagealphablending($img, false);
foreach ($slots as $slot) {
    imagefilledrectangle($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'] - 1, $slot['y'] + $slot['h'] - 1, $transparent);
}
imagealphablending($img, true);

// ----------------------------------------------------
// 2. TOP HEADER SECTION (Date & Digital Clock)
// ----------------------------------------------------
// Date line: "Sabtu, 23 Maret 2026"
drawTextSafe($img, 16, 0, -1, 80, $whiteSoft, 'segoe', 'Sabtu, 23 Maret 2026');

// Clock line: "12.28"
drawTextSafe($img, 52, 0, -1, 155, $white, 'segoebd', '12.28');


// ----------------------------------------------------
// 3. PHOTO SLOT BORDERS (Rounded White Frames)
// ----------------------------------------------------
foreach ($slots as $slot) {
    drawRoundedRectBorder($img, $slot['x'], $slot['y'], $slot['x'] + $slot['w'], $slot['y'] + $slot['h'], 22, 6, $white);
}


// ----------------------------------------------------
// 4. CREATIVE OVERLAPPING ELEMENTS (standar_bingkai.md)
// ----------------------------------------------------

// Overlapping 1: Vinyl Record Disc peeking out of Slot #1 bottom-right corner
drawVinylRecord($img, 510, 990, 65, $vinylDark, $vinylLabel, $white);
drawTextSafe($img, 7, 0, 485, 993, $white, 'arialbd', '33 RPM');

// Overlapping 2: "NOW PLAYING" Equalizer Badge overlapping top-left of Slot #0
$badgeBg = imagecolorallocatealpha($img, 10, 40, 30, 20);
drawFilledRoundedRect($img, 30, 172, 215, 215, 14, $badgeBg);
drawRoundedRectBorder($img, 30, 172, 215, 215, 14, 2, $white);
// Equalizer bars graphic inside badge
$eqX = 42;
$eqHeights = [14, 22, 10, 18, 25, 12];
foreach ($eqHeights as $idx => $h) {
    imagefilledrectangle($img, $eqX + ($idx * 6), 194 - intval($h/2), $eqX + ($idx * 6) + 3, 194 + intval($h/2), $white);
}
drawTextSafe($img, 9, 0, 85, 198, $white, 'segoebd', 'PLAYING NOW');

// Overlapping 3: Floating Music Note / Heart Badge on Slot #2 top-left
drawFilledRoundedRect($img, 30, 990, 80, 1040, 20, $accentRed);
drawHeartIcon($img, 55, 1010, 22, $white);


// ----------------------------------------------------
// 5. MUSIC PLAYER CONTROLS SECTION (Bottom)
// ----------------------------------------------------

// Track Title & Subtitle (Left Aligned, room for dynamic overlay)
drawTextSafe($img, 24, 0, 50, 1485, $white, 'segoebd', 'Your Favorite Playlist');
drawTextSafe($img, 16, 0, 50, 1525, $mutedText, 'segoe', 'Murad Naser');

// Red Heart Icon on Right
drawHeartIcon($img, 520, 1495, 30, $accentRed);

// Timeline / Progress Bar (y = 1570)
// Time Elapsed "1.10"
drawTextSafe($img, 11, 0, 50, 1575, $mutedText, 'segoe', '1.10');
// Time Remaining "-4.15"
drawTextSafe($img, 11, 0, 510, 1575, $mutedText, 'segoe', '-4.15');

// Progress Bar Line (x: 90 to 500)
$barY = 1570;
$barStart = 90;
$barEnd = 495;
$playheadX = 240; // ~35% progress

// Inactive track line
$barInactive = imagecolorallocatealpha($img, 255, 255, 255, 120);
imagefilledrectangle($img, $barStart, $barY - 2, $barEnd, $barY + 2, $barInactive);

// Active track line
imagefilledrectangle($img, $barStart, $barY - 2, $playheadX, $barY + 2, $white);

// Playhead Circle Dot
imagefilledellipse($img, $playheadX, $barY, 16, 16, $white);

// Control Buttons (Shuffle, Prev, Play Circle, Next, Repeat)
drawPlayerControls($img, $white, $darkGreen);

// ----------------------------------------------------
// 6. BOTTOM BRAND PILL BADGE
// ----------------------------------------------------
// White pill background (x: 170 to 430, y: 1910 to 1955)
drawFilledRoundedRect($img, 170, 1910, 430, 1955, 22, $white);
drawTextSafe($img, 13, 0, -1, 1939, $darkGreen, 'segoebd', 'LARANA PHOTOBOX');


// ----------------------------------------------------
// SAVE GENERATED PNG
// ----------------------------------------------------
$targetFile = $outputDir . 'music_player_strip.png';
imagepng($img, $targetFile);
imagedestroy($img);

echo "Successfully generated: " . $targetFile . "\n";
