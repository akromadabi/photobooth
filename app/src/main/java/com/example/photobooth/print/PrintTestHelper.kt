package com.example.photobooth.print

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import com.example.photobooth.data.ConfigManager
import com.google.zxing.BarcodeFormat
import com.google.zxing.MultiFormatWriter
import com.google.zxing.common.BitMatrix

object PrintTestHelper {

    fun generateQrCode(text: String, width: Int, height: Int): Bitmap {
        val bitMatrix: BitMatrix = MultiFormatWriter().encode(text, BarcodeFormat.QR_CODE, width, height)
        val w = bitMatrix.width
        val h = bitMatrix.height
        val pixels = IntArray(w * h)
        for (y in 0 until h) {
            val offset = y * w
            for (x in 0 until w) {
                pixels[offset + x] = if (bitMatrix.get(x, y)) android.graphics.Color.BLACK else android.graphics.Color.WHITE
            }
        }
        val bitmap = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        bitmap.setPixels(pixels, 0, w, 0, 0, w, h)
        return bitmap
    }

    suspend fun runTestPrint(context: Context, configManager: ConfigManager, forceType: String? = null): String {
        val printerTypeToUse = forceType ?: configManager.printerType
        if (printerTypeToUse == "NONE") {
            return "Tipe printer aktif: Tidak Ada"
        }

        val bitmap = if (printerTypeToUse == "COLOR") {
            // Generate Color Test Page (width 800, height 1000)
            val w = 800
            val h = 1000
            val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
            val canvas = Canvas(bmp)
            val paint = Paint().apply { isAntiAlias = true }
            
            // Background
            canvas.drawColor(android.graphics.Color.WHITE)
            
            // Outer border
            paint.color = android.graphics.Color.RED
            paint.strokeWidth = 8f
            paint.style = Paint.Style.STROKE
            canvas.drawRect(20f, 20f, w - 20f, h - 20f, paint)
            
            // Title
            paint.style = Paint.Style.FILL
            paint.color = android.graphics.Color.BLACK
            paint.textSize = 40f
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
            canvas.drawText("UJI COBA CETAK WARNA", 180f, 100f, paint)
            
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
            paint.textSize = 28f
            canvas.drawText("Tipe Printer: COLOR PRINTER (PDF/SYSTEM)", 80f, 200f, paint)
            canvas.drawText("Pengujian: TEST CETAK WARNA (COLOR TEST)", 80f, 260f, paint)
            canvas.drawText("Ukuran Kertas: A4 / 4R (Sesuai Setelan Dialog)", 80f, 320f, paint)
            canvas.drawText("Aplikasi: Creative Studio Kiosk v1.16.0", 80f, 380f, paint)
            
            // Draw color bands to test printer colors
            val colors = intArrayOf(
                android.graphics.Color.RED,
                android.graphics.Color.GREEN,
                android.graphics.Color.BLUE,
                android.graphics.Color.YELLOW,
                android.graphics.Color.CYAN,
                android.graphics.Color.MAGENTA,
                android.graphics.Color.BLACK
            )
            val colorNames = arrayOf("RED (MERAH)", "GREEN (HIJAU)", "BLUE (BIRU)", "YELLOW (KUNING)", "CYAN (BIRU MUDA)", "MAGENTA (MERAH MUDA)", "BLACK (HITAM)")
            
            paint.textSize = 22f
            for (i in colors.indices) {
                val y = 460f + i * 60f
                paint.color = colors[i]
                paint.style = Paint.Style.FILL
                canvas.drawRect(80f, y, 200f, y + 40f, paint)
                
                paint.color = android.graphics.Color.BLACK
                canvas.drawText(colorNames[i], 230f, y + 28f, paint)
            }
            
            paint.textSize = 26f
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
            canvas.drawText("Status: Printer Warna Siap!", 240f, 920f, paint)
            
            bmp
        } else {
            // Generate Thermal Test Page (width 384, height 600)
            val w = 384
            val h = 600
            val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
            val canvas = Canvas(bmp)
            val paint = Paint().apply { isAntiAlias = true }
            
            canvas.drawColor(android.graphics.Color.WHITE)
            
            // Border
            paint.color = android.graphics.Color.BLACK
            paint.strokeWidth = 4f
            paint.style = Paint.Style.STROKE
            canvas.drawRect(10f, 10f, w - 10f, h - 10f, paint)
            
            // Title
            paint.style = Paint.Style.FILL
            paint.textSize = 24f
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
            canvas.drawText("UJI COBA CETAK STRUK", 60f, 60f, paint)
            
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
            paint.textSize = 16f
            canvas.drawText("Tipe Printer: RECEIPT PRINTER (THERMAL)", 30f, 130f, paint)
            canvas.drawText("Pengujian: TEST CETAK STRUK (THERMAL TEST)", 30f, 180f, paint)
            canvas.drawText("Ukuran Kertas: ${configManager.printerPaperWidth} mm", 30f, 230f, paint)
            canvas.drawText("Mode Protokol: ${configManager.thermalMode}", 30f, 280f, paint)
            canvas.drawText("Port/Alamat: ${configManager.printerAddress}", 30f, 330f, paint)
            canvas.drawText("Aplikasi: Creative Studio Kiosk v1.16.0", 30f, 380f, paint)
            
            // Checkerboard pattern to test thermal printing density
            paint.color = android.graphics.Color.BLACK
            val startY = 430f
            for (i in 0..2) {
                val yPos = startY + i * 25f
                for (j in 0..11) {
                    val xPos = 40f + j * 25f
                    if ((i + j) % 2 == 0) {
                        canvas.drawRect(xPos, yPos, xPos + 25f, yPos + 25f, paint)
                    }
                }
            }
            
            paint.textSize = 18f
            paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
            canvas.drawText("Status: Printer Struk Siap!", 65f, 550f, paint)
            
            bmp
        }

        val driver: PrinterManager = when (printerTypeToUse) {
            "THERMAL" -> ThermalPrinterDriver()
            "COLOR" -> ColorPrinterDriver()
            else -> return "Tipe printer terkonfigurasi: Tidak Ada"
        }
        
        val result = driver.printBitmap(bitmap, context)
        return when (result) {
            is PrintResult.Success -> "Test print sukses terkirim ke printer!"
            is PrintResult.Error -> "Gagal mencetak: ${result.message}"
        }
    }

