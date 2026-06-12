package com.example.photobooth.print

import android.content.Context
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
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.FileOutputStream
import java.io.IOException

class ColorPrinterDriver : PrinterManager {
    override suspend fun printBitmap(bitmap: Bitmap, context: Context): PrintResult = withContext(Dispatchers.IO) {
        var bitmapToPrint = bitmap
        val isStrip = bitmap.width.toFloat() / bitmap.height.toFloat() < 0.5f
        try {
            val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
                ?: return@withContext PrintResult.Error("Print Service not available on this device")
                
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
            
            // Detect vertical strip layout (width/height ratio < 0.5) and duplicate
            if (isStrip) {
                bitmapToPrint = duplicateStripFor4R(bitmap)
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
                    // Use bitmap dimensions for page bounds
                    val pageInfo = PdfDocument.PageInfo.Builder(bitmapToPrint.width, bitmapToPrint.height, 1).create()
                    val page = pdfDocument.startPage(pageInfo)
                    
                    val canvas: Canvas = page.canvas
                    canvas.drawBitmap(bitmapToPrint, 0f, 0f, null)
                    pdfDocument.finishPage(page)
                    
                    try {
                        pdfDocument.writeTo(FileOutputStream(destination.fileDescriptor))
                        callback.onWriteFinished(arrayOf(PageRange.ALL_PAGES))
                    } catch (e: IOException) {
                        callback.onWriteFailed("Failed to write print data: ${e.message}")
                    } finally {
                        pdfDocument.close()
                    }
                }

                override fun onFinish() {
                    super.onFinish()
                    // Clean up temporary double strip bitmap if created
                    if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                        bitmapToPrint.recycle()
                    }
                }
            }, null)
            
            PrintResult.Success
        } catch (e: Exception) {
            // Clean up temporary double strip bitmap if print fails to start
            if (bitmapToPrint != bitmap && !bitmapToPrint.isRecycled) {
                bitmapToPrint.recycle()
            }
            PrintResult.Error("Gagal memulai proses cetak: ${e.message}")
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
