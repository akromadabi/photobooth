package com.example.photobooth.ui.admin

import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Paint
import androidx.compose.ui.graphics.Color
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.Image
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.*
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import coil.compose.AsyncImage
import com.example.photobooth.api.HistoryItem
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
import java.io.File
import java.io.FileOutputStream
import java.net.URL
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AdminScreen(
    onBackClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }

    // Tab Selection
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabTitles = listOf("Pengaturan", "Printer", "Riwayat Foto")
    
    // State variables
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
    
    // History states
    val photoHistory = remember { mutableStateListOf<HistoryItem>() }
    var isLoadingHistory by remember { mutableStateOf(false) }
    var selectedHistoryItem by remember { mutableStateOf<HistoryItem?>(null) }
    var qrCodeBitmap by remember { mutableStateOf<Bitmap?>(null) }
    var isReprinting by remember { mutableStateOf(false) }

    // Initial loads
    LaunchedEffect(Unit) {
        // USB
        val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
        usbManager?.deviceList?.values?.forEach { device ->
            usbDevices.add(device)
        }
        
        // Bluetooth
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

    // Refresh history when Tab 2 (Riwayat Foto) is selected
    LaunchedEffect(selectedTab) {
        if (selectedTab == 2) {
            isLoadingHistory = true
            photoHistory.clear()
            scope.launch(Dispatchers.IO) {
                try {
                    val api = NetworkClient.getApi(configManager.backendUrl)
                    val response = api.getPhotoHistory()
                    withContext(Dispatchers.Main) {
                        if (response.isSuccessful && response.body() != null) {
                            photoHistory.addAll(response.body()!!)
                        } else {
                            Toast.makeText(context, "Gagal memuat riwayat server", Toast.LENGTH_SHORT).show()
                        }
                        isLoadingHistory = false
                    }
                } catch (e: Exception) {
                    withContext(Dispatchers.Main) {
                        Toast.makeText(context, "Error koneksi riwayat: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                        isLoadingHistory = false
                    }
                }
            }
        }
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("MENU ADMIN KIOSK", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = Color(0xFF0F0F12),
                    titleContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFF0F0F12),
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Elegant Navigation Tab Row
            TabRow(
                selectedTabIndex = selectedTab,
                containerColor = Color(0xFF18181F),
                contentColor = Color(0xFFE63946),
                indicator = { tabPositions ->
                    TabRowDefaults.Indicator(
                        modifier = Modifier.tabIndicatorOffset(tabPositions[selectedTab]),
                        color = Color(0xFFE63946),
                        height = 3.dp
                    )
                }
            ) {
                tabTitles.forEachIndexed { index, title ->
                    Tab(
                        selected = selectedTab == index,
                        onClick = { selectedTab = index },
                        text = {
                            Text(
                                text = title,
                                fontSize = 14.sp,
                                fontWeight = if (selectedTab == index) FontWeight.Bold else FontWeight.Normal,
                                color = if (selectedTab == index) Color.White else Color.Gray
                            )
                        }
                    )
                }
            }

            // Tab Contents
            Box(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                when (selectedTab) {
                    // TAB 0: Configurations
                    0 -> Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        // Section 1: Server Config
                        AdminCard(title = "Koneksi Backend aaPanel") {
                            OutlinedTextField(
                                value = backendUrl,
                                onValueChange = { backendUrl = it },
                                label = { Text("Base URL API Server") },
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = Color.White,
                                    unfocusedTextColor = Color.White,
                                    focusedBorderColor = Color(0xFFE63946)
                                ),
                                modifier = Modifier.fillMaxWidth()
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            OutlinedTextField(
                                value = adminPin,
                                onValueChange = { if (it.length <= 6) adminPin = it },
                                label = { Text("PIN Akses Kiosk") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = Color.White,
                                    unfocusedTextColor = Color.White,
                                    focusedBorderColor = Color(0xFFE63946)
                                ),
                                modifier = Modifier.fillMaxWidth()
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Button(
                                onClick = {
                                    configManager.backendUrl = backendUrl
                                    configManager.adminPin = adminPin
                                    Toast.makeText(context, "Setelan server disimpan!", Toast.LENGTH_SHORT).show()
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.align(Alignment.End)
                            ) {
                                Text("Simpan Setelan Server", fontWeight = FontWeight.Bold)
                            }
                        }

                        // Section 2: Sync Templates
                        AdminCard(title = "Sinkronisasi Bingkai (Dynamic Sync)") {
                            Text(
                                text = "Mendownload katalog bingkai (.json) dan ornamen bingkai (.png) dari aaPanel agar aplikasi dapat bekerja 100% offline.",
                                color = Color.Gray,
                                fontSize = 12.sp,
                                lineHeight = 16.sp
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            if (isSyncing) {
                                Row(
                                    horizontalArrangement = Arrangement.Center,
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    CircularProgressIndicator(color = Color(0xFFE63946), modifier = Modifier.size(24.dp))
                                    Spacer(modifier = Modifier.width(12.dp))
                                    Text("Menghubungkan & mengunduh...", color = Color.Gray, fontSize = 13.sp)
                                }
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
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Text("SYNC KATALOG SEKARANG", fontWeight = FontWeight.Bold)
                                }
                            }
                        }

                        // Section 3: Capture timings
                        AdminCard(title = "Setelan Sesi Foto") {
                            OutlinedTextField(
                                value = countdownSeconds,
                                onValueChange = { countdownSeconds = it },
                                label = { Text("Durasi Hitung Mundur (Detik)") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = Color.White,
                                    unfocusedTextColor = Color.White,
                                    focusedBorderColor = Color(0xFFE63946)
                                ),
                                modifier = Modifier.fillMaxWidth()
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            OutlinedTextField(
                                value = totalShots,
                                onValueChange = { totalShots = it },
                                label = { Text("Jumlah Jepretan Foto per Sesi") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = Color.White,
                                    unfocusedTextColor = Color.White,
                                    focusedBorderColor = Color(0xFFE63946)
                                ),
                                modifier = Modifier.fillMaxWidth()
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Button(
                                onClick = {
                                    val cd = countdownSeconds.toIntOrNull() ?: 5
                                    val ts = totalShots.toIntOrNull() ?: 4
                                    configManager.countdownSeconds = cd
                                    configManager.totalShots = ts
                                    Toast.makeText(context, "Setelan sesi disimpan!", Toast.LENGTH_SHORT).show()
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.align(Alignment.End)
                            ) {
                                Text("Simpan Setelan Sesi", fontWeight = FontWeight.Bold)
                            }
                        }
                    }

                    // TAB 1: Printer Config
                    1 -> Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        AdminCard(title = "Konfigurasi Printer") {
                            Text("Tipe Driver Printer Terhubung:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                            
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    RadioButton(
                                        selected = printerType == "NONE",
                                        onClick = { printerType = "NONE"; configManager.printerType = "NONE" },
                                        colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                    )
                                    Text("NONE", color = Color.White)
                                }
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    RadioButton(
                                        selected = printerType == "THERMAL",
                                        onClick = { printerType = "THERMAL"; configManager.printerType = "THERMAL" },
                                        colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                    )
                                    Text("THERMAL (XP-420B)", color = Color.White)
                                }
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    RadioButton(
                                        selected = printerType == "COLOR",
                                        onClick = { printerType = "COLOR"; configManager.printerType = "COLOR" },
                                        colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                    )
                                    Text("COLOR (PDF)", color = Color.White)
                                }
                            }

                            if (printerType == "THERMAL") {
                                HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(vertical = 12.dp))
                                Text("Pilih Port Printer Thermal Terdeteksi:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                
                                // USB list
                                if (usbDevices.isNotEmpty()) {
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text("Koneksi USB OTG:", fontSize = 12.sp, color = Color.Gray, fontWeight = FontWeight.Bold)
                                    usbDevices.forEach { device ->
                                        val deviceAddr = "USB:${device.vendorId},${device.productId}"
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Xprinter Label Printer (VID:${device.vendorId} PID:${device.productId})", color = Color.White, fontSize = 13.sp)
                                            RadioButton(
                                                selected = printerAddress == deviceAddr,
                                                onClick = {
                                                    printerAddress = deviceAddr
                                                    configManager.printerAddress = deviceAddr
                                                },
                                                colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                            )
                                        }
                                    }
                                }

                                // Bluetooth list
                                if (bluetoothDevices.isNotEmpty()) {
                                    Spacer(modifier = Modifier.height(12.dp))
                                    Text("Koneksi Bluetooth Paired:", fontSize = 12.sp, color = Color.Gray, fontWeight = FontWeight.Bold)
                                    bluetoothDevices.forEach { (name, mac) ->
                                        val deviceAddr = "BT:$mac"
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("$name ($mac)", color = Color.White, fontSize = 13.sp)
                                            RadioButton(
                                                selected = printerAddress == deviceAddr,
                                                onClick = {
                                                    printerAddress = deviceAddr
                                                    configManager.printerAddress = deviceAddr
                                                },
                                                colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                            )
                                        }
                                    }
                                }
                                
                                if (usbDevices.isEmpty() && bluetoothDevices.isEmpty()) {
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text("Tidak ada printer terdeteksi. Colok kabel USB OTG printer ke tablet atau hubungkan Bluetooth printer ke tablet.", color = Color.Gray, fontSize = 12.sp)
                                }
                            }

                            Spacer(modifier = Modifier.height(16.dp))
                            
                            if (isTestingPrint) {
                                Row(
                                    horizontalArrangement = Arrangement.Center,
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    CircularProgressIndicator(color = Color(0xFFE63946))
                                }
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
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Text("UJI CETAK STRIP COBA (TEST PRINT)", fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }

                    // TAB 2: Photo History Grid
                    2 -> {
                        if (isLoadingHistory) {
                            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator(color = Color(0xFFE63946))
                            }
                        } else if (photoHistory.isEmpty()) {
                            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                                Text("Belum ada foto yang diunggah ke server.", color = Color.Gray)
                            }
                        } else {
                            LazyVerticalGrid(
                                columns = GridCells.Fixed(3),
                                horizontalArrangement = Arrangement.spacedBy(12.dp),
                                verticalArrangement = Arrangement.spacedBy(12.dp),
                                modifier = Modifier.fillMaxSize()
                            ) {
                                items(photoHistory) { item ->
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .aspectRatio(0.45f)
                                            .clip(RoundedCornerShape(12.dp))
                                            .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                            .background(Color(0xFF18181F))
                                            .clickable { selectedHistoryItem = item }
                                    ) {
                                        AsyncImage(
                                            model = item.photoUrl,
                                            contentDescription = "History Photo",
                                            modifier = Modifier.fillMaxSize()
                                        )
                                        
                                        // Show time taken on overlay bottom
                                        val timeStr = remember(item.timestamp) {
                                            val sdf = SimpleDateFormat("dd MMM, HH:mm", Locale.getDefault())
                                            sdf.format(Date(item.timestamp * 1000))
                                        }
                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .align(Alignment.BottomCenter)
                                                .background(Color.Black.copy(alpha = 0.6f))
                                                .padding(4.dp)
                                        ) {
                                            Text(
                                                text = timeStr,
                                                color = Color.White,
                                                fontSize = 9.sp,
                                                fontWeight = FontWeight.Bold,
                                                modifier = Modifier.fillMaxWidth(),
                                                textAlign = TextAlign.Center
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Detail History Dialog
        selectedHistoryItem?.let { item ->
            // Generate QR Code bitmap for the history download url
            LaunchedEffect(item.id) {
                scope.launch(Dispatchers.IO) {
                    qrCodeBitmap = generateQrCode(item.downloadUrl, 300, 300)
                }
            }
            
            Dialog(onDismissRequest = { 
                selectedHistoryItem = null
                qrCodeBitmap = null
            }) {
                Card(
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                    border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(8.dp)
                ) {
                    Column(
                        modifier = Modifier
                            .padding(20.dp)
                            .verticalScroll(rememberScrollState()),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(text = "Rincian Foto Kiosk", fontWeight = FontWeight.Bold, color = Color.White, fontSize = 18.sp)
                        
                        val dateStr = remember(item.timestamp) {
                            val sdf = SimpleDateFormat("dd MMMM yyyy, HH:mm:ss", Locale.getDefault())
                            sdf.format(Date(item.timestamp * 1000))
                        }
                        Text(text = "Waktu Jepret: $dateStr", color = Color.Gray, fontSize = 12.sp)

                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(320.dp),
                            horizontalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            // Large preview of the photo strip
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .fillMaxHeight()
                                    .clip(RoundedCornerShape(12.dp))
                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                    .background(Color.Black),
                                contentAlignment = Alignment.Center
                            ) {
                                AsyncImage(
                                    model = item.photoUrl,
                                    contentDescription = "History Detail Strip",
                                    modifier = Modifier.fillMaxHeight()
                                )
                            }
                            
                            // QR code to download
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .fillMaxHeight(),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.Center
                                ) {
                                    if (qrCodeBitmap != null) {
                                        Box(
                                            modifier = Modifier
                                                .size(150.dp)
                                                .clip(RoundedCornerShape(12.dp))
                                                .background(Color.White)
                                                .padding(6.dp),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Image(
                                                bitmap = qrCodeBitmap!!.asImageBitmap(),
                                                contentDescription = "QR Code share"
                                            )
                                        }
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(text = "Scan QR Code untuk\nDownload Ulang", color = Color.Gray, fontSize = 10.sp, textAlign = TextAlign.Center)
                                    } else {
                                        CircularProgressIndicator(color = Color(0xFFE63946))
                                    }
                                }
                            }
                        }

                        // Options buttons
                        Column(
                            modifier = Modifier.fillMaxWidth(),
                            verticalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            if (isReprinting) {
                                Row(
                                    horizontalArrangement = Arrangement.Center,
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    CircularProgressIndicator(color = Color(0xFFE63946), modifier = Modifier.size(24.dp))
                                    Spacer(modifier = Modifier.width(12.dp))
                                    Text("Mengunduh & mengirim cetak...", color = Color.Gray, fontSize = 13.sp)
                                }
                            } else {
                                Button(
                                    onClick = {
                                        isReprinting = true
                                        scope.launch {
                                            val successMsg = runReprintFromHistory(context, item.photoUrl, configManager)
                                            isReprinting = false
                                            Toast.makeText(context, successMsg, Toast.LENGTH_LONG).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                    shape = RoundedCornerShape(12.dp),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Text("CETAK ULANG FOTO (REPRINT)", fontWeight = FontWeight.Bold)
                                }
                            }
                            
                            OutlinedButton(
                                onClick = { 
                                    selectedHistoryItem = null
                                    qrCodeBitmap = null
                                },
                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text("Tutup")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun AdminCard(
    title: String,
    content: @Composable ColumnScope.() -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
        border = BorderStroke(1.dp, Color(0xFF2A2A35))
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Text(
                text = title,
                color = Color.White,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold
            )
            content()
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

// Reprint from history: Download image and send to printer driver
private suspend fun runReprintFromHistory(context: Context, photoUrl: String, configManager: ConfigManager): String {
    return withContext(Dispatchers.IO) {
        try {
            val driver: com.example.photobooth.print.PrinterManager = when (configManager.printerType) {
                "THERMAL" -> ThermalPrinterDriver()
                "COLOR" -> ColorPrinterDriver()
                else -> return@withContext "Tipe printer aktif: Tidak Ada"
            }
            
            // Download the photo strip bitmap from server
            val url = URL(photoUrl)
            val connection = url.openConnection()
            connection.connectTimeout = 10000
            connection.readTimeout = 15000
            
            val inputStream = connection.getInputStream()
            val bitmap = BitmapFactory.decodeStream(inputStream)
            inputStream.close()
            
            if (bitmap == null) {
                return@withContext "Gagal mengunduh berkas gambar untuk cetak."
            }
            
            val result = driver.printBitmap(bitmap, context)
            
            when (result) {
                is PrintResult.Success -> "Cetak ulang berhasil terkirim ke printer!"
                is PrintResult.Error -> "Gagal mencetak ulang: ${result.message}"
            }
        } catch (e: Exception) {
            "Kesalahan cetak ulang: ${e.localizedMessage}"
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