    suspend fun printCouponReceipt(
        context: Context,
        configManager: ConfigManager,
        couponCode: String,
        packageName: String
    ): String {
        // Width: 384 pixels, Height: 720 pixels
        val w = 384
        val h = 720
        val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bmp)
        val paint = Paint().apply { isAntiAlias = true }

        canvas.drawColor(android.graphics.Color.WHITE)

        // Draw header
        paint.color = android.graphics.Color.BLACK
        paint.style = Paint.Style.FILL
        paint.textAlign = Paint.Align.CENTER
        paint.textSize = 24f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
        canvas.drawText("CREATIVE STUDIO", w / 2f, 50f, paint)

        paint.textSize = 16f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
        canvas.drawText("\"Capture Your Best Moments\"", w / 2f, 80f, paint)

        // Dashed line
        paint.strokeWidth = 2f
        paint.style = Paint.Style.STROKE
        var x = 20f
        while (x < w - 20f) {
            canvas.drawLine(x, 110f, x + 10f, 110f, paint)
            x += 15f
        }

        // Coupon details
        paint.style = Paint.Style.FILL
        paint.textAlign = Paint.Align.CENTER
        paint.textSize = 18f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
        canvas.drawText("PAKET: ${packageName.uppercase()}", w / 2f, 150f, paint)

        paint.textSize = 16f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
        canvas.drawText("KODE KUPON:", w / 2f, 190f, paint)

        // Large boxed coupon code
        paint.textSize = 32f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
        
        // Draw border box for coupon code
        val rectPaint = Paint().apply {
            color = android.graphics.Color.BLACK
            strokeWidth = 3f
            style = Paint.Style.STROKE
        }
        canvas.drawRect(60f, 215f, w - 60f, 285f, rectPaint)
        
        // Center text coupon code
        canvas.drawText(couponCode, w / 2f, 262f, paint)

        // QR Code of the coupon code
        try {
            val qrSize = 180
            val qrBmp = generateQrCode(couponCode, qrSize, qrSize)
            canvas.drawBitmap(qrBmp, (w - qrSize) / 2f, 315f, paint)
        } catch (e: Exception) {
            e.printStackTrace()
        }

        // Instructions
        paint.textSize = 14f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
        canvas.drawText("Pindai QR atau masukkan kode kupon", w / 2f, 530f, paint)
        canvas.drawText("pada menu pembayaran Kiosk Anda.", w / 2f, 555f, paint)

        // Date & Cashier
        val dateStr = android.text.format.DateFormat.format("dd MMM yyyy HH:mm", java.util.Date()).toString()
        paint.textSize = 12f
        canvas.drawText("Tanggal: $dateStr", w / 2f, 600f, paint)
        canvas.drawText("Kasir  : Kiosk Operator", w / 2f, 620f, paint)

        // Double line divider at bottom
        paint.strokeWidth = 2f
        paint.style = Paint.Style.STROKE
        canvas.drawLine(20f, 650f, w - 20f, 650f, paint)
        canvas.drawLine(20f, 655f, w - 20f, 655f, paint)

        paint.style = Paint.Style.FILL
        paint.textSize = 14f
        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
        canvas.drawText("TERIMA KASIH & SELAMAT BERFOTO!", w / 2f, 685f, paint)

        val driver = ThermalPrinterDriver()
        val result = driver.printBitmap(bmp, context)
        return when (result) {
            is PrintResult.Success -> "Kupon berhasil dicetak!"
            is PrintResult.Error -> "Gagal mencetak kupon: ${result.message}"
        }
    }
}
