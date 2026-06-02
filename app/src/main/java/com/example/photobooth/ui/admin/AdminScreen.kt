package com.example.photobooth.ui.admin

import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.print.ColorPrinterDriver
import com.example.photobooth.print.PrintResult
import com.example.photobooth.print.ThermalPrinterDriver
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.ResponseBody
import java.io.File
import java.io.FileOutputStream
import java.net.URL

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AdminScreen(
    onBackClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }

    // State Variables
    var backendUrl by remember { mutableStateOf(configManager.backendUrl) }
    var adminPin by remember { mutableStateOf(configManager.adminPin) }
    var countdownSeconds by remember { mutableStateOf(configManager.countdownSeconds.toString()) }
    var totalShots by remember { mutableStateOf(configManager.totalShots.toString()) }
    var printerType by remember { mutableStateOf(configManager.printerType) }
    var printerAddress by remember { mutableStateOf(configManager.printerAddress) }
    
    var isSyncing by remember { mutableStateOf(false) }
    var isTestingPrint by remember { mutableStateOf(false) }
    
    // Scan lists
    val usbDevices = remember { mutableStateListOf<UsbDevice>() }
    val bluetoothDevices = remember { mutableStateListOf<Pair<String, String>>() } // Name, MAC

    // Fetch lists
    LaunchedEffect(Unit) {
        // USB devices
        val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
        usbManager?.deviceList?.values?.forEach { device ->
            usbDevices.add(device)
        }
        
        // Bluetooth paired devices
        try {
            val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
            val adapter = bluetoothManager?.adapter
            if (adapter != null && adapter.isEnabled) {
                @SuppressLint("MissingPermission")
                adapter.bondedDevices.forEach { device ->
                    bluetoothDevices.add(Pair(device.name ?: "Unknown Printer", device.address))
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Menu Admin & Konfigurasi", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surfaceColorAtElevation(3.dp)
                )
            )
        },
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp)
        ) {
            
            // Server aaPanel Settings Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Text("Konfigurasi Server & Hosting", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                    
                    OutlinedTextField(
                        value = backendUrl,
                        onValueChange = { backendUrl = it },
                        label = { Text("Base URL aaPanel API") },
                        placeholder = { Text("https://photobooth.domainanda.com/") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    OutlinedTextField(
                        value = adminPin,
                        onValueChange = { if (it.length <= 6) adminPin = it },
                        label = { Text("PIN Akses Admin") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    Button(
                        onClick = {
                            configManager.backendUrl = backendUrl
                            configManager.adminPin = adminPin
                            Toast.makeText(context, "Server config disimpan!", Toast.LENGTH_SHORT).show()
                        },
                        modifier = Modifier.align(Alignment.End)
                    ) {
                        Text("Simpan Konfigurasi Server")
                    }
                }
            }

            // Sync Frames Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text("Sinkronisasi Bingkai (Frames)", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary, modifier = Modifier.align(Alignment.Start))
                    
                    Text(
                        text = "Mendownload daftar bingkai dan gambar `.png` dari hosting aaPanel Anda sehingga dapat digunakan secara offline.",
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.align(Alignment.Start)
                    )
                    
                    if (isSyncing) {
                        CircularProgressIndicator()
                        Text("Sedang menyinkronkan bingkai...", fontSize = 12.sp)
                    } else {
                        Button(
                            onClick = {
                                isSyncing = true
                                scope.launch(Dispatchers.IO) {
                                    val result = syncFramesFromBackend(context, backendUrl, configManager)
                                    withContext(Dispatchers.Main) {
                                        isSyncing = false
                                        Toast.makeText(context, result, Toast.LENGTH_LONG).show()
                                    }
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.secondary),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text("SINKRONISASI BINGKAI SEKARANG")
                        }
                    }
                }
            }

            // App Behavior Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Text("Pengaturan Sesi Foto", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                    
                    OutlinedTextField(
                        value = countdownSeconds,
                        onValueChange = { countdownSeconds = it },
                        label = { Text("Waktu Hitung Mundur (detik)") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.fillMaxWidth()
                    )

                    OutlinedTextField(
                        value = totalShots,
                        onValueChange = { totalShots = it },
                        label = { Text("Jumlah Jepretan Foto per Sesi") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    Button(
                        onClick = {
                            val cd = countdownSeconds.toIntOrNull() ?: 5
                            val ts = totalShots.toIntOrNull() ?: 4
                            configManager.countdownSeconds = cd
                            configManager.totalShots = ts
                            Toast.makeText(context, "Pengaturan sesi disimpan!", Toast.LENGTH_SHORT).show()
                        },
                        modifier = Modifier.align(Alignment.End)
                    ) {
                        Text("Simpan Pengaturan Sesi")
                    }
                }
            }

            // Printer Configuration Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Text("Konfigurasi Pencetakan (Printer)", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                    
                    Text("Pilih Tipe Printer:", fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            RadioButton(selected = printerType == "NONE", onClick = { printerType = "NONE"; configManager.printerType = "NONE" })
                            Text("Tidak Ada")
                        }
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            RadioButton(selected = printerType == "THERMAL", onClick = { printerType = "THERMAL"; configManager.printerType = "THERMAL" })
                            Text("Thermal (XP-420B)")
                        }
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            RadioButton(selected = printerType == "COLOR", onClick = { printerType = "COLOR"; configManager.printerType = "COLOR" })
                            Text("Standard Color")
                        }
                    }

                    if (printerType == "THERMAL") {
                        Divider()
                        Text("Pilih Port/Device Thermal Printer:", fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        
                        // USB List
                        if (usbDevices.isNotEmpty()) {
                            Text("USB Devices detected:", fontSize = 12.sp, color = Color.Gray)
                            usbDevices.forEach { device ->
                                val deviceAddr = "USB:${device.vendorId},${device.productId}"
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("USB Printer (VID:${device.vendorId} PID:${device.productId})", fontSize = 13.sp)
                                    RadioButton(
                                        selected = printerAddress == deviceAddr,
                                        onClick = {
                                            printerAddress = deviceAddr
                                            configManager.printerAddress = deviceAddr
                                        }
                                    )
                                }
                            }
                        }

                        // Bluetooth List
                        if (bluetoothDevices.isNotEmpty()) {
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("Bluetooth Paired Devices:", fontSize = 12.sp, color = Color.Gray)
                            bluetoothDevices.forEach { (name, mac) ->
                                val deviceAddr = "BT:$mac"
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("$name ($mac)", fontSize = 13.sp)
                                    RadioButton(
                                        selected = printerAddress == deviceAddr,
                                        onClick = {
                                            printerAddress = deviceAddr
                                            configManager.printerAddress = deviceAddr
                                        }
                                    )
                                }
                            }
                        }
                        
                        if (usbDevices.isEmpty() && bluetoothDevices.isEmpty()) {
                            Text("Hubungkan printer USB via kabel OTG atau sandingkan printer via Bluetooth.", fontSize = 12.sp, color = Color.Gray)
                        }
                    }

                    Spacer(modifier = Modifier.height(8.dp))
                    
                    if (isTestingPrint) {
                        CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
                    } else {
                        Button(
                            onClick = {
                                isTestingPrint = true
                                scope.launch {
                                    val success = testPrintJob(context, configManager)
                                    isTestingPrint = false
                                    Toast.makeText(context, success, Toast.LENGTH_LONG).show()
                                }
                            },
                            enabled = printerType != "NONE",
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text("TEST CETAK SEKARANG")
                        }
                    }
                }
            }
        }
    }
}

