package com.example.photobooth.ui.home

import android.content.Context
import android.content.ContextWrapper
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.*
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.animateColor
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.core.content.ContextCompat
import androidx.compose.ui.graphics.Shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import com.example.photobooth.theme.*
import androidx.fragment.app.FragmentActivity
import coil.compose.AsyncImage
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onStartClick: (String) -> Unit,
    onAdminNavigate: () -> Unit,
    modifier: Modifier = Modifier,
    onRemoteStartClick: (frameId: String, eventId: String, packageId: String, sessionId: String) -> Unit = { _, _, _, _ -> }
) {
    val context = LocalContext.current
    val configManager = remember { ConfigManager(context) }
    
    val prefs = remember { context.getSharedPreferences("photobooth_prefs", Context.MODE_PRIVATE) }
    var syncedFramesJsonState by remember { mutableStateOf(prefs.getString("synced_frames_json", "") ?: "") }
    
    DisposableEffect(prefs) {
        val listener = android.content.SharedPreferences.OnSharedPreferenceChangeListener { p, key ->
            if (key == "synced_frames_json") {
                syncedFramesJsonState = p.getString("synced_frames_json", "") ?: ""
            }
        }
        prefs.registerOnSharedPreferenceChangeListener(listener)
        onDispose {
            prefs.unregisterOnSharedPreferenceChangeListener(listener)
        }
    }

    // Auto Update States
    val updateManager = remember { com.example.photobooth.data.UpdateManager(context) }
    val currentVersionCode = remember { updateManager.getCurrentVersionCode() }
    val currentVersionName = remember { updateManager.getCurrentVersionName() }
    
    var autoUpdateInfo by remember { mutableStateOf<com.example.photobooth.data.UpdateInfo?>(null) }
    var isCheckingAutoUpdate by remember { mutableStateOf(false) }
    var autoUpdateError by remember { mutableStateOf<String?>(null) }
    var autoDownloadProgress by remember { mutableStateOf<Float?>(null) }
    var isAutoDownloading by remember { mutableStateOf(false) }
    var isAutoInstallPermissionNeeded by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    // Background sync and update check on startup
    LaunchedEffect(configManager.backendUrl) {
        val backendUrl = configManager.backendUrl
        if (backendUrl.isNotEmpty()) {
            delay(1500) // Small delay to let network stabilize if the app just booted
            
            // 1. Silent sync frames
            try {
                com.example.photobooth.api.CatalogSync.syncFramesFromBackend(context, backendUrl, configManager)
            } catch (e: Exception) {
                e.printStackTrace()
            }
            
            // 2. Check for updates
            isCheckingAutoUpdate = true
            autoUpdateError = null
            try {
                val info = updateManager.checkUpdate(backendUrl)
                if (info != null && info.versionCode > currentVersionCode) {
                    autoUpdateInfo = info
                }
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                isCheckingAutoUpdate = false
            }
        }
    }

    val configuration = androidx.compose.ui.platform.LocalConfiguration.current
    val isLandscape = configuration.orientation == android.content.res.Configuration.ORIENTATION_LANDSCAPE
    
    var logoTapCount by remember { mutableIntStateOf(0) }
    var showPinDialog by remember { mutableStateOf(false) }

    // Event states
    var showEventCodeDialog by remember { mutableStateOf(false) }
    var eventCodeInput by remember { mutableStateOf("") }
    var eventCodeError by remember { mutableStateOf<String?>(null) }
    var unlockedEventId by remember { mutableStateOf("general") }
    var showUnlockSuccessAnim by remember { mutableStateOf(false) }

    // Exit states
    var showExitPinDialog by remember { mutableStateOf(false) }

    // Quick Settings states
    var showQuickSettings by remember { mutableStateOf(false) }
    var cameraTapCount by remember { mutableIntStateOf(0) }

    LaunchedEffect(cameraTapCount) {
        if (cameraTapCount > 0) {
            delay(2000)
            cameraTapCount = 0
        }
    }

    val exitKioskApp = {
        context.findActivity()?.let { act ->
            try {
                act.stopLockTask()
            } catch (e: Exception) {
                e.printStackTrace()
            }
            act.finishAndRemoveTask()
        }
    }

    // Intercept back gesture/button to prevent exiting
    BackHandler(enabled = true) {
        if (configManager.useBiometric) {
            checkAndShowBiometric(
                context = context,
                onSuccess = {
                    exitKioskApp()
                },
                onFallbackPin = {
                    showExitPinDialog = true
                }
            )
        } else {
            showExitPinDialog = true
        }
    }

    // Dynamic Event Name and Logo Resolution
    val resolvedEventName = remember(syncedFramesJsonState, configManager.activeEventId, configManager.kioskMode, unlockedEventId) {
        val activeId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
        if (activeId == "general") null
        else {
            try {
                val config = com.google.gson.Gson().fromJson(syncedFramesJsonState, com.example.photobooth.data.FrameConfig::class.java)
                config?.events?.firstOrNull { it.id == activeId }?.name
            } catch (e: Exception) {
                null
            }
        }
    }

    val logoTextPart1 = remember(resolvedEventName) {
        if (resolvedEventName.isNullOrEmpty()) "Jeprat"
        else {
            val words = resolvedEventName.split(" ")
            if (words.size >= 2) words.take(words.size / 2).joinToString(" ")
            else words.first()
        }
    }
    
    val logoTextPart2 = remember(resolvedEventName) {
        if (resolvedEventName.isNullOrEmpty()) "Jepret"
        else {
            val words = resolvedEventName.split(" ")
            if (words.size >= 2) words.drop(words.size / 2).joinToString(" ")
            else ""
        }
    }

    // Live gallery state
    var historyList by remember { mutableStateOf<List<String>>(emptyList()) }
    var currentPhotoIndex by remember { mutableIntStateOf(0) }

    // Fetch history
    LaunchedEffect(configManager.backendUrl) {
        try {
            val api = NetworkClient.getApi(configManager.backendUrl)
            val response = api.getPhotoHistory()
            if (response.isSuccessful) {
                val items = response.body() ?: emptyList()
                historyList = items.map { it.photoUrl }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    // Auto-advance history slideshow every 4 seconds
    LaunchedEffect(historyList) {
        if (historyList.isNotEmpty()) {
            while (true) {
                delay(4000)
                currentPhotoIndex = (currentPhotoIndex + 1) % historyList.size
            }
        }
    }

    // Reset tap count after 3 seconds of inactivity
    LaunchedEffect(logoTapCount) {
        if (logoTapCount > 0) {
            delay(3000)
            logoTapCount = 0
        }
    }

    // Polling Remote Kiosk Command
    LaunchedEffect(configManager.backendUrl, configManager.kioskMode, unlockedEventId) {
        while (true) {
            try {
                val api = NetworkClient.getApi(configManager.backendUrl)
                val response = api.getKioskCommand()
                if (response.isSuccessful && response.body() != null) {
                    val cmdRes = response.body()!!
                    if (cmdRes.success && cmdRes.active) {
                        if (cmdRes.command == "START_CAPTURE") {
                            val frameId = cmdRes.frame_id ?: ""
                            val sessionId = cmdRes.session_id ?: ""
                            val packageId = cmdRes.package_id ?: ""
                            val eventId = if (!cmdRes.event_id.isNullOrEmpty()) {
                                cmdRes.event_id
                            } else {
                                if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                            }
                            if (frameId.isNotEmpty() && sessionId.isNotEmpty() && sessionId != configManager.lastRemoteSessionId) {
                                configManager.lastRemoteSessionId = sessionId
                                onRemoteStartClick(frameId, eventId, packageId, sessionId)
                            }
                        }
                    }
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
            delay(1000) // Poll every 1 second
        }
    }

    // Breathing float animation for the slogan text
    val infiniteTransition = rememberInfiniteTransition(label = "SloganFloating")
    val dy by infiniteTransition.animateFloat(
        initialValue = -8f,
        targetValue = 8f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 2500, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "FloatingDeltaY"
    )

    // Pulsing animation for the START button
    val buttonScale by infiniteTransition.animateFloat(
        initialValue = 0.96f,
        targetValue = 1.04f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 1000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "StartButtonPulse"
    )

    // Keyframe slide animations for logo texts
    val creativeX by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 0f,
        animationSpec = infiniteRepeatable(
            animation = keyframes {
                durationMillis = 6000
                0f at 0 with LinearEasing
                0f at 2000 with EaseInCubic
                500f at 3000 with LinearEasing
                -500f at 3001 with EaseOutCubic
                0f at 4200 with LinearEasing
                0f at 6000
            },
            repeatMode = RepeatMode.Restart
        ),
        label = "CreativeXAnimation"
    )

    val studioX by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 0f,
        animationSpec = infiniteRepeatable(
            animation = keyframes {
                durationMillis = 6000
                0f at 0 with LinearEasing
                0f at 2000 with EaseInCubic
                -500f at 3000 with LinearEasing
                500f at 3001 with EaseOutCubic
                0f at 4200 with LinearEasing
                0f at 6000
            },
            repeatMode = RepeatMode.Restart
        ),
        label = "StudioXAnimation"
    )

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(AppTheme.colors.background)
    ) {
        val activeTheme = AppTheme.type
        val onLogoClick = {
            logoTapCount++
            if (logoTapCount >= 5) {
                logoTapCount = 0
                if (configManager.useBiometric) {
                    checkAndShowBiometric(
                        context = context,
                        onSuccess = { onAdminNavigate() },
                        onFallbackPin = { showPinDialog = true }
                    )
                } else {
                    showPinDialog = true
                }
            }
        }

        Crossfade(targetState = activeTheme, label = "ThemeCrossfade") { theme ->
            val onCameraClickLambda = {
                cameraTapCount++
                if (cameraTapCount >= 3) {
                    cameraTapCount = 0
                    showQuickSettings = true
                }
            }
            when (theme) {
                AppThemeType.CUTE_PASTEL -> CutePastelHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    buttonScale = buttonScale,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
                AppThemeType.CUTE_NARA -> CuteNaraHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    buttonScale = buttonScale,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
                AppThemeType.LUXURY_GOLD -> LuxuryGoldHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
                AppThemeType.MINIMAL_MODERN -> MinimalModernHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
                AppThemeType.CREATIVE_DYNAMIC -> CreativeDynamicHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    buttonScale = buttonScale,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
                else -> ModernHomeLayout(
                    resolvedEventName = resolvedEventName,
                    logoTextPart1 = logoTextPart1,
                    logoTextPart2 = logoTextPart2,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    buttonScale = buttonScale,
                    dy = dy,
                    creativeX = creativeX,
                    studioX = studioX,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true },
                    onCameraClick = onCameraClickLambda
                )
            }
        }

        // Admin PIN Dialog
        if (showPinDialog) {
            PinEntryDialog(
                title = "Admin Access",
                subtitle = "Masukkan PIN 4-digit untuk masuk ke menu admin.",
                correctPin = configManager.adminPin,
                onDismissRequest = {
                    showPinDialog = false
                },
                onSuccess = {
                    showPinDialog = false
                    onAdminNavigate()
                }
            )
        }

        // Exit PIN Dialog
        if (showExitPinDialog) {
            PinEntryDialog(
                title = "Keluar Aplikasi",
                subtitle = "Masukkan PIN Admin untuk menutup aplikasi kiosk.",
                correctPin = configManager.adminPin,
                onDismissRequest = {
                    showExitPinDialog = false
                },
                onSuccess = {
                    showExitPinDialog = false
                    exitKioskApp()
                }
            )
        }



        // Event Code Verification Dialog (Scenario B)
        if (showEventCodeDialog) {
            Dialog(onDismissRequest = { 
                showEventCodeDialog = false
                eventCodeInput = ""
                eventCodeError = null
            }) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24))
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "🎟️ Masukkan Kode Event",
                            color = Color.White,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Masukkan kode undangan event (misal: RIANANI26) untuk membuka bingkai foto eksklusif.",
                            color = Color.Gray,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center
                        )

                        OutlinedTextField(
                            value = eventCodeInput,
                            onValueChange = { 
                                eventCodeInput = it
                                eventCodeError = null
                            },
                            placeholder = { Text("KODE EVENT") },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = Color.White,
                                unfocusedTextColor = Color.White,
                                focusedBorderColor = Color(0xFFE63946),
                                unfocusedBorderColor = Color.Gray
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )

                        if (eventCodeError != null) {
                            Text(
                                text = eventCodeError!!,
                                color = Color(0xFFE63946),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            TextButton(
                                onClick = {
                                    showEventCodeDialog = false
                                    eventCodeInput = ""
                                    eventCodeError = null
                                },
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Batal", color = Color.Gray)
                            }
                            
                            Button(
                                onClick = {
                                    val config = try {
                                        com.google.gson.Gson().fromJson(syncedFramesJsonState, com.example.photobooth.data.FrameConfig::class.java)
                                    } catch (e: Exception) {
                                        null
                                    }
                                    val matchedEvent = config?.events?.firstOrNull { it.code.equals(eventCodeInput, ignoreCase = true) }
                                    if (matchedEvent != null) {
                                        unlockedEventId = matchedEvent.id
                                        showEventCodeDialog = false
                                        showUnlockSuccessAnim = true
                                    } else if (eventCodeInput.equals("UMUM", ignoreCase = true)) {
                                        unlockedEventId = "general"
                                        showEventCodeDialog = false
                                        Toast.makeText(context, "Sesi foto diatur kembali ke umum.", Toast.LENGTH_SHORT).show()
                                    } else {
                                        eventCodeError = "Kode Event salah / tidak ditemukan!"
                                    }
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Verifikasi", color = Color.White)
                            }
                        }
                    }
                }
            }
        }

        // Event Unlocked Success Dialog (Scenario B)
        if (showUnlockSuccessAnim) {
            Dialog(onDismissRequest = { showUnlockSuccessAnim = false }) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24)),
                    border = BorderStroke(2.dp, Color(0xFFF7B801)) // Gold border
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "✨ EVENT TERBUKA ✨",
                            color = Color(0xFFF7B801),
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Black
                        )
                        
                        Text("🔓", fontSize = 48.sp)
                        
                        Text(
                            text = "Selamat Datang di\n${resolvedEventName ?: "Acara Khusus"}!",
                            color = Color.White,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            textAlign = TextAlign.Center,
                            lineHeight = 22.sp
                        )
                        
                        Text(
                            text = "Seluruh jepretan Anda akan masuk dalam galeri album eksklusif acara ini.",
                            color = Color.Gray,
                            fontSize = 11.sp,
                            textAlign = TextAlign.Center
                        )
                        
                        Button(
                            onClick = {
                                showUnlockSuccessAnim = false
                                onStartClick(unlockedEventId)
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFF7B801)),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text("MULAI SESI FOTO", color = Color(0xFF1E1E24), fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }

        // Camera settings button has been moved to be layout-specific, placed above the ticket button.

        // Auto Update Dialog
        if (autoUpdateInfo != null) {
            Dialog(onDismissRequest = {
                if (!isAutoDownloading) {
                    autoUpdateInfo = null
                }
            }) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24)),
                    border = BorderStroke(1.dp, Color(0xFFE63946).copy(alpha = 0.5f))
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "🚀 Pembaruan Aplikasi",
                            color = Color.White,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        
                        Text(
                            text = "Versi Baru: v${autoUpdateInfo!!.versionName} (${autoUpdateInfo!!.versionCode})\nVersi Sekarang: v$currentVersionName ($currentVersionCode)",
                            color = Color.LightGray,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                            lineHeight = 18.sp
                        )

                        Text(
                            text = "Catatan Rilis:\n${autoUpdateInfo!!.changeLog}",
                            color = Color.Gray,
                            fontSize = 12.sp,
                            textAlign = TextAlign.Center,
                            lineHeight = 16.sp,
                            modifier = Modifier
                                .fillMaxWidth()
                                .background(Color.Black.copy(alpha = 0.2f), shape = RoundedCornerShape(12.dp))
                                .padding(12.dp)
                        )

                        if (autoUpdateError != null) {
                            Text(
                                text = autoUpdateError!!,
                                color = Color(0xFFE63946),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                textAlign = TextAlign.Center
                            )
                        }

                        if (isAutoDownloading) {
                            Column(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                LinearProgressIndicator(
                                    progress = autoDownloadProgress ?: 0f,
                                    color = Color(0xFFE63946),
                                    trackColor = Color.White.copy(alpha = 0.1f),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(8.dp)
                                        .clip(RoundedCornerShape(4.dp))
                                )
                                val pct = ((autoDownloadProgress ?: 0f) * 100).toInt()
                                Text(
                                    text = "Mengunduh pembaruan: $pct%",
                                    color = Color.Gray,
                                    fontSize = 11.sp
                                )
                            }
                        } else if (isAutoInstallPermissionNeeded) {
                            Text(
                                text = "Izin instalasi dari sumber tidak dikenal diperlukan untuk melanjutkan pembaruan.",
                                color = Color.Yellow,
                                fontSize = 12.sp,
                                textAlign = TextAlign.Center
                            )
                            
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                TextButton(
                                    onClick = { isAutoInstallPermissionNeeded = false },
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text("Batal", color = Color.Gray)
                                }
                                Button(
                                    onClick = {
                                        updateManager.openInstallPermissionSettings()
                                        isAutoInstallPermissionNeeded = false
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                    modifier = Modifier.weight(1.5f)
                                ) {
                                    Text("Buka Pengaturan", color = Color.White, fontSize = 13.sp)
                                }
                            }
                        } else {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                TextButton(
                                    onClick = { autoUpdateInfo = null },
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Text("Nanti", color = Color.Gray)
                                }
                                Button(
                                    onClick = {
                                        if (!updateManager.canRequestPackageInstalls()) {
                                            isAutoInstallPermissionNeeded = true
                                        } else {
                                            isAutoDownloading = true
                                            autoUpdateError = null
                                            scope.launch {
                                                val backendUrl = configManager.backendUrl
                                                val sanitizedBase = if (backendUrl.endsWith("/")) backendUrl else "$backendUrl/"
                                                val fullApkUrl = if (autoUpdateInfo!!.apkUrl.startsWith("http")) {
                                                    autoUpdateInfo!!.apkUrl
                                                } else {
                                                    "$sanitizedBase${autoUpdateInfo!!.apkUrl}"
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
                                                    autoDownloadProgress = progress
                                                }
                                                isAutoDownloading = false
                                                if (file != null) {
                                                    updateManager.installApk(file)
                                                } else {
                                                    autoUpdateError = "Gagal mengunduh APK. Silakan periksa koneksi."
                                                }
                                            }
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF52B788)),
                                    modifier = Modifier.weight(2f)
                                ) {
                                    Text("Unduh & Instal", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                }
                            }
                        }
                    }
                }
            }
        }

        // Sliding side drawer from the right
        AnimatedVisibility(
            visible = showQuickSettings,
            enter = slideInHorizontally(initialOffsetX = { it }),
            exit = slideOutHorizontally(targetOffsetX = { it }),
            modifier = Modifier.fillMaxSize()
        ) {
            QuickSettingsDialog(
                configManager = configManager,
                onDismissRequest = { showQuickSettings = false }
            )
        }
    }
}

