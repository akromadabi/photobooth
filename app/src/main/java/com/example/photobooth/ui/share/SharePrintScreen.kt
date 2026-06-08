package com.example.photobooth.ui.share

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.print.ColorPrinterDriver
import com.example.photobooth.print.PrintResult
import com.example.photobooth.print.ThermalPrinterDriver
import com.google.zxing.BarcodeFormat
import com.google.zxing.MultiFormatWriter
import com.google.zxing.common.BitMatrix
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
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
    packageId: String = "",
    characterId: String = ""
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    val finalSessionId = remember {
        if (sessionId.isNotEmpty()) sessionId else java.util.UUID.randomUUID().toString().replace("-", "").take(16)
    }
    
    var uploadStatus by remember { 
        mutableStateOf(
            if (characterId.isNotEmpty()) "Menghubungi AI Generator..." else "Mengunggah foto ke server..."
        ) 
    }
    var printStatus by remember { mutableStateOf(if (shouldPrint) "Menyiapkan pencetakan..." else "Cetak dinonaktifkan") }
    
    var qrCodeBitmap by remember { mutableStateOf<Bitmap?>(null) }
    var downloadUrl by remember { mutableStateOf("") }
    
    var isUploading by remember { mutableStateOf(true) }
    var isPrinting by remember { mutableStateOf(shouldPrint && packageId.isNotEmpty()) }
    var showManualPrintOption by remember { mutableStateOf(shouldPrint && packageId.isEmpty()) }
    var manualPrintFlow by remember { mutableStateOf<String?>(null) }
    
    var autoDismissSeconds by remember { mutableIntStateOf(15) }

    LaunchedEffect(isUploading, isPrinting, showManualPrintOption) {
        if (!isUploading && !isPrinting && !showManualPrintOption) {
            autoDismissSeconds = 15
            while (autoDismissSeconds > 0) {
                delay(1000)
                autoDismissSeconds--
            }
            onFinishClick()
        }
    }

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
                
                withContext(Dispatchers.Main) {
                    uploadStatus = if (characterId.isNotEmpty()) {
                        "Sedang memproses AI Face Swap (3-5 detik)..."
                    } else {
                        "Mengunggah foto ke server..."
                    }
                }
                
                val api = NetworkClient.getApi(configManager.backendUrl)
                val response = if (characterId.isNotEmpty()) {
                    api.generateAiPhoto(
                        photo = photoPart,
                        characterId = characterId,
                        eventId = if (eventId.isNotEmpty() && eventId != "general") eventId else null,
                        sessionId = finalSessionId,
                        packageId = if (packageId.isNotEmpty()) packageId else null
                    )
                } else {
                    api.uploadPhotos(
                        photo = photoPart,
                        frameId = if (frameId.isNotEmpty()) frameId else null,
                        eventId = if (eventId.isNotEmpty() && eventId != "general") eventId else null,
                        sessionId = finalSessionId,
                        packageId = if (packageId.isNotEmpty()) packageId else null
                    )
                }
                
                withContext(Dispatchers.Main) {
                    if (response.isSuccessful && response.body() != null && response.body()!!.success) {
                        val body = response.body()!!
                        downloadUrl = body.download_url ?: ""
                        if (downloadUrl.isNotEmpty()) {
                            qrCodeBitmap = generateQrCode(downloadUrl, 400, 400)
                        }
                        uploadStatus = if (characterId.isNotEmpty()) "Wajah berhasil diproses AI!" else "Foto berhasil diunggah!"
                        isUploading = false
                        
                        scope.launch(Dispatchers.IO) {
                            try {
                                api.completeSession(finalSessionId)
                            } catch (e: Exception) {
                                e.printStackTrace()
                            }
                        }
                    } else {
                        val msg = response.body()?.message ?: "Gagal memproses foto."
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

        // Task 2: Print (if requested and package is selected)
        if (shouldPrint && packageId.isNotEmpty()) {
            scope.launch(Dispatchers.IO) {
                try {
                    val api = NetworkClient.getApi(configManager.backendUrl)
                    var activePackage: com.example.photobooth.api.PackageDto? = null
                    try {
                        val pkgRes = api.getPackages()
                        if (pkgRes.isSuccessful && pkgRes.body() != null) {
                            activePackage = pkgRes.body()!!.find { it.id == packageId }
                        }
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }

                    var bitmap = BitmapFactory.decodeFile(finalPhotoPath)
                    if (bitmap != null) {
                        val flow = activePackage?.printFlow ?: "COLOR_PRINT"
                        if (flow == "ID_CARD") {
                            val verifyUrl = "${configManager.backendUrl}/license.php?id=$finalSessionId"
                            val charName = characterId.replace('_', ' ').replaceFirstChar { 
                                if (it.isLowerCase()) it.titlecase() else it.toString() 
                            }
                            bitmap = generateIdCardBitmap(bitmap, finalSessionId, verifyUrl, charName)
                        } else if (flow == "RECEIPT") {
                            // Standard Receipt sizing
                            val printWidthMm = activePackage?.printWidthMm ?: 58
                            val printHeightMm = activePackage?.printHeightMm ?: 200
                            // Map mm to pixels (e.g. 10 pixels per mm for printable output)
                            val targetW = printWidthMm * 10
                            val targetH = printHeightMm * 10
                            bitmap = Bitmap.createScaledBitmap(bitmap, targetW, targetH, true)
                        }

                        val printerTypeToUse = when (configManager.printerType) {
                            "AUTO" -> if (flow == "RECEIPT") "THERMAL" else "COLOR"
                            else -> configManager.printerType
                        }

                        val driver: com.example.photobooth.print.PrinterManager = when (printerTypeToUse) {
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

    // Task 3: Manual Print Flow Trigger (if manual kiosk session)
    LaunchedEffect(manualPrintFlow) {
        if (shouldPrint && packageId.isEmpty() && manualPrintFlow != null) {
            isPrinting = true
            printStatus = "Menyiapkan pencetakan..."
            scope.launch(Dispatchers.IO) {
                try {
                    var bitmap = BitmapFactory.decodeFile(finalPhotoPath)
                    if (bitmap != null) {
                        val flow = manualPrintFlow!!
                        if (flow == "RECEIPT") {
                            // Manual receipt size fallback
                            val printWidthMm = 58
                            val printHeightMm = 200
                            val targetW = printWidthMm * 10
                            val targetH = printHeightMm * 10
                            bitmap = Bitmap.createScaledBitmap(bitmap, targetW, targetH, true)
                        }

                        val printerTypeToUse = when (configManager.printerType) {
                            "AUTO" -> if (flow == "RECEIPT") "THERMAL" else "COLOR"
                            else -> configManager.printerType
                        }

                        val driver: com.example.photobooth.print.PrinterManager = when (printerTypeToUse) {
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

    val configuration = LocalConfiguration.current
    val isLandscape = configuration.orientation == android.content.res.Configuration.ORIENTATION_LANDSCAPE

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Color(0xFF0F0F12))
            .padding(16.dp)
    ) {
        // Direct Close Button in the top right corner
        IconButton(
            onClick = onFinishClick,
            modifier = Modifier
                .statusBarsPadding()
                .align(Alignment.TopEnd)
        ) {
            Icon(
                imageVector = Icons.Default.Close,
                contentDescription = "Lewati",
                tint = Color.White.copy(alpha = 0.6f)
            )
        }

        if (isLandscape) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.Center)
                    .padding(bottom = 68.dp, top = 20.dp),
                horizontalArrangement = Arrangement.spacedBy(24.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Left Column: Success Icon, Thank you, Status Card
                Column(
                    modifier = Modifier.weight(1.2f),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.CheckCircle,
                        contentDescription = "Success",
                        tint = Color(0xFFE63946),
                        modifier = Modifier.size(48.dp)
                    )
                    
                    Text(
                        text = "TERIMA KASIH!",
                        color = Color.White,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 2.sp
                    )

                    // Status Card
                    Card(
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text("Status Proses:", fontSize = 11.sp, color = Color.Gray, fontWeight = FontWeight.Bold)
                            
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("Simpan Digital:", color = Color.White, fontSize = 13.sp)
                                if (isUploading) {
                                    CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Color(0xFFE63946))
                                } else {
                                    Text(uploadStatus, color = Color.LightGray, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                            
                            if (shouldPrint) {
                                Divider(color = Color(0xFF2A2A35))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Cetak Fisik:", color = Color.White, fontSize = 13.sp)
                                    if (isPrinting) {
                                        CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Color(0xFFE63946))
                                    } else {
                                        Text(printStatus, color = Color.LightGray, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                    }
                                }
                            }
                        }
                    }
                }

                // Right Column: QR Code, Instructions
                if (qrCodeBitmap != null) {
                    Column(
                        modifier = Modifier.weight(1f),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Card(
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = Color.White),
                            modifier = Modifier.size(150.dp)
                        ) {
                            Box(
                                modifier = Modifier.fillMaxSize(),
                                contentAlignment = Alignment.Center
                            ) {
                                Image(
                                    bitmap = qrCodeBitmap!!.asImageBitmap(),
                                    contentDescription = "QR Code",
                                    modifier = Modifier.size(130.dp)
                                )
                            }
                        }
                        
                        Text(
                            text = "Pindai QR Code di atas dengan HP Anda untuk mengunduh foto digital.",
                            color = Color.LightGray,
                            fontSize = 11.sp,
                            textAlign = TextAlign.Center,
                            lineHeight = 15.sp
                        )
                    }
                }
            }
        } else {
            // Portrait Layout
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.Center)
                    .padding(bottom = 68.dp),
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
            val buttonText = if (!isUploading && !isPrinting && !showManualPrintOption) {
                "SELESAI (${autoDismissSeconds}s)"
            } else {
                "SELESAI"
            }
            Text(buttonText, fontWeight = FontWeight.Bold, fontSize = 16.sp)
        }
    }

    // Manual print flow selection dialog (if manual session without package)
    if (shouldPrint && packageId.isEmpty() && showManualPrintOption) {
        Dialog(onDismissRequest = { 
            showManualPrintOption = false 
            isPrinting = false
        }) {
            Card(
                shape = RoundedCornerShape(24.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(18.dp)
                ) {
                    Text("Pilih Tipe Cetakan", fontWeight = FontWeight.Bold, color = Color.White, fontSize = 18.sp)
                    
                    Text(
                        text = "Kiosk berjalan dalam sesi manual (tanpa paket). Silakan pilih printer tujuan cetak Anda:",
                        color = Color.Gray,
                        fontSize = 13.sp,
                        textAlign = TextAlign.Center,
                        lineHeight = 18.sp
                    )
                    
                    Button(
                        onClick = {
                            showManualPrintOption = false
                            manualPrintFlow = "COLOR_PRINT"
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                    ) {
                        Text("CETAK FOTO WARNA (EPSON)", fontWeight = FontWeight.Bold, color = Color.White)
                    }
                    
                    Button(
                        onClick = {
                            showManualPrintOption = false
                            manualPrintFlow = "RECEIPT"
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                    ) {
                        Text("CETAK STRUK THERMAL (XPRINTER)", fontWeight = FontWeight.Bold, color = Color.White)
                    }
                    
                    OutlinedButton(
                        onClick = {
                            showManualPrintOption = false
                            isPrinting = false
                        },
                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                    ) {
                        Text("LEWATI TANPA CETAK", color = Color.White)
                    }
                }
            }
        }
    }
}

// Helper to generate ID Card layout bitmap dynamically
private fun generateIdCardBitmap(
    photo: Bitmap,
    sessionId: String,
    verifyUrl: String,
    characterName: String
): Bitmap {
    // Standard CR80 aspect ratio: 54x86 -> 600 x 956 pixels
    val cardW = 600
    val cardH = 956
    val bitmap = Bitmap.createBitmap(cardW, cardH, Bitmap.Config.ARGB_8888)
    val canvas = android.graphics.Canvas(bitmap)
    val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG)

    // Draw elegant dark gradient background
    val gradient = android.graphics.LinearGradient(
        0f, 0f, 0f, cardH.toFloat(),
        android.graphics.Color.parseColor("#13131c"),
        android.graphics.Color.parseColor("#050508"),
        android.graphics.Shader.TileMode.CLAMP
    )
    paint.shader = gradient
    canvas.drawRect(0f, 0f, cardW.toFloat(), cardH.toFloat(), paint)
    paint.shader = null // reset

    // Outer glowing borders
    paint.style = android.graphics.Paint.Style.STROKE
    paint.strokeWidth = 10f
    paint.color = android.graphics.Color.parseColor("#E63946") // red border
    canvas.drawRect(5f, 5f, cardW.toFloat() - 5f, cardH.toFloat() - 5f, paint)

    paint.strokeWidth = 2f
    paint.color = android.graphics.Color.parseColor("#F7B801") // gold inner border
    canvas.drawRect(12f, 12f, cardW.toFloat() - 12f, cardH.toFloat() - 12f, paint)

    // Draw Header Texts
    paint.style = android.graphics.Paint.Style.FILL
    paint.textAlign = android.graphics.Paint.Align.CENTER
    
    paint.color = android.graphics.Color.WHITE
    paint.textSize = 28f
    paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
    canvas.drawText("CREATIVE STUDIO", cardW / 2f, 60f, paint)

    paint.color = android.graphics.Color.parseColor("#F7B801")
    paint.textSize = 14f
    paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
    canvas.drawText("OFFICIAL CHARACTER LICENSE CARD", cardW / 2f, 85f, paint)

    // Draw Photo Frame
    paint.color = android.graphics.Color.parseColor("#1E1E26")
    val frameL = 100f
    val frameT = 125f
    val frameR = 500f
    val frameB = 625f
    canvas.drawRect(frameL, frameT, frameR, frameB, paint)

    // Draw user's generated photo inside the frame
    val srcRect = android.graphics.Rect(0, 0, photo.width, photo.height)
    val destRect = android.graphics.Rect(frameL.toInt() + 4, frameT.toInt() + 4, frameR.toInt() - 4, frameB.toInt() - 4)
    canvas.drawBitmap(photo, srcRect, destRect, paint)

    // Character Name
    paint.color = android.graphics.Color.WHITE
    paint.textSize = 32f
    paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
    canvas.drawText(characterName.uppercase(), cardW / 2f, 675f, paint)

    // License Serial Key
    paint.color = android.graphics.Color.parseColor("#F7B801")
    paint.textSize = 18f
    paint.typeface = android.graphics.Typeface.MONOSPACE
    val sessionPart = sessionId.take(4).uppercase()
    val hashPart = Integer.toHexString(sessionId.hashCode()).take(4).uppercase()
    val licenseKey = "LIC-$sessionPart-$hashPart"
    canvas.drawText(licenseKey, cardW / 2f, 715f, paint)

    // Verification small note
    paint.color = android.graphics.Color.parseColor("#8D8D9F")
    paint.textSize = 12f
    paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.NORMAL)
    canvas.drawText("Verified AI generated character by Creative Studio Kiosk", cardW / 2f, 740f, paint)

    // Generate and Draw License Page QR Code
    try {
        val qrSize = 130
        val qrBmp = generateQrCode(verifyUrl, qrSize, qrSize)
        canvas.drawBitmap(qrBmp, (cardW - qrSize) / 2f, 765f, paint)
    } catch (e: java.lang.Exception) {
        e.printStackTrace()
    }

    // Draw verified badge status text
    paint.color = android.graphics.Color.parseColor("#39FF14") // neon green
    paint.textSize = 14f
    paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
    canvas.drawText("✓ VERIFIED LICENSE", cardW / 2f, 925f, paint)

    return bitmap
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
