package com.example.photobooth.print

import android.content.Context
import android.graphics.Bitmap

interface PrinterManager {
    suspend fun printBitmap(bitmap: Bitmap, context: Context): PrintResult
}

sealed class PrintResult {
    object Success : PrintResult()
    data class Error(val message: String) : PrintResult()
}