fun Context.findActivity(): FragmentActivity? {
    var context = this
    while (context is ContextWrapper) {
        if (context is FragmentActivity) return context
        context = context.baseContext
    }
    return null
}

fun checkAndShowBiometric(
    context: Context,
    onSuccess: () -> Unit,
    onFallbackPin: () -> Unit
) {
    val activity = context.findActivity()
    if (activity == null) {
        onFallbackPin()
        return
    }

    val biometricManager = BiometricManager.from(context)
    val canAuthenticate = biometricManager.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG)
    
    if (canAuthenticate == BiometricManager.BIOMETRIC_SUCCESS) {
        val executor = ContextCompat.getMainExecutor(context)
        val biometricPrompt = BiometricPrompt(
            activity,
            executor,
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    super.onAuthenticationError(errorCode, errString)
                    // If user cancelled or error occurs, fallback to PIN
                    onFallbackPin()
                }

                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    super.onAuthenticationSucceeded(result)
                    onSuccess()
                }

                override fun onAuthenticationFailed() {
                    super.onAuthenticationFailed()
                    // Keep scanning
                }
            }
        )

        val promptInfo = BiometricPrompt.PromptInfo.Builder()
            .setTitle("Admin Access")
            .setSubtitle("Gunakan sidik jari Anda untuk memverifikasi identitas")
            .setNegativeButtonText("Gunakan PIN")
            .setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_STRONG)
            .build()

        try {
            biometricPrompt.authenticate(promptInfo)
        } catch (e: Exception) {
            onFallbackPin()
        }
    } else {
        onFallbackPin()
    }
}

