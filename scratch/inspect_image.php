<?php
$filePath = __DIR__ . '/../backend/frames/postcard_black.png';
if (!file_exists($filePath)) {
    echo "File does not exist: $filePath\n";
    exit;
}

$info = getimagesize($filePath);
if ($info === false) {
    echo "Failed to get image size. Not a valid image.\n";
    exit;
}

echo "Dimensions: " . $info[0] . "x" . $info[1] . "\n";
echo "Mime Type: " . $info['mime'] . "\n";

$im = imagecreatefrompng($filePath);
if (!$im) {
    echo "Failed to load PNG using GD library.\n";
    exit;
}

// Check if image is transparent
$w = imagesx($im);
$h = imagesy($im);
$opaquePixels = 0;
$transparentPixels = 0;

for ($x = 0; $x < $w; $x += 10) {
    for ($y = 0; $y < $h; $y += 10) {
        $colorIndex = imagecolorat($im, $x, $y);
        $colorInfo = imagecolorsforindex($im, $colorIndex);
        if ($colorInfo['alpha'] == 127) {
            $transparentPixels++;
        } else {
            $opaquePixels++;
        }
    }
}

echo "Total checked pixels: " . ($opaquePixels + $transparentPixels) . "\n";
echo "Opaque pixels: $opaquePixels\n";
echo "Transparent pixels: $transparentPixels\n";
?>
