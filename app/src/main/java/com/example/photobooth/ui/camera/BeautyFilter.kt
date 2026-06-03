package com.example.photobooth.ui.camera

import android.graphics.*
import kotlin.math.max
import kotlin.math.min

object BeautyFilter {

    /**
     * Applies Orton Soft-Glow and a very subtle glowing pink cheek blush to the bitmap
     * based on face bounding boxes detected by ML Kit.
     */
    fun applyBeautyFilter(
        src: Bitmap,
        faces: List<Rect>
    ): Bitmap {
        if (faces.isEmpty()) return src

        // Create a mutable copy of the source bitmap to draw beauty adjustments on
        val resultBmp = src.copy(Bitmap.Config.ARGB_8888, true)
        val canvas = Canvas(resultBmp)
        val paint = Paint().apply { isAntiAlias = true; isFilterBitmap = true }

        for (faceRect in faces) {
            // 1. Safe boundary checks to keep cropping within bitmap dimensions
            val left = max(0, faceRect.left)
            val top = max(0, faceRect.top)
            val right = min(src.width, faceRect.right)
            val bottom = min(src.height, faceRect.bottom)
            val faceW = right - left
            val faceH = bottom - top

            if (faceW <= 0 || faceH <= 0) continue

            // 2. Extract face bitmap region
            val faceBmp = Bitmap.createBitmap(resultBmp, left, top, faceW, faceH)

            // 3. Orton Soft-Glow: Bilateral-like smart blur by scaling down and up
            // Downscale face by 2x to create a natural, dream-like soft focus blur while keeping sharpness
            val scaleFactor = 2
            val smallW = max(1, faceW / scaleFactor)
            val smallH = max(1, faceH / scaleFactor)
            
            val smallBmp = Bitmap.createScaledBitmap(faceBmp, smallW, smallH, true)
            
            // Soften the small bitmap using a mild color brightness filter to make skin look brighter/glowing
            val glowCanvas = Canvas(smallBmp)
            val glowPaint = Paint().apply {
                colorFilter = ColorMatrixColorFilter(ColorMatrix().apply {
                    // Slightly increase brightness and warmth
                    setScale(1.04f, 1.03f, 1.01f, 1.0f)
                })
            }
            glowCanvas.drawBitmap(smallBmp, 0f, 0f, glowPaint)

            // Scale back up to full face region with bilinear filtering enabled (true)
            val blurredFaceBmp = Bitmap.createScaledBitmap(smallBmp, faceW, faceH, true)

            // 4. Blend the glowing blurred face back onto the original face canvas with 18% opacity
            // This selectively smooths out skin pores/lines while keeping contrasty lines (eyes, teeth, hair) sharp!
            paint.alpha = 45 // ~18% opacity
            canvas.drawBitmap(blurredFaceBmp, left.toFloat(), top.toFloat(), paint)

            // 5. Procedural Glowing Cheek Blush-on
            // Estimate cheeks positions dynamically relative to the face bounding box
            // Left cheek is roughly at 30% width, 62% height of the face box
            // Right cheek is roughly at 70% width, 62% height of the face box
            val leftCheekX = left + (faceW * 0.32f)
            val rightCheekX = left + (faceW * 0.68f)
            val cheeksY = top + (faceH * 0.60f)
            val blushRadius = faceW * 0.16f // Blush size relative to face width

            if (blushRadius > 0) {
                val blushPaint = Paint().apply {
                    isAntiAlias = true
                    style = Paint.Style.FILL
                }

                // Smooth radial pink gradient centered on cheeks
                val pinkColor = Color.argb(32, 255, 105, 180) // 12% opacity soft pink (Hot Pink)
                val transparentColor = Color.argb(0, 255, 105, 180)
                
                // Left Cheek Blush
                val leftShader = RadialGradient(
                    leftCheekX, cheeksY, blushRadius,
                    pinkColor, transparentColor, Shader.TileMode.CLAMP
                )
                blushPaint.shader = leftShader
                canvas.drawCircle(leftCheekX, cheeksY, blushRadius, blushPaint)

                // Right Cheek Blush
                val rightShader = RadialGradient(
                    rightCheekX, cheeksY, blushRadius,
                    pinkColor, transparentColor, Shader.TileMode.CLAMP
                )
                blushPaint.shader = rightShader
                canvas.drawCircle(rightCheekX, cheeksY, blushRadius, blushPaint)
            }

            // Recycle temporary bitmaps
            faceBmp.recycle()
            smallBmp.recycle()
            blurredFaceBmp.recycle()
        }

        return resultBmp
    }
}