@Composable
fun InfiniteScrollingPhotoList(
    photoUrls: List<String>,
    isLandscape: Boolean = false,
    modifier: Modifier = Modifier
) {
    val items = remember(photoUrls) {
        val baseList = if (photoUrls.isNotEmpty()) photoUrls else listOf("mock1", "mock2", "mock3", "mock4")
        var repeated = baseList
        // Keep repeating the list until we have at least 16 items to ensure seamless loop
        while (repeated.size < 16) {
            repeated = repeated + baseList
        }
        repeated
    }
    val itemSpacing = 12.dp
    val itemHeight = if (isLandscape) 844.dp else 396.dp
    
    val scrollState = rememberScrollState()
    val density = androidx.compose.ui.platform.LocalDensity.current
    
    val oneCycleHeightPx = remember(items, density, isLandscape) {
        val baseListSize = if (photoUrls.isNotEmpty()) photoUrls.size else 4
        val itemHeightPx = with(density) { itemHeight.toPx() }
        val spacingPx = with(density) { itemSpacing.toPx() }
        (itemHeightPx + spacingPx) * baseListSize
    }

    LaunchedEffect(items, oneCycleHeightPx) {
        if (oneCycleHeightPx > 0f) {
            var currentScroll = 0f
            while (true) {
                try {
                    currentScroll += 1.5f
                    if (currentScroll >= oneCycleHeightPx) {
                        currentScroll -= oneCycleHeightPx
                    }
                    scrollState.scrollTo(currentScroll.toInt())
                } catch (e: Exception) {
                    e.printStackTrace()
                }
                delay(16)
            }
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(scrollState, enabled = false),
        verticalArrangement = Arrangement.spacedBy(itemSpacing)
    ) {
        items.forEach { item ->
            StripItem(item = item, isLandscape = isLandscape)
        }
    }
}

@Composable
fun StripItem(item: String, isLandscape: Boolean = false) {
    val itemHeight = if (isLandscape) 844.dp else 396.dp
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(itemHeight)
            .clip(RoundedCornerShape(6.dp)),
        contentAlignment = Alignment.Center
    ) {
        if (item.startsWith("http")) {
            AsyncImage(
                model = item,
                contentDescription = "History Photo",
                contentScale = ContentScale.Fit,
                modifier = Modifier.fillMaxSize()
            )
        } else {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        brush = androidx.compose.ui.graphics.Brush.linearGradient(
                            colors = listOf(Color(0xFFDFDFDF), Color(0xFFF5F5F5))
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "📸",
                    fontSize = 24.sp,
                    color = Color.Gray
                )
            }
        }
    }
}

@Composable
fun PinEntryDialog(
    title: String,
    subtitle: String,
    correctPin: String,
    onDismissRequest: () -> Unit,
    onSuccess: () -> Unit
) {
    var pinText by remember { mutableStateOf("") }
    var isError by remember { mutableStateOf(false) }
    val coroutineScope = rememberCoroutineScope()
    val shakeOffset = remember { Animatable(0f) }

    val onDigitClick = { digit: String ->
        if (pinText.length < 4 && !isError) {
            pinText += digit
            if (pinText.length == 4) {
                if (pinText == correctPin) {
                    onSuccess()
                } else {
                    isError = true
                    coroutineScope.launch {
                        shakeOffset.animateTo(
                            targetValue = 0f,
                            animationSpec = keyframes {
                                durationMillis = 400
                                0f at 0
                                -15f at 50
                                15f at 100
                                -15f at 150
                                15f at 200
                                -10f at 250
                                10f at 300
                                -5f at 350
                                0f at 400
                            }
                        )
                    }
                    coroutineScope.launch {
                        delay(800)
                        pinText = ""
                        isError = false
                    }
                }
            }
        }
    }

    val onBackspaceClick = {
        if (pinText.isNotEmpty() && !isError) {
            pinText = pinText.dropLast(1)
        }
    }

    Dialog(onDismissRequest = onDismissRequest) {
        Card(
            modifier = Modifier
                .width(340.dp)
                .padding(8.dp),
            shape = RoundedCornerShape(28.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF121217)),
            border = BorderStroke(1.dp, Color(0xFF2A2A35))
        ) {
            Column(
                modifier = Modifier
                    .padding(24.dp)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text(
                    text = title,
                    color = Color.White,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center
                )

                Text(
                    text = subtitle,
                    color = Color.Gray,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(horizontal = 8.dp)
                )

                Spacer(modifier = Modifier.height(8.dp))

                Row(
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .offset { IntOffset(shakeOffset.value.toInt(), 0) }
                        .padding(vertical = 8.dp)
                ) {
                    for (i in 0 until 4) {
                        val isFilled = i < pinText.length
                        val dotColor = when {
                            isError -> Color(0xFFE63946)
                            isFilled -> Color(0xFFE63946)
                            else -> Color.White.copy(alpha = 0.2f)
                        }
                        val scale by animateFloatAsState(
                            targetValue = if (isFilled) 1.3f else 1.0f,
                            animationSpec = spring(
                                dampingRatio = Spring.DampingRatioMediumBouncy,
                                stiffness = Spring.StiffnessLow
                            ),
                            label = "DotScale"
                        )
                        Box(
                            modifier = Modifier
                                .size(14.dp)
                                .graphicsLayer {
                                    scaleX = scale
                                    scaleY = scale
                                }
                                .background(dotColor, CircleShape)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    val spacing = 16.dp
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing)) {
                        KeypadButton("1", { onDigitClick("1") })
                        KeypadButton("2", { onDigitClick("2") })
                        KeypadButton("3", { onDigitClick("3") })
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing)) {
                        KeypadButton("4", { onDigitClick("4") })
                        KeypadButton("5", { onDigitClick("5") })
                        KeypadButton("6", { onDigitClick("6") })
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing)) {
                        KeypadButton("7", { onDigitClick("7") })
                        KeypadButton("8", { onDigitClick("8") })
                        KeypadButton("9", { onDigitClick("9") })
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing)) {
                        KeypadButton("BATAL", { onDismissRequest() }, isAction = true)
                        KeypadButton("0", { onDigitClick("0") })
                        KeypadButton("⌫", { onBackspaceClick() }, isAction = true)
                    }
                }
            }
        }
    }
}

@Composable
fun KeypadButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    isAction: Boolean = false
) {
    Box(
        modifier = modifier
            .size(68.dp)
            .clip(CircleShape)
            .background(if (isAction) Color.Transparent else Color.White.copy(alpha = 0.08f))
            .clickable(onClick = onClick)
            .then(
                if (!isAction) Modifier.border(1.dp, Color.White.copy(alpha = 0.08f), CircleShape)
                else Modifier
            ),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = text,
            color = if (isAction) Color.White.copy(alpha = 0.6f) else Color.White,
            fontSize = if (isAction) 13.sp else 24.sp,
            fontWeight = if (isAction) FontWeight.Medium else FontWeight.SemiBold
        )
    }
}

