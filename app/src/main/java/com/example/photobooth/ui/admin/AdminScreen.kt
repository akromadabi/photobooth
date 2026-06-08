package com.example.photobooth.ui.admin

import android.annotation.SuppressLint
import android.content.ContentValues
import android.provider.MediaStore
import java.io.OutputStream
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
import androidx.compose.material.icons.filled.Check
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
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.LinearEasing
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Brush

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AdminScreen(
    onBackClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }

    val syncedFramesCount = remember(configManager.syncedFramesJson) {
        try {
            val config = com.google.gson.Gson().fromJson(configManager.syncedFramesJson, com.example.photobooth.data.FrameConfig::class.java)
            config?.frames?.size ?: 0
        } catch (e: Exception) {
            0
        }
    }

    // Tab Selection
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabTitles = listOf("Dashboard", "Pengaturan", "Printer", "Riwayat Foto")
    var refreshTrigger by remember { mutableIntStateOf(0) }
    
    // State variables
    var backendUrl by remember { mutableStateOf(configManager.backendUrl) }
    var adminPin by remember { mutableStateOf(configManager.adminPin) }
    var countdownSeconds by remember { mutableStateOf(configManager.countdownSeconds.toString()) }
    var totalShots by remember { mutableStateOf(configManager.totalShots.toString()) }
    var printerType by remember { mutableStateOf(configManager.printerType) }
    var printerAddress by remember { mutableStateOf(configManager.printerAddress) }
    var thermalMode by remember { mutableStateOf(configManager.thermalMode) }
    var printerPaperWidth by remember { mutableStateOf(configManager.printerPaperWidth) }
    var printDensity by remember { mutableStateOf(configManager.printDensity) }
    var printerAutoCut by remember { mutableStateOf(configManager.printerAutoCut) }
    var useBiometric by remember { mutableStateOf(configManager.useBiometric) }
    
    var kioskMode by remember { mutableStateOf(configManager.kioskMode) }
    var activeEventId by remember { mutableStateOf(configManager.activeEventId) }
    var showEventDialog by remember { mutableStateOf(false) }

    val eventsList = remember(configManager.syncedFramesJson) {
        val list = mutableListOf<com.example.photobooth.data.EventInfo>()
        list.add(com.example.photobooth.data.EventInfo("general", "Umum (Default)", "UMUM"))
        val syncedJson = configManager.syncedFramesJson
        if (syncedJson.isNotEmpty()) {
            try {
                val config = com.google.gson.Gson().fromJson(syncedJson, com.example.photobooth.data.FrameConfig::class.java)
                config?.events?.let { list.addAll(it) }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
        list
    }
    
    var isSyncing by remember { mutableStateOf(false) }
    var isTestingPrint by remember { mutableStateOf(false) }

    // Live Server Connectivity Status
    var serverOnline by remember { mutableStateOf<Boolean?>(null) }
    LaunchedEffect(backendUrl) {
        serverOnline = null
        try {
            val api = NetworkClient.getApi(backendUrl)
            val response = api.getPhotoHistory()
            serverOnline = response.isSuccessful
        } catch (e: Exception) {
            serverOnline = false
        }
    }

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
            var isPrinter = false
            for (i in 0 until device.interfaceCount) {
                if (device.getInterface(i).interfaceClass == 7) {
                    isPrinter = true
                    break
                }
            }
            if (isPrinter) {
                usbDevices.add(device)
            }
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

    // Refresh history when Tab 3 (Riwayat Foto) or Tab 0 (Dashboard) is selected, or when refresh is triggered
    LaunchedEffect(selectedTab, refreshTrigger) {
        if (selectedTab == 0 || selectedTab == 3) {
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
                            Toast.makeText(context, "Gagal memuat data dari server", Toast.LENGTH_SHORT).show()
                        }
                        isLoadingHistory = false
                    }
                } catch (e: Exception) {
                    withContext(Dispatchers.Main) {
                        Toast.makeText(context, "Error koneksi data: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
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
                    // TAB 0: Interactive Dashboard
                    0 -> DashboardTab(
                        photoHistory = photoHistory,
                        isLoading = isLoadingHistory,
                        serverOnline = serverOnline,
                        printerType = printerType,
                        syncedFramesCount = syncedFramesCount,
                        onRefresh = { refreshTrigger++ },
                        onNavigateToTab = { tabIndex -> selectedTab = tabIndex }
                    )

                    // TAB 1: Configurations
                    1 -> Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        // Quick Stats Dashboard Row
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            // Stat 1: Server Status
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(90.dp)
                                    .clip(RoundedCornerShape(16.dp))
                                    .background(Color(0xFF18181F))
                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                                    .padding(12.dp)
                            ) {
                                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                    Text("SERVER STATUS", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .size(8.dp)
                                                .clip(RoundedCornerShape(50.dp))
                                                .background(
                                                    when (serverOnline) {
                                                        true -> Color(0xFF52B788)
                                                        false -> Color(0xFFE63946)
                                                        else -> Color(0xFFF7B801)
                                                    }
                                                )
                                        )
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text(
                                            text = when (serverOnline) {
                                                true -> "ONLINE"
                                                false -> "OFFLINE"
                                                else -> "CHECKING"
                                            },
                                            color = Color.White,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                }
                            }

                            // Stat 2: Frames Sync
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(90.dp)
                                    .clip(RoundedCornerShape(16.dp))
                                    .background(Color(0xFF18181F))
                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                                    .padding(12.dp)
                            ) {
                                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                    Text("SYNCED FRAMES", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                    Text(
                                        text = "$syncedFramesCount Bingkai",
                                        color = Color.White,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }
                            }

                            // Stat 3: Printer Driver
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(90.dp)
                                    .clip(RoundedCornerShape(16.dp))
                                    .background(Color(0xFF18181F))
                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                                    .padding(12.dp)
                            ) {
                                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                    Text("ACTIVE PRINTER", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                    Text(
                                        text = when (printerType) {
                                            "THERMAL" -> "XP-420B"
                                            "COLOR" -> "COLOR PDF"
                                            "AUTO" -> "AUTO (THERMAL & COLOR)"
                                            else -> "NONE"
                                        },
                                        color = Color.White,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }
                            }
                        }

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
                            
                            // Biometric Auth Gate Toggle
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text("Gunakan Sensor Sidik Jari (Biometrik)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    Text("Otentikasi biometrik cepat untuk masuk menu admin tanpa PIN", color = Color.Gray, fontSize = 12.sp)
                                }
                                Switch(
                                    checked = useBiometric,
                                    onCheckedChange = { useBiometric = it },
                                    colors = SwitchDefaults.colors(
                                        checkedThumbColor = Color.White,
                                        checkedTrackColor = Color(0xFFE63946),
                                        uncheckedThumbColor = Color.Gray,
                                        uncheckedTrackColor = Color(0xFF2A2A35)
                                    )
                                )
                            }
                            Spacer(modifier = Modifier.height(16.dp))

                            Button(
                                onClick = {
                                    configManager.backendUrl = backendUrl
                                    configManager.adminPin = adminPin
                                    configManager.useBiometric = useBiometric
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

                        // Section 4: Event & Kiosk Mode Config
                        AdminCard(title = "Mode Kiosk & Pengelolaan Event") {
                            Text("Pilih Mode Operasional Kiosk:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(8.dp))
                            
                            // Kiosk mode options
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier
                                        .weight(1f)
                                        .clickable { kioskMode = "MULTI_EVENT" }
                                ) {
                                    RadioButton(
                                        selected = kioskMode == "MULTI_EVENT",
                                        onClick = { kioskMode = "MULTI_EVENT" },
                                        colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Multi-Event (Kode)", color = Color.White, fontSize = 12.sp)
                                }
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier
                                        .weight(1f)
                                        .clickable { kioskMode = "DEDICATED" }
                                ) {
                                    RadioButton(
                                        selected = kioskMode == "DEDICATED",
                                        onClick = { kioskMode = "DEDICATED" },
                                        colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Satu Event Terkunci", color = Color.White, fontSize = 12.sp)
                                }
                            }
                            
                            Spacer(modifier = Modifier.height(16.dp))
                            
                            if (kioskMode == "DEDICATED") {
                                val currentEventName = eventsList.firstOrNull { it.id == activeEventId }?.name ?: "Pilih Event"
                                Text("Pilih Event Aktif Acara:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(8.dp))
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(Color(0xFF2A2A35))
                                        .border(1.dp, Color(0xFF3F3F4F), RoundedCornerShape(8.dp))
                                        .clickable { showEventDialog = true }
                                        .padding(16.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text(currentEventName, color = Color.White, fontSize = 14.sp)
                                        Text("Ubah ▾", color = Color(0xFFE63946), fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                                Spacer(modifier = Modifier.height(16.dp))
                            }
                            
                            Button(
                                onClick = {
                                    configManager.kioskMode = kioskMode
                                    configManager.activeEventId = activeEventId
                                    Toast.makeText(context, "Setelan mode event disimpan!", Toast.LENGTH_SHORT).show()
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.align(Alignment.End)
                            ) {
                                Text("Simpan Setelan Event", fontWeight = FontWeight.Bold)
                            }
                        }

                        // Event Selection Dialog
                        if (showEventDialog) {
                            Dialog(onDismissRequest = { showEventDialog = false }) {
                                Card(
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24))
                                ) {
                                    Column(
                                        modifier = Modifier.padding(16.dp),
                                        verticalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Text("Pilih Event Aktif", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                        Spacer(modifier = Modifier.height(8.dp))
                                        eventsList.forEach { event ->
                                            Row(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .clickable {
                                                        activeEventId = event.id
                                                        showEventDialog = false
                                                    }
                                                    .padding(vertical = 12.dp, horizontal = 8.dp),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Text(event.name, color = Color.White, fontSize = 14.sp)
                                                if (activeEventId == event.id) {
                                                    Icon(
                                                        imageVector = Icons.Default.Check,
                                                        contentDescription = "Selected",
                                                        tint = Color(0xFFE63946)
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // TAB 2: Printer Config
                    2 -> Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        AdminCard(title = "Konfigurasi Printer") {
                            Text("Tipe Driver Printer Terhubung:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                            
                             Column(
                                 modifier = Modifier.fillMaxWidth(),
                                 verticalArrangement = Arrangement.spacedBy(10.dp)
                             ) {
                                 // Option 1: NONE
                                 Row(
                                     modifier = Modifier
                                         .fillMaxWidth()
                                         .clip(RoundedCornerShape(12.dp))
                                         .background(if (printerType == "NONE") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                         .border(1.5.dp, if (printerType == "NONE") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                         .clickable { printerType = "NONE"; configManager.printerType = "NONE" }
                                         .padding(horizontal = 16.dp, vertical = 12.dp),
                                     verticalAlignment = Alignment.CenterVertically
                                 ) {
                                     RadioButton(
                                         selected = printerType == "NONE",
                                         onClick = { printerType = "NONE"; configManager.printerType = "NONE" },
                                         colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946), unselectedColor = Color.Gray)
                                     )
                                     Spacer(modifier = Modifier.width(12.dp))
                                     Column {
                                         Text("NONE / TANPA PRINTER", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                         Text("Mode digital saja, printer dinonaktifkan.", color = Color.Gray, fontSize = 11.sp)
                                     }
                                 }

                                 // Option 2: THERMAL
                                 Row(
                                     modifier = Modifier
                                         .fillMaxWidth()
                                         .clip(RoundedCornerShape(12.dp))
                                         .background(if (printerType == "THERMAL") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                         .border(1.5.dp, if (printerType == "THERMAL") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                         .clickable { printerType = "THERMAL"; configManager.printerType = "THERMAL" }
                                         .padding(horizontal = 16.dp, vertical = 12.dp),
                                     verticalAlignment = Alignment.CenterVertically
                                 ) {
                                     RadioButton(
                                         selected = printerType == "THERMAL",
                                         onClick = { printerType = "THERMAL"; configManager.printerType = "THERMAL" },
                                         colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946), unselectedColor = Color.Gray)
                                     )
                                     Spacer(modifier = Modifier.width(12.dp))
                                     Column {
                                         Text("THERMAL PRINTER (XP-420B)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                         Text("Cetak langsung lewat USB/Bluetooth (Dithering Monokrom).", color = Color.Gray, fontSize = 11.sp)
                                     }
                                 }

                                 // Option 3: COLOR
                                 Row(
                                     modifier = Modifier
                                         .fillMaxWidth()
                                         .clip(RoundedCornerShape(12.dp))
                                         .background(if (printerType == "COLOR") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                         .border(1.5.dp, if (printerType == "COLOR") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                         .clickable { printerType = "COLOR"; configManager.printerType = "COLOR" }
                                         .padding(horizontal = 16.dp, vertical = 12.dp),
                                     verticalAlignment = Alignment.CenterVertically
                                 ) {
                                     RadioButton(
                                         selected = printerType == "COLOR",
                                         onClick = { printerType = "COLOR"; configManager.printerType = "COLOR" },
                                         colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946), unselectedColor = Color.Gray)
                                     )
                                     Spacer(modifier = Modifier.width(12.dp))
                                     Column {
                                         Text("COLOR PRINTER (PDF/SYSTEM)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                         Text("Cetak warna penuh menggunakan printer sistem Android.", color = Color.Gray, fontSize = 11.sp)
                                     }
                                 }

                                 // Option 4: AUTO / DYNAMIC
                                 Row(
                                     modifier = Modifier
                                         .fillMaxWidth()
                                         .clip(RoundedCornerShape(12.dp))
                                         .background(if (printerType == "AUTO") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                         .border(1.5.dp, if (printerType == "AUTO") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(12.dp))
                                         .clickable { printerType = "AUTO"; configManager.printerType = "AUTO" }
                                         .padding(horizontal = 16.dp, vertical = 12.dp),
                                     verticalAlignment = Alignment.CenterVertically
                                 ) {
                                     RadioButton(
                                         selected = printerType == "AUTO",
                                         onClick = { printerType = "AUTO"; configManager.printerType = "AUTO" },
                                         colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946), unselectedColor = Color.Gray)
                                     )
                                     Spacer(modifier = Modifier.width(12.dp))
                                     Column {
                                         Text("AUTO / DYNAMIC (THERMAL & COLOR)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                         Text("Mendeteksi otomatis: Epson untuk warna & Xprinter untuk receipt.", color = Color.Gray, fontSize = 11.sp)
                                     }
                                 }
                             }

                            if (printerType == "THERMAL" || printerType == "AUTO") {
                                HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(vertical = 12.dp))
                                
                                Text("Pilih Protokol Printer Thermal:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    // Option 1: TSPL
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (thermalMode == "TSPL") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                            .border(1.dp, if (thermalMode == "TSPL") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            .clickable { thermalMode = "TSPL"; configManager.thermalMode = "TSPL" }
                                            .padding(12.dp)
                                    ) {
                                        RadioButton(
                                            selected = thermalMode == "TSPL",
                                            onClick = { thermalMode = "TSPL"; configManager.thermalMode = "TSPL" },
                                            colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Column {
                                            Text("TSPL", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text("Untuk Kertas Label / Stiker", color = Color.Gray, fontSize = 10.sp)
                                        }
                                    }

                                    // Option 2: ESC/POS
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (thermalMode == "ESC_POS") Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                            .border(1.dp, if (thermalMode == "ESC_POS") Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            .clickable { thermalMode = "ESC_POS"; configManager.thermalMode = "ESC_POS" }
                                            .padding(12.dp)
                                    ) {
                                        RadioButton(
                                            selected = thermalMode == "ESC_POS",
                                            onClick = { thermalMode = "ESC_POS"; configManager.thermalMode = "ESC_POS" },
                                            colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Column {
                                            Text("ESC/POS", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text("Untuk Kertas Struk / Kasir", color = Color.Gray, fontSize = 10.sp)
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(16.dp))
                                Text("Ukuran Lebar Kertas:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    // Option 58mm
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (printerPaperWidth == 58) Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                            .border(1.dp, if (printerPaperWidth == 58) Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            .clickable { 
                                                printerPaperWidth = 58
                                                configManager.printerPaperWidth = 58 
                                            }
                                            .padding(12.dp)
                                    ) {
                                        RadioButton(
                                            selected = printerPaperWidth == 58,
                                            onClick = { 
                                                printerPaperWidth = 58
                                                configManager.printerPaperWidth = 58 
                                            },
                                            colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Column {
                                            Text("58 mm", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text("Struk Kasir Kecil", color = Color.Gray, fontSize = 10.sp)
                                        }
                                    }

                                    // Option 80mm
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (printerPaperWidth == 80) Color(0xFFE63946).copy(alpha = 0.15f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                            .border(1.dp, if (printerPaperWidth == 80) Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            .clickable { 
                                                printerPaperWidth = 80
                                                configManager.printerPaperWidth = 80 
                                            }
                                            .padding(12.dp)
                                    ) {
                                        RadioButton(
                                            selected = printerPaperWidth == 80,
                                            onClick = { 
                                                printerPaperWidth = 80
                                                configManager.printerPaperWidth = 80 
                                            },
                                            colors = RadioButtonDefaults.colors(selectedColor = Color(0xFFE63946))
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Column {
                                            Text("80 mm", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text("Struk Kasir Lebar / Stiker", color = Color.Gray, fontSize = 10.sp)
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(16.dp))
                                Text("Kepekatan Cetak (Density): $printDensity", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    (1..5).forEach { densityVal ->
                                        val isSelected = printDensity == densityVal
                                        Box(
                                            contentAlignment = Alignment.Center,
                                            modifier = Modifier
                                                .weight(1f)
                                                .height(45.dp)
                                                .clip(RoundedCornerShape(8.dp))
                                                .background(if (isSelected) Color(0xFFE63946) else Color(0xFF2A2A35))
                                                .border(1.dp, if (isSelected) Color(0xFFE63946) else Color(0xFF3F3F4F), RoundedCornerShape(8.dp))
                                                .clickable {
                                                    printDensity = densityVal
                                                    configManager.printDensity = densityVal
                                                }
                                        ) {
                                            Text(
                                                text = densityVal.toString(),
                                                color = if (isSelected) Color.White else Color.Gray,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 14.sp
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(16.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("Potong Kertas Otomatis (Auto-Cut)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        Text("Memotong kertas struk secara otomatis setelah selesai mencetak", color = Color.Gray, fontSize = 12.sp)
                                    }
                                    Switch(
                                        checked = printerAutoCut,
                                        onCheckedChange = {
                                            printerAutoCut = it
                                            configManager.printerAutoCut = it
                                        },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Color.White,
                                            checkedTrackColor = Color(0xFFE63946),
                                            uncheckedThumbColor = Color.Gray,
                                            uncheckedTrackColor = Color(0xFF2A2A35)
                                        )
                                    )
                                }

                                Spacer(modifier = Modifier.height(16.dp))
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
                                            val deviceName = device.productName ?: "Printer USB"
                                            Text("$deviceName (VID:${device.vendorId} PID:${device.productId})", color = Color.White, fontSize = 13.sp)
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
                                if (printerType == "AUTO") {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Button(
                                            onClick = {
                                                isTestingPrint = true
                                                scope.launch {
                                                    val success = testPrintJob(context, configManager, "THERMAL")
                                                    isTestingPrint = false
                                                    Toast.makeText(context, success, Toast.LENGTH_LONG).show()
                                                }
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                            modifier = Modifier.weight(1f)
                                        ) {
                                            Text("TEST THERMAL (XP)", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                        }
                                        Button(
                                            onClick = {
                                                isTestingPrint = true
                                                scope.launch {
                                                    val success = testPrintJob(context, configManager, "COLOR")
                                                    isTestingPrint = false
                                                    Toast.makeText(context, success, Toast.LENGTH_LONG).show()
                                                }
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                                            modifier = Modifier.weight(1f)
                                        ) {
                                            Text("TEST COLOR (EPSON)", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                        }
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
                    }

                    // TAB 3: Photo History Grid
                    3 -> {
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
            var isSaving by remember { mutableStateOf(false) }

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
                                com.example.photobooth.ui.share.PrintStatusDialog(
                                    photoPath = item.photoUrl,
                                    onDismissRequest = {},
                                    statusText = "Mengunduh & mengirim cetak ulang..."
                                )
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

                            Button(
                                onClick = {
                                    isSaving = true
                                    scope.launch {
                                        val msg = saveImageToGallery(context, item.photoUrl)
                                        isSaving = false
                                        Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                                    }
                                },
                                enabled = !isSaving,
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                if (isSaving) {
                                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp))
                                } else {
                                    Text("SIMPAN FOTO KE GALERI", fontWeight = FontWeight.Bold)
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
private suspend fun testPrintJob(context: Context, configManager: ConfigManager, forceType: String? = null): String {
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

    val driver: com.example.photobooth.print.PrinterManager = when (printerTypeToUse) {
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
            
            val printerTypeToUse = if (configManager.printerType == "AUTO") {
                "COLOR"
            } else {
                configManager.printerType
            }

            val driver: com.example.photobooth.print.PrinterManager = when (printerTypeToUse) {
                "THERMAL" -> ThermalPrinterDriver()
                "COLOR" -> ColorPrinterDriver()
                else -> return@withContext "Tipe printer aktif: Tidak Ada"
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

@Composable
fun DashboardTab(
    photoHistory: List<HistoryItem>,
    isLoading: Boolean,
    serverOnline: Boolean?,
    printerType: String,
    syncedFramesCount: Int,
    onRefresh: () -> Unit,
    onNavigateToTab: (Int) -> Unit
) {
    if (isLoading) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            CircularProgressIndicator(color = Color(0xFFE63946))
        }
        return
    }

    val context = LocalContext.current

    // Calculate stats: today count
    val todayCount = remember(photoHistory) {
        val cal = java.util.Calendar.getInstance()
        val todayYear = cal.get(java.util.Calendar.YEAR)
        val todayDay = cal.get(java.util.Calendar.DAY_OF_YEAR)
        photoHistory.count { item ->
            val itemCal = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }
            itemCal.get(java.util.Calendar.YEAR) == todayYear &&
            itemCal.get(java.util.Calendar.DAY_OF_YEAR) == todayDay
        }
    }

    // Peak Hour calculation
    val peakHourStr = remember(photoHistory) {
        if (photoHistory.isEmpty()) {
            "N/A"
        } else {
            val hourlyCounts = IntArray(24)
            photoHistory.forEach { item ->
                val cal = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }
                val hour = cal.get(java.util.Calendar.HOUR_OF_DAY)
                if (hour in 0..23) {
                    hourlyCounts[hour]++
                }
            }
            val maxHour = hourlyCounts.indices.maxByOrNull { hourlyCounts[it] } ?: 0
            val maxHourFormatted = String.format(Locale.getDefault(), "%02d:00", maxHour)
            val nextHourFormatted = String.format(Locale.getDefault(), "%02d:00", (maxHour + 1) % 24)
            "$maxHourFormatted - $nextHourFormatted"
        }
    }

    // Weekly metrics
    val last7Days = remember {
        (0..6).map { i ->
            val cal = java.util.Calendar.getInstance()
            cal.add(java.util.Calendar.DAY_OF_YEAR, -i)
            cal.time
        }.reversed()
    }
    val weeklyLabels = remember(last7Days) {
        last7Days.map { date ->
            SimpleDateFormat("dd/MM", Locale.getDefault()).format(date)
        }
    }
    val weeklyValues = remember(photoHistory, last7Days) {
        last7Days.map { date ->
            val cal = java.util.Calendar.getInstance().apply { time = date }
            val targetYear = cal.get(java.util.Calendar.YEAR)
            val targetDay = cal.get(java.util.Calendar.DAY_OF_YEAR)
            photoHistory.count { item ->
                val itemCal = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }
                itemCal.get(java.util.Calendar.YEAR) == targetYear &&
                itemCal.get(java.util.Calendar.DAY_OF_YEAR) == targetDay
            }
        }
    }

    // Time of day metrics
    val morningCount = remember(photoHistory) {
        photoHistory.count { item ->
            val hour = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }.get(java.util.Calendar.HOUR_OF_DAY)
            hour in 6..11
        }
    }
    val afternoonCount = remember(photoHistory) {
        photoHistory.count { item ->
            val hour = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }.get(java.util.Calendar.HOUR_OF_DAY)
            hour in 12..17
        }
    }
    val eveningCount = remember(photoHistory) {
        photoHistory.count { item ->
            val hour = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }.get(java.util.Calendar.HOUR_OF_DAY)
            hour in 18..23
        }
    }
    val nightCount = remember(photoHistory) {
        photoHistory.count { item ->
            val hour = java.util.Calendar.getInstance().apply { timeInMillis = item.timestamp * 1000L }.get(java.util.Calendar.HOUR_OF_DAY)
            hour in 0..5
        }
    }
    val timeLabels = listOf("Pagi", "Siang", "Malam", "Subuh")
    val timeValues = listOf(morningCount, afternoonCount, eveningCount, nightCount)

    // Toggle logic for chart
    var selectedChartMode by remember { mutableStateOf("WEEKLY") } // "WEEKLY" or "TIMEOFDAY"

    // Pulsing dot animation for live connection indicator
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val pulseAlpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1.0f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseAlpha"
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Upper stats row
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Stat 1: Total Sesi
            Box(
                modifier = Modifier
                    .weight(1f)
                    .height(95.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color(0xFF18181F))
                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("TOTAL SESI FOTO", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        text = "${photoHistory.size} Sesi",
                        color = Color.White,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            // Stat 2: Sesi Hari Ini
            Box(
                modifier = Modifier
                    .weight(1f)
                    .height(95.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color(0xFF18181F))
                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("SESI HARI INI", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        text = "$todayCount Sesi",
                        color = Color(0xFF52B788),
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            // Stat 3: Jam Teramai
            Box(
                modifier = Modifier
                    .weight(1f)
                    .height(95.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color(0xFF18181F))
                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("JAM TERAMAI", color = Color.Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        text = peakHourStr,
                        color = Color(0xFFF7B801),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }

        // Toggle Chart Selector Row
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFF18181F))
                .padding(4.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Button(
                onClick = { selectedChartMode = "WEEKLY" },
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (selectedChartMode == "WEEKLY") Color(0xFFE63946) else Color.Transparent,
                    contentColor = if (selectedChartMode == "WEEKLY") Color.White else Color.Gray
                ),
                shape = RoundedCornerShape(8.dp),
                contentPadding = PaddingValues(vertical = 10.dp)
            ) {
                Text("Grafik 7 Hari Terakhir", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
            Button(
                onClick = { selectedChartMode = "TIMEOFDAY" },
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (selectedChartMode == "TIMEOFDAY") Color(0xFFE63946) else Color.Transparent,
                    contentColor = if (selectedChartMode == "TIMEOFDAY") Color.White else Color.Gray
                ),
                shape = RoundedCornerShape(8.dp),
                contentPadding = PaddingValues(vertical = 10.dp)
            ) {
                Text("Distribusi Waktu Hari", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        // The Custom Chart Card
        if (selectedChartMode == "WEEKLY") {
            InteractiveBarChart(
                title = "Statistik Mingguan",
                labels = weeklyLabels,
                values = weeklyValues,
                accentColor = Color(0xFFE63946)
            )
        } else {
            InteractiveBarChart(
                title = "Distribusi Berdasarkan Waktu",
                labels = timeLabels,
                values = timeValues,
                accentColor = Color(0xFFF7B801)
            )
        }

        // Live connection & status summary card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
            border = BorderStroke(1.dp, Color(0xFF2A2A35))
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("Status Sistem & Koneksi Kiosk", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    IconButton(onClick = onRefresh) {
                        Icon(imageVector = Icons.Default.Refresh, contentDescription = "Refresh Data", tint = Color.White)
                    }
                }

                HorizontalDivider(color = Color(0xFF2A2A35))

                // Detail connection row 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("API Server:", color = Color.Gray, fontSize = 12.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(RoundedCornerShape(50.dp))
                                .alpha(pulseAlpha)
                                .background(
                                    when (serverOnline) {
                                        true -> Color(0xFF52B788)
                                        false -> Color(0xFFE63946)
                                        else -> Color(0xFFF7B801)
                                    }
                                )
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = when (serverOnline) {
                                true -> "ONLINE"
                                false -> "OFFLINE"
                                else -> "CHECKING"
                            },
                            color = Color.White,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }

                // Detail connection row 2
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Printer Driver Terpilih:", color = Color.Gray, fontSize = 12.sp)
                    Text(
                        text = when (printerType) {
                            "THERMAL" -> "THERMAL (XP-420B)"
                            "COLOR" -> "COLOR (PDF/SYSTEM)"
                            "AUTO" -> "OTOMATIS (THERMAL & WARNA)"
                            else -> "NONE"
                        },
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                // Detail connection row 3
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Katalog Bingkai Offline:", color = Color.Gray, fontSize = 12.sp)
                    Text(
                        text = "$syncedFramesCount Bingkai",
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }

        // Fast actions shortcuts list
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
            border = BorderStroke(1.dp, Color(0xFF2A2A35))
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Text("Pintasan Navigasi Cepat", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = { onNavigateToTab(1) },
                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White)
                    ) {
                        Text("Pengaturan", fontSize = 11.sp, maxLines = 1)
                    }

                    OutlinedButton(
                        onClick = { onNavigateToTab(2) },
                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White)
                    ) {
                        Text("Setel Printer", fontSize = 11.sp, maxLines = 1)
                    }

                    OutlinedButton(
                        onClick = { onNavigateToTab(3) },
                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White)
                    ) {
                        Text("Riwayat Foto", fontSize = 11.sp, maxLines = 1)
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
    }
}

@Composable
fun InteractiveBarChart(
    title: String,
    labels: List<String>,
    values: List<Int>,
    accentColor: Color = Color(0xFFE63946),
    modifier: Modifier = Modifier
) {
    var selectedBarIndex by remember(labels, values) { mutableStateOf<Int?>(null) }
    val maxVal = if (values.maxOrNull() ?: 0 > 0) values.maxOrNull()!! else 10

    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
        border = BorderStroke(1.dp, Color(0xFF2A2A35))
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = title,
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold
                )
                if (selectedBarIndex != null) {
                    Text(
                        text = "${labels[selectedBarIndex!!]}: ${values[selectedBarIndex!!]} foto",
                        color = accentColor,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                } else {
                    Text(
                        text = "Sentuh batang untuk detail",
                        color = Color.Gray,
                        fontSize = 11.sp
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(150.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Bottom
            ) {
                values.forEachIndexed { index, value ->
                    val barHeightFraction = value.toFloat() / maxVal
                    val isSelected = selectedBarIndex == index
                    
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .weight(1f)
                            .clickable { selectedBarIndex = if (isSelected) null else index }
                    ) {
                        Box(
                            modifier = Modifier.height(20.dp),
                            contentAlignment = Alignment.BottomCenter
                        ) {
                            if (isSelected || (value > 0 && selectedBarIndex == null)) {
                                Text(
                                    text = value.toString(),
                                    color = if (isSelected) accentColor else Color.White.copy(alpha = 0.8f),
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                        
                        Spacer(modifier = Modifier.height(4.dp))
                        
                        val barHeight = (100 * barHeightFraction).coerceAtLeast(4f).dp
                        
                        Box(
                            modifier = Modifier
                                .fillMaxWidth(0.5f)
                                .height(barHeight)
                                .clip(RoundedCornerShape(topStart = 6.dp, topEnd = 6.dp))
                                .background(
                                    brush = Brush.verticalGradient(
                                        colors = if (isSelected) {
                                            listOf(accentColor, accentColor.copy(alpha = 0.7f))
                                        } else {
                                            listOf(accentColor.copy(alpha = 0.4f), accentColor.copy(alpha = 0.15f))
                                        }
                                    )
                                )
                        )
                        
                        Spacer(modifier = Modifier.height(6.dp))
                        
                        Text(
                            text = labels[index],
                            color = if (isSelected) Color.White else Color.Gray,
                            fontSize = 10.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                        )
                    }
                }
            }
        }
    }
}

private suspend fun saveImageToGallery(context: Context, photoUrl: String): String {
    return withContext(Dispatchers.IO) {
        try {
            val url = URL(photoUrl)
            val connection = url.openConnection()
            connection.connectTimeout = 10000
            connection.readTimeout = 15000
            val inputStream = connection.getInputStream()
            val bitmap = BitmapFactory.decodeStream(inputStream)
            inputStream.close()
            
            if (bitmap == null) {
                return@withContext "Gagal mengunduh gambar untuk disimpan."
            }
            
            val filename = "Photobooth_${System.currentTimeMillis()}.png"
            val contentValues = ContentValues().apply {
                put(MediaStore.MediaColumns.DISPLAY_NAME, filename)
                put(MediaStore.MediaColumns.MIME_TYPE, "image/png")
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                    put(MediaStore.MediaColumns.RELATIVE_PATH, "Pictures/Photobooth")
                    put(MediaStore.MediaColumns.IS_PENDING, 1)
                }
            }
            
            val resolver = context.contentResolver
            val uri = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, contentValues)
            
            if (uri == null) {
                return@withContext "Gagal membuat entri galeri."
            }
            
            val outputStream: java.io.OutputStream? = resolver.openOutputStream(uri)
            if (outputStream == null) {
                return@withContext "Gagal membuka media penyimpanan."
            }
            
            val saved = bitmap.compress(Bitmap.CompressFormat.PNG, 100, outputStream)
            outputStream.flush()
            outputStream.close()
            
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                contentValues.clear()
                contentValues.put(MediaStore.MediaColumns.IS_PENDING, 0)
                resolver.update(uri, contentValues, null, null)
            }
            
            if (saved) {
                "Foto berhasil disimpan ke Galeri!"
            } else {
                "Gagal menyimpan berkas gambar."
            }
        } catch (e: Exception) {
            "Gagal menyimpan: ${e.localizedMessage}"
        }
    }
}
