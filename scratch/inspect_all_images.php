<?php
$dir = __DIR__ . '/../backend/frames/';
$files = glob($dir . '*.png');

foreach ($files as $file) {
    if (basename($file) === 'dummy.png' || basename($file) === 'original_magazine_strip.png' || basename($file) === 'original_TES.png') {
        continue;
    }
    
    $im = @imagecreatefrompng($file);
    if (!$im) {
        echo basename($file) . ": FAILED TO LOAD\n";
        continue;
    }
    
    $w = imagesx($im);
    $h = imagesy($im);
    $opaque = 0;
    
    // Check a sample of pixels
    for ($x = 0; $x < $w; $x += 10) {
        for ($y = 0; $y < $h; $y += 10) {
            $color = imagecolorat($im, $x, $y);
            $alpha = imagecolorsforindex($im, $color)['alpha'];
            if ($alpha < 127) {
                $opaque++;
            }
        }
    }
    
    if ($opaque === 0) {
        echo basename($file) . ": WARNING - 100% TRANSPARENT!\n";
    } else {
        echo basename($file) . ": OK (Opaque pixels found)\n";
    }
    imagedestroy($im);
}
?>