@Composable
fun ModernHomeLayout(
    resolvedEventName: String?,
    logoTextPart1: String,
    logoTextPart2: String,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    buttonScale: Float,
    dy: Float,
    creativeX: Float,
    studioX: Float,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Top Left Logo
        Column(
            modifier = Modifier
                .statusBarsPadding()
                .padding(top = 40.dp, start = if (isLandscape) 144.dp else 24.dp)
                .align(Alignment.TopStart)
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                    onClick = onLogoClick
                )
        ) {
            Text(
                text = logoTextPart1,
                color = Color.White,
                fontSize = 32.sp,
                fontWeight = FontWeight.Black,
                lineHeight = 32.sp,
                modifier = Modifier.offset { IntOffset(creativeX.dp.roundToPx(), 0) }
            )
            if (logoTextPart2.isNotEmpty()) {
                Text(
                    text = logoTextPart2,
                    color = Color.White,
                    fontSize = 32.sp,
                    fontWeight = FontWeight.Light,
                    lineHeight = 32.sp,
                    modifier = Modifier.offset { IntOffset(studioX.dp.roundToPx(), 0) }
                )
            }
        }

        // Elongated Tilted Scrolling Photo Strip in the Top Right Corner (Aligns directly to screen edges)
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(
                    x = if (isLandscape) 30.dp else 10.dp,
                    y = if (isLandscape) (-150).dp else (-150).dp
                )
                .graphicsLayer {
                    rotationZ = if (isLandscape) -22f else -24f
                    shadowElevation = 24f
                    shape = RoundedCornerShape(16.dp)
                    clip = true
                }
                .requiredWidth(if (isLandscape) 300.dp else 220.dp)
                .requiredHeight(if (isLandscape) 4000.dp else 3000.dp)
                .background(Color.White)
                .padding(8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
        }

        // Center Content: Slogan (Left side)
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.Center)
                .padding(bottom = 60.dp, start = if (isLandscape) 144.dp else 24.dp),
            verticalArrangement = Arrangement.Center
        ) {
            Text(
                text = "All You need\nis special",
                color = Color.White,
                fontSize = if (isLandscape) 56.sp else 46.sp,
                fontWeight = FontWeight.ExtraBold,
                lineHeight = if (isLandscape) 64.sp else 52.sp,
                fontFamily = FontFamily.SansSerif,
                modifier = Modifier
                    .fillMaxWidth(if (isLandscape) 0.5f else 0.6f)
                    .offset { IntOffset(0, dy.dp.roundToPx()) }
            )
        }

        // Bottom CTA and Description
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .align(Alignment.BottomCenter)
                .padding(bottom = 40.dp, start = if (isLandscape) 144.dp else 24.dp, end = if (isLandscape) 144.dp else 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(24.dp)
        ) {
            // Pill shape START Button
            Button(
                onClick = onStartClick,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF121212),
                    contentColor = Color.White
                ),
                shape = RoundedCornerShape(50.dp),
                contentPadding = PaddingValues(horizontal = 40.dp, vertical = 18.dp),
                modifier = Modifier
                    .height(60.dp)
                    .width(220.dp)
                    .graphicsLayer {
                        scaleX = buttonScale
                        scaleY = buttonScale
                    }
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center,
                    modifier = Modifier.fillMaxSize()
                ) {
                    Text(
                        text = "START",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Icon(
                        imageVector = Icons.Default.ArrowForward,
                        contentDescription = "Start",
                        tint = Color.White
                    )
                }
            }

            // Description text & branding logo
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Bottom
            ) {
                Text(
                    text = "Our Creative Studio provides a\nprofessional place to capture some\nspecial moments. So that, You need\nto decide choosing us as your first option.",
                    color = Color.White.copy(alpha = 0.7f),
                    fontSize = 10.sp,
                    lineHeight = 14.sp,
                    modifier = Modifier.weight(1f)
                )
                
                Column(
                    horizontalAlignment = Alignment.End,
                    modifier = Modifier.padding(start = 16.dp)
                ) {
                    Text(
                        text = "Jeprat",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        lineHeight = 18.sp
                    )
                    Text(
                        text = "Jepret",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Light,
                        lineHeight = 18.sp
                    )
                }
            }
        }

        // Quick Settings Camera Button (styled exactly like ticket button, right above it)
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(
                    bottom = if (isMultiEventMode) 200.dp else 144.dp,
                    start = if (isLandscape) 144.dp else 48.dp
                )
                .size(44.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.15f))
                .border(BorderStroke(1.dp, Color.White.copy(alpha = 0.25f)), CircleShape)
                .clickable { onCameraClick() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CameraAlt,
                contentDescription = "Quick Settings Trigger",
                tint = Color.White.copy(alpha = 0.8f),
                modifier = Modifier.size(20.dp)
            )
        }

        // Multi-Event Ticket Launcher Icon
        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 144.dp, start = if (isLandscape) 144.dp else 48.dp)
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.15f))
                    .border(BorderStroke(1.dp, Color.White.copy(alpha = 0.25f)), CircleShape)
                    .clickable { onTicketClick() },
                contentAlignment = Alignment.Center
            ) {
                Text(text = "🎟️", fontSize = 18.sp)
            }
        }
    }
}

@Composable
fun CutePastelHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    buttonScale: Float,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Inner card with border & padding
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp)
                .background(themeColors.background, RoundedCornerShape(16.dp))
                .border(BorderStroke(4.dp, themeColors.border), RoundedCornerShape(16.dp))
                .padding(16.dp)
        ) {
            // Cartoon ornaments
            Text(
                text = "★",
                color = themeColors.accentColor,
                fontSize = 32.sp,
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .offset(x = 20.dp, y = 80.dp)
            )
            Text(
                text = "💛",
                fontSize = 28.sp,
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 40.dp, y = (-160).dp)
            )
            Text(
                text = "✨",
                fontSize = 24.sp,
                modifier = Modifier
                    .align(Alignment.CenterStart)
                    .offset(x = 60.dp, y = (-20).dp)
            )

            // Top Left Logo
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .statusBarsPadding()
                    .padding(top = 16.dp, start = if (isLandscape) 80.dp else 16.dp)
                    .align(Alignment.TopStart)
                    .clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null,
                        onClick = onLogoClick
                    )
            ) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(themeColors.buttonBackground)
                        .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(12.dp))
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                ) {
                    Text(
                        text = resolvedEventName ?: "Jeprat Jepret",
                        color = themeColors.buttonContent,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            // Center Content
            Column(
                modifier = Modifier
                    .fillMaxWidth(if (isLandscape) 0.5f else 0.65f)
                    .align(Alignment.CenterStart)
                    .padding(start = if (isLandscape) 80.dp else 16.dp),
                verticalArrangement = Arrangement.spacedBy(20.dp)
            ) {
                Box(
                    modifier = Modifier
                        .background(Color.White, RoundedCornerShape(16.dp))
                        .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(16.dp))
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                ) {
                    Text(
                        text = "Ayo foto bareng! 📸✨",
                        color = themeColors.onBackground,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
                
                Box(
                    modifier = Modifier
                        .size(if (isLandscape) 180.dp else 150.dp)
                        .clip(RoundedCornerShape(24.dp))
                        .background(themeColors.cardBackground)
                        .border(BorderStroke(4.dp, themeColors.border), RoundedCornerShape(24.dp))
                        .padding(12.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Box(
                        modifier = Modifier
                            .size(24.dp)
                            .align(Alignment.TopEnd)
                            .clip(CircleShape)
                            .background(themeColors.accentColor)
                            .border(BorderStroke(3.dp, themeColors.border), CircleShape)
                    )
                    Box(
                        modifier = Modifier
                            .fillMaxSize(0.6f)
                            .clip(CircleShape)
                            .background(Color.White)
                            .border(BorderStroke(4.dp, themeColors.border), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxSize(0.5f)
                                .clip(CircleShape)
                                .background(themeColors.border)
                        )
                    }
                }

                Text(
                    text = "Setiap Momen\nSangat Istimewa!",
                    color = themeColors.onBackground,
                    fontSize = if (isLandscape) 38.sp else 32.sp,
                    fontWeight = FontWeight.ExtraBold,
                    lineHeight = if (isLandscape) 46.sp else 38.sp,
                    fontFamily = themeColors.fontFamily
                )
            }

            // Bottom CTA
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .align(Alignment.BottomCenter)
                    .padding(bottom = 16.dp, start = if (isLandscape) 80.dp else 16.dp, end = if (isLandscape) 80.dp else 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(20.dp)
            ) {
                Button(
                    onClick = onStartClick,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = themeColors.buttonBackground,
                        contentColor = themeColors.buttonContent
                    ),
                    shape = RoundedCornerShape(50.dp),
                    border = BorderStroke(4.dp, themeColors.border),
                    contentPadding = PaddingValues(horizontal = 36.dp, vertical = 16.dp),
                    modifier = Modifier
                        .height(64.dp)
                        .width(240.dp)
                        .graphicsLayer {
                            scaleX = buttonScale
                            scaleY = buttonScale
                        }
                ) {
                    Text(
                        text = "TAP TO START ➔",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Black,
                        fontFamily = themeColors.fontFamily
                    )
                }

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Bottom
                ) {
                    Text(
                        text = "Ambil foto seru bersama teman-teman!\nHasil cetak stiker bisa langsung ditempel.",
                        color = themeColors.onBackground.copy(alpha = 0.7f),
                        fontSize = 11.sp,
                        lineHeight = 15.sp,
                        fontFamily = themeColors.fontFamily,
                        modifier = Modifier.weight(1f)
                    )
                    
                    Text(
                        text = "Jeprat-Jepret Kiosk",
                        color = themeColors.onBackground,
                        fontSize = 16.sp,
                        fontFamily = themeColors.fontFamily,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            // Quick Settings Camera Button
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = if (isLandscape) 80.dp else 16.dp)
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(themeColors.accentColor)
                    .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(12.dp))
                    .clickable { onCameraClick() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.CameraAlt,
                    contentDescription = "Quick Settings Trigger",
                    tint = themeColors.onBackground,
                    modifier = Modifier.size(24.dp)
                )
            }

            if (isMultiEventMode) {
                Box(
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(bottom = 180.dp, start = if (isLandscape) 80.dp else 16.dp)
                        .size(48.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(themeColors.accentColor)
                        .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(12.dp))
                        .clickable { onTicketClick() },
                    contentAlignment = Alignment.Center
                ) {
                    Text(text = "🎟️", fontSize = 20.sp)
                }
            }
        }

        // Tilted Photo Strip (Overlayed outside the bordered inner card, aligns directly to screen bounds)
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(
                    x = if (isLandscape) 40.dp else 20.dp,
                    y = if (isLandscape) (-130).dp else (-130).dp
                )
                .graphicsLayer {
                    rotationZ = -15f
                    shadowElevation = 12f
                    shape = RoundedCornerShape(12.dp)
                    clip = true
                }
                .requiredWidth(if (isLandscape) 280.dp else 200.dp)
                .requiredHeight(if (isLandscape) 4000.dp else 3000.dp)
                .background(Color.White)
                .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(12.dp))
                .padding(8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
            
            Box(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .offset(y = (-5).dp)
                    .width(70.dp)
                    .height(24.dp)
                    .background(themeColors.accentColor.copy(alpha = 0.85f))
                    .border(BorderStroke(2.dp, themeColors.border))
            )
        }
    }
}

