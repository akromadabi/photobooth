package com.example.photobooth.ui.home

import android.annotation.SuppressLint
import android.os.Build
import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import android.content.Context
import android.content.ContextWrapper
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager
import android.bluetooth.BluetoothManager
import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.fragment.app.FragmentActivity
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.api.PackageDto
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.print.PrintTestHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@Composable
fun QuickSettingsDialog(
    configManager: ConfigManager,
    onDismissRequest: () -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val scrollState = rememberScrollState()

    // 1. Theme Configuration States
    var activeThemeState by remember { mutableStateOf(configManager.appTheme) }
    var isThemeDropdownExpanded by remember { mutableStateOf(false) }
    val themeList = listOf(
        Pair("NEON_RED", "Neon Red (Modern)"),
        Pair("CUTE_PASTEL", "Cute Pastel (Wood)"),
        Pair("LUXURY_GOLD", "Luxury Gold (Wedding)"),
        Pair("RETRO_ARCADE", "Retro Arcade (8-Bit)")
    )
    val activeThemeName = themeList.firstOrNull { it.first == activeThemeState }?.second ?: activeThemeState

    // 2. Countdown State
    var countdownSecs by remember { mutableIntStateOf(configManager.countdownSeconds) }

    // 3. Printer Struk (Thermal) Configuration States
    var printerType by remember { mutableStateOf(configManager.printerType) }
    val isThermalEnabled = remember(printerType) { printerType == "AUTO" || printerType == "THERMAL" }
    var printerAddress by remember { mutableStateOf(configManager.printerAddress) }
    var wifiIpAddress by remember { mutableStateOf("") }
    var wifiPort by remember { mutableStateOf("9100") }
    
    // USB and BT lists
    val usbDevices = remember { mutableStateListOf<UsbDevice>() }
    val bluetoothDevices = remember { mutableStateListOf<Pair<String, String>>() } // Name, MAC

    val requestBtPermissionsLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions(),
        onResult = { permissions ->
            val connectGranted = permissions[android.Manifest.permission.BLUETOOTH_CONNECT] ?: false
            if (connectGranted) {
                usbDevices.clear()
                bluetoothDevices.clear()
                val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
                usbManager?.deviceList?.values?.forEach { device ->
                    var isPrinter = false
                    for (i in 0 until device.interfaceCount) {
                        if (device.getInterface(i).interfaceClass == 7) {
                            isPrinter = true
                            break
                        }
                    }
                    if (isPrinter) usbDevices.add(device)
                }
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
            } else {
                Toast.makeText(context, "Izin Bluetooth ditolak. Gagal memindai printer Bluetooth.", Toast.LENGTH_SHORT).show()
            }
        }
    )

    // Scan printers
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
            if (isPrinter) usbDevices.add(device)
        }
        
        // Bluetooth
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val hasConnect = ContextCompat.checkSelfPermission(
                context,
                android.Manifest.permission.BLUETOOTH_CONNECT
            ) == PackageManager.PERMISSION_GRANTED
            val hasScan = ContextCompat.checkSelfPermission(
                context,
                android.Manifest.permission.BLUETOOTH_SCAN
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
                            bluetoothDevices.add(Pair(device.name ?: "Unknown Printer", device.address))
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        } else {
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
    }

    LaunchedEffect(Unit) {
        scanPrinters()
    }

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

    // Printer Warna Configuration States
    val isColorEnabled = remember(printerType) { printerType == "AUTO" || printerType == "COLOR" }

    // 4. Coupon Fast Print States
    var packagesList by remember { mutableStateOf<List<PackageDto>>(emptyList()) }
    var selectedPackageId by remember { mutableStateOf("any") }
    var isPackageDropdownExpanded by remember { mutableStateOf(false) }
    var isCreatingCoupon by remember { mutableStateOf(false) }

    LaunchedEffect(configManager.backendUrl) {
        try {
            val api = NetworkClient.getApi(configManager.backendUrl)
            val response = api.getPackages()
            if (response.isSuccessful && response.body() != null) {
                packagesList = response.body()!!
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    val selectedPackageName = if (selectedPackageId == "any") "Semua Paket" else packagesList.find { it.id == selectedPackageId }?.name ?: selectedPackageId

    // Actions triggers
    var isTestingThermalPrint by remember { mutableStateOf(false) }
    var isTestingColorPrint by remember { mutableStateOf(false) }
    var isSyncingCatalog by remember { mutableStateOf(false) }
    var isResettingQueue by remember { mutableStateOf(false) }

    // Dialog layout content
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black.copy(alpha = 0.5f))
            .clickable(onClick = onDismissRequest) // Dismiss on clicking background
    ) {
        // Right side panel (Drawer content)
        Column(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .fillMaxHeight()
                .width(380.dp)
                .background(Color(0xFF0F0F14)) // Dark graphite color
                .border(BorderStroke(1.dp, Color(0xFF2A2A35)))
                .padding(20.dp)
                .clickable(enabled = false) {} // Prevent click propagation to background
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "PENGATURAN CEPAT",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        letterSpacing = 1.sp
                    )
                    Text(
                        text = "Kiosk Operator Panel (Pinless Access)",
                        fontSize = 11.sp,
                        color = Color.Gray
                    )
                }
                IconButton(
                    onClick = onDismissRequest,
                    modifier = Modifier
                        .size(36.dp)
                        .background(Color(0xFF1E1E24), CircleShape)
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = "Close",
                        tint = Color.White,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(16.dp))
            Divider(color = Color(0xFF2A2A35), modifier = Modifier.fillMaxWidth())
            Spacer(modifier = Modifier.height(16.dp))

            // Scrollable Settings Box
            Column(
                modifier = Modifier
                    .weight(1f)
                    .verticalScroll(scrollState),
                verticalArrangement = Arrangement.spacedBy(20.dp)
            ) {
                // Section 1: Tema Tampilan Kiosk (Total Layout)
                QuickSettingsSection(title = "Tema Tampilan Kiosk") {
                    Box(modifier = Modifier.fillMaxWidth()) {
                        OutlinedButton(
                            onClick = { isThemeDropdownExpanded = true },
                            modifier = Modifier.fillMaxWidth(),
                            border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                            colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                            shape = RoundedCornerShape(8.dp),
                            contentPadding = PaddingValues(12.dp)
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(text = activeThemeName, color = Color.White, fontSize = 13.sp)
                                Text(text = "▼", color = Color(0xFFE63946), fontSize = 10.sp)
                            }
                        }
                        DropdownMenu(
                            expanded = isThemeDropdownExpanded,
                            onDismissRequest = { isThemeDropdownExpanded = false },
                            modifier = Modifier
                                .width(340.dp)
                                .background(Color(0xFF1E1E24))
                                .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                        ) {
                            themeList.forEach { (themeId, themeName) ->
                                DropdownMenuItem(
                                    text = { Text(themeName, color = Color.White, fontSize = 13.sp) },
                                    onClick = {
                                        activeThemeState = themeId
                                        configManager.appTheme = themeId
                                        isThemeDropdownExpanded = false
                                        Toast.makeText(context, "Tema diganti ke $themeName!", Toast.LENGTH_SHORT).show()
                                    }
                                )
                            }
                        }
                    }
                }

                // Section 2: Hitung Mundur (Countdown seconds)
                QuickSettingsSection(title = "Durasi Hitung Mundur (Detik)") {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "$countdownSecs Detik",
                            color = Color.White,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Button(
                                onClick = {
                                    if (countdownSecs > 2) {
                                        countdownSecs--
                                        configManager.countdownSeconds = countdownSecs
                                    }
                                },
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp)
                            ) {
                                Text("-", color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            }
                            Button(
                                onClick = {
                                    if (countdownSecs < 15) {
                                        countdownSecs++
                                        configManager.countdownSeconds = countdownSecs
                                    }
                                },
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp)
                            ) {
                                Text("+", color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                // Section 3: Printer Receipt (Thermal)
                QuickSettingsSection(title = "Printer Struk (Thermal)") {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text("Aktifkan Printer Thermal", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                Text(
                                    text = if (isThermalEnabled) "Terdeteksi: $selectedPrinterText" else "Mode Digital Saja",
                                    color = Color.Gray,
                                    fontSize = 10.sp
                                )
                            }
                            Switch(
                                checked = isThermalEnabled,
                                onCheckedChange = { checked ->
                                    val newType = if (checked) {
                                        if (isColorEnabled) "AUTO" else "THERMAL"
                                    } else {
                                        if (isColorEnabled) "COLOR" else "NONE"
                                    }
                                    printerType = newType
                                    configManager.printerType = newType
                                },
                                colors = SwitchDefaults.colors(
                                    checkedThumbColor = Color(0xFFE63946),
                                    checkedTrackColor = Color(0xFFE63946).copy(alpha = 0.4f)
                                )
                            )
                        }

                        if (isThermalEnabled) {
                            // Dropdown port printer address selection
                            Box(modifier = Modifier.fillMaxWidth()) {
                                OutlinedButton(
                                    onClick = {
                                        scanPrinters()
                                        isPrinterPortDropdownExpanded = true
                                    },
                                    modifier = Modifier.fillMaxWidth(),
                                    border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(10.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text(text = selectedPrinterText, color = Color.White, fontSize = 12.sp)
                                        Text(text = "▼", color = Color(0xFFE63946), fontSize = 10.sp)
                                    }
                                }
                                DropdownMenu(
                                    expanded = isPrinterPortDropdownExpanded,
                                    onDismissRequest = { isPrinterPortDropdownExpanded = false },
                                    modifier = Modifier
                                        .width(340.dp)
                                        .background(Color(0xFF1E1E24))
                                        .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                                ) {
                                    if (printerOptions.isEmpty()) {
                                        DropdownMenuItem(
                                            text = { Text("Tidak ada printer terdeteksi", color = Color.Gray, fontSize = 12.sp) },
                                            onClick = { isPrinterPortDropdownExpanded = false }
                                        )
                                    } else {
                                        printerOptions.forEach { (addr, details) ->
                                            val (name, type) = details
                                            DropdownMenuItem(
                                                text = { Text("[$type] $name", color = Color.White, fontSize = 12.sp) },
                                                onClick = {
                                                    printerAddress = addr
                                                    configManager.printerAddress = addr
                                                    isPrinterPortDropdownExpanded = false
                                                    Toast.makeText(context, "Port printer disetel!", Toast.LENGTH_SHORT).show()
                                                }
                                            )
                                        }
                                    }
                                }
                            }

                            Spacer(modifier = Modifier.height(6.dp))
                            
                            // WiFi Printer Inputs
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                OutlinedTextField(
                                    value = wifiIpAddress,
                                    onValueChange = { wifiIpAddress = it },
                                    placeholder = { Text("IP Printer (cth: 192.168.1.100)", color = Color.Gray, fontSize = 11.sp) },
                                    modifier = Modifier.weight(2f),
                                    singleLine = true,
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Color(0xFFE63946),
                                        unfocusedBorderColor = Color(0xFF2A2A35),
                                        focusedTextColor = Color.White,
                                        unfocusedTextColor = Color.White,
                                        focusedContainerColor = Color.Transparent,
                                        unfocusedContainerColor = Color.Transparent
                                    ),
                                    shape = RoundedCornerShape(8.dp)
                                )
                                OutlinedTextField(
                                    value = wifiPort,
                                    onValueChange = { wifiPort = it },
                                    placeholder = { Text("Port", color = Color.Gray, fontSize = 11.sp) },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Color(0xFFE63946),
                                        unfocusedBorderColor = Color(0xFF2A2A35),
                                        focusedTextColor = Color.White,
                                        unfocusedTextColor = Color.White,
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
                                            Toast.makeText(context, "Printer WiFi disetel!", Toast.LENGTH_SHORT).show()
                                        } else {
                                            Toast.makeText(context, "IP tidak boleh kosong", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 10.dp)
                                ) {
                                    Text("Set", color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                            
                            Spacer(modifier = Modifier.height(6.dp))

                            // Test print button
                            Button(
                                onClick = {
                                    if (isTestingThermalPrint) return@Button
                                    isTestingThermalPrint = true
                                    scope.launch(Dispatchers.IO) {
                                        val res = PrintTestHelper.runTestPrint(context, configManager, "THERMAL")
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, res, Toast.LENGTH_LONG).show()
                                            isTestingThermalPrint = false
                                        }
                                    }
                                },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                                contentPadding = PaddingValues(10.dp)
                            ) {
                                if (isTestingThermalPrint) {
                                    CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                                } else {
                                    Text("Test Print Struk Thermal", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                    }
                }

                // Section 4: Printer Foto Warna
                QuickSettingsSection(title = "Printer Warna") {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text("Aktifkan Printer Warna", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                Text("Mencetak halaman foto warna utama (A4/4R)", color = Color.Gray, fontSize = 10.sp)
                            }
                            Switch(
                                checked = isColorEnabled,
                                onCheckedChange = { checked ->
                                    val newType = if (checked) {
                                        if (isThermalEnabled) "AUTO" else "COLOR"
                                    } else {
                                        if (isThermalEnabled) "THERMAL" else "NONE"
                                    }
                                    printerType = newType
                                    configManager.printerType = newType
                                },
                                colors = SwitchDefaults.colors(
                                    checkedThumbColor = Color(0xFFE63946),
                                    checkedTrackColor = Color(0xFFE63946).copy(alpha = 0.4f)
                                )
                            )
                        }

                        if (isColorEnabled) {
                            Button(
                                onClick = {
                                    if (isTestingColorPrint) return@Button
                                    isTestingColorPrint = true
                                    scope.launch(Dispatchers.IO) {
                                        val res = PrintTestHelper.runTestPrint(context, configManager, "COLOR")
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, res, Toast.LENGTH_LONG).show()
                                            isTestingColorPrint = false
                                        }
                                    }
                                },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                                contentPadding = PaddingValues(10.dp)
                            ) {
                                if (isTestingColorPrint) {
                                    CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                                } else {
                                    Text("Test Print Halaman Warna", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                    }
                }

                // Section 5: Cetak Kupon Cepat
                QuickSettingsSection(title = "Cetak Kupon Cepat") {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text(
                            text = "Buat dan cetak kupon/voucher pembayaran secara instan menggunakan printer struk lokal.",
                            color = Color.Gray,
                            fontSize = 11.sp,
                            lineHeight = 15.sp
                        )

                        // Dropdown choice for package
                        Box(modifier = Modifier.fillMaxWidth()) {
                            OutlinedButton(
                                onClick = { isPackageDropdownExpanded = true },
                                modifier = Modifier.fillMaxWidth(),
                                border = BorderStroke(1.dp, Color(0xFF2A2A35)),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                                shape = RoundedCornerShape(8.dp),
                                contentPadding = PaddingValues(10.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(text = "Paket: $selectedPackageName", color = Color.White, fontSize = 12.sp)
                                    Text(text = "▼", color = Color(0xFFE63946), fontSize = 10.sp)
                                }
                            }
                            DropdownMenu(
                                expanded = isPackageDropdownExpanded,
                                onDismissRequest = { isPackageDropdownExpanded = false },
                                modifier = Modifier
                                    .width(340.dp)
                                    .background(Color(0xFF1E1E24))
                                    .border(1.dp, Color(0xFF2A2A35), RoundedCornerShape(8.dp))
                            ) {
                                DropdownMenuItem(
                                    text = { Text("Semua Paket (Bisa Pilih Bebas)", color = Color.White, fontSize = 12.sp) },
                                    onClick = {
                                        selectedPackageId = "any"
                                        isPackageDropdownExpanded = false
                                    }
                                )
                                packagesList.forEach { pkg ->
                                    DropdownMenuItem(
                                        text = { Text("${pkg.name} (Rp ${pkg.price})", color = Color.White, fontSize = 12.sp) },
                                        onClick = {
                                            selectedPackageId = pkg.id
                                            isPackageDropdownExpanded = false
                                        }
                                    )
                                }
                            }
                        }

                        Button(
                            onClick = {
                                if (isCreatingCoupon) return@Button
                                isCreatingCoupon = true
                                scope.launch(Dispatchers.IO) {
                                    try {
                                        val api = NetworkClient.getApi(configManager.backendUrl)
                                        val res = api.createCoupon(packageId = selectedPackageId)
                                        if (res.isSuccessful && res.body() != null && res.body()!!.success) {
                                            val coupon = res.body()!!.coupon!!
                                            val printRes = PrintTestHelper.printCouponReceipt(
                                                context = context,
                                                configManager = configManager,
                                                couponCode = coupon.code,
                                                packageName = selectedPackageName
                                            )
                                            withContext(Dispatchers.Main) {
                                                Toast.makeText(context, "Kupon ${coupon.code} terbit! $printRes", Toast.LENGTH_LONG).show()
                                            }
                                        } else {
                                            val errorMsg = res.body()?.message ?: "Gagal membuat kupon dari backend"
                                            withContext(Dispatchers.Main) {
                                                Toast.makeText(context, "Error: $errorMsg", Toast.LENGTH_LONG).show()
                                            }
                                        }
                                    } catch (e: Exception) {
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, "Kesalahan: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                                        }
                                    } finally {
                                        withContext(Dispatchers.Main) {
                                            isCreatingCoupon = false
                                        }
                                    }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)), // Red brand color
                            contentPadding = PaddingValues(11.dp),
                            enabled = isThermalEnabled && !isCreatingCoupon
                        ) {
                            if (isCreatingCoupon) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                            } else {
                                Text(
                                    text = if (isThermalEnabled) "Cetak Kupon Baru 🎫" else "Aktifkan printer thermal untuk mencetak",
                                    color = if (isThermalEnabled) Color.White else Color.Gray,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                }

                // Section 6: Fitur Utilitas Lainnya
                QuickSettingsSection(title = "Fitur Utilitas Kiosk") {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        // 1. Sync catalog/frames
                        Button(
                            onClick = {
                                if (isSyncingCatalog) return@Button
                                isSyncingCatalog = true
                                scope.launch(Dispatchers.IO) {
                                    try {
                                        val resJson = com.example.photobooth.api.CatalogSync.syncFramesFromBackend(
                                            context,
                                            configManager.backendUrl,
                                            configManager
                                        )
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, "Sinkronisasi Bingkai Berhasil!", Toast.LENGTH_SHORT).show()
                                        }
                                    } catch (e: Exception) {
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, "Gagal Sync: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                                        }
                                    } finally {
                                        withContext(Dispatchers.Main) {
                                            isSyncingCatalog = false
                                        }
                                    }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                            contentPadding = PaddingValues(10.dp)
                        ) {
                            if (isSyncingCatalog) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                            } else {
                                Row(
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(imageVector = Icons.Default.Refresh, contentDescription = "Sync", tint = Color.White, modifier = Modifier.size(16.dp))
                                    Text("Sinkronisasi Bingkai & Acara", color = Color.White, fontSize = 12.sp)
                                }
                            }
                        }

                        // 2. Reset queue
                        Button(
                            onClick = {
                                if (isResettingQueue) return@Button
                                isResettingQueue = true
                                scope.launch(Dispatchers.IO) {
                                    try {
                                        val api = NetworkClient.getApi(configManager.backendUrl)
                                        val res = api.resetQueue()
                                        withContext(Dispatchers.Main) {
                                            if (res.isSuccessful && res.body()?.success == true) {
                                                Toast.makeText(context, "Antrean Kiosk berhasil direset total!", Toast.LENGTH_SHORT).show()
                                            } else {
                                                Toast.makeText(context, "Reset Gagal: ${res.body()?.message ?: "error backend"}", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    } catch (e: Exception) {
                                        withContext(Dispatchers.Main) {
                                            Toast.makeText(context, "Kesalahan reset: ${e.localizedMessage}", Toast.LENGTH_LONG).show()
                                        }
                                    } finally {
                                        withContext(Dispatchers.Main) {
                                            isResettingQueue = false
                                        }
                                    }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E1E24)),
                            contentPadding = PaddingValues(10.dp)
                        ) {
                            if (isResettingQueue) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                            } else {
                                Text("Reset Antrean Kiosk (Server)", color = Color(0xFFEF4444), fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }

                        // 3. Exit application
                        Button(
                            onClick = {
                                context.findActivity()?.let { act ->
                                    try {
                                        act.stopLockTask()
                                    } catch (e: Exception) {
                                        e.printStackTrace()
                                    }
                                    act.finishAndRemoveTask()
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.15f)),
                            border = BorderStroke(1.dp, Color(0xFFEF4444)),
                            contentPadding = PaddingValues(10.dp)
                        ) {
                            Text("Tutup & Keluar Aplikasi Kiosk", color = Color(0xFFEF4444), fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun QuickSettingsSection(
    title: String,
    content: @Composable () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(Color(0xFF141419), RoundedCornerShape(12.dp))
            .border(BorderStroke(1.dp, Color(0xFF2A2A35)), RoundedCornerShape(12.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = title,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White.copy(alpha = 0.9f),
            letterSpacing = 0.5.sp
        )
        content()
    }
}