// Background dynamic frames syncing function
private suspend fun syncFramesFromBackend(context: Context, baseUrl: String, configManager: ConfigManager): String {
    return try {
        val finalUrl = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
        val api = NetworkClient.getApi(finalUrl)
        
        // Fetch JSON configuration
        val response = api.getFrameConfig("frames/config.json")
        if (!response.isSuccessful || response.body() == null) {
            return "Gagal mengunduh config.json: Code ${response.code()}"
        }
        
        val frameConfig = response.body()!!
        
        // Save config JSON to SharedPreferences
        val gson = com.google.gson.Gson()
        val jsonStr = gson.toJson(frameConfig)
        configManager.syncedFramesJson = jsonStr
        
        // Download all frame files
        val framesDir = File(context.cacheDir, "frames")
        if (!framesDir.exists()) framesDir.mkdirs()
        
        for (frame in frameConfig.frames) {
            val relativePath = frame.imageUrl // e.g. "frames/classic_strip_black.png"
            val fileUrl = URL("$finalUrl$relativePath")
            val connection = withContext(Dispatchers.IO) { fileUrl.openConnection() }
            
            val localFile = File(framesDir, frame.id + ".png")
            
            withContext(Dispatchers.IO) {
                connection.getInputStream().use { input ->
                    FileOutputStream(localFile).use { output ->
                        input.copyTo(output)
                    }
                }
            }
        }
        
        "Sinkronisasi berhasil! Berhasil mendownload ${frameConfig.frames.size} bingkai secara offline."
    } catch (e: Exception) {
        "Kesalahan sinkronisasi: ${e.localizedMessage}"
    }
}

// Run test print job
private suspend fun testPrintJob(context: Context, configManager: ConfigManager): String {
    // Generate a simple test Bitmap card (width 600, height 800)
    val width = 600
    val height = 800
    val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(bitmap)
    val paint = Paint()
    
    // Clear canvas with white background
    canvas.drawColor(android.graphics.Color.WHITE)
    
    // Draw outer box
    paint.color = android.graphics.Color.BLACK
    paint.strokeWidth = 10f
    paint.style = Paint.Style.STROKE
    canvas.drawRect(20f, 20f, width - 20f, height - 20f, paint)
    
    // Draw text
    paint.strokeWidth = 0f
    paint.style = Paint.Style.FILL
    paint.textSize = 40f
    paint.isAntiAlias = true
    canvas.drawText("CREATIVE STUDIO", 120f, 150f, paint)
    
    paint.textSize = 30f
    canvas.drawText("Test Print Thermal XP-420B", 100f, 220f, paint)
    
    // Draw checkerboard patterns to test printer density
    paint.color = android.graphics.Color.BLACK
    for (i in 0..5) {
        val yPos = 300f + i * 50f
        for (j in 0..11) {
            val xPos = 50f + j * 40f
            if ((i + j) % 2 == 0) {
                canvas.drawRect(xPos, yPos, xPos + 40f, yPos + 50f, paint)
            }
        }
    }
    
    paint.color = android.graphics.Color.BLACK
    paint.textSize = 25f
    canvas.drawText("Tingkat Hitam-Putih Floyd-Steinberg", 60f, 660f, paint)
    canvas.drawText("Selesai! Printer siap digunakan.", 100f, 720f, paint)

    val driver: com.example.photobooth.print.PrinterManager = when (configManager.printerType) {
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