@Composable
fun SparkleStar(
    modifier: Modifier = Modifier,
    color: Color = Color.White,
    pulseDuration: Int = 1500
) {
    val infiniteTransition = rememberInfiniteTransition(label = "SparklePulse")
    val scale by infiniteTransition.animateFloat(
        initialValue = 0.5f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(pulseDuration, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "Scale"
    )
    
    Text(
        text = "✦",
        color = color,
        fontSize = 24.sp,
        modifier = modifier.graphicsLayer {
            scaleX = scale
            scaleY = scale
        }
    )
}

@Composable
fun CartoonFlower(
    modifier: Modifier = Modifier,
    petalColor: Color,
    centerColor: Color = Color(0xFFFFD166),
    rotationSpeed: Int = 8000
) {
    val infiniteTransition = rememberInfiniteTransition(label = "FlowerRotation")
    val angle by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(rotationSpeed, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "Angle"
    )
    
    Box(
        modifier = modifier
            .graphicsLayer { rotationZ = angle }
            .size(56.dp),
        contentAlignment = Alignment.Center
    ) {
        // 5 Petals
        for (i in 0 until 5) {
            val petalAngle = i * 72f
            Box(
                modifier = Modifier
                    .graphicsLayer {
                        rotationZ = petalAngle
                        translationY = -12.dp.toPx()
                    }
                    .size(20.dp)
                    .clip(CircleShape)
                    .background(petalColor)
                    .border(BorderStroke(2.dp, Color(0xFF4A1525)), CircleShape)
            )
        }
        // Center
        Box(
            modifier = Modifier
                .size(20.dp)
                .clip(CircleShape)
                .background(centerColor)
                .border(BorderStroke(2.dp, Color(0xFF4A1525)), CircleShape)
        )
    }
}

@Composable
fun CartoonLeaf(
    modifier: Modifier = Modifier,
    leafColor: Color = Color(0xFF99E2B4)
) {
    Box(
        modifier = modifier
            .size(32.dp, 48.dp)
            .clip(RoundedCornerShape(topStartPercent = 80, bottomEndPercent = 80))
            .background(leafColor)
            .border(
                BorderStroke(2.dp, Color(0xFF4A1525)),
                RoundedCornerShape(topStartPercent = 80, bottomEndPercent = 80)
            )
    )
}

@Composable
fun CuteNaraHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    buttonScale: Float,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // 1. Wavy Background Waves
        androidx.compose.foundation.Canvas(
            modifier = Modifier.fillMaxSize()
        ) {
            val w = size.width
            val h = size.height
            
            // Top cream wave
            val pathTop = androidx.compose.ui.graphics.Path().apply {
                moveTo(0f, 0f)
                lineTo(w, 0f)
                lineTo(w, h * 0.4f)
                cubicTo(
                    w * 0.75f, h * 0.5f,
                    w * 0.3f, h * 0.3f,
                    0f, h * 0.45f
                )
                close()
            }
            drawPath(pathTop, Color(0xFFFFF9FA))
            
            // Bottom pink wave
            val pathBottom = androidx.compose.ui.graphics.Path().apply {
                moveTo(0f, h)
                lineTo(w, h)
                lineTo(w, h * 0.7f)
                cubicTo(
                    w * 0.65f, h * 0.6f,
                    w * 0.35f, h * 0.8f,
                    0f, h * 0.65f
                )
                close()
            }
            drawPath(pathBottom, Color(0xFFFDE8E9))
        }

        // 2. Background Sparkles/Stars
        SparkleStar(
            modifier = Modifier
                .align(Alignment.TopStart)
                .offset(x = 180.dp, y = 100.dp),
            color = Color(0xFFFFB3C6)
        )
        SparkleStar(
            modifier = Modifier
                .align(Alignment.CenterStart)
                .offset(x = 80.dp, y = (-80).dp),
            color = Color(0xFFFF7597),
            pulseDuration = 1800
        )
        SparkleStar(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(x = (-320).dp, y = 140.dp),
            color = Color(0xFFFFB3C6),
            pulseDuration = 2200
        )
        SparkleStar(
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .offset(x = (-250).dp, y = (-220).dp),
            color = Color(0xFFFF7597),
            pulseDuration = 1200
        )

        // 3. Flower and Leaf Garden at the Bottom
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.BottomStart)
                .height(180.dp)
        ) {
            // Layered leaves and flowers along the bottom
            // Leaf 1
            CartoonLeaf(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 24.dp, y = 10.dp)
                    .graphicsLayer { rotationZ = -20f },
                leafColor = Color(0xFFB7E4C7)
            )
            // Leaf 2
            CartoonLeaf(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 90.dp, y = 30.dp)
                    .graphicsLayer { rotationZ = 15f },
                leafColor = Color(0xFF74C69D)
            )
            // Flower 1 (Vibrant pink)
            CartoonFlower(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 50.dp, y = (-10).dp),
                petalColor = Color(0xFFFF4D6D),
                rotationSpeed = 10000
            )
            
            // Flower 2 (Soft peach)
            CartoonFlower(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 130.dp, y = 20.dp),
                petalColor = Color(0xFFFFB3C6),
                rotationSpeed = 12000
            )

            // Leaf 3 (Middle left)
            CartoonLeaf(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 220.dp, y = 40.dp)
                    .graphicsLayer { rotationZ = -10f },
                leafColor = Color(0xFF95D5B2)
            )

            // Center-right flowers/leaves
            // Leaf 4
            CartoonLeaf(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .offset(x = (-120).dp, y = 20.dp)
                    .graphicsLayer { rotationZ = 30f },
                leafColor = Color(0xFFB7E4C7)
            )
            // Flower 3 (Sweet orange/yellow)
            CartoonFlower(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .offset(x = (-80).dp, y = (-20).dp),
                petalColor = Color(0xFFFF9F1C),
                rotationSpeed = 9000
            )
            // Leaf 5
            CartoonLeaf(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .offset(x = (-30).dp, y = 10.dp)
                    .graphicsLayer { rotationZ = -15f },
                leafColor = Color(0xFF74C69D)
            )
            // Flower 4 (Coral red)
            CartoonFlower(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .offset(x = (-20).dp, y = 10.dp),
                petalColor = Color(0xFFFF7597),
                rotationSpeed = 15000
            )
        }

        // 4. Top Left Photo Preview Container
        Box(
            modifier = Modifier
                .statusBarsPadding()
                .padding(top = 16.dp, start = 16.dp)
                .size(110.dp)
                .align(Alignment.TopStart)
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                    onClick = onLogoClick
                )
                .clip(RoundedCornerShape(16.dp))
                .background(Color.White)
                .border(BorderStroke(4.dp, Color.White), RoundedCornerShape(16.dp))
                .border(BorderStroke(2.dp, Color(0xFF4A1525)), RoundedCornerShape(16.dp)),
            contentAlignment = Alignment.Center
        ) {
            if (historyList.isNotEmpty()) {
                AsyncImage(
                    model = historyList.first(),
                    contentDescription = "Latest Kiosk Photo",
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color(0xFFFFF0F5)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.CameraAlt,
                        contentDescription = "Camera placeholder",
                        tint = Color(0xFFFF7597),
                        modifier = Modifier.size(36.dp)
                    )
                }
            }
        }

        // 5. Main Title & Center Content
        Column(
            modifier = Modifier
                .fillMaxWidth(if (isLandscape) 0.55f else 0.85f)
                .align(if (isLandscape) Alignment.CenterStart else Alignment.Center)
                .padding(start = if (isLandscape) 150.dp else 16.dp, end = if (isLandscape) 0.dp else 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Slanted Title container
            Box(
                modifier = Modifier
                    .graphicsLayer {
                        rotationZ = -5f
                        shadowElevation = 10f
                    }
                    .background(Color(0xFFFF7597), RoundedCornerShape(24.dp))
                    .border(BorderStroke(4.dp, Color.White), RoundedCornerShape(24.dp))
                    .padding(horizontal = 36.dp, vertical = 16.dp)
            ) {
                Text(
                    text = resolvedEventName?.uppercase() ?: "NARA KLIK",
                    color = Color.White,
                    fontSize = if (isLandscape) 40.sp else 32.sp,
                    fontWeight = FontWeight.Black,
                    fontFamily = FontFamily.SansSerif,
                    textAlign = TextAlign.Center,
                    style = LocalTextStyle.current.copy(
                        shadow = Shadow(
                            color = Color(0xFF4A1525),
                            offset = Offset(4f, 4f),
                            blurRadius = 0f
                        )
                    )
                )
            }

            // Subtitle pill badge
            Box(
                modifier = Modifier
                    .graphicsLayer {
                        rotationZ = 3f
                    }
                    .background(Color(0xFF95D5B2), RoundedCornerShape(50.dp))
                    .border(BorderStroke(2.dp, Color.White), RoundedCornerShape(50.dp))
                    .border(BorderStroke(1.5.dp, Color(0xFF2D6A4F)), RoundedCornerShape(50.dp))
                    .padding(horizontal = 20.dp, vertical = 6.dp)
            ) {
                Text(
                    text = "Mini Studio Foto",
                    color = Color(0xFF2D6A4F),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.SansSerif
                )
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Pulse Start Button
            Button(
                onClick = onStartClick,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFFFF7597),
                    contentColor = Color.White
                ),
                shape = RoundedCornerShape(50.dp),
                border = BorderStroke(4.dp, Color.White),
                contentPadding = PaddingValues(horizontal = 32.dp, vertical = 14.dp),
                modifier = Modifier
                    .height(60.dp)
                    .width(240.dp)
                    .graphicsLayer {
                        scaleX = buttonScale
                        scaleY = buttonScale
                        shadowElevation = 8f
                    }
            ) {
                Text(
                    text = "TAP TO START ➔",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Black,
                    fontFamily = FontFamily.SansSerif
                )
            }
        }

        // 6. Bottom Social Handles and Phone Info
        Row(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(bottom = 24.dp)
                .fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterHorizontally),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Instagram badge
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier
                    .background(Color.White, RoundedCornerShape(20.dp))
                    .border(BorderStroke(2.dp, Color(0xFF4A1525)), RoundedCornerShape(20.dp))
                    .padding(horizontal = 12.dp, vertical = 6.dp)
            ) {
                Text(text = "📸", fontSize = 14.sp)
                Text(
                    text = "@nara.klik",
                    color = Color(0xFF4A1525),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black
                )
            }

            // WhatsApp phone number badge
            Box(
                modifier = Modifier
                    .background(Color(0xFFFF7597), RoundedCornerShape(20.dp))
                    .border(BorderStroke(2.dp, Color.White), RoundedCornerShape(20.dp))
                    .border(BorderStroke(1.5.dp, Color(0xFF4A1525)), RoundedCornerShape(20.dp))
                    .padding(horizontal = 14.dp, vertical = 6.dp)
            ) {
                Text(
                    text = "08963000888",
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black
                )
            }
        }

        // Quick Settings Camera Button (styled exactly like ticket button, right above it)
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(bottom = 180.dp, start = 16.dp)
                .size(48.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFFFFB3C6))
                .border(BorderStroke(2.dp, Color(0xFF4A1525)), RoundedCornerShape(12.dp))
                .clickable { onCameraClick() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CameraAlt,
                contentDescription = "Quick Settings Trigger",
                tint = Color(0xFF4A1525),
                modifier = Modifier.size(24.dp)
            )
        }

        // Multi-Event Ticket Button if enabled
        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = 16.dp)
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFFFB3C6))
                    .border(BorderStroke(2.dp, Color(0xFF4A1525)), RoundedCornerShape(12.dp))
                    .clickable { onTicketClick() },
                contentAlignment = Alignment.Center
            ) {
                Text(text = "🎟️", fontSize = 20.sp)
            }
        }

        // 7. Right Tilted Photo Strip (for history)
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(
                    x = if (isLandscape) 40.dp else 20.dp,
                    y = if (isLandscape) (-130).dp else (-130).dp
                )
                .graphicsLayer {
                    rotationZ = -15f
                    shadowElevation = 12f
                    shape = RoundedCornerShape(12.dp)
                    clip = true
                }
                .requiredWidth(if (isLandscape) 280.dp else 200.dp)
                .requiredHeight(if (isLandscape) 4000.dp else 3000.dp)
                .background(Color.White)
                .border(BorderStroke(3.dp, Color(0xFFFF7597)), RoundedCornerShape(12.dp))
                .padding(8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
            
            // Pink hanging tape
            Box(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .offset(y = (-5).dp)
                    .width(70.dp)
                    .height(24.dp)
                    .background(Color(0xFFFFB3C6).copy(alpha = 0.9f))
                    .border(BorderStroke(2.dp, Color(0xFFFF7597)))
            )
        }
    }
}

