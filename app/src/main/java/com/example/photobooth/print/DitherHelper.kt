package com.example.photobooth.print

import android.graphics.Bitmap
import android.graphics.Color

object DitherHelper {

    /**
     * Converts a standard colored Bitmap to a 1-bit monokrom (black and white) Bitmap
     * using the Floyd-Steinberg error diffusion dithering algorithm.
     */
    fun ditherFloydSteinberg(src: Bitmap): Bitmap {
        val width = src.width
        val height = src.height
        
        // Create an array representing the grayscale values
        val grayData = IntArray(width * height)
        val pixels = IntArray(width * height)
        src.getPixels(pixels, 0, width, 0, 0, width, height)
        
        for (i in pixels.indices) {
            val color = pixels[i]
            val r = Color.red(color)
            val g = Color.green(color)
            val b = Color.blue(color)
            // Luma formula for grayscale conversion
            grayData[i] = (0.299 * r + 0.587 * g + 0.114 * b).toInt()
        }

        // Apply Floyd-Steinberg dithering
        for (y in 0 until height) {
            for (x in 0 until width) {
                val index = y * width + x
                val oldVal = grayData[index]
                // Quantize to 0 (black) or 255 (white)
                val newVal = if (oldVal < 128) 0 else 255
                grayData[index] = newVal
                
                val error = oldVal - newVal
                
                // Distribute error to neighbors
                if (x + 1 < width) {
                    grayData[index + 1] += (error * 7 / 16)
                }
                if (y + 1 < height) {
                    if (x - 1 >= 0) {
                        grayData[(y + 1) * width + (x - 1)] += (error * 3 / 16)
                    }
                    grayData[(y + 1) * width + x] += (error * 5 / 16)
                    if (x + 1 < width) {
                        grayData[(y + 1) * width + (x + 1)] += (error * 1 / 16)
                    }
                }
            }
        }
        
        // Re-construct monokrom Bitmap
        val outPixels = IntArray(width * height)
        for (i in grayData.indices) {
            val val8 = if (grayData[i] < 128) 0 else 255
            outPixels[i] = Color.rgb(val8, val8, val8)
        }
        
        val dest = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
        dest.setPixels(outPixels, 0, width, 0, 0, width, height)
        return dest
    }

    /**
     * Converts a 1-bit monochrome Bitmap into raw byte data formatted for ESC/POS or TSPL BITMAP printing.
     * Each byte represents 8 pixels horizontally, where 1 is black and 0 is white (or vice-versa depending on printer).
     * For TSPL: 0 is white, 1 is black (or vice versa). Usually in TSPL/ESC-POS, 1-bit means:
     * 1 bit = 1 pixel. (1 = black, 0 = white in ESC/POS; 0 = black, 1 = white in some configurations).
     * We'll map: black pixel -> bit 1, white pixel -> bit 0.
     */
    fun convertTo1BitRaster(bitmap: Bitmap): ByteArray {
        val width = bitmap.width
        val height = bitmap.height
        val widthBytes = (width + 7) / 8
        val raster = ByteArray(widthBytes * height)
        
        val pixels = IntArray(width * height)
        bitmap.getPixels(pixels, 0, width, 0, 0, width, height)
        
        var index = 0
        for (y in 0 until height) {
            for (xb in 0 until widthBytes) {
                var byteVal = 0
                for (bit in 0..7) {
                    val x = xb * 8 + bit
                    if (x < width) {
                        val color = pixels[y * width + x]
                        val r = Color.red(color)
                        // If color is black (less than 128), it's printed (1). Else white (0).
                        if (r < 128) {
                            byteVal = byteVal or (1 shl (7 - bit))
                        }
                    }
                }
                raster[index++] = byteVal.toByte()
            }
        }
        return raster
    }
}
