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
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.platform.LocalConfiguration
import android.content.res.Configuration
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
import com.example.photobooth.data.HistoryPrinter
import android.content.ContextWrapper
import androidx.fragment.app.FragmentActivity
import com.example.photobooth.data.UpdateManager
import com.example.photobooth.data.UpdateInfo
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
    val configuration = LocalConfiguration.current
    val isLandscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE

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
    var historyListState by remember { mutableStateOf(configManager.getPrinterHistory()) }
    val isThermalEnabled = remember(printerType) { printerType == "AUTO" || printerType == "THERMAL" }
    val isColorEnabled = remember(printerType) { printerType == "AUTO" || printerType == "COLOR" }
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

    // Scan function
    val scanPrinters = {
        usbDevices.clear()
        bluetoothDevices.clear()
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

    // Initial loads
    LaunchedEffect(Unit) {
        scanPrinters()
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
                    1 -> {
                        // Define blocks inside the tab
                        val QuickStatsBlock: @Composable () -> Unit = {
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
                        }

                        val ServerConfigBlock: @Composable () -> Unit = {
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
                        }

                        val SyncTemplatesBlock: @Composable () -> Unit = {
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
                        }

                        val CaptureTimingsBlock: @Composable () -> Unit = {
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

                        val KioskModeBlock: @Composable () -> Unit = {
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
                        }

                        // Kiosk Theme Customization
                        var activeThemeState by remember { mutableStateOf(configManager.appTheme) }
                        var isThemeDropdownExpanded by remember { mutableStateOf(false) }
                        val themeList = listOf(
                            Pair("NEON_RED", "Neon Red (Modern)"),
                            Pair("CUTE_PASTEL", "Cute Pastel (Wood)"),
                            Pair("LUXURY_GOLD", "Luxury Gold (Wedding)"),
                            Pair("RETRO_ARCADE", "Retro Arcade (8-Bit)")
                        )
                        val activeThemeName = themeList.firstOrNull { it.first == activeThemeState }?.second ?: activeThemeState
                        
                        val ThemeBlock: @Composable () -> Unit = {
                            AdminCard(title = "Tema Tampilan Kiosk (Total Layout)") {
                                Text(
                                    text = "Pilih gaya visual total untuk Kiosk. Tema akan merubah keseluruhan tata letak, gaya tombol, ornamen, dan tipografi.",
                                    color = Color.Gray,
                                    fontSize = 12.sp,
                                    lineHeight = 16.sp
                                )
                                Spacer(modifier = Modifier.height(12.dp))
                                
                                Box(modifier = Modifier.fillMaxWidth()) {
                                    OutlinedButton(
                                        onClick = { isThemeDropdownExpanded = true },
                                        modifier = Modifier.fillMaxWidth(),
                                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                        shape = RoundedCornerShape(8.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(text = activeThemeName, color = Color.White, fontSize = 14.sp)
                                            Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                        }
                                    }
                                    DropdownMenu(
                                        expanded = isThemeDropdownExpanded,
                                        onDismissRequest = { isThemeDropdownExpanded = false },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(Color(0xFF1E1E24))
                                            .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                    ) {
                                        themeList.forEach { (themeId, themeName) ->
                                            DropdownMenuItem(
                                                text = { Text(themeName, color = Color.White) },
                                                onClick = {
                                                    activeThemeState = themeId
                                                    configManager.appTheme = themeId
                                                    isThemeDropdownExpanded = false
                                                    Toast.makeText(context, "Tema diatur ke $themeName!", Toast.LENGTH_SHORT).show()
                                                }
                                            )
                                        }
                                    }
                                }
                            }
                        }

                        // Pembaruan Aplikasi States
                        val updateManager = remember { UpdateManager(context) }
                        var updateInfo by remember { mutableStateOf<UpdateInfo?>(null) }
                        var isCheckingUpdate by remember { mutableStateOf(false) }
                        var updateError by remember { mutableStateOf<String?>(null) }
                        var downloadProgress by remember { mutableStateOf<Float?>(null) }
                        var isDownloading by remember { mutableStateOf(false) }
                        var isInstallPermissionNeeded by remember { mutableStateOf(false) }

                        val currentVersionName = remember { updateManager.getCurrentVersionName() }
                        val currentVersionCode = remember { updateManager.getCurrentVersionCode() }

                        val AppUpdateBlock: @Composable () -> Unit = {
                            AdminCard(title = "Pembaruan Aplikasi") {
                                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column {
                                            Text("Versi Sekarang", color = Color.Gray, fontSize = 11.sp)
                                            Text("v$currentVersionName ($currentVersionCode)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        }
                                        if (updateInfo != null) {
                                            Column(horizontalAlignment = Alignment.End) {
                                                Text("Versi Terbaru", color = Color.Gray, fontSize = 11.sp)
                                                Text("v${updateInfo!!.versionName} (${updateInfo!!.versionCode})", color = Color(0xFF52B788), fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                    
                                    HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(vertical = 4.dp))
                                    
                                    if (updateError != null) {
                                        Text(updateError!!, color = Color(0xFFE63946), fontSize = 12.sp)
                                    }
                                    
                                    if (updateInfo == null) {
                                        if (isCheckingUpdate) {
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.Center,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                CircularProgressIndicator(color = Color(0xFFE63946), modifier = Modifier.size(20.dp))
                                                Spacer(modifier = Modifier.width(8.dp))
                                                Text("Memeriksa pembaruan...", color = Color.Gray, fontSize = 13.sp)
                                            }
                                        } else {
                                            Button(
                                                onClick = {
                                                    isCheckingUpdate = true
                                                    updateError = null
                                                    scope.launch {
                                                        val info = updateManager.checkUpdate(backendUrl)
                                                        isCheckingUpdate = false
                                                        if (info != null) {
                                                            updateInfo = info
                                                        } else {
                                                            updateError = "Gagal terhubung ke server update."
                                                        }
                                                    }
                                                },
                                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                                                modifier = Modifier.fillMaxWidth()
                                            ) {
                                                Text("PERIKSA PEMBARUAN", fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    } else {
                                        val hasNewVersion = updateInfo!!.versionCode > currentVersionCode
                                        if (hasNewVersion) {
                                            Text(
                                                text = "Pembaruan tersedia! Catatan rilis:\n${updateInfo!!.changeLog}",
                                                color = Color.White,
                                                fontSize = 12.sp,
                                                lineHeight = 16.sp
                                            )
                                            
                                            Spacer(modifier = Modifier.height(8.dp))
                                            
                                            if (isDownloading) {
                                                Column(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalAlignment = Alignment.CenterHorizontally,
                                                    verticalArrangement = Arrangement.spacedBy(4.dp)
                                                ) {
                                                    LinearProgressIndicator(
                                                        progress = downloadProgress ?: 0f,
                                                        color = Color(0xFFE63946),
                                                        trackColor = Color(0xFF2A2A35),
                                                        modifier = Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(4.dp))
                                                    )
                                                    val pct = ((downloadProgress ?: 0f) * 100).toInt()
                                                    Text("Mengunduh update: $pct%", color = Color.Gray, fontSize = 11.sp)
                                                }
                                            } else {
                                                Button(
                                                    onClick = {
                                                        if (!updateManager.canRequestPackageInstalls()) {
                                                            isInstallPermissionNeeded = true
                                                        } else {
                                                            isDownloading = true
                                                            updateError = null
                                                            scope.launch {
                                                                val sanitizedBase = if (backendUrl.endsWith("/")) backendUrl else "$backendUrl/"
                                                                val fullApkUrl = if (updateInfo!!.apkUrl.startsWith("http")) {
                                                                    updateInfo!!.apkUrl
                                                                } else {
                                                                    "$sanitizedBase${updateInfo!!.apkUrl}"
                                                                }
                                                                
                                                                // Stop Lock Task Mode before updating
                                                                context.findActivity()?.let { act ->
                                                                    try {
                                                                        act.stopLockTask()
                                                                    } catch (e: Exception) {
                                                                        e.printStackTrace()
                                                                    }
                                                                }
                                                                
                                                                val file = updateManager.downloadApk(fullApkUrl) { progress ->
                                                                    downloadProgress = progress
                                                                }
                                                                isDownloading = false
                                                                if (file != null) {
                                                                    updateManager.installApk(file)
                                                                } else {
                                                                    updateError = "Gagal mengunduh APK. Silakan periksa koneksi."
                                                                }
                                                            }
                                                        }
                                                    },
                                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF52B788)),
                                                    modifier = Modifier.fillMaxWidth()
                                                ) {
                                                    Text("UNDUH & INSTAL SEKARANG", fontWeight = FontWeight.Bold, color = Color.White)
                                                }
                                            }
                                        } else {
                                            Text(
                                                text = "Aplikasi Anda sudah versi terbaru.",
                                                color = Color(0xFF52B788),
                                                fontSize = 13.sp,
                                                fontWeight = FontWeight.Bold,
                                                textAlign = TextAlign.Center,
                                                modifier = Modifier.fillMaxWidth()
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Button(
                                                onClick = {
                                                    updateInfo = null
                                                    updateError = null
                                                },
                                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2A2A35)),
                                                modifier = Modifier.fillMaxWidth()
                                            ) {
                                                Text("OK", fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Responsive Column Layout
                        if (isLandscape) {
                            Row(
                                modifier = Modifier.fillMaxSize(),
                                horizontalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                Column(
                                    modifier = Modifier
                                        .weight(1f)
                                        .verticalScroll(rememberScrollState()),
                                    verticalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    QuickStatsBlock()
                                    ServerConfigBlock()
                                    SyncTemplatesBlock()
                                    CaptureTimingsBlock()
                                }
                                
                                Column(
                                    modifier = Modifier
                                        .weight(1f)
                                        .verticalScroll(rememberScrollState()),
                                    verticalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    KioskModeBlock()
                                    ThemeBlock()
                                    AppUpdateBlock()
                                }
                            }
                        } else {
                            Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .verticalScroll(rememberScrollState()),
                                verticalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                QuickStatsBlock()
                                ServerConfigBlock()
                                SyncTemplatesBlock()
                                CaptureTimingsBlock()
                                KioskModeBlock()
                                ThemeBlock()
                                AppUpdateBlock()
                            }
                        }

                        // Dialog for requesting unknown sources permission
                        if (isInstallPermissionNeeded) {
                            AlertDialog(
                                onDismissRequest = { isInstallPermissionNeeded = false },
                                title = { Text("Izin Instalasi Diperlukan", color = Color.White) },
                                text = { 
                                    Text(
                                        "Untuk memperbarui aplikasi Photobooth langsung dari kiosk, Anda harus memberikan izin untuk menginstal aplikasi dari sumber tidak dikenal.",
                                        color = Color.Gray
                                    ) 
                                },
                                confirmButton = {
                                     Button(
                                         onClick = {
                                             isInstallPermissionNeeded = false
                                             context.findActivity()?.let { act ->
                                                 try {
                                                     act.stopLockTask()
                                                 } catch (e: Exception) {
                                                     e.printStackTrace()
                                                 }
                                             }
                                             updateManager.openInstallPermissionSettings()
                                         },
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946))
                                    ) {
                                        Text("Buka Setelan")
                                    }
                                },
                                dismissButton = {
                                    TextButton(onClick = { isInstallPermissionNeeded = false }) {
                                        Text("Batal", color = Color.Gray)
                                    }
                                },
                                containerColor = Color(0xFF1E1E24)
                            )
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
                    2 -> {
                        // Dropdown expanded states
                        var isProtocolDropdownExpanded by remember { mutableStateOf(false) }
                        val protocolList = listOf(
                            Pair("TSPL", "TSPL (Kertas Label / Stiker)"),
                            Pair("ESC_POS", "ESC/POS (Kertas Struk / Kasir)")
                        )
                        val selectedProtocolText = protocolList.firstOrNull { it.first == thermalMode }?.second ?: thermalMode

                        var isPaperWidthDropdownExpanded by remember { mutableStateOf(false) }
                        val paperWidthList = listOf(
                            Pair(58, "58 mm (Struk Kasir Kecil)"),
                            Pair(80, "80 mm (Struk Kasir Lebar / Stiker)")
                        )
                        val selectedPaperWidthText = paperWidthList.firstOrNull { it.first == printerPaperWidth }?.second ?: "$printerPaperWidth mm"

                        var isDensityDropdownExpanded by remember { mutableStateOf(false) }
                        val densityList = listOf(1, 2, 3, 4, 5)
                        val selectedDensityText = "Level $printDensity"

                        val printerOptions = remember(usbDevices, bluetoothDevices) {
                            val list = mutableListOf<Pair<String, Pair<String, String>>>() // Pair(address, Pair(displayName, type))
                            usbDevices.forEach { device ->
                                val addr = "USB:${device.vendorId},${device.productId}"
                                val name = device.productName ?: "Printer USB"
                                list.add(Pair(addr, Pair("$name (VID:${device.vendorId} PID:${device.productId})", "USB")))
                            }
                            bluetoothDevices.forEach { (name, mac) ->
                                val addr = "BT:$mac"
                                list.add(Pair(addr, Pair("$name ($mac)", "BT")))
                            }
                            list
                        }

                        var isPrinterPortDropdownExpanded by remember { mutableStateOf(false) }
                        val selectedPrinterText = printerOptions.firstOrNull { it.first == printerAddress }?.second?.first ?: printerAddress.ifEmpty { "Pilih Port Printer..." }

                        // Define sub-blocks
                        val ThermalSettingsCard: @Composable () -> Unit = {
                            AdminCard(title = "Pengaturan Printer Struk (Thermal)") {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("Aktifkan Printer Struk", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                        Text("Gunakan printer thermal untuk mencetak struk jepretan", color = Color.Gray, fontSize = 11.sp)
                                    }
                                    Switch(
                                        checked = isThermalEnabled,
                                        onCheckedChange = { checked ->
                                            val newType = when {
                                                checked && isColorEnabled -> "AUTO"
                                                checked -> "THERMAL"
                                                isColorEnabled -> "COLOR"
                                                else -> "NONE"
                                            }
                                            printerType = newType
                                            configManager.printerType = newType
                                        },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Color.White,
                                            checkedTrackColor = Color(0xFFE63946),
                                            uncheckedThumbColor = Color.Gray,
                                            uncheckedTrackColor = Color(0xFF2A2A35)
                                        )
                                    )
                                }

                                if (isThermalEnabled) {
                                    HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(vertical = 4.dp))

                                    // Protocol Dropdown Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Protokol:", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isProtocolDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedProtocolText, color = Color.White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isProtocolDropdownExpanded,
                                                onDismissRequest = { isProtocolDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(Color(0xFF1E1E24))
                                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            ) {
                                                protocolList.forEach { (mode, label) ->
                                                    DropdownMenuItem(
                                                        text = { Text(label, color = Color.White) },
                                                        onClick = {
                                                            thermalMode = mode
                                                            configManager.thermalMode = mode
                                                            isProtocolDropdownExpanded = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(8.dp))

                                    // Paper Width Dropdown Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Lebar Kertas:", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isPaperWidthDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedPaperWidthText, color = Color.White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isPaperWidthDropdownExpanded,
                                                onDismissRequest = { isPaperWidthDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(Color(0xFF1E1E24))
                                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            ) {
                                                paperWidthList.forEach { (width, label) ->
                                                    DropdownMenuItem(
                                                        text = { Text(label, color = Color.White) },
                                                        onClick = {
                                                            printerPaperWidth = width
                                                            configManager.printerPaperWidth = width
                                                            isPaperWidthDropdownExpanded = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(8.dp))

                                    // Density Dropdown Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Kepekatan:", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isDensityDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedDensityText, color = Color.White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isDensityDropdownExpanded,
                                                onDismissRequest = { isDensityDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(Color(0xFF1E1E24))
                                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                            ) {
                                                densityList.forEach { valDensity ->
                                                    DropdownMenuItem(
                                                        text = { Text("Level $valDensity", color = Color.White) },
                                                        onClick = {
                                                            printDensity = valDensity
                                                            configManager.printDensity = valDensity
                                                            isDensityDropdownExpanded = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(8.dp))

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text("Potong Kertas Otomatis (Auto-Cut)", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Text("Memotong kertas struk secara otomatis setelah selesai mencetak", color = Color.Gray, fontSize = 11.sp)
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
                                }
                            }
                        }

                        val PrinterHistoryCard: @Composable () -> Unit = {
                            Card(
                                colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                shape = RoundedCornerShape(16.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(
                                    modifier = Modifier.padding(16.dp),
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Text("Riwayat & Prioritas Printer (Auto-Connect)", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                    Text(
                                        text = "Aplikasi akan otomatis mendeteksi dan menggunakan printer teratas yang berstatus TERSEDIA saat dibuka (startup) atau sebagai cadangan jika printer utama offline.",
                                        color = Color.Gray,
                                        fontSize = 11.sp,
                                        lineHeight = 15.sp
                                    )
                                    
                                    if (historyListState.isEmpty()) {
                                        Text(
                                            text = "Belum ada riwayat printer terhubung. Pilih printer di bawah untuk menambahkannya.",
                                            color = Color.Gray,
                                            fontSize = 12.sp,
                                            textAlign = TextAlign.Center,
                                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp)
                                        )
                                    } else {
                                        historyListState.forEachIndexed { index, printer ->
                                            val isAvailable = remember(usbDevices, bluetoothDevices) {
                                                if (printer.type == "USB") {
                                                    val parts = printer.address.substring(4).split(",")
                                                    if (parts.size == 2) {
                                                        val vid = parts[0].toIntOrNull() ?: 0
                                                        val pid = parts[1].toIntOrNull() ?: 0
                                                        usbDevices.any { it.vendorId == vid && it.productId == pid }
                                                    } else false
                                                } else {
                                                    val mac = printer.address.substring(3)
                                                    bluetoothDevices.any { it.second.equals(mac, ignoreCase = true) }
                                                }
                                            }
                                            
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .clip(RoundedCornerShape(8.dp))
                                                    .background(if (printerAddress == printer.address) Color(0xFFE63946).copy(alpha = 0.08f) else Color(0xFF2A2A35).copy(alpha = 0.4f))
                                                    .border(1.dp, if (printerAddress == printer.address) Color(0xFFE63946) else Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                                    .padding(12.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Column(modifier = Modifier.weight(1f)) {
                                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                                            Box(
                                                                contentAlignment = Alignment.Center,
                                                                modifier = Modifier
                                                                    .size(20.dp)
                                                                    .clip(CircleShape)
                                                                    .background(Color(0xFFE63946))
                                                            ) {
                                                                Text(
                                                                    text = (index + 1).toString(),
                                                                    color = Color.White,
                                                                    fontSize = 10.sp,
                                                                    fontWeight = FontWeight.Bold
                                                                )
                                                            }
                                                            Spacer(modifier = Modifier.width(8.dp))
                                                            Text(printer.name, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                        }
                                                        Spacer(modifier = Modifier.height(4.dp))
                                                        Text("${printer.type} | ${printer.address}", color = Color.Gray, fontSize = 10.sp)
                                                        Spacer(modifier = Modifier.height(4.dp))
                                                        
                                                        // Availability label
                                                        Box(
                                                            modifier = Modifier
                                                                .clip(RoundedCornerShape(4.dp))
                                                                .background(if (isAvailable) Color(0xFF52B788).copy(alpha = 0.15f) else Color.Gray.copy(alpha = 0.15f))
                                                                .padding(horizontal = 6.dp, vertical = 2.dp)
                                                        ) {
                                                            Text(
                                                                text = if (isAvailable) "TERSEDIA" else "OFFLINE",
                                                                color = if (isAvailable) Color(0xFF52B788) else Color.Gray,
                                                                fontSize = 8.sp,
                                                                fontWeight = FontWeight.Bold
                                                            )
                                                        }
                                                    }
                                                    
                                                    Row(
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        // Move Up
                                                        if (index > 0) {
                                                            IconButton(
                                                                onClick = {
                                                                    val list = historyListState.toMutableList()
                                                                    val temp = list[index]
                                                                    list[index] = list[index - 1]
                                                                    list[index - 1] = temp
                                                                    configManager.savePrinterHistory(list)
                                                                    historyListState = list
                                                                },
                                                                modifier = Modifier.size(28.dp)
                                                            ) {
                                                                Text("▲", color = Color.White, fontSize = 10.sp)
                                                            }
                                                        }
                                                        
                                                        // Move Down
                                                        if (index < historyListState.size - 1) {
                                                            IconButton(
                                                                onClick = {
                                                                    val list = historyListState.toMutableList()
                                                                    val temp = list[index]
                                                                    list[index] = list[index + 1]
                                                                    list[index + 1] = temp
                                                                    configManager.savePrinterHistory(list)
                                                                    historyListState = list
                                                                },
                                                                modifier = Modifier.size(28.dp)
                                                            ) {
                                                                Text("▼", color = Color.White, fontSize = 10.sp)
                                                            }
                                                        }
                                                        
                                                        // Delete
                                                        IconButton(
                                                            onClick = {
                                                                val list = historyListState.toMutableList()
                                                                list.removeAt(index)
                                                                configManager.savePrinterHistory(list)
                                                                historyListState = list
                                                            },
                                                            modifier = Modifier.size(28.dp)
                                                        ) {
                                                            Text("❌", color = Color(0xFFE63946), fontSize = 10.sp)
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        val ThermalConnectionCard: @Composable () -> Unit = {
                            AdminCard(title = "Koneksi Printer Struk") {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Pilih Port Printer Thermal Terdeteksi:", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    IconButton(
                                        onClick = {
                                            scanPrinters()
                                            Toast.makeText(context, "Daftar printer diperbarui", Toast.LENGTH_SHORT).show()
                                        }
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Refresh,
                                            contentDescription = "Pindai Ulang",
                                            tint = Color(0xFFE63946)
                                        )
                                    }
                                }
                                
                                Spacer(modifier = Modifier.height(4.dp))

                                Box(modifier = Modifier.fillMaxWidth()) {
                                    OutlinedButton(
                                        onClick = { isPrinterPortDropdownExpanded = true },
                                        modifier = Modifier.fillMaxWidth(),
                                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                        shape = RoundedCornerShape(8.dp),
                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(text = selectedPrinterText, color = Color.White, fontSize = 13.sp)
                                            Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                        }
                                    }
                                    DropdownMenu(
                                        expanded = isPrinterPortDropdownExpanded,
                                        onDismissRequest = { isPrinterPortDropdownExpanded = false },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(Color(0xFF1E1E24))
                                            .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                    ) {
                                        if (printerOptions.isEmpty()) {
                                            DropdownMenuItem(
                                                text = { Text("Tidak ada printer terdeteksi", color = Color.Gray) },
                                                onClick = { isPrinterPortDropdownExpanded = false }
                                            )
                                        } else {
                                            printerOptions.forEach { (addr, info) ->
                                                val (dispName, type) = info
                                                DropdownMenuItem(
                                                    text = { Text("[$type] $dispName", color = Color.White) },
                                                    onClick = {
                                                        printerAddress = addr
                                                        configManager.printerAddress = addr
                                                        configManager.addPrinterToHistory(addr, dispName.split(" (")[0], type)
                                                        historyListState = configManager.getPrinterHistory()
                                                        isPrinterPortDropdownExpanded = false
                                                    }
                                                )
                                            }
                                        }
                                    }
                                }

                                if (printerOptions.isEmpty() && usbDevices.isEmpty() && bluetoothDevices.isEmpty()) {
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text("Tidak ada printer terdeteksi. Hubungkan printer struk menggunakan kabel USB OTG atau koneksi Bluetooth.", color = Color.Gray, fontSize = 12.sp)
                                }

                                Spacer(modifier = Modifier.height(12.dp))
                                
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
                                                val success = testPrintJob(context, configManager, "THERMAL")
                                                isTestingPrint = false
                                                Toast.makeText(context, success, Toast.LENGTH_LONG).show()
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Text("UJI COBA CETAK STRUK", fontWeight = FontWeight.Bold, color = Color.White)
                                    }
                                }
                            }
                        }

                        val ColorPrinterCard: @Composable () -> Unit = {
                            AdminCard(title = "Printer Foto Warna (Sistem)") {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("Aktifkan Printer Warna", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                        Text("Gunakan printer warna sistem Android untuk mencetak hasil foto", color = Color.Gray, fontSize = 11.sp)
                                    }
                                    Switch(
                                        checked = isColorEnabled,
                                        onCheckedChange = { checked ->
                                            val newType = when {
                                                isThermalEnabled && checked -> "AUTO"
                                                isThermalEnabled -> "THERMAL"
                                                checked -> "COLOR"
                                                else -> "NONE"
                                            }
                                            printerType = newType
                                            configManager.printerType = newType
                                        },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Color.White,
                                            checkedTrackColor = Color(0xFFE63946),
                                            uncheckedThumbColor = Color.Gray,
                                            uncheckedTrackColor = Color(0xFF2A2A35)
                                        )
                                    )
                                }

                                if (isColorEnabled) {
                                    HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(vertical = 12.dp))
                                    
                                    Card(
                                        colors = CardDefaults.cardColors(containerColor = Color(0xFF2A2A35).copy(alpha = 0.4f)),
                                        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                        shape = RoundedCornerShape(12.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Text("Panduan Koneksi Printer Warna:", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text("1. Hubungkan printer warna Anda ke tablet menggunakan kabel USB OTG atau jaringan Wi-Fi.", color = Color.Gray, fontSize = 12.sp)
                                            Text("2. Pastikan Anda telah menginstal plugin cetak (Print Service Plugin) yang sesuai dari Google Play Store sesuai merek printer Anda.", color = Color.Gray, fontSize = 12.sp)
                                            Text("3. Saat mencetak, dialog cetak sistem Android akan muncul. Silakan pilih printer Anda di jendela tersebut.", color = Color.Gray, fontSize = 12.sp)
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
                                                    val success = testPrintJob(context, configManager, "COLOR")
                                                    isTestingPrint = false
                                                    Toast.makeText(context, success, Toast.LENGTH_LONG).show()
                                                }
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text("UJI COBA CETAK WARNA", fontWeight = FontWeight.Bold, color = Color.White)
                                        }
                                    }
                                }
                            }
                        }

                        // Responsive double-column or single-column layout for Tab 2
                        if (isLandscape && isThermalEnabled) {
                            Row(
                                modifier = Modifier.fillMaxSize(),
                                horizontalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                Column(
                                    modifier = Modifier
                                        .weight(1f)
                                        .verticalScroll(rememberScrollState()),
                                    verticalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    ThermalSettingsCard()
                                    ColorPrinterCard()
                                }
                                
                                Column(
                                    modifier = Modifier
                                        .weight(1f)
                                        .verticalScroll(rememberScrollState()),
                                    verticalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    PrinterHistoryCard()
                                    ThermalConnectionCard()
                                }
                            }
                        } else {
                            Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .verticalScroll(rememberScrollState()),
                                verticalArrangement = Arrangement.spacedBy(16.dp)
                            ) {
                                ThermalSettingsCard()
                                if (isThermalEnabled) {
                                    PrinterHistoryCard()
                                    ThermalConnectionCard()
                                }
                                ColorPrinterCard()
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

@Composable
fun ThemePreviewCard(
    name: String,
    isSelected: Boolean,
    previewContent: @Composable () -> Unit,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .height(130.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24)),
        border = BorderStroke(if (isSelected) 2.dp else 1.dp, if (isSelected) Color(0xFFE63946) else Color(0xFF2A2A35))
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            Box(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp))
            ) {
                previewContent()
            }
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(if (isSelected) Color(0xFFE63946).copy(alpha = 0.15f) else Color.Transparent)
                    .padding(vertical = 6.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = name,
                    color = if (isSelected) Color(0xFFE63946) else Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center
                )
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

private fun Context.findActivity(): FragmentActivity? {
    var context = this
    while (context is ContextWrapper) {
        if (context is FragmentActivity) return context
        context = context.baseContext
    }
    return null
}