@Composable
fun LuxuryGoldHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Inner card with double borders & padding
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp)
                .background(themeColors.background, RoundedCornerShape(8.dp))
                .border(BorderStroke(1.dp, themeColors.accentColor), RoundedCornerShape(8.dp))
                .padding(4.dp)
                .border(BorderStroke(2.dp, themeColors.accentColor), RoundedCornerShape(6.dp))
                .padding(16.dp)
        ) {
            // Luxury sparkles
            Text(
                text = "✦",
                color = themeColors.accentColor.copy(alpha = 0.5f),
                fontSize = 24.sp,
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .offset(x = 40.dp, y = 100.dp)
            )
            Text(
                text = "✦",
                color = themeColors.accentColor.copy(alpha = 0.4f),
                fontSize = 18.sp,
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 60.dp, y = (-200).dp)
            )
            Text(
                text = "✦",
                color = themeColors.accentColor.copy(alpha = 0.6f),
                fontSize = 22.sp,
                modifier = Modifier
                    .align(Alignment.CenterEnd)
                    .offset(x = (-300).dp, y = 80.dp)
            )

            // Top Monogram Logo
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier
                    .statusBarsPadding()
                    .padding(top = 16.dp, start = if (isLandscape) 80.dp else 16.dp)
                    .align(Alignment.TopStart)
                    .clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null,
                        onClick = onLogoClick
                    )
            ) {
                Box(
                    modifier = Modifier
                        .size(64.dp)
                        .border(BorderStroke(1.5.dp, themeColors.accentColor), CircleShape)
                        .padding(4.dp)
                        .border(BorderStroke(0.5.dp, themeColors.onBackground), CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = if (resolvedEventName.isNullOrEmpty()) "J" else resolvedEventName.take(1),
                        color = themeColors.accentColor,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = resolvedEventName ?: "WEDDING KIOSK",
                    color = themeColors.onBackground,
                    fontFamily = themeColors.fontFamily,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 2.sp
                )
            }

            // Center Content
            Column(
                modifier = Modifier
                    .fillMaxWidth(if (isLandscape) 0.5f else 0.65f)
                    .align(Alignment.CenterStart)
                    .padding(start = if (isLandscape) 80.dp else 16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text(
                    text = "WELCOME TO",
                    color = themeColors.accentColor,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium,
                    letterSpacing = 4.sp,
                    fontFamily = themeColors.fontFamily
                )
                Text(
                    text = resolvedEventName ?: "A Beautiful\nCelebration",
                    color = themeColors.onBackground,
                    fontSize = if (isLandscape) 44.sp else 36.sp,
                    fontWeight = FontWeight.Light,
                    lineHeight = if (isLandscape) 52.sp else 44.sp,
                    fontFamily = themeColors.fontFamily
                )
                Box(
                    modifier = Modifier
                        .width(80.dp)
                        .height(1.dp)
                        .background(themeColors.accentColor)
                )
                Text(
                    text = "Capture your special moments in our exclusive luxury photo kiosk.",
                    color = themeColors.onBackground.copy(alpha = 0.6f),
                    fontSize = 12.sp,
                    lineHeight = 18.sp,
                    fontFamily = themeColors.fontFamily
                )
            }

            // Bottom CTA
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .align(Alignment.BottomCenter)
                    .padding(bottom = 16.dp, start = if (isLandscape) 80.dp else 16.dp, end = if (isLandscape) 80.dp else 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(20.dp)
            ) {
                Button(
                    onClick = onStartClick,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = themeColors.buttonBackground,
                        contentColor = themeColors.buttonContent
                    ),
                    shape = RoundedCornerShape(4.dp),
                    border = BorderStroke(1.5.dp, themeColors.onBackground),
                    contentPadding = PaddingValues(horizontal = 44.dp, vertical = 18.dp),
                    modifier = Modifier
                        .height(56.dp)
                        .width(260.dp)
                ) {
                    Text(
                        text = "BEGIN EXPERIENCE",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 2.sp,
                        fontFamily = themeColors.fontFamily
                    )
                }

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Bottom
                ) {
                    Text(
                        text = "Designed for premium celebrations.",
                        color = themeColors.onBackground.copy(alpha = 0.4f),
                        fontSize = 9.sp,
                        letterSpacing = 1.sp,
                        fontFamily = themeColors.fontFamily
                    )
                    Text(
                        text = "EST. 2026",
                        color = themeColors.accentColor,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp,
                        fontFamily = themeColors.fontFamily
                    )
                }
            }

            // Quick Settings Camera Button
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 180.dp, start = if (isLandscape) 80.dp else 16.dp)
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(Color.Transparent)
                    .border(BorderStroke(1.5.dp, themeColors.accentColor), CircleShape)
                    .clickable { onCameraClick() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.CameraAlt,
                    contentDescription = "Quick Settings Trigger",
                    tint = themeColors.accentColor,
                    modifier = Modifier.size(20.dp)
                )
            }

            if (isMultiEventMode) {
                Box(
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(bottom = 120.dp, start = if (isLandscape) 80.dp else 16.dp)
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(Color.Transparent)
                        .border(BorderStroke(1.5.dp, themeColors.accentColor), CircleShape)
                        .clickable { onTicketClick() },
                    contentAlignment = Alignment.Center
                ) {
                    Text(text = "🎟️", fontSize = 16.sp)
                }
            }
        }

        // Tilted photo strip (Outside the bordered card, aligns to screen edges)
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(
                    x = if (isLandscape) 30.dp else 10.dp,
                    y = if (isLandscape) (-150).dp else (-150).dp
                )
                .graphicsLayer {
                    rotationZ = -20f
                    shadowElevation = 16f
                    shape = RoundedCornerShape(8.dp)
                    clip = true
                }
                .requiredWidth(if (isLandscape) 280.dp else 200.dp)
                .requiredHeight(if (isLandscape) 4000.dp else 3000.dp)
                .background(Color.White)
                .border(BorderStroke(2.dp, themeColors.accentColor), RoundedCornerShape(8.dp))
                .padding(8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
        }
    }
}

