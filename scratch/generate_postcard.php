<?php
$im = imagecreatetruecolor(1200, 900);

// Enable alpha blending and save alpha channel
imagealphablending($im, false);
imagesavealpha($im, true);

// Create solid black background color (#121212)
$black = imagecolorallocate($im, 18, 18, 18);
imagefill($im, 0, 0, $black);

// Create transparent color for the center photo slot
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);

// Draw transparent rectangle for photo slot at x=50, y=50, x2=1150, y2=850 (width=1100, height=800)
imagefilledrectangle($im, 50, 50, 1150, 850, $transparent);

// Output to the frames directory
$outputPath = __DIR__ . '/../backend/frames/postcard_black.png';
if (imagepng($im, $outputPath)) {
    echo "Generated postcard_black.png successfully at $outputPath\n";
} else {
    echo "Failed to generate image.\n";
}

imagedestroy($im);
?>
