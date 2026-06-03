package com.example.photobooth.ui.share

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.widget.Toast
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.print.ColorPrinterDriver
import com.example.photobooth.print.PrintResult
import com.example.photobooth.print.ThermalPrinterDriver
import com.google.zxing.BarcodeFormat
import com.google.zxing.MultiFormatWriter
import com.google.zxing.common.BitMatrix
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import java.io.File

@Composable
fun SharePrintScreen(
    finalPhotoPath: String,
    shouldPrint: Boolean,
    onFinishClick: () -> Unit,
    modifier: Modifier = Modifier,
    frameId: String = "",
    eventId: String = "general",
    sessionId: String = "",
    packageId: String = ""
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    
    var uploadStatus by remember { mutableStateOf("Mengunggah foto ke server...") }
    var printStatus by remember { mutableStateOf(if (shouldPrint) "Menyiapkan pencetakan..." else "Cetak dinonaktifkan") }
    
    var qrCodeBitmap by remember { mutableStateOf<Bitmap?>(null) }
    var downloadUrl by remember { mutableStateOf("") }
    
    var isUploading by remember { mutableStateOf(true) }
    var isPrinting by remember { mutableStateOf(shouldPrint) }

    if (isPrinting) {
        PrintStatusDialog(
            photoPath = finalPhotoPath,
            onDismissRequest = { /* keep showing */ },
            statusText = printStatus
        )
    }

    // Upload & Print tasks execution
    LaunchedEffect(Unit) {
        // Task 1: Upload to aaPanel
        scope.launch(Dispatchers.IO) {
            try {
                val file = File(finalPhotoPath)
                val requestFile = file.asRequestBody("image/png".toMediaTypeOrNull())
                val photoPart = MultipartBody.Part.createFormData("photo", file.name, requestFile)
                
                val api = NetworkClient.getApi(configManager.backendUrl)
                val response = api.uploadPhotos(
                    photo = photoPart,
                    frameId = if (frameId.isNotEmpty()) frameId else null,
                    eventId = if (eventId.isNotEmpty() && eventId != "general") eventId else null,
                    sessionId = if (sessionId.isNotEmpty()) sessionId else null,
                    packageId = if (packageId.isNotEmpty()) packageId else null
                )
                
                withContext(Dispatchers.Main) {
                    if (response.isSuccessful && response.body() != null && response.body()!!.success) {
                        val body = response.body()!!
                        downloadUrl = body.download_url ?: ""
                        if (downloadUrl.isNotEmpty()) {
                            qrCodeBitmap = generateQrCode(downloadUrl, 400, 400)
                        }
                        uploadStatus = "Foto berhasil diunggah!"
                        isUploading = false
                        
                        if (sessionId.isNotEmpty()) {
                            scope.launch(Dispatchers.IO) {
                                try {
                                    api.completeSession(sessionId)
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                }
                            }
                        }
                    } else {
                        val msg = response.body()?.message ?: "Gagal mengunggah foto."
                        uploadStatus = "Gagal: $msg"
                        isUploading = false
                    }
                }
            } catch (e: Exception) {
                withContext(Dispatchers.Main) {
                    uploadStatus = "Koneksi Gagal: ${e.localizedMessage}"
                    isUploading = false
                }
            }
        }

        // Task 2: Print (if requested)
        if (shouldPrint) {
            scope.launch(Dispatchers.IO) {
                try {
                    val bitmap = BitmapFactory.decodeFile(finalPhotoPath)
                    if (bitmap != null) {
                        val driver: com.example.photobooth.print.PrinterManager = when (configManager.printerType) {
                            "THERMAL" -> ThermalPrinterDriver()
                            "COLOR" -> ColorPrinterDriver()
                            else -> null
                        } ?: throw Exception("Printer tidak siap")
                        
                        val printResult = driver.printBitmap(bitmap, context)
                        withContext(Dispatchers.Main) {
                            when (printResult) {
                                is PrintResult.Success -> {
                                    printStatus = "Selesai mencetak! Silakan ambil foto Anda."
                                    isPrinting = false
                                }
                                is PrintResult.Error -> {
                                    printStatus = "Gagal mencetak: ${printResult.message}"
                                    isPrinting = false
                                }
                            }
                        }
                    } else {
                        withContext(Dispatchers.Main) {
                            printStatus = "Gagal memproses gambar cetak."
                            isPrinting = false
                        }
                    }
                } catch (e: Exception) {
                    withContext(Dispatchers.Main) {
                        printStatus = "Gagal: ${e.localizedMessage}"
                        isPrinting = false
                    }
                }
            }
        }
    }

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Color(0xFF0F0F12))
            .padding(24.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.Center),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(24.dp)
        ) {
            
            // Header Success icon
            Icon(
                imageVector = Icons.Default.CheckCircle,
                contentDescription = "Success",
                tint = Color(0xFFE63946),
                modifier = Modifier.size(64.dp)
            )
            
            Text(
                text = "TERIMA KASIH!",
                color = Color.White,
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 2.sp
            )

            // Status Card
            Card(
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Text("Status Proses:", fontSize = 12.sp, color = Color.Gray, fontWeight = FontWeight.Bold)
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Simpan Digital:", color = Color.White, fontSize = 14.sp)
                        if (isUploading) {
                            CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = Color(0xFFE63946))
                        } else {
                            Text(uploadStatus, color = Color.LightGray, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                    
                    if (shouldPrint) {
                        Divider(color = Color(0xFF2A2A35))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Cetak Fisik:", color = Color.White, fontSize = 14.sp)
                            if (isPrinting) {
                                CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = Color(0xFFE63946))
                            } else {
                                Text(printStatus, color = Color.LightGray, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                }
            }

            // QR Code Scan Card
            if (qrCodeBitmap != null) {
                Card(
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    modifier = Modifier
                        .size(240.dp)
                        .padding(8.dp)
                ) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Image(
                            bitmap = qrCodeBitmap!!.asImageBitmap(),
                            contentDescription = "QR Code",
                            modifier = Modifier.size(200.dp)
                        )
                    }
                }
                
                Text(
                    text = "Pindai QR Code di atas dengan HP Anda\nuntuk mengunduh file foto digital.",
                    color = Color.LightGray,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                    lineHeight = 18.sp
                )
            }
        }

        // Bottom Finish Button
        Button(
            onClick = onFinishClick,
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp)
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
        ) {
            Text("SELESAI", fontWeight = FontWeight.Bold, fontSize = 16.sp)
        }
    }
}

// Helper to generate QR code bitmap via ZXing
private fun generateQrCode(text: String, width: Int, height: Int): Bitmap {
    val bitMatrix: BitMatrix = MultiFormatWriter().encode(text, BarcodeFormat.QR_CODE, width, height)
    val bitMatrixWidth = bitMatrix.width
    val bitMatrixHeight = bitMatrix.height
    val pixels = IntArray(bitMatrixWidth * bitMatrixHeight)
    for (y in 0 until bitMatrixHeight) {
        val offset = y * bitMatrixWidth
        for (x in 0 until bitMatrixWidth) {
            pixels[offset + x] = if (bitMatrix.get(x, y)) android.graphics.Color.BLACK else android.graphics.Color.WHITE
        }
    }
    val bitmap = Bitmap.createBitmap(bitMatrixWidth, bitMatrixHeight, Bitmap.Config.ARGB_8888)
    bitmap.setPixels(pixels, 0, bitMatrixWidth, 0, 0, bitMatrixWidth, bitMatrixHeight)
    return bitmap
}