@Composable
fun MinimalModernHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition(label = "MinimalModernBackground")
    
    val blob1X by infiniteTransition.animateFloat(
        initialValue = 0.1f,
        targetValue = 0.9f,
        animationSpec = infiniteRepeatable(
            animation = tween(15000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "blob1X"
    )
    val blob1Y by infiniteTransition.animateFloat(
        initialValue = 0.2f,
        targetValue = 0.8f,
        animationSpec = infiniteRepeatable(
            animation = tween(18000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "blob1Y"
    )
    val blob2X by infiniteTransition.animateFloat(
        initialValue = 0.8f,
        targetValue = 0.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(22000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "blob2X"
    )
    val blob2Y by infiniteTransition.animateFloat(
        initialValue = 0.9f,
        targetValue = 0.1f,
        animationSpec = infiniteRepeatable(
            animation = tween(16000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "blob2Y"
    )
    
    val startButtonScale by infiniteTransition.animateFloat(
        initialValue = 0.97f,
        targetValue = 1.03f,
        animationSpec = infiniteRepeatable(
            animation = tween(1500, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "startButtonScale"
    )
    
    val stripFloatOffset by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = -20f,
        animationSpec = infiniteRepeatable(
            animation = tween(4000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "stripFloat"
    )

    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Morphing Gradient Background
        androidx.compose.foundation.Canvas(
            modifier = Modifier.fillMaxSize()
        ) {
            drawRect(color = Color(0xFF05050A))
            
            // Cyan Glow
            drawCircle(
                brush = Brush.radialGradient(
                    colors = listOf(Color(0xFF06B6D4).copy(alpha = 0.15f), Color.Transparent),
                    center = Offset(size.width * blob1X, size.height * blob1Y),
                    radius = size.minDimension * 0.7f
                ),
                center = Offset(size.width * blob1X, size.height * blob1Y),
                radius = size.minDimension * 0.7f
            )
            
            // Indigo Glow
            drawCircle(
                brush = Brush.radialGradient(
                    colors = listOf(Color(0xFF6366F1).copy(alpha = 0.12f), Color.Transparent),
                    center = Offset(size.width * blob2X, size.height * blob2Y),
                    radius = size.minDimension * 0.8f
                ),
                center = Offset(size.width * blob2X, size.height * blob2Y),
                radius = size.minDimension * 0.8f
            )
        }

        // Layout content
        if (isLandscape) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 80.dp, vertical = 40.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Left Column: Branding and CTA
                Column(
                    modifier = Modifier
                        .fillMaxHeight()
                        .weight(1.2f),
                    verticalArrangement = Arrangement.SpaceBetween
                ) {
                    // Top Logo
                    Column(
                        modifier = Modifier
                            .statusBarsPadding()
                            .clickable(
                                interactionSource = remember { MutableInteractionSource() },
                                indication = null,
                                onClick = onLogoClick
                            )
                    ) {
                        Text(
                            text = "CREATIVE // STUDIO",
                            color = Color.White,
                            fontFamily = themeColors.fontFamily,
                            fontSize = 24.sp,
                            fontWeight = FontWeight.ExtraBold,
                            letterSpacing = 2.sp
                        )
                        Text(
                            text = resolvedEventName ?: "CLEAN EXPERIENCE",
                            color = themeColors.accentColor,
                            fontFamily = themeColors.fontFamily,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 4.sp
                        )
                    }

                    // Main copy
                    Column(
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        modifier = Modifier.padding(vertical = 24.dp)
                    ) {
                        Text(
                            text = "CAPTURE\nTHE MOMENT",
                            color = Color.White,
                            fontFamily = themeColors.fontFamily,
                            fontSize = 44.sp,
                            fontWeight = FontWeight.Black,
                            lineHeight = 50.sp,
                            letterSpacing = (-1).sp
                        )
                        Text(
                            text = "Step inside, strike a pose, and let the magic begin. Your memories are printing instantly.",
                            color = Color.White.copy(alpha = 0.6f),
                            fontFamily = themeColors.fontFamily,
                            fontSize = 14.sp,
                            lineHeight = 22.sp,
                            modifier = Modifier.fillMaxWidth(0.9f)
                        )
                    }

                    // Bottom CTA
                    Box(
                        modifier = Modifier
                            .navigationBarsPadding()
                            .graphicsLayer {
                                scaleX = startButtonScale
                                scaleY = startButtonScale
                            }
                    ) {
                        Button(
                            onClick = onStartClick,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color.Transparent
                            ),
                            contentPadding = PaddingValues(0.dp),
                            shape = RoundedCornerShape(30.dp),
                            modifier = Modifier
                                .height(60.dp)
                                .width(220.dp)
                                .background(
                                    brush = Brush.linearGradient(
                                        colors = listOf(Color(0xFF6366F1), Color(0xFF06B6D4))
                                    ),
                                    shape = RoundedCornerShape(30.dp)
                                )
                        ) {
                            Text(
                                text = "START SESSION",
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = themeColors.fontFamily,
                                color = Color.White,
                                letterSpacing = 1.sp
                            )
                        }
                    }
                }

                // Right side: Glassmorphic Floating photostrip
                Box(
                    modifier = Modifier
                        .weight(0.8f)
                        .fillMaxHeight(),
                    contentAlignment = Alignment.Center
                ) {
                    Box(
                        modifier = Modifier
                            .offset(y = stripFloatOffset.dp)
                            .graphicsLayer {
                                rotationZ = 4f
                                shadowElevation = 24f
                                shape = RoundedCornerShape(24.dp)
                                clip = true
                            }
                            .width(220.dp)
                            .height(520.dp)
                            .background(Color(0x0AFFFFFF))
                            .border(
                                BorderStroke(1.dp, Color(0x1FFFFFFF)),
                                RoundedCornerShape(24.dp)
                            )
                            .padding(12.dp),
                        contentAlignment = Alignment.TopCenter
                    ) {
                        InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
                    }
                }
            }
        } else {
            // Portrait layout
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(24.dp),
                verticalArrangement = Arrangement.SpaceBetween,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Top Logo
                Column(
                    modifier = Modifier
                        .statusBarsPadding()
                        .padding(top = 16.dp)
                        .fillMaxWidth()
                        .clickable(
                            interactionSource = remember { MutableInteractionSource() },
                            indication = null,
                            onClick = onLogoClick
                        ),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = "CREATIVE // STUDIO",
                        color = Color.White,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.ExtraBold,
                        letterSpacing = 2.sp
                    )
                    Text(
                        text = resolvedEventName ?: "CLEAN EXPERIENCE",
                        color = themeColors.accentColor,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 4.sp
                    )
                }

                // Floating Photostrip (Middle top)
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(300.dp)
                        .padding(vertical = 12.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Box(
                        modifier = Modifier
                            .offset(y = (stripFloatOffset * 0.7f).dp)
                            .graphicsLayer {
                                rotationZ = 3f
                                shadowElevation = 20f
                                shape = RoundedCornerShape(20.dp)
                                clip = true
                            }
                            .width(180.dp)
                            .height(280.dp)
                            .background(Color(0x0AFFFFFF))
                            .border(
                                BorderStroke(1.dp, Color(0x1FFFFFFF)),
                                RoundedCornerShape(20.dp)
                            )
                            .padding(8.dp),
                        contentAlignment = Alignment.TopCenter
                    ) {
                        InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
                    }
                }

                // Copy + CTA (Bottom)
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .padding(bottom = 16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = "CAPTURE THE MOMENT",
                        color = Color.White,
                        fontFamily = themeColors.fontFamily,
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Black,
                        letterSpacing = (-0.5).sp,
                        textAlign = androidx.compose.ui.text.style.TextAlign.Center
                    )
                    Text(
                        text = "Step inside, strike a pose, and let the magic begin. Your memories are printing instantly.",
                        color = Color.White.copy(alpha = 0.6f),
                        fontFamily = themeColors.fontFamily,
                        fontSize = 13.sp,
                        lineHeight = 18.sp,
                        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                        modifier = Modifier.padding(horizontal = 24.dp)
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Box(
                        modifier = Modifier
                            .graphicsLayer {
                                scaleX = startButtonScale
                                scaleY = startButtonScale
                            }
                    ) {
                        Button(
                            onClick = onStartClick,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color.Transparent
                            ),
                            contentPadding = PaddingValues(0.dp),
                            shape = RoundedCornerShape(30.dp),
                            modifier = Modifier
                                .height(56.dp)
                                .width(200.dp)
                                .background(
                                    brush = Brush.linearGradient(
                                        colors = listOf(Color(0xFF6366F1), Color(0xFF06B6D4))
                                    ),
                                    shape = RoundedCornerShape(30.dp)
                                )
                        ) {
                            Text(
                                text = "START SESSION",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = themeColors.fontFamily,
                                color = Color.White,
                                letterSpacing = 1.sp
                            )
                        }
                    }
                }
            }
        }

        // Quick Settings Camera Button (styled exactly like ticket button, right above it)
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(
                    bottom = if (isMultiEventMode) 80.dp else 24.dp,
                    start = if (isLandscape) 80.dp else 24.dp
                )
                .size(44.dp)
                .clip(CircleShape)
                .background(Color(0x1AFFFFFF))
                .border(BorderStroke(1.dp, Color(0x33FFFFFF)), CircleShape)
                .clickable { onCameraClick() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CameraAlt,
                contentDescription = "Quick Settings Trigger",
                tint = Color.White.copy(alpha = 0.8f),
                modifier = Modifier.size(20.dp)
            )
        }

        // Event code button if multi-event mode
        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 24.dp, start = if (isLandscape) 80.dp else 24.dp)
                    .size(44.dp)
                    .clip(RoundedCornerShape(22.dp))
                    .background(Color(0x1AFFFFFF))
                    .border(BorderStroke(1.dp, Color(0x33FFFFFF)), RoundedCornerShape(22.dp))
                    .clickable { onTicketClick() },
                contentAlignment = Alignment.Center
            ) {
                Text(text = "🎟️", fontSize = 16.sp)
            }
        }
    }
}

@Composable
fun FloatingParticle(
    modifier: Modifier = Modifier,
    driftRadius: Float = 40f,
    scale: Float = 1f,
    durationMs: Int = 5000,
    content: @Composable () -> Unit
) {
    val infiniteTransition = rememberInfiniteTransition(label = "particle")
    val angle by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(
            animation = tween(durationMs, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "angle"
    )
    val alpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 0.8f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMs / 2, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "alpha"
    )
    val rotation by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMs * 2, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "rotation"
    )

    val dx = (Math.sin(angle.toDouble()) * driftRadius).toFloat()
    val dy = (Math.cos(angle.toDouble()) * driftRadius).toFloat()

    Box(
        modifier = modifier
            .offset(x = dx.dp, y = dy.dp)
            .graphicsLayer {
                rotationZ = rotation
                scaleX = scale
                scaleY = scale
                this.alpha = alpha
            }
    ) {
        content()
    }
}

