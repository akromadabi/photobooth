package com.example.photobooth.print

import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.DashPathEffect
import android.graphics.Paint
import android.graphics.pdf.PdfDocument
import android.os.Build
import android.os.Bundle
import android.os.CancellationSignal
import android.os.ParcelFileDescriptor
import android.print.PageRange
import android.print.PrintAttributes
import android.print.PrintDocumentAdapter
import android.print.PrintDocumentInfo
import android.print.PrintManager
import androidx.core.content.FileProvider
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.io.IOException

class ColorPrinterDriver : PrinterManager {
    override suspend fun printBitmap(bitmap: Bitmap, context: Context): PrintResult = withContext(Dispatchers.IO) {
        var bitmapToPrint = bitmap
        val isStrip = bitmap.width.toFloat() / bitmap.height.toFloat() < 0.5f
        
        // Detect vertical strip layout (width/height ratio < 0.5) and duplicate
        if (isStrip) {
            bitmapToPrint = duplicateStripFor4R(bitmap)
        }

        val configManager = ConfigManager(context)
        val mode = configManager.colorPrinterMode

        if (mode != "SYSTEM") {
            // Save bitmap to temporary file for sharing
            val cacheFile = File(context.cacheDir, "temp_print.jpg")
            try {
                FileOutputStream(cacheFile).use { out ->
                    bitmapToPrint.compress(Bitmap.CompressFormat.JPEG, 100, out)
                }
            } catch (e: Exception) {
                if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                    bitmapToPrint.recycle()
                }
                return@withContext PrintResult.Error("Gagal menyimpan file sementara: ${e.message}")
            }

            val authority = "${context.packageName}.fileprovider"
            val uri = FileProvider.getUriForFile(context, authority, cacheFile)

            // Suspend Lock Task Mode temporarily on the Main thread to allow external app to open
            withContext(Dispatchers.Main) {
                val activity = context.findActivity()
                if (activity != null) {
                    try {
                        val am = activity.getSystemService(Context.ACTIVITY_SERVICE) as? android.app.ActivityManager
                        val isPinned = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                            am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_LOCKED ||
                            am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_PINNED
                        } else {
                            @Suppress("DEPRECATION")
                            am?.isInLockTaskMode ?: false
                        }
                        if (isPinned) {
                            activity.stopLockTask()
                        }
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }
            }

            try {
                when (mode) {
                    "NOKOPRINT" -> {
                        withContext(Dispatchers.Main) {
                            try {
                                val intent = Intent(Intent.ACTION_SEND).apply {
                                    type = "image/jpeg"
                                    putExtra(Intent.EXTRA_STREAM, uri)
                                    setPackage("com.nokoprint")
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                }
                                context.startActivity(intent)
                            } catch (e: android.content.ActivityNotFoundException) {
                                // Fallback to ACTION_VIEW in case NokoPrint handles it differently
                                val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                                    setDataAndType(uri, "image/jpeg")
                                    setPackage("com.nokoprint")
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                }
                                context.startActivity(viewIntent)
                            }
                        }
                    }
                    "PRINTERSHARE" -> {
                        withContext(Dispatchers.Main) {
                            val intent = Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "image/jpeg")
                                setPackage("com.dynamixsoftware.printershare")
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            }
                            context.startActivity(intent)
                        }
                    }
                    "SHARE" -> {
                        withContext(Dispatchers.Main) {
                            val intent = Intent(Intent.ACTION_SEND).apply {
                                type = "image/jpeg"
                                putExtra(Intent.EXTRA_STREAM, uri)
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            }
                            val chooser = Intent.createChooser(intent, "Cetak foto menggunakan...")
                            chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            context.startActivity(chooser)
                        }
                    }
                }

                // Clean up temporary double strip bitmap if created
                if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                    bitmapToPrint.recycle()
                }
                PrintResult.Success
            } catch (e: android.content.ActivityNotFoundException) {
                // Restore Lock Task Mode if application is not found
                withContext(Dispatchers.Main) {
                    val activity = context.findActivity()
                    if (activity != null) {
                        try {
                            activity.startLockTask()
                        } catch (ex: Exception) {
                            ex.printStackTrace()
                        }
                    }
                }
                if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                    bitmapToPrint.recycle()
                }
                val appName = if (mode == "NOKOPRINT") "NokoPrint" else "PrinterShare"
                PrintResult.Error("Aplikasi $appName tidak ditemukan. Silakan instal aplikasi $appName dari Google Play Store terlebih dahulu.")
            } catch (e: Exception) {
                if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                    bitmapToPrint.recycle()
                }
                PrintResult.Error("Gagal membuka aplikasi cetak: ${e.message}")
            }
        } else {
            // System print flow (original code)
            try {
                val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
                    ?: return@withContext PrintResult.Error("Layanan Cetak tidak tersedia di perangkat ini")
                    
                val jobName = "Creative Studio Photobooth"
                
                // Suspend Lock Task Mode temporarily on the Main thread to allow system print dialog to open
                withContext(Dispatchers.Main) {
                    val activity = context.findActivity()
                    if (activity != null) {
                        try {
                            val am = activity.getSystemService(Context.ACTIVITY_SERVICE) as? android.app.ActivityManager
                            val isPinned = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_LOCKED ||
                                am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_PINNED
                            } else {
                                @Suppress("DEPRECATION")
                                am?.isInLockTaskMode ?: false
                            }
                            if (isPinned) {
                                activity.stopLockTask()
                            }
                        } catch (e: Exception) {
                            e.printStackTrace()
                        }
                    }
                }
                
                printManager.print(jobName, object : PrintDocumentAdapter() {
                    override fun onLayout(
                        oldAttributes: PrintAttributes?,
                        newAttributes: PrintAttributes,
                        cancellationSignal: CancellationSignal?,
                        callback: LayoutResultCallback,
                        extras: Bundle?
                    ) {
                        if (cancellationSignal?.isCanceled == true) {
                            callback.onLayoutCancelled()
                            return
                        }
                        
                        val info = PrintDocumentInfo.Builder("photobooth_strip.pdf")
                            .setContentType(PrintDocumentInfo.CONTENT_TYPE_DOCUMENT)
                            .setPageCount(1)
                            .build()
                        callback.onLayoutFinished(info, true)
                    }

                    override fun onWrite(
                        pages: Array<out PageRange>?,
                        destination: ParcelFileDescriptor,
                        cancellationSignal: CancellationSignal?,
                        callback: WriteResultCallback
                    ) {
                        val pdfDocument = PdfDocument()
                        val pageInfo = PdfDocument.PageInfo.Builder(bitmapToPrint.width, bitmapToPrint.height, 1).create()
                        val page = pdfDocument.startPage(pageInfo)
                        
                        val canvas: Canvas = page.canvas
                        canvas.drawBitmap(bitmapToPrint, 0f, 0f, null)
                        pdfDocument.finishPage(page)
                        
                        try {
                            pdfDocument.writeTo(FileOutputStream(destination.fileDescriptor))
                            callback.onWriteFinished(arrayOf(PageRange.ALL_PAGES))
                        } catch (e: IOException) {
                            callback.onWriteFailed("Gagal menulis data cetak: ${e.message}")
                        } finally {
                            pdfDocument.close()
                        }
                    }

                    override fun onFinish() {
                        super.onFinish()
                        if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                            bitmapToPrint.recycle()
                        }
                    }
                }, null)
                
                PrintResult.Success
            } catch (e: Exception) {
                if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                    bitmapToPrint.recycle()
                }
                PrintResult.Error("Gagal memulai proses cetak sistem: ${e.message}")
            }
        }
    }

    private fun duplicateStripFor4R(bitmap: Bitmap): Bitmap {
        val stripW = bitmap.width
        val stripH = bitmap.height

        val gap = (stripW * 0.05f).toInt().coerceAtLeast(10)
        val neededW = stripW * 2 + gap
        val neededH = stripH

        val finalW: Int
        val finalH: Int
        val marginX: Int
        val marginY: Int

        if (neededW.toFloat() / neededH.toFloat() > 2f / 3f) {
            // Combined width is wider than 2:3 ratio. Add top/bottom margins.
            finalW = neededW
            finalH = (neededW * 3f / 2f).toInt()
            marginX = 0
            marginY = (finalH - neededH) / 2
        } else {
            // Combined width is narrower than 2:3 ratio. Add left/right margins.
            finalH = neededH
            finalW = (neededH * 2f / 3f).toInt()
            marginX = (finalW - neededW) / 2
            marginY = 0
        }

        val doubleBitmap = Bitmap.createBitmap(finalW, finalH, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(doubleBitmap)
        canvas.drawColor(android.graphics.Color.WHITE)

        val left1 = marginX
        val left2 = marginX + stripW + gap
        val top = marginY

        // Draw first strip
        canvas.drawBitmap(bitmap, left1.toFloat(), top.toFloat(), null)
        // Draw second strip
        canvas.drawBitmap(bitmap, left2.toFloat(), top.toFloat(), null)

        // Draw vertical dashed cutting guide line down the middle
        val midX = (left1 + stripW + left2) / 2f
        val paint = Paint().apply {
            color = android.graphics.Color.LTGRAY
            style = Paint.Style.STROKE
            strokeWidth = 2f
            pathEffect = DashPathEffect(floatArrayOf(10f, 10f), 0f)
        }
        canvas.drawLine(midX, top.toFloat(), midX, (top + stripH).toFloat(), paint)

        return doubleBitmap
    }
}

private fun Context.findActivity(): android.app.Activity? {
    var currentContext = this
    while (currentContext is android.content.ContextWrapper) {
        if (currentContext is android.app.Activity) {
            return currentContext
        }
        currentContext = currentContext.baseContext
    }
    return null
}
