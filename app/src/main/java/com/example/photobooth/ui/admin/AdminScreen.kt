package com.example.photobooth.ui.admin

import android.annotation.SuppressLint
import android.os.Build
import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
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
import android.hardware.usb.UsbConstants
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
import com.example.photobooth.api.PackageDto
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

data class AdminThemeColors(
    val bgMain: Color,
    val bgCard: Color,
    val bgInnerCard: Color,
    val borderColor: Color,
    val textMain: Color,
    val textMuted: Color,
    val accentColor: Color = Color(0xFFE63946)
)

val LocalAdminThemeColors = staticCompositionLocalOf {
    AdminThemeColors(
        bgMain = Color(0xFF0F0F12),
        bgCard = Color(0xFF18181F),
        bgInnerCard = Color(0xFF1E1E24),
        borderColor = Color(0xFF2A2A35),
        textMain = Color.White,
        textMuted = Color.Gray
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AdminScreen(
    onBackClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    
    var isAdminDarkModeState by remember { mutableStateOf(configManager.isAdminDarkMode) }
    
    val BgMain = if (isAdminDarkModeState) Color(0xFF0F0F12) else Color(0xFFF8FAFC)
    val BgCard = if (isAdminDarkModeState) Color(0xFF18181F) else Color(0xFFFFFFFF)
    val BgInnerCard = if (isAdminDarkModeState) Color(0xFF1E1E24) else Color(0xFFF1F5F9)
    val BorderColor = if (isAdminDarkModeState) Color(0xFF2A2A35) else Color(0xFFE2E8F0)
    val White = if (isAdminDarkModeState) Color.White else Color(0xFF0F172A)
    val Gray = if (isAdminDarkModeState) Color.Gray else Color(0xFF64748B)
    val LightGray = if (isAdminDarkModeState) Color.LightGray else Color(0xFF475569)
    
    val prefs = remember { context.getSharedPreferences("photobooth_prefs", Context.MODE_PRIVATE) }
    var syncedJsonState by remember { mutableStateOf(prefs.getString("synced_frames_json", "") ?: "") }
    DisposableEffect(prefs) {
        val listener = android.content.SharedPreferences.OnSharedPreferenceChangeListener { p, key ->
            if (key == "synced_frames_json") {
                syncedJsonState = p.getString("synced_frames_json", "") ?: ""
            }
        }
        prefs.registerOnSharedPreferenceChangeListener(listener)
        onDispose {
            prefs.unregisterOnSharedPreferenceChangeListener(listener)
        }
    }
    
    val configuration = LocalConfiguration.current
    val isLandscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE

    val syncedFramesCount = remember(syncedJsonState) {
        try {
            val config = com.google.gson.Gson().fromJson(syncedJsonState, com.example.photobooth.data.FrameConfig::class.java)
            config?.frames?.size ?: 0
        } catch (e: Exception) {
            0
        }
    }

    // Tab Selection
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabTitles = listOf("Dashboard", "Pengaturan", "Printer", "Riwayat Foto", "Kupon")
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
    var colorPrinterMode by remember { mutableStateOf(configManager.colorPrinterMode) }
    var isColorPrinterModeDropdownExpanded by remember { mutableStateOf(false) }
    var printDensity by remember { mutableStateOf(configManager.printDensity) }
    var printerAutoCut by remember { mutableStateOf(configManager.printerAutoCut) }
    var thermalContrast by remember { mutableStateOf(configManager.thermalContrast) }
    var thermalBrightness by remember { mutableStateOf(configManager.thermalBrightness) }
    var thermalSharpness by remember { mutableStateOf(configManager.thermalSharpness) }
    var thermalDenoise by remember { mutableStateOf(configManager.thermalDenoise) }
    var useBiometric by remember { mutableStateOf(configManager.useBiometric) }
    var wifiIpAddress by remember { mutableStateOf("") }
    var wifiPort by remember { mutableStateOf("9100") }
    
    var kioskMode by remember { mutableStateOf(configManager.kioskMode) }
    var activeEventId by remember { mutableStateOf(configManager.activeEventId) }
    var showEventDialog by remember { mutableStateOf(false) }

    val eventsList = remember(syncedJsonState) {
        val list = mutableListOf<com.example.photobooth.data.EventInfo>()
        list.add(com.example.photobooth.data.EventInfo("general", "Umum (Default)", "UMUM"))
        val syncedJson = syncedJsonState
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
    var isScanningPrinters by remember { mutableStateOf(false) }
    var lastScanTime by remember { mutableStateOf("") }
    var packagesList by remember { mutableStateOf<List<PackageDto>>(emptyList()) }
    var selectedPackageId by remember { mutableStateOf("any") }
    var isPackageDropdownExpanded by remember { mutableStateOf(false) }
    var isCreatingCoupon by remember { mutableStateOf(false) }
    var couponQtyInput by remember { mutableStateOf("1") }
    var isThermalPrintChecked by remember { mutableStateOf(true) }

    // Live Server Connectivity Status
    var serverOnline by remember { mutableStateOf<Boolean?>(null) }
    LaunchedEffect(backendUrl) {
        serverOnline = null
        try {
            val api = NetworkClient.getApi(backendUrl)
            val response = api.getPhotoHistory()
            serverOnline = response.isSuccessful
            
            // Fetch packages list for coupon printing
            val pkgResponse = api.getPackages()
            if (pkgResponse.isSuccessful && pkgResponse.body() != null) {
                packagesList = pkgResponse.body()!!
            }
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

    val requestBtPermissionsLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions(),
        onResult = { permissions ->
            val connectGranted = permissions[android.Manifest.permission.BLUETOOTH_CONNECT] ?: false
            if (connectGranted) {
                // Perform the USB and BT scans
                usbDevices.clear()
                bluetoothDevices.clear()
                val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
                usbManager?.deviceList?.values?.forEach { device ->
                    var isPrinter = false
                    for (i in 0 until device.interfaceCount) {
                        val intr = device.getInterface(i)
                        if (intr.interfaceClass == 7) {
                            isPrinter = true
                            break
                        }
                        for (j in 0 until intr.endpointCount) {
                            val ep = intr.getEndpoint(j)
                            if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_OUT) {
                                isPrinter = true
                                break
                            }
                        }
                        if (isPrinter) break
                    }
                    if (isPrinter) usbDevices.add(device)
                }
                try {
                    val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
                    val adapter = bluetoothManager?.adapter
                    if (adapter != null && adapter.isEnabled) {
                        @SuppressLint("MissingPermission")
                        adapter.bondedDevices.forEach { device ->
                            if (isBluetoothDevicePrinter(device)) {
                                bluetoothDevices.add(Pair(device.name ?: "Unknown Printer", device.address))
                            }
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            } else {
                Toast.makeText(context, "Izin Bluetooth ditolak. Gagal memindai printer Bluetooth.", Toast.LENGTH_SHORT).show()
            }
        }
    )

    // Scan function — runs on IO thread with loading state
    val scanPrinters: () -> Unit = {
        scope.launch {
            isScanningPrinters = true
            usbDevices.clear()
            bluetoothDevices.clear()

            // Simulasikan delay kecil agar UI sempat menampilkan spinner
            withContext(Dispatchers.IO) { Thread.sleep(600) }

            // USB — scan perangkat yang terhubung
            val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
            usbManager?.deviceList?.values?.forEach { device ->
                var isPrinter = false
                for (i in 0 until device.interfaceCount) {
                    val intr = device.getInterface(i)
                    if (intr.interfaceClass == 7) { isPrinter = true; break }
                    for (j in 0 until intr.endpointCount) {
                        val ep = intr.getEndpoint(j)
                        if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_OUT) {
                            isPrinter = true; break
                        }
                    }
                    if (isPrinter) break
                }
                if (isPrinter) usbDevices.add(device)
            }

            // Bluetooth — scan perangkat yang sudah dipasangkan
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val hasConnect = ContextCompat.checkSelfPermission(
                    context, android.Manifest.permission.BLUETOOTH_CONNECT
                ) == PackageManager.PERMISSION_GRANTED
                val hasScan = ContextCompat.checkSelfPermission(
                    context, android.Manifest.permission.BLUETOOTH_SCAN
                ) == PackageManager.PERMISSION_GRANTED

                if (!hasConnect || !hasScan) {
                    requestBtPermissionsLauncher.launch(
                        arrayOf(
                            android.Manifest.permission.BLUETOOTH_CONNECT,
                            android.Manifest.permission.BLUETOOTH_SCAN
                        )
                    )
                } else {
                    try {
                        val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
                        val adapter = bluetoothManager?.adapter
                        if (adapter != null && adapter.isEnabled) {
                            @SuppressLint("MissingPermission")
                            adapter.bondedDevices.forEach { device ->
                                if (isBluetoothDevicePrinter(device)) {
                                    bluetoothDevices.add(Pair(device.name ?: "Unknown Printer", device.address))
                                }
                            }
                        }
                    } catch (e: Exception) { e.printStackTrace() }
                }
            } else {
                try {
                    val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
                    val adapter = bluetoothManager?.adapter
                    if (adapter != null && adapter.isEnabled) {
                        @SuppressLint("MissingPermission")
                        adapter.bondedDevices.forEach { device ->
                            if (isBluetoothDevicePrinter(device)) {
                                bluetoothDevices.add(Pair(device.name ?: "Unknown Printer", device.address))
                            }
                        }
                    }
                } catch (e: Exception) { e.printStackTrace() }
            }

            // Catat waktu scan selesai
            val sdf = SimpleDateFormat("HH:mm:ss", Locale.getDefault())
            lastScanTime = sdf.format(Date())
            isScanningPrinters = false
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

    val themeColors = remember(isAdminDarkModeState) {
        AdminThemeColors(
            bgMain = BgMain,
            bgCard = BgCard,
            bgInnerCard = BgInnerCard,
            borderColor = BorderColor,
            textMain = White,
            textMuted = Gray
        )
    }

    CompositionLocalProvider(LocalAdminThemeColors provides themeColors) {
        Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("MENU ADMIN KIOSK", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back", tint = White)
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = BgMain,
                    titleContentColor = White
                )
            )
        },
        containerColor = BgMain,
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
                containerColor = BgCard,
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
                                color = if (selectedTab == index) White else Gray
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
                        printerAddress = printerAddress,
                        historyListState = historyListState,
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
                                        .background(BgCard)
                                        .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                                        .padding(12.dp)
                                ) {
                                    Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                        Text("SERVER STATUS", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
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
                                                color = White,
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
                                        .background(BgCard)
                                        .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                                        .padding(12.dp)
                                ) {
                                    Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                        Text("SYNCED FRAMES", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                        Text(
                                            text = "$syncedFramesCount Bingkai",
                                            color = White,
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
                                        .background(BgCard)
                                        .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                                        .padding(12.dp)
                                ) {
                                    Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                                        Text("ACTIVE PRINTER", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                        val activeThermalPrinterName = remember(printerAddress, historyListState) {
                                            val found = historyListState.firstOrNull { it.address == printerAddress }
                                            found?.name ?: if (printerAddress.isNotEmpty()) {
                                                if (printerAddress.startsWith("BT:")) "Bluetooth Printer"
                                                else if (printerAddress.startsWith("USB:")) "USB Printer"
                                                else if (printerAddress.startsWith("NET:")) "Network Printer"
                                                else "Thermal Printer"
                                            } else {
                                                "Printer Thermal"
                                            }
                                        }
                                        Text(
                                            text = when (printerType) {
                                                "THERMAL" -> activeThermalPrinterName
                                                "COLOR" -> "COLOR PDF"
                                                "AUTO" -> "AUTO ($activeThermalPrinterName & COLOR)"
                                                else -> "NONE"
                                            },
                                            color = White,
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
                                        focusedTextColor = White,
                                        unfocusedTextColor = White,
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
                                        focusedTextColor = White,
                                        unfocusedTextColor = White,
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
                                        Text("Gunakan Sensor Sidik Jari (Biometrik)", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        Text("Otentikasi biometrik cepat untuk masuk menu admin tanpa PIN", color = Gray, fontSize = 12.sp)
                                    }
                                    Switch(
                                        checked = useBiometric,
                                        onCheckedChange = { useBiometric = it },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Color.White,
                                            checkedTrackColor = Color(0xFFE63946),
                                            uncheckedThumbColor = Gray,
                                            uncheckedTrackColor = BorderColor
                                        )
                                    )
                                }
                                Spacer(modifier = Modifier.height(16.dp))

                                
                            }
                        }

                        val SyncTemplatesBlock: @Composable () -> Unit = {
                            AdminCard(title = "Sinkronisasi Bingkai (Dynamic Sync)") {
                                Text(
                                    text = "Mendownload katalog bingkai (.json) dan ornamen bingkai (.png) dari aaPanel agar aplikasi dapat bekerja 100% offline.",
                                    color = Gray,
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
                                        Text("Menghubungkan & mengunduh...", color = Gray, fontSize = 13.sp)
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
                                        colors = ButtonDefaults.buttonColors(containerColor = BorderColor),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Text("SYNC KATALOG SEKARANG", fontWeight = FontWeight.Bold, color = White)
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
                                        focusedTextColor = White,
                                        unfocusedTextColor = White,
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
                                        focusedTextColor = White,
                                        unfocusedTextColor = White,
                                        focusedBorderColor = Color(0xFFE63946)
                                    ),
                                    modifier = Modifier.fillMaxWidth()
                                )
                                Spacer(modifier = Modifier.height(16.dp))
                                
                            }
                        }

                        val KioskModeBlock: @Composable () -> Unit = {
                            AdminCard(title = "Mode Kiosk & Pengelolaan Event") {
                                Text("Pilih Mode Operasional Kiosk:", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
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
                                        Text("Multi-Event (Kode)", color = White, fontSize = 12.sp)
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
                                        Text("Satu Event Terkunci", color = White, fontSize = 12.sp)
                                    }
                                }
                                
                                Spacer(modifier = Modifier.height(16.dp))
                                
                                if (kioskMode == "DEDICATED") {
                                    val currentEventName = eventsList.firstOrNull { it.id == activeEventId }?.name ?: "Pilih Event"
                                    Text("Pilih Event Aktif Acara:", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(BorderColor)
                                            .border(1.dp, Color(0xFF3F3F4F), RoundedCornerShape(8.dp))
                                            .clickable { showEventDialog = true }
                                            .padding(16.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(currentEventName, color = White, fontSize = 14.sp)
                                            Text("Ubah ▾", color = Color(0xFFE63946), fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                    Spacer(modifier = Modifier.height(16.dp))
                                }
                                
                                
                            }
                        }

                        // Kiosk Theme Customization
                        var activeThemeState by remember { mutableStateOf(configManager.appTheme) }
                        var isThemeDropdownExpanded by remember { mutableStateOf(false) }
                        val themeList = listOf(
                            Pair("NEON_RED", "Neon Red (Modern)"),
                            Pair("CUTE_PASTEL", "Cute Pastel (Wood)"),
                            Pair("CUTE_NARA", "Cute Nara (Pinky Flower)"),
                            Pair("LUXURY_GOLD", "Luxury Gold (Wedding)"),
                            Pair("MINIMAL_MODERN", "Minimal Modern (Clean & Creative)"),
                            Pair("CREATIVE_DYNAMIC", "Creative Dynamic (Glowing)")
                        )
                        val activeThemeName = themeList.firstOrNull { it.first == activeThemeState }?.second ?: activeThemeState
                        
                        val ThemeBlock: @Composable () -> Unit = {
                            AdminCard(title = "Tema Tampilan Kiosk (Total Layout)") {
                                Text(
                                    text = "Pilih gaya visual total untuk Kiosk. Tema akan merubah keseluruhan tata letak, gaya tombol, ornamen, dan tipografi.",
                                    color = Gray,
                                    fontSize = 12.sp,
                                    lineHeight = 16.sp
                                )
                                Spacer(modifier = Modifier.height(12.dp))
                                
                                Box(modifier = Modifier.fillMaxWidth()) {
                                    OutlinedButton(
                                        onClick = { isThemeDropdownExpanded = true },
                                        modifier = Modifier.fillMaxWidth(),
                                        border = BorderStroke(1.dp, BorderColor),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                        shape = RoundedCornerShape(8.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(text = activeThemeName, color = White, fontSize = 14.sp)
                                            Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                        }
                                    }
                                    DropdownMenu(
                                        expanded = isThemeDropdownExpanded,
                                        onDismissRequest = { isThemeDropdownExpanded = false },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(BgInnerCard)
                                            .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                    ) {
                                        themeList.forEach { (themeId, themeName) ->
                                            DropdownMenuItem(
                                                text = { Text(themeName, color = White) },
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
                                
                                Spacer(modifier = Modifier.height(16.dp))
                                HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 4.dp))
                                Spacer(modifier = Modifier.height(16.dp))
                                
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("Mode Gelap Admin", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        Text("Ubah tampilan menu admin ini menjadi mode gelap atau terang.", color = Gray, fontSize = 11.sp)
                                    }
                                    Switch(
                                        checked = isAdminDarkModeState,
                                        onCheckedChange = { isChecked ->
                                            isAdminDarkModeState = isChecked
                                            configManager.isAdminDarkMode = isChecked
                                        },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Color.White,
                                            checkedTrackColor = Color(0xFFE63946),
                                            uncheckedThumbColor = Gray,
                                            uncheckedTrackColor = BorderColor
                                        )
                                    )
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
                                            Text("Versi Sekarang", color = Gray, fontSize = 11.sp)
                                            Text("v$currentVersionName ($currentVersionCode)", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        }
                                        if (updateInfo != null) {
                                            Column(horizontalAlignment = Alignment.End) {
                                                Text("Versi Terbaru", color = Gray, fontSize = 11.sp)
                                                Text("v${updateInfo!!.versionName} (${updateInfo!!.versionCode})", color = Color(0xFF52B788), fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                    
                                    HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 4.dp))
                                    
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
                                                Text("Memeriksa pembaruan...", color = Gray, fontSize = 13.sp)
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
                                                colors = ButtonDefaults.buttonColors(containerColor = BorderColor),
                                                modifier = Modifier.fillMaxWidth()
                                            ) {
                                                Text("PERIKSA PEMBARUAN", fontWeight = FontWeight.Bold, color = White)
                                            }
                                        }
                                    } else {
                                        val hasNewVersion = updateInfo!!.versionCode > currentVersionCode
                                        if (hasNewVersion) {
                                            Text(
                                                text = "Pembaruan tersedia! Catatan rilis:\n${updateInfo!!.changeLog}",
                                                color = White,
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
                                                        trackColor = BorderColor,
                                                        modifier = Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(4.dp))
                                                    )
                                                    val pct = ((downloadProgress ?: 0f) * 100).toInt()
                                                    Text("Mengunduh update: $pct%", color = Gray, fontSize = 11.sp)
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
                                                colors = ButtonDefaults.buttonColors(containerColor = BorderColor),
                                                modifier = Modifier.fillMaxWidth()
                                            ) {
                                                Text("OK", fontWeight = FontWeight.Bold, color = White)
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Responsive Column Layout
                        Column(
                            modifier = Modifier
                                .fillMaxSize()
                                .verticalScroll(rememberScrollState()),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            QuickStatsBlock()
                            
                            if (isLandscape) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        ServerConfigBlock()
                                        SyncTemplatesBlock()
                                        CaptureTimingsBlock()
                                    }
                                    
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        KioskModeBlock()
                                        ThemeBlock()
                                        AppUpdateBlock()
                                    }
                                }
                            } else {
                                ServerConfigBlock()
                                SyncTemplatesBlock()
                                CaptureTimingsBlock()
                                KioskModeBlock()
                                ThemeBlock()
                                AppUpdateBlock()
                            }
                            
                            Spacer(modifier = Modifier.height(8.dp))
                            
                            Button(
                                onClick = {
                                    configManager.backendUrl = backendUrl
                                    configManager.adminPin = adminPin
                                    configManager.useBiometric = useBiometric
                                    
                                    val cd = countdownSeconds.toIntOrNull() ?: 5
                                    val ts = totalShots.toIntOrNull() ?: 4
                                    configManager.countdownSeconds = cd
                                    configManager.totalShots = ts
                                    
                                    configManager.kioskMode = kioskMode
                                    configManager.activeEventId = activeEventId
                                    
                                    Toast.makeText(context, "Semua setelan berhasil disimpan!", Toast.LENGTH_SHORT).show()
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(54.dp)
                            ) {
                                Text("SIMPAN SEMUA PERUBAHAN", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color.White)
                            }
                        }

                        // Dialog for requesting unknown sources permission
                        if (isInstallPermissionNeeded) {
                            AlertDialog(
                                onDismissRequest = { isInstallPermissionNeeded = false },
                                title = { Text("Izin Instalasi Diperlukan", color = White) },
                                text = { 
                                    Text(
                                        "Untuk memperbarui aplikasi Photobooth langsung dari kiosk, Anda harus memberikan izin untuk menginstal aplikasi dari sumber tidak dikenal.",
                                        color = Gray
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
                                        Text("Buka Setelan", color = Color.White)
                                    }
                                },
                                dismissButton = {
                                    TextButton(onClick = { isInstallPermissionNeeded = false }) {
                                        Text("Batal", color = Gray)
                                    }
                                },
                                containerColor = BgInnerCard
                            )
                        }

                        // Event Selection Dialog
                        if (showEventDialog) {
                            Dialog(onDismissRequest = { showEventDialog = false }) {
                                Card(
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = BgInnerCard)
                                ) {
                                    Column(
                                        modifier = Modifier.padding(16.dp),
                                        verticalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Text("Pilih Event Aktif", color = White, fontWeight = FontWeight.Bold, fontSize = 16.sp)
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
                                                Text(event.name, color = White, fontSize = 14.sp)
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
                                        Text("Aktifkan Printer Struk", color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                        Text("Gunakan printer thermal untuk mencetak struk jepretan", color = Gray, fontSize = 11.sp)
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
                                            uncheckedThumbColor = Gray,
                                            uncheckedTrackColor = BorderColor
                                        )
                                    )
                                }

                                if (isThermalEnabled) {
                                    HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 4.dp))

                                    // Protocol Dropdown Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Protokol:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isProtocolDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, BorderColor),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedProtocolText, color = White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isProtocolDropdownExpanded,
                                                onDismissRequest = { isProtocolDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(BgInnerCard)
                                                    .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                            ) {
                                                protocolList.forEach { (mode, label) ->
                                                    DropdownMenuItem(
                                                        text = { Text(label, color = White) },
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
                                        Text("Lebar Kertas:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isPaperWidthDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, BorderColor),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedPaperWidthText, color = White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isPaperWidthDropdownExpanded,
                                                onDismissRequest = { isPaperWidthDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(BgInnerCard)
                                                    .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                            ) {
                                                paperWidthList.forEach { (width, label) ->
                                                    DropdownMenuItem(
                                                        text = { Text(label, color = White) },
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
                                        Text("Kepekatan:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isDensityDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, BorderColor),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedDensityText, color = White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isDensityDropdownExpanded,
                                                onDismissRequest = { isDensityDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(BgInnerCard)
                                                    .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                            ) {
                                                densityList.forEach { valDensity ->
                                                    DropdownMenuItem(
                                                        text = { Text("Level $valDensity", color = White) },
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
                                            Text("Potong Kertas Otomatis (Auto-Cut)", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Text("Memotong kertas struk secara otomatis setelah selesai mencetak", color = Gray, fontSize = 11.sp)
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
                                                uncheckedThumbColor = Gray,
                                                uncheckedTrackColor = BorderColor
                                            )
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(10.dp))
                                    HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 4.dp))
                                    Spacer(modifier = Modifier.height(6.dp))

                                    // Contrast Slider Row
                                    Column(modifier = Modifier.fillMaxWidth()) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Kontras Cetak (Contrast):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text(String.format("%.1f", thermalContrast), color = Color(0xFFE63946), fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                        }
                                        Slider(
                                            value = thermalContrast,
                                            onValueChange = {
                                                thermalContrast = it
                                                configManager.thermalContrast = it
                                            },
                                            valueRange = 0.5f..3.0f,
                                            colors = SliderDefaults.colors(
                                                thumbColor = Color(0xFFE63946),
                                                activeTrackColor = Color(0xFFE63946)
                                            )
                                        )
                                        Text("Nilai default: 1.2. Meningkatkan perbedaan hitam-putih cetakan.", color = Gray, fontSize = 10.sp)
                                    }

                                    Spacer(modifier = Modifier.height(8.dp))

                                    // Brightness Slider Row
                                    Column(modifier = Modifier.fillMaxWidth()) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Kecerahan Cetak (Brightness):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text(String.format("%s%.1f", if (thermalBrightness >= 0) "+" else "", thermalBrightness), color = Color(0xFFE63946), fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                        }
                                        Slider(
                                            value = thermalBrightness,
                                            onValueChange = {
                                                thermalBrightness = it
                                                configManager.thermalBrightness = it
                                            },
                                            valueRange = -50f..50f,
                                            colors = SliderDefaults.colors(
                                                thumbColor = Color(0xFFE63946),
                                                activeTrackColor = Color(0xFFE63946)
                                            )
                                        )
                                        Text("Nilai default: +10.0. Mencerahkan area bayangan agar tidak hitam pekat.", color = Gray, fontSize = 10.sp)
                                    }

                                    Spacer(modifier = Modifier.height(8.dp))

                                    // Sharpness Slider Row
                                    Column(modifier = Modifier.fillMaxWidth()) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Ketajaman Cetak (Sharpness):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                            Text(String.format("%.1f", thermalSharpness), color = Color(0xFFE63946), fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                        }
                                        Slider(
                                            value = thermalSharpness,
                                            onValueChange = {
                                                thermalSharpness = it
                                                configManager.thermalSharpness = it
                                            },
                                            valueRange = 0.0f..2.0f,
                                            colors = SliderDefaults.colors(
                                                thumbColor = Color(0xFFE63946),
                                                activeTrackColor = Color(0xFFE63946)
                                            )
                                        )
                                        Text("Nilai default: 0.4. Memperjelas teks/garis tepi.", color = Gray, fontSize = 10.sp)
                                    }

                                    Spacer(modifier = Modifier.height(10.dp))

                                    // Denoise Toggle Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text("Pengurangan Noise (Denoise)", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Text("Menyaring noise bintik dari tangkapan kamera", color = Gray, fontSize = 11.sp)
                                        }
                                        Switch(
                                            checked = thermalDenoise,
                                            onCheckedChange = {
                                                thermalDenoise = it
                                                configManager.thermalDenoise = it
                                            },
                                            colors = SwitchDefaults.colors(
                                                checkedThumbColor = Color.White,
                                                checkedTrackColor = Color(0xFFE63946),
                                                uncheckedThumbColor = Gray,
                                                uncheckedTrackColor = BorderColor
                                            )
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))
                                    HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 4.dp))
                                    Spacer(modifier = Modifier.height(8.dp))

                                    // Dither Preview Title
                                    Text(
                                        text = "Pratinjau Hasil Cetak (Dither Simulation)",
                                        color = Color(0xFFE63946),
                                        fontSize = 14.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                    Text(
                                        text = "Simulasi tampilan foto pada kertas thermal (hitam-putih 1-bit) berdasarkan setelan Kontras, Kecerahan, Ketajaman & Denoise di atas.",
                                        color = Gray,
                                        fontSize = 11.sp,
                                        lineHeight = 15.sp
                                    )

                                    Spacer(modifier = Modifier.height(10.dp))

                                    // Generate and process dummy bitmaps
                                    val dummyPhoto = remember {
                                        val w = 300
                                        val h = 400
                                        val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
                                        val canvas = Canvas(bmp)
                                        val paint = Paint().apply { isAntiAlias = true }

                                        // Background with linear gradient (gray-to-white)
                                        paint.shader = android.graphics.LinearGradient(
                                            0f, 0f, w.toFloat(), h.toFloat(),
                                            android.graphics.Color.rgb(240, 240, 240),
                                            android.graphics.Color.rgb(100, 100, 100),
                                            android.graphics.Shader.TileMode.CLAMP
                                        )
                                        canvas.drawRect(0f, 0f, w.toFloat(), h.toFloat(), paint)
                                        paint.shader = null

                                        // Draw Face (Skin)
                                        paint.color = android.graphics.Color.rgb(235, 195, 165)
                                        canvas.drawOval(w/2f - 65f, h/2f - 90f, w/2f + 65f, h/2f + 40f, paint)

                                        // Hair
                                        paint.color = android.graphics.Color.rgb(50, 45, 45)
                                        canvas.drawArc(w/2f - 75f, h/2f - 110f, w/2f + 75f, h/2f - 20f, 180f, 180f, true, paint)
                                        canvas.drawRect(w/2f - 75f, h/2f - 60f, w/2f - 55f, h/2f + 20f, paint)
                                        canvas.drawRect(w/2f + 55f, h/2f - 60f, w/2f + 75f, h/2f + 20f, paint)

                                        // Eyes
                                        paint.color = android.graphics.Color.WHITE
                                        canvas.drawOval(w/2f - 30f, h/2f - 40f, w/2f - 10f, h/2f - 25f, paint)
                                        canvas.drawOval(w/2f + 10f, h/2f - 40f, w/2f + 30f, h/2f - 25f, paint)
                                        paint.color = android.graphics.Color.BLACK
                                        canvas.drawCircle(w/2f - 20f, h/2f - 32f, 5f, paint)
                                        canvas.drawCircle(w/2f + 20f, h/2f - 32f, 5f, paint)

                                        // Smile
                                        paint.color = android.graphics.Color.rgb(200, 80, 80)
                                        paint.style = Paint.Style.STROKE
                                        paint.strokeWidth = 4f
                                        canvas.drawArc(w/2f - 20f, h/2f - 5f, w/2f + 20f, h/2f + 15f, 0f, 180f, false, paint)

                                        // Shaded sphere (test for brightness and contrast gradient)
                                        paint.style = Paint.Style.FILL
                                        paint.shader = android.graphics.RadialGradient(
                                            w/2f + 65f, h - 85f, 50f,
                                            android.graphics.Color.WHITE,
                                            android.graphics.Color.BLACK,
                                            android.graphics.Shader.TileMode.CLAMP
                                        )
                                        canvas.drawCircle(w/2f + 55f, h - 75f, 35f, paint)
                                        paint.shader = null

                                        // Checkerboard (test for sharpness)
                                        paint.color = android.graphics.Color.rgb(80, 80, 80)
                                        for (i in 0..4) {
                                            for (j in 0..4) {
                                                 if ((i + j) % 2 == 0) {
                                                     canvas.drawRect(25f + i * 8f, h - 75f + j * 8f, 33f + i * 8f, h - 67f + j * 8f, paint)
                                                 }
                                            }
                                        }

                                        // Text label
                                        paint.color = android.graphics.Color.BLACK
                                        paint.textSize = 18f
                                        paint.typeface = android.graphics.Typeface.create(android.graphics.Typeface.SANS_SERIF, android.graphics.Typeface.BOLD)
                                        canvas.drawText("FOTO DUMMY", 20f, 40f, paint)

                                        bmp
                                    }

                                    val ditheredPhoto = remember(dummyPhoto, thermalContrast, thermalBrightness, thermalSharpness, thermalDenoise) {
                                        com.example.photobooth.print.DitherHelper.ditherFloydSteinberg(
                                            dummyPhoto,
                                            contrast = thermalContrast,
                                            brightness = thermalBrightness,
                                            sharpStrength = thermalSharpness,
                                            denoise = thermalDenoise
                                         )
                                    }

                                    Row(
                                         modifier = Modifier.fillMaxWidth(),
                                         horizontalArrangement = Arrangement.spacedBy(16.dp),
                                         verticalAlignment = Alignment.CenterVertically
                                    ) {
                                         // Column 1: Original
                                         Column(
                                             modifier = Modifier.weight(1f),
                                             horizontalAlignment = Alignment.CenterHorizontally
                                         ) {
                                             Text("Foto Asli", color = Gray, fontSize = 11.sp, modifier = Modifier.padding(bottom = 4.dp))
                                             Box(
                                                 modifier = Modifier
                                                     .aspectRatio(3f / 4f)
                                                     .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                                     .clip(RoundedCornerShape(8.dp))
                                                     .background(Color.White)
                                             ) {
                                                 Image(
                                                     bitmap = dummyPhoto.asImageBitmap(),
                                                     contentDescription = "Original Dummy Photo",
                                                     modifier = Modifier.fillMaxSize()
                                                 )
                                             }
                                         }

                                         // Column 2: Simulated Thermal
                                         Column(
                                             modifier = Modifier.weight(1f),
                                             horizontalAlignment = Alignment.CenterHorizontally
                                         ) {
                                             Text("Hasil Simulasi Cetak", color = Gray, fontSize = 11.sp, modifier = Modifier.padding(bottom = 4.dp))
                                             Box(
                                                 modifier = Modifier
                                                     .aspectRatio(3f / 4f)
                                                     .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                                     .clip(RoundedCornerShape(8.dp))
                                                     .background(Color.White)
                                             ) {
                                                 Image(
                                                     bitmap = ditheredPhoto.asImageBitmap(),
                                                     contentDescription = "Dithered Print Preview",
                                                     modifier = Modifier.fillMaxSize()
                                                 )
                                             }
                                         }
                                    }
                                }
                            }
                        }

                        val PrinterHistoryCard: @Composable () -> Unit = {
                            Card(
                                colors = CardDefaults.cardColors(containerColor = BgCard),
                                border = BorderStroke(1.dp, BorderColor),
                                shape = RoundedCornerShape(16.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(
                                    modifier = Modifier.padding(16.dp),
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Text("Riwayat & Prioritas Printer (Auto-Connect)", color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                    Text(
                                        text = "Aplikasi akan otomatis mendeteksi dan menggunakan printer teratas yang berstatus TERSEDIA saat dibuka (startup) atau sebagai cadangan jika printer utama offline.",
                                        color = Gray,
                                        fontSize = 11.sp,
                                        lineHeight = 15.sp
                                    )
                                    
                                    if (historyListState.isEmpty()) {
                                        Text(
                                            text = "Belum ada riwayat printer terhubung. Pilih printer di bawah untuk menambahkannya.",
                                            color = Gray,
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
                                                } else if (printer.type == "BT") {
                                                    val mac = printer.address.substring(3)
                                                    bluetoothDevices.any { it.second.equals(mac, ignoreCase = true) }
                                                } else if (printer.type == "NET") {
                                                    isNetworkConnected(context)
                                                } else {
                                                    false
                                                }
                                            }
                                            
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .clip(RoundedCornerShape(8.dp))
                                                    .background(if (printerAddress == printer.address) Color(0xFFE63946).copy(alpha = 0.08f) else BorderColor.copy(alpha = 0.4f))
                                                    .border(1.dp, if (printerAddress == printer.address) Color(0xFFE63946) else BorderColor, RoundedCornerShape(8.dp))
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
                                                                    color = White,
                                                                    fontSize = 10.sp,
                                                                    fontWeight = FontWeight.Bold
                                                                )
                                                            }
                                                            Spacer(modifier = Modifier.width(8.dp))
                                                            Text(printer.name, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                        }
                                                        Spacer(modifier = Modifier.height(4.dp))
                                                        Text("${printer.type} | ${printer.address}", color = Gray, fontSize = 10.sp)
                                                        Spacer(modifier = Modifier.height(4.dp))
                                                        
                                                        // Availability label
                                                        Box(
                                                            modifier = Modifier
                                                                .clip(RoundedCornerShape(4.dp))
                                                                .background(if (isAvailable) Color(0xFF52B788).copy(alpha = 0.15f) else Gray.copy(alpha = 0.15f))
                                                                .padding(horizontal = 6.dp, vertical = 2.dp)
                                                        ) {
                                                            Text(
                                                                text = if (isAvailable) "TERSEDIA" else "OFFLINE",
                                                                color = if (isAvailable) Color(0xFF52B788) else Gray,
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
                                                                Text("▲", color = White, fontSize = 10.sp)
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
                                                                Text("▼", color = White, fontSize = 10.sp)
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

                                // ── Header: judul + tombol refresh ──────────────────────────
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Port Printer Thermal Terdeteksi:", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    IconButton(
                                        onClick = { scanPrinters() },
                                        enabled = !isScanningPrinters
                                    ) {
                                        if (isScanningPrinters) {
                                            CircularProgressIndicator(
                                                modifier = Modifier.size(20.dp),
                                                color = Color(0xFFE63946),
                                                strokeWidth = 2.dp
                                            )
                                        } else {
                                            Icon(
                                                imageVector = Icons.Default.Refresh,
                                                contentDescription = "Pindai Ulang",
                                                tint = Color(0xFFE63946)
                                            )
                                        }
                                    }
                                }

                                // ── Banner status scan ──────────────────────────────────────
                                if (isScanningPrinters) {
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(Color(0xFF1A1A2E))
                                            .border(1.dp, Color(0xFF3A3A5C), RoundedCornerShape(8.dp))
                                            .padding(horizontal = 12.dp, vertical = 10.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                                    ) {
                                        CircularProgressIndicator(
                                            modifier = Modifier.size(16.dp),
                                            color = Color(0xFFE63946),
                                            strokeWidth = 2.dp
                                        )
                                        Text(
                                            "Sedang memindai printer USB & Bluetooth yang terpasang...",
                                            color = Color(0xFFAAAAAA),
                                            fontSize = 12.sp
                                        )
                                    }
                                } else if (lastScanTime.isNotEmpty()) {
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .size(7.dp)
                                                .clip(CircleShape)
                                                .background(Color(0xFF52B788))
                                        )
                                        Text(
                                            "Daftar diperbarui pukul $lastScanTime · ${bluetoothDevices.size} BT, ${usbDevices.size} USB ditemukan",
                                            color = Color(0xFF52B788),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(8.dp))

                                // ── Dropdown pilih printer ──────────────────────────────────
                                Box(modifier = Modifier.fillMaxWidth()) {
                                    OutlinedButton(
                                        onClick = { isPrinterPortDropdownExpanded = true },
                                        modifier = Modifier.fillMaxWidth(),
                                        border = BorderStroke(1.dp, BorderColor),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                        shape = RoundedCornerShape(8.dp),
                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(text = selectedPrinterText, color = White, fontSize = 13.sp, modifier = Modifier.weight(1f))
                                            Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                        }
                                    }
                                    DropdownMenu(
                                        expanded = isPrinterPortDropdownExpanded,
                                        onDismissRequest = { isPrinterPortDropdownExpanded = false },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .background(BgInnerCard)
                                            .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                    ) {
                                        if (printerOptions.isEmpty()) {
                                            DropdownMenuItem(
                                                text = {
                                                    Column {
                                                        Text("Tidak ada printer terdeteksi", color = Gray)
                                                        Text("Tekan ikon refresh di atas untuk memindai ulang", color = Gray, fontSize = 11.sp)
                                                    }
                                                },
                                                onClick = { isPrinterPortDropdownExpanded = false }
                                            )
                                        } else {
                                            printerOptions.forEach { (addr, info) ->
                                                val (dispName, type) = info
                                                val isCurrentlyActive = addr == printerAddress
                                                DropdownMenuItem(
                                                    text = {
                                                        Row(
                                                            modifier = Modifier.fillMaxWidth(),
                                                            horizontalArrangement = Arrangement.SpaceBetween,
                                                            verticalAlignment = Alignment.CenterVertically
                                                        ) {
                                                            Column(modifier = Modifier.weight(1f)) {
                                                                Text("[$type] $dispName", color = White, fontSize = 13.sp)
                                                                Text(addr.substringAfter(":"), color = Gray, fontSize = 10.sp)
                                                            }
                                                            if (isCurrentlyActive) {
                                                                Box(
                                                                    modifier = Modifier
                                                                        .clip(RoundedCornerShape(4.dp))
                                                                        .background(Color(0xFF52B788))
                                                                        .padding(horizontal = 6.dp, vertical = 2.dp)
                                                                ) {
                                                                    Text("AKTIF", color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                                                }
                                                            }
                                                        }
                                                    },
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

                                // ── Badge status printer aktif ──────────────────────────────
                                Spacer(modifier = Modifier.height(8.dp))
                                val isActivePrinterVisible = printerAddress.isNotEmpty() && printerOptions.any { it.first == printerAddress }
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(
                                            if (isActivePrinterVisible) Color(0xFF0D2B1A) else Color(0xFF2B0D0D)
                                        )
                                        .border(
                                            1.dp,
                                            if (isActivePrinterVisible) Color(0xFF52B788) else Color(0xFF8B2020),
                                            RoundedCornerShape(8.dp)
                                        )
                                        .padding(horizontal = 12.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(8.dp)
                                            .clip(CircleShape)
                                            .background(
                                                if (isActivePrinterVisible) Color(0xFF52B788) else Color(0xFFE63946)
                                            )
                                    )
                                    Column {
                                        Text(
                                            text = if (isActivePrinterVisible) "✓ Printer aktif terdeteksi dalam daftar scan"
                                                   else if (printerAddress.isEmpty()) "Belum ada printer yang dipilih"
                                                   else "⚠ Printer yang tersimpan tidak terdeteksi saat ini — coba pindai ulang atau pastikan Bluetooth/USB aktif",
                                            color = if (isActivePrinterVisible) Color(0xFF52B788) else Color(0xFFE4A445),
                                            fontSize = 11.sp,
                                            lineHeight = 15.sp
                                        )
                                        if (printerAddress.isNotEmpty()) {
                                            Text(
                                                text = "Tersimpan: $printerAddress",
                                                color = Gray,
                                                fontSize = 10.sp
                                            )
                                        }
                                    }
                                }

                                if (printerOptions.isEmpty() && !isScanningPrinters) {
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(
                                        "Tidak ada printer terdeteksi. Pastikan printer sudah dipasangkan via Bluetooth atau kabel USB OTG terhubung, lalu tekan refresh.",
                                        color = Gray,
                                        fontSize = 12.sp,
                                        lineHeight = 16.sp
                                    )
                                }

                                HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 12.dp))
                                
                                Text("Atau Hubungkan Printer WiFi / Network (LAN):", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(6.dp))
                                
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    OutlinedTextField(
                                        value = wifiIpAddress,
                                        onValueChange = { wifiIpAddress = it },
                                        placeholder = { Text("IP Address (cth: 192.168.1.100)", color = Gray, fontSize = 12.sp) },
                                        modifier = Modifier.weight(2f),
                                        singleLine = true,
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Color(0xFFE63946),
                                            unfocusedBorderColor = BorderColor,
                                            focusedTextColor = White,
                                            unfocusedTextColor = White,
                                            focusedContainerColor = Color.Transparent,
                                            unfocusedContainerColor = Color.Transparent
                                        ),
                                        shape = RoundedCornerShape(8.dp)
                                    )
                                    OutlinedTextField(
                                        value = wifiPort,
                                        onValueChange = { wifiPort = it },
                                        placeholder = { Text("Port", color = Gray, fontSize = 12.sp) },
                                        modifier = Modifier.weight(1f),
                                        singleLine = true,
                                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Color(0xFFE63946),
                                            unfocusedBorderColor = BorderColor,
                                            focusedTextColor = White,
                                            unfocusedTextColor = White,
                                            focusedContainerColor = Color.Transparent,
                                            unfocusedContainerColor = Color.Transparent
                                        ),
                                        shape = RoundedCornerShape(8.dp)
                                    )
                                    Button(
                                        onClick = {
                                            if (wifiIpAddress.trim().isNotEmpty()) {
                                                val portVal = wifiPort.trim().ifEmpty { "9100" }
                                                val addr = "NET:${wifiIpAddress.trim()}:$portVal"
                                                printerAddress = addr
                                                configManager.printerAddress = addr
                                                configManager.addPrinterToHistory(addr, "WiFi Printer (${wifiIpAddress.trim()})", "NET")
                                                historyListState = configManager.getPrinterHistory()
                                                Toast.makeText(context, "Printer WiFi berhasil disimpan!", Toast.LENGTH_SHORT).show()
                                            } else {
                                                Toast.makeText(context, "IP Address tidak boleh kosong", Toast.LENGTH_SHORT).show()
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                        shape = RoundedCornerShape(8.dp),
                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 14.dp)
                                    ) {
                                        Text("Hubungkan", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                    }
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
                            AdminCard(title = "Printer Foto Warna") {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("Aktifkan Printer Warna", color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                        Text("Gunakan printer warna untuk mencetak hasil foto", color = Gray, fontSize = 11.sp)
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
                                            uncheckedThumbColor = Gray,
                                            uncheckedTrackColor = BorderColor
                                        )
                                    )
                                }

                                if (isColorEnabled) {
                                    HorizontalDivider(color = BorderColor, modifier = Modifier.padding(vertical = 12.dp))

                                    // Color Print Mode Dropdown Row
                                    val colorPrinterModeList = listOf(
                                        Pair("SYSTEM", "Sistem Android (Spooler)"),
                                        Pair("NOKOPRINT", "Aplikasi NokoPrint"),
                                        Pair("PRINTERSHARE", "Aplikasi PrinterShare"),
                                        Pair("SHARE", "Android Share Sheet (Pilih Manual)")
                                    )
                                    val selectedColorModeText = colorPrinterModeList.firstOrNull { it.first == colorPrinterMode }?.second ?: "Pilih Metode..."

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Metode Cetak:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(100.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isColorPrinterModeDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, BorderColor),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Text(text = selectedColorModeText, color = White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isColorPrinterModeDropdownExpanded,
                                                onDismissRequest = { isColorPrinterModeDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(BgInnerCard)
                                                    .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                            ) {
                                                colorPrinterModeList.forEach { (modeVal, label) ->
                                                    DropdownMenuItem(
                                                        text = { Text(label, color = White) },
                                                        onClick = {
                                                            colorPrinterMode = modeVal
                                                            configManager.colorPrinterMode = modeVal
                                                            isColorPrinterModeDropdownExpanded = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))

                                    Card(
                                        colors = CardDefaults.cardColors(containerColor = BorderColor.copy(alpha = 0.4f)),
                                        border = BorderStroke(1.dp, BorderColor),
                                        shape = RoundedCornerShape(12.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                            when (colorPrinterMode) {
                                                "SYSTEM" -> {
                                                    Text("Panduan Koneksi (Sistem Android):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                    Text("1. Hubungkan printer warna ke tablet menggunakan kabel USB OTG atau jaringan Wi-Fi.", color = Gray, fontSize = 12.sp)
                                                    Text("2. Pastikan Anda telah menginstal plugin cetak (Print Service Plugin) yang sesuai dari Google Play Store sesuai merek printer Anda (misal: Epson Print Service Plugin, HP Print Service, Mopria, dll) dan mengaktifkannya di Pengaturan Android -> Pencetakan.", color = Gray, fontSize = 12.sp)
                                                    Text("3. Saat mencetak, dialog cetak sistem Android akan muncul. Pilih printer Anda di jendela tersebut.", color = Gray, fontSize = 12.sp)
                                                }
                                                "NOKOPRINT" -> {
                                                    Text("Panduan Koneksi (Aplikasi NokoPrint):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                    Text("1. Pastikan Anda telah menginstal aplikasi NokoPrint dari Google Play Store.", color = Gray, fontSize = 12.sp)
                                                    Text("2. Hubungkan printer warna ke tablet (USB OTG, Bluetooth, atau Wi-Fi), buka aplikasi NokoPrint, dan pilih printer untuk konfigurasi awal.", color = Gray, fontSize = 12.sp)
                                                    Text("3. Saat mencetak, foto akan otomatis dikirim dan dibuka di aplikasi NokoPrint untuk pencetakan langsung.", color = Gray, fontSize = 12.sp)
                                                }
                                                "PRINTERSHARE" -> {
                                                    Text("Panduan Koneksi (Aplikasi PrinterShare):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                    Text("1. Pastikan Anda telah menginstal aplikasi PrinterShare dari Google Play Store.", color = Gray, fontSize = 12.sp)
                                                    Text("2. Hubungkan printer warna ke tablet (USB OTG, Bluetooth, atau Wi-Fi), buka aplikasi PrinterShare, dan pilih printer untuk konfigurasi awal.", color = Gray, fontSize = 12.sp)
                                                    Text("3. Saat mencetak, foto akan otomatis dikirim dan dibuka di aplikasi PrinterShare untuk pencetakan langsung.", color = Gray, fontSize = 12.sp)
                                                }
                                                "SHARE" -> {
                                                    Text("Panduan Koneksi (Android Share Sheet):", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                                    Text("1. Cara ini cocok jika Anda ingin menggunakan aplikasi printer kustom lainnya (seperti Epson iPrint, Canon PRINT, Brother iPrint&Scan, dll).", color = Gray, fontSize = 12.sp)
                                                    Text("2. Saat mencetak, dialog 'Berbagi' (Share Sheet) sistem Android akan muncul.", color = Gray, fontSize = 12.sp)
                                                    Text("3. Pilih aplikasi printer yang ingin Anda gunakan dari daftar aplikasi yang muncul.", color = Gray, fontSize = 12.sp)
                                                }
                                            }
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
                        Column(
                            modifier = Modifier
                                .fillMaxSize()
                                .verticalScroll(rememberScrollState()),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            if (isLandscape && isThermalEnabled) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        ThermalSettingsCard()
                                        ColorPrinterCard()
                                    }
                                    
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        PrinterHistoryCard()
                                        ThermalConnectionCard()
                                    }
                                }
                            } else {
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
                                Text("Belum ada foto yang diunggah ke server.", color = Gray)
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
                                            .border(1.dp, BorderColor, RoundedCornerShape(12.dp))
                                            .background(BgCard)
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
                                                color = White,
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

                    // TAB 4: Kupon
                    4 -> {
                        // Define Composable blocks inside TAB 4
                        val CetakKuponCard: @Composable () -> Unit = {
                            AdminCard(title = "Cetak Kupon Baru Kiosk") {
                                Column(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Text("Buat kupon baru dari server backend dan cetak struk kode kupon secara fisik.", color = Gray, fontSize = 11.sp)
                                    
                                    // Pilihan Paket Foto
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Pilih Paket Foto:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(120.dp))
                                        Box(modifier = Modifier.weight(1f)) {
                                            OutlinedButton(
                                                onClick = { isPackageDropdownExpanded = true },
                                                modifier = Modifier.fillMaxWidth(),
                                                border = BorderStroke(1.dp, BorderColor),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth(),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    val selectedText = if (selectedPackageId == "any") "Semua Paket (Bisa Pilih Bebas)" else {
                                                        val pkg = packagesList.find { it.id == selectedPackageId }
                                                        if (pkg != null) "${pkg.name} (Rp ${pkg.price})" else selectedPackageId
                                                    }
                                                    Text(text = selectedText, color = White, fontSize = 13.sp)
                                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 12.sp)
                                                }
                                            }
                                            DropdownMenu(
                                                expanded = isPackageDropdownExpanded,
                                                onDismissRequest = { isPackageDropdownExpanded = false },
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .background(BgInnerCard)
                                                    .border(1.dp, BorderColor, RoundedCornerShape(8.dp))
                                            ) {
                                                DropdownMenuItem(
                                                    text = { Text("Semua Paket (Bisa Pilih Bebas)", color = White, fontSize = 12.sp) },
                                                    onClick = {
                                                        selectedPackageId = "any"
                                                        isPackageDropdownExpanded = false
                                                    }
                                                )
                                                packagesList.forEach { pkg ->
                                                    DropdownMenuItem(
                                                        text = { Text("${pkg.name} (Rp ${pkg.price})", color = White, fontSize = 12.sp) },
                                                        onClick = {
                                                            selectedPackageId = pkg.id
                                                            isPackageDropdownExpanded = false
                                                        }
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    // Input Jumlah Kupon
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Jumlah Kupon:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(120.dp))
                                        OutlinedTextField(
                                            value = couponQtyInput,
                                            onValueChange = { newValue ->
                                                if (newValue.isEmpty() || newValue.all { it.isDigit() }) {
                                                    couponQtyInput = newValue
                                                }
                                            },
                                            singleLine = true,
                                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedTextColor = White,
                                                unfocusedTextColor = White,
                                                focusedBorderColor = Color(0xFFE63946)
                                            ),
                                            modifier = Modifier.weight(1f)
                                        )
                                    }

                                    // Switch Cetak Struk Fisik
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text("Cetak Struk Fisik", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Text("Cetak struk kupon fisik menggunakan printer thermal", color = Gray, fontSize = 11.sp)
                                        }
                                        Switch(
                                            checked = isThermalPrintChecked,
                                            onCheckedChange = { isThermalPrintChecked = it },
                                            enabled = isThermalEnabled,
                                            colors = SwitchDefaults.colors(
                                                checkedThumbColor = Color.White,
                                                checkedTrackColor = Color(0xFFE63946),
                                                uncheckedThumbColor = Gray,
                                                uncheckedTrackColor = BorderColor
                                            )
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(4.dp))

                                    val selectedPackageName = if (selectedPackageId == "any") "Semua Paket" else packagesList.find { it.id == selectedPackageId }?.name ?: selectedPackageId

                                    if (isCreatingCoupon) {
                                        Row(
                                            horizontalArrangement = Arrangement.Center,
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            CircularProgressIndicator(color = Color(0xFFE63946), modifier = Modifier.size(24.dp))
                                        }
                                    } else {
                                        Button(
                                            onClick = {
                                                val qty = couponQtyInput.toIntOrNull() ?: 1
                                                if (qty <= 0) {
                                                    Toast.makeText(context, "Jumlah kupon harus lebih dari 0", Toast.LENGTH_SHORT).show()
                                                    return@Button
                                                }
                                                if (isCreatingCoupon) return@Button
                                                isCreatingCoupon = true
                                                scope.launch(Dispatchers.IO) {
                                                    var successCount = 0
                                                    var failureCount = 0
                                                    for (i in 1..qty) {
                                                        try {
                                                            val api = NetworkClient.getApi(configManager.backendUrl)
                                                            val res = api.createCoupon(packageId = selectedPackageId)
                                                            if (res.isSuccessful && res.body() != null && res.body()!!.success) {
                                                                val coupon = res.body()!!.coupon!!
                                                                successCount++
                                                                
                                                                if (isThermalPrintChecked && isThermalEnabled) {
                                                                    com.example.photobooth.print.PrintTestHelper.printCouponReceipt(
                                                                        context = context,
                                                                        configManager = configManager,
                                                                        couponCode = coupon.code,
                                                                        packageName = selectedPackageName
                                                                    )
                                                                }
                                                            } else {
                                                                failureCount++
                                                            }
                                                        } catch (e: Exception) {
                                                            failureCount++
                                                            e.printStackTrace()
                                                        }
                                                    }
                                                    withContext(Dispatchers.Main) {
                                                        isCreatingCoupon = false
                                                        if (failureCount == 0) {
                                                            Toast.makeText(context, "Sukses mencetak $successCount kupon! 🎉", Toast.LENGTH_LONG).show()
                                                        } else {
                                                            Toast.makeText(context, "Proses selesai. Sukses: $successCount, Gagal: $failureCount", Toast.LENGTH_LONG).show()
                                                        }
                                                    }
                                                }
                                            },
                                            modifier = Modifier.fillMaxWidth(),
                                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946))
                                        ) {
                                            Text(
                                                text = if (isThermalEnabled || !isThermalPrintChecked) "PROSES & CETAK KUPON 🎫" else "Aktifkan printer thermal untuk mencetak struk",
                                                 fontWeight = FontWeight.Bold,
                                                 color = Color.White
                                            )
                                        }
                                    }
                                }
                            }
                        }

                        val StatusPrinterKuponCard: @Composable () -> Unit = {
                            AdminCard(title = "Status Printer & Auto-Cut") {
                                Column(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Status Koneksi:", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                        Text(
                                            text = if (printerAddress.isNotEmpty()) "Terhubung ($printerAddress)" else "Belum Dikonfigurasi",
                                            color = if (printerAddress.isNotEmpty()) Color(0xFF52B788) else Color(0xFFE63946),
                                            fontSize = 13.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }

                                    // Switch Auto-Cut
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text("Potong Kertas Otomatis (Auto-Cut)", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Text("Potong kertas struk secara otomatis setelah pencetakan selesai", color = Gray, fontSize = 11.sp)
                                        }
                                        Switch(
                                            checked = printerAutoCut,
                                            onCheckedChange = { isChecked ->
                                                printerAutoCut = isChecked
                                                configManager.printerAutoCut = isChecked
                                            },
                                            colors = SwitchDefaults.colors(
                                                checkedThumbColor = Color.White,
                                                checkedTrackColor = Color(0xFFE63946),
                                                uncheckedThumbColor = Gray,
                                                uncheckedTrackColor = BorderColor
                                            )
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(4.dp))

                                    // Tombol Uji Coba Cetak Struk
                                    if (isTestingPrint) {
                                        Row(
                                            horizontalArrangement = Arrangement.Center,
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            CircularProgressIndicator(color = Color(0xFFE63946), modifier = Modifier.size(24.dp))
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
                                            colors = ButtonDefaults.buttonColors(containerColor = BorderColor),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text("UJI COBA CETAK STRUK 📄", fontWeight = FontWeight.Bold, color = White)
                                        }
                                    }
                                }
                            }
                        }

                        val PanduanKuponCard: @Composable () -> Unit = {
                            AdminCard(title = "Panduan Kupon Kiosk") {
                                Column(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text("1.", color = Color(0xFFE63946), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text("Pilih paket foto yang diinginkan untuk kupon yang akan diterbitkan.", color = White, fontSize = 13.sp)
                                    }
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text("2.", color = Color(0xFFE63946), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text("Masukkan jumlah kupon (mendukung cetak masal secara berurutan).", color = White, fontSize = 13.sp)
                                    }
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text("3.", color = Color(0xFFE63946), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text("Aktifkan cetak struk fisik agar kode kupon otomatis tercetak pada printer thermal.", color = White, fontSize = 13.sp)
                                    }
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text("4.", color = Color(0xFFE63946), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text("Pastikan sakelar Auto-Cut aktif agar kertas terpotong otomatis di setiap struk kupon.", color = White, fontSize = 13.sp)
                                    }
                                }
                            }
                        }

                        // Layout Responsif untuk Tab Kupon
                        Column(
                            modifier = Modifier
                                .fillMaxSize()
                                .verticalScroll(rememberScrollState()),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            if (isLandscape) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                                ) {
                                    Column(
                                        modifier = Modifier.weight(1.5f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        CetakKuponCard()
                                    }
                                    Column(
                                        modifier = Modifier.weight(1f),
                                        verticalArrangement = Arrangement.spacedBy(16.dp)
                                    ) {
                                        StatusPrinterKuponCard()
                                        PanduanKuponCard()
                                    }
                                }
                            } else {
                                CetakKuponCard()
                                StatusPrinterKuponCard()
                                PanduanKuponCard()
                            }
                        }
                    }
                }
            }
        }
        
        // Detail History Dialog
        selectedHistoryItem?.let { item ->
            var isSaving by remember { mutableStateOf(false) }
            var showReprintChooserDialog by remember { mutableStateOf(false) }

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
                    colors = CardDefaults.cardColors(containerColor = BgCard),
                    border = BorderStroke(1.dp, BorderColor),
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
                        Text(text = "Rincian Foto Kiosk", fontWeight = FontWeight.Bold, color = White, fontSize = 18.sp)
                        
                        val dateStr = remember(item.timestamp) {
                            val sdf = SimpleDateFormat("dd MMMM yyyy, HH:mm:ss", Locale.getDefault())
                            sdf.format(Date(item.timestamp * 1000))
                        }
                        Text(text = "Waktu Jepret: $dateStr", color = Gray, fontSize = 12.sp)

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
                                    .border(1.dp, BorderColor, RoundedCornerShape(12.dp))
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
                                        Text(text = "Scan QR Code untuk\nDownload Ulang", color = Gray, fontSize = 10.sp, textAlign = TextAlign.Center)
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
                                        val type = configManager.printerType
                                        if (type == "AUTO") {
                                            showReprintChooserDialog = true
                                        } else if (type == "THERMAL") {
                                            isReprinting = true
                                            scope.launch {
                                                val successMsg = runReprintFromHistory(context, item.photoUrl, "THERMAL")
                                                isReprinting = false
                                                Toast.makeText(context, successMsg, Toast.LENGTH_LONG).show()
                                            }
                                        } else if (type == "COLOR") {
                                            isReprinting = true
                                            scope.launch {
                                                val successMsg = runReprintFromHistory(context, item.photoUrl, "COLOR")
                                                isReprinting = false
                                                Toast.makeText(context, successMsg, Toast.LENGTH_LONG).show()
                                            }
                                        } else {
                                            Toast.makeText(context, "Tidak ada printer aktif yang diaktifkan di pengaturan admin.", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                    shape = RoundedCornerShape(12.dp),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Text("CETAK ULANG FOTO (REPRINT)", fontWeight = FontWeight.Bold, color = Color.White)
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
                                colors = ButtonDefaults.buttonColors(containerColor = BorderColor),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                if (isSaving) {
                                    CircularProgressIndicator(color = White, modifier = Modifier.size(20.dp))
                                } else {
                                    Text("SIMPAN FOTO KE GALERI", fontWeight = FontWeight.Bold, color = White)
                                }
                            }
                            
                            OutlinedButton(
                                onClick = { 
                                    selectedHistoryItem = null
                                    qrCodeBitmap = null
                                },
                                border = BorderStroke(1.dp, BorderColor),
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = White),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text("Tutup")
                            }
                        }
                    }
                }
            }

            if (showReprintChooserDialog) {
                AlertDialog(
                    onDismissRequest = { showReprintChooserDialog = false },
                    title = { Text("Pilih Printer", color = White) },
                    text = { Text("Pilih printer yang ingin digunakan untuk mencetak ulang foto ini:", color = Gray) },
                    confirmButton = {
                        Button(
                            onClick = {
                                showReprintChooserDialog = false
                                isReprinting = true
                                scope.launch {
                                    val successMsg = runReprintFromHistory(context, item.photoUrl, "THERMAL")
                                    isReprinting = false
                                    Toast.makeText(context, successMsg, Toast.LENGTH_LONG).show()
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946))
                        ) {
                            Text("Printer Struk (Thermal)", color = Color.White)
                        }
                    },
                    dismissButton = {
                        Button(
                            onClick = {
                                showReprintChooserDialog = false
                                isReprinting = true
                                scope.launch {
                                    val successMsg = runReprintFromHistory(context, item.photoUrl, "COLOR")
                                    isReprinting = false
                                    Toast.makeText(context, successMsg, Toast.LENGTH_LONG).show()
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color.Gray)
                        ) {
                            Text("Printer Warna", color = Color.White)
                        }
                    },
                    containerColor = BgCard
                )
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
    val colors = LocalAdminThemeColors.current
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = colors.bgCard),
        border = BorderStroke(1.dp, colors.borderColor)
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Text(
                text = title,
                color = colors.textMain,
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
    val colors = LocalAdminThemeColors.current
    Card(
        modifier = modifier
            .fillMaxWidth()
            .height(130.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = colors.bgInnerCard),
        border = BorderStroke(if (isSelected) 2.dp else 1.dp, if (isSelected) Color(0xFFE63946) else colors.borderColor)
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
                    color = if (isSelected) Color(0xFFE63946) else colors.textMain,
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
    return com.example.photobooth.api.CatalogSync.syncFramesFromBackend(context, baseUrl, configManager)
}

// Run test print job
private suspend fun testPrintJob(context: Context, configManager: ConfigManager, forceType: String? = null): String {
    return com.example.photobooth.print.PrintTestHelper.runTestPrint(context, configManager, forceType)
}

// Reprint from history: Download image and send to printer driver
private suspend fun runReprintFromHistory(context: Context, photoUrl: String, printerTypeToUse: String): String {
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
    printerAddress: String,
    historyListState: List<HistoryPrinter>,
    onRefresh: () -> Unit,
    onNavigateToTab: (Int) -> Unit
) {
    val colors = LocalAdminThemeColors.current
    val BgCard = colors.bgCard
    val BorderColor = colors.borderColor
    val White = colors.textMain
    val Gray = colors.textMuted

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
                    .background(BgCard)
                    .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("TOTAL SESI FOTO", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        text = "${photoHistory.size} Sesi",
                        color = White,
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
                    .background(BgCard)
                    .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("SESI HARI INI", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
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
                    .background(BgCard)
                    .border(1.dp, BorderColor, RoundedCornerShape(16.dp))
                    .padding(12.dp)
            ) {
                Column(verticalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxSize()) {
                    Text("JAM TERAMAI", color = Gray, fontSize = 9.sp, fontWeight = FontWeight.Bold)
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
                .background(BgCard)
                .padding(4.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Button(
                onClick = { selectedChartMode = "WEEKLY" },
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (selectedChartMode == "WEEKLY") Color(0xFFE63946) else Color.Transparent,
                    contentColor = if (selectedChartMode == "WEEKLY") Color.White else Gray
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
                    contentColor = if (selectedChartMode == "TIMEOFDAY") Color.White else Gray
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
            colors = CardDefaults.cardColors(containerColor = BgCard),
            border = BorderStroke(1.dp, BorderColor)
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
                    Text("Status Sistem & Koneksi Kiosk", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    IconButton(onClick = onRefresh) {
                        Icon(imageVector = Icons.Default.Refresh, contentDescription = "Refresh Data", tint = Color.White)
                    }
                }

                HorizontalDivider(color = BorderColor)

                // Detail connection row 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("API Server:", color = Gray, fontSize = 12.sp)
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
                            color = White,
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
                    Text("Printer Driver Terpilih:", color = Gray, fontSize = 12.sp)
                    val activeThermalPrinterName = remember(printerAddress, historyListState) {
                        val found = historyListState.firstOrNull { it.address == printerAddress }
                        found?.name ?: if (printerAddress.isNotEmpty()) {
                            if (printerAddress.startsWith("BT:")) "Bluetooth Printer"
                            else if (printerAddress.startsWith("USB:")) "USB Printer"
                            else if (printerAddress.startsWith("NET:")) "Network Printer"
                            else "Thermal Printer"
                        } else {
                            "Printer Thermal"
                        }
                    }
                    Text(
                        text = when (printerType) {
                            "THERMAL" -> "THERMAL ($activeThermalPrinterName)"
                            "COLOR" -> "COLOR (PDF/SYSTEM)"
                            "AUTO" -> "OTOMATIS ($activeThermalPrinterName & WARNA)"
                            else -> "NONE"
                        },
                        color = White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                // Detail connection row 3
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Katalog Bingkai Offline:", color = Gray, fontSize = 12.sp)
                    Text(
                        text = "$syncedFramesCount Bingkai",
                        color = White,
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
            colors = CardDefaults.cardColors(containerColor = BgCard),
            border = BorderStroke(1.dp, BorderColor)
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Text("Pintasan Navigasi Cepat", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = { onNavigateToTab(1) },
                        border = BorderStroke(1.dp, BorderColor),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = White)
                    ) {
                        Text("Pengaturan", fontSize = 11.sp, maxLines = 1)
                    }

                    OutlinedButton(
                        onClick = { onNavigateToTab(2) },
                        border = BorderStroke(1.dp, BorderColor),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = White)
                    ) {
                        Text("Setel Printer", fontSize = 11.sp, maxLines = 1)
                    }

                    OutlinedButton(
                        onClick = { onNavigateToTab(3) },
                        border = BorderStroke(1.dp, BorderColor),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = White)
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
    val colors = LocalAdminThemeColors.current
    val BgCard = colors.bgCard
    val BorderColor = colors.borderColor
    val White = colors.textMain
    val Gray = colors.textMuted
    var selectedBarIndex by remember(labels, values) { mutableStateOf<Int?>(null) }
    val maxVal = if (values.maxOrNull() ?: 0 > 0) values.maxOrNull()!! else 10

    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = BgCard),
        border = BorderStroke(1.dp, BorderColor)
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = title,
                    color = White,
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
                        color = Gray,
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
                            color = if (isSelected) Color.White else Gray,
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

private fun isNetworkConnected(context: Context): Boolean {
    val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? android.net.ConnectivityManager
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
        val network = connectivityManager?.activeNetwork ?: return false
        val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
        return capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_WIFI) ||
                capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_CELLULAR) ||
                capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_ETHERNET)
    } else {
        @Suppress("DEPRECATION")
        val networkInfo = connectivityManager?.activeNetworkInfo
        @Suppress("DEPRECATION")
        return networkInfo != null && networkInfo.isConnected
    }
}

@SuppressLint("MissingPermission")
private fun isBluetoothDevicePrinter(device: android.bluetooth.BluetoothDevice): Boolean {
    val name = device.name?.lowercase(Locale.getDefault()) ?: ""
    
    // 1. Check device class
    val bluetoothClass = device.bluetoothClass
    if (bluetoothClass != null) {
        val majorClass = bluetoothClass.majorDeviceClass
        val devClass = bluetoothClass.deviceClass
        
        if (majorClass == android.bluetooth.BluetoothClass.Device.Major.IMAGING ||
            devClass == 1664 // 0x0680 (IMAGING_PRINTER)
        ) {
            return true
        }
    }
    
    // 2. Check if name contains printer keywords
    val keywords = listOf(
        "print", "printer", "pos", "mpt", "pt-", "zj", "xp", "rp", "thermal",
        "epson", "bixolon", "star", "sewoo", "hoin", "rongta", "goojprt",
        "milestone", "innerprinter", "peripage", "mtp"
    )
    if (keywords.any { name.contains(it) }) {
        return true
    }
    
    return false
}