@Composable
fun CreativeDynamicHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    buttonScale: Float,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit,
    onCameraClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition(label = "bg_anim")
    val animatedOffset by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(10000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "offset"
    )

    val gradientBrush = Brush.linearGradient(
        colors = listOf(
            Color(0xFF0F0B1E),
            Color(0xFF241442),
            Color(0xFF1B072B),
            Color(0xFF0D061A)
        ),
        start = Offset(animatedOffset, 0f),
        end = Offset(animatedOffset + 800f, 1200f)
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(gradientBrush)
    ) {
        // Floating particles / shapes in background
        FloatingParticle(
            modifier = Modifier.align(Alignment.TopStart).offset(x = 100.dp, y = 120.dp),
            driftRadius = 30f,
            scale = 1.2f,
            durationMs = 6000
        ) {
            Text(text = "✨", fontSize = 24.sp)
        }

        FloatingParticle(
            modifier = Modifier.align(Alignment.TopEnd).offset(x = (-120).dp, y = 160.dp),
            driftRadius = 50f,
            scale = 0.8f,
            durationMs = 8000
        ) {
            Text(text = "🌟", fontSize = 20.sp)
        }

        FloatingParticle(
            modifier = Modifier.align(Alignment.CenterStart).offset(x = 80.dp, y = (-100).dp),
            driftRadius = 40f,
            scale = 1f,
            durationMs = 7000
        ) {
            Box(
                modifier = Modifier
                    .size(24.dp)
                    .border(2.dp, Color(0xFFD946EF), CircleShape)
            )
        }

        FloatingParticle(
            modifier = Modifier.align(Alignment.BottomEnd).offset(x = (-150).dp, y = (-200).dp),
            driftRadius = 35f,
            scale = 1.1f,
            durationMs = 5500
        ) {
            Text(text = "💫", fontSize = 22.sp)
        }

        FloatingParticle(
            modifier = Modifier.align(Alignment.BottomStart).offset(x = 120.dp, y = (-120).dp),
            driftRadius = 45f,
            scale = 0.9f,
            durationMs = 9000
        ) {
            Box(
                modifier = Modifier
                    .size(16.dp)
                    .background(Color(0xFFEC4899), CircleShape)
            )
        }

        // Photo Preview Container (Top Left)
        Box(
            modifier = Modifier
                .statusBarsPadding()
                .padding(top = 16.dp, start = 16.dp)
                .size(110.dp)
                .align(Alignment.TopStart)
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                    onClick = onLogoClick
                )
                .clip(RoundedCornerShape(16.dp))
                .background(Color(0xFF1E1538))
                .border(BorderStroke(3.dp, Color(0xFFD946EF)), RoundedCornerShape(16.dp)),
            contentAlignment = Alignment.Center
        ) {
            if (historyList.isNotEmpty()) {
                AsyncImage(
                    model = historyList.first(),
                    contentDescription = "Latest Kiosk Photo",
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color(0xFF120C24)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.CameraAlt,
                        contentDescription = "Camera placeholder",
                        tint = Color(0xFFA855F7),
                        modifier = Modifier.size(36.dp)
                    )
                }
            }
        }

        // Main Content (Center)
        Column(
            modifier = Modifier
                .fillMaxWidth(if (isLandscape) 0.55f else 0.85f)
                .align(if (isLandscape) Alignment.CenterStart else Alignment.Center)
                .padding(start = if (isLandscape) 150.dp else 16.dp, end = if (isLandscape) 0.dp else 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(20.dp)
        ) {
            // Dynamic Wavy Text
            val titleText = resolvedEventName ?: "CREATIVE CLICK"
            Row(
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                titleText.forEachIndexed { index, char ->
                    val charDelay = index * 60
                    val charAnim = rememberInfiniteTransition(label = "char_$index")
                    val yOffset by charAnim.animateFloat(
                        initialValue = 0f,
                        targetValue = -12f,
                        animationSpec = infiniteRepeatable(
                            animation = tween(700, delayMillis = charDelay, easing = FastOutSlowInEasing),
                            repeatMode = RepeatMode.Reverse
                        ),
                        label = "y_$index"
                    )
                    val colorAnim by charAnim.animateColor(
                        initialValue = Color(0xFFA855F7),
                        targetValue = Color(0xFFEC4899),
                        animationSpec = infiniteRepeatable(
                            animation = tween(1400, delayMillis = charDelay, easing = LinearEasing),
                            repeatMode = RepeatMode.Reverse
                        )
                    )
                    Text(
                        text = char.toString(),
                        color = colorAnim,
                        fontSize = if (isLandscape) 42.sp else 34.sp,
                        fontWeight = FontWeight.Black,
                        fontFamily = FontFamily.SansSerif,
                        modifier = Modifier.graphicsLayer {
                            translationY = yOffset
                        },
                        style = LocalTextStyle.current.copy(
                            shadow = Shadow(
                                color = Color(0xFFD946EF),
                                offset = Offset(2f, 4f),
                                blurRadius = 2f
                            )
                        )
                    )
                }
            }

            // Slogan/Subtitle
            Box(
                modifier = Modifier
                    .graphicsLayer {
                        rotationZ = 2f
                    }
                    .background(Color(0xFF1E1B4B), RoundedCornerShape(50.dp))
                    .border(BorderStroke(2.dp, Color(0xFFA855F7)), RoundedCornerShape(50.dp))
                    .padding(horizontal = 20.dp, vertical = 6.dp)
            ) {
                Text(
                    text = "🌟 Creative Studio 🌟",
                    color = Color(0xFFE9D5FF),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.SansSerif
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Pulse Start Button
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier.size(width = 280.dp, height = 100.dp)
            ) {
                val infiniteGlow = rememberInfiniteTransition(label = "glow")
                val glowScale by infiniteGlow.animateFloat(
                    initialValue = 1f,
                    targetValue = 1.35f,
                    animationSpec = infiniteRepeatable(
                        animation = tween(1500, easing = EaseOutQuad),
                        repeatMode = RepeatMode.Restart
                    ),
                    label = "glowScale"
                )
                val glowAlpha by infiniteGlow.animateFloat(
                    initialValue = 0.7f,
                    targetValue = 0f,
                    animationSpec = infiniteRepeatable(
                        animation = tween(1500, easing = EaseOutQuad),
                        repeatMode = RepeatMode.Restart
                    ),
                    label = "glowAlpha"
                )

                // Expanding Glow ring
                Box(
                    modifier = Modifier
                        .fillMaxWidth(0.85f)
                        .height(60.dp)
                        .graphicsLayer {
                            scaleX = glowScale
                            scaleY = glowScale
                            alpha = glowAlpha
                        }
                        .background(Color(0xFFEC4899).copy(alpha = 0.5f), RoundedCornerShape(30.dp))
                )

                Button(
                    onClick = onStartClick,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFFA855F7),
                        contentColor = Color.White
                    ),
                    shape = RoundedCornerShape(30.dp),
                    border = BorderStroke(3.dp, Color(0xFFD946EF)),
                    contentPadding = PaddingValues(horizontal = 32.dp, vertical = 14.dp),
                    modifier = Modifier
                        .height(60.dp)
                        .width(240.dp)
                        .graphicsLayer {
                            val pulse = 1f + 0.05f * (buttonScale - 1f)
                            scaleX = pulse
                            scaleY = pulse
                            shadowElevation = 12f
                        }
                ) {
                    Text(
                        text = "TAP TO START ➔",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Black,
                        fontFamily = FontFamily.SansSerif,
                        style = LocalTextStyle.current.copy(
                            shadow = Shadow(
                                color = Color(0xFFEC4899),
                                offset = Offset(1f, 2f),
                                blurRadius = 2f
                            )
                        )
                    )
                }
            }
        }

        // Bottom Social handles
        Row(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(bottom = 24.dp)
                .fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterHorizontally),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier
                    .background(Color(0xFF1E1538), RoundedCornerShape(20.dp))
                    .border(BorderStroke(2.dp, Color(0xFFA855F7)), RoundedCornerShape(20.dp))
                    .padding(horizontal = 12.dp, vertical = 6.dp)
            ) {
                Text(text = "📸", fontSize = 14.sp)
                Text(
                    text = "@photobooth.creative",
                    color = Color(0xFFF1E9FF),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black
                )
            }

            Box(
                modifier = Modifier
                    .background(Color(0xFFEC4899), RoundedCornerShape(20.dp))
                    .border(BorderStroke(2.dp, Color.White), RoundedCornerShape(20.dp))
                    .padding(horizontal = 14.dp, vertical = 6.dp)
            ) {
                Text(
                    text = "Tap Screen to Play ⚡",
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black
                )
            }
        }

        // Quick Settings trigger button
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(
                    bottom = if (isMultiEventMode) 80.dp else 24.dp,
                    start = if (isLandscape) 80.dp else 24.dp
                )
                .size(44.dp)
                .clip(CircleShape)
                .background(Color(0x1AFFFFFF))
                .border(BorderStroke(1.dp, Color(0x33FFFFFF)), CircleShape)
                .clickable { onCameraClick() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CameraAlt,
                contentDescription = "Quick Settings Trigger",
                tint = Color.White.copy(alpha = 0.8f),
                modifier = Modifier.size(20.dp)
            )
        }

        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 24.dp, start = if (isLandscape) 80.dp else 24.dp)
                    .size(44.dp)
                    .clip(RoundedCornerShape(22.dp))
                    .background(Color(0x1AFFFFFF))
                    .border(BorderStroke(1.dp, Color(0x33FFFFFF)), RoundedCornerShape(22.dp))
                    .clickable { onTicketClick() },
                contentAlignment = Alignment.Center
            ) {
                Text(text = "🎟️", fontSize = 16.sp)
            }
        }
    }
}
