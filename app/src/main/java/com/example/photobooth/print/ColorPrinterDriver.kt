package com.example.photobooth.print

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.pdf.PdfDocument
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
        try {
            val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
                ?: return@withContext PrintResult.Error("Print Service not available on this device")
                
            val jobName = "Creative Studio Photobooth"
            
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
                    val pageInfo = PdfDocument.PageInfo.Builder(bitmap.width, bitmap.height, 1).create()
                    val page = pdfDocument.startPage(pageInfo)
                    
                    val canvas: Canvas = page.canvas
                    canvas.drawBitmap(bitmap, 0f, 0f, null)
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
            }, null)
            
            PrintResult.Success
        } catch (e: Exception) {
            PrintResult.Error("Gagal memulai proses cetak: ${e.message}")
        }
    }
}
