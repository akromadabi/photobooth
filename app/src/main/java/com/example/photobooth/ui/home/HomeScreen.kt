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
    val resolvedEventName = remember(configManager.syncedFramesJson, configManager.activeEventId, configManager.kioskMode, unlockedEventId) {
        val activeId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
        if (activeId == "general") null
        else {
            try {
                val config = com.google.gson.Gson().fromJson(configManager.syncedFramesJson, com.example.photobooth.data.FrameConfig::class.java)
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
                            val eventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
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
            .padding(24.dp)
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
                    onTicketClick = { showEventCodeDialog = true }
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
                    onTicketClick = { showEventCodeDialog = true }
                )
                AppThemeType.RETRO_ARCADE -> RetroArcadeHomeLayout(
                    resolvedEventName = resolvedEventName,
                    onLogoClick = onLogoClick,
                    isLandscape = isLandscape,
                    historyList = historyList,
                    onStartClick = {
                        val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                        onStartClick(finalEventId)
                    },
                    isMultiEventMode = configManager.kioskMode == "MULTI_EVENT",
                    onTicketClick = { showEventCodeDialog = true }
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
                    onTicketClick = { showEventCodeDialog = true }
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
                                        com.google.gson.Gson().fromJson(configManager.syncedFramesJson, com.example.photobooth.data.FrameConfig::class.java)
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
    onTicketClick: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Top Left Logo
        Column(
            modifier = Modifier
                .statusBarsPadding()
                .padding(top = 16.dp, start = if (isLandscape) 120.dp else 0.dp)
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

        // Elongated Tilted Scrolling Photo Strip in the Top Right Corner
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
                .padding(bottom = 60.dp, start = if (isLandscape) 120.dp else 0.dp),
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
                .padding(bottom = 16.dp, start = if (isLandscape) 120.dp else 0.dp, end = if (isLandscape) 120.dp else 0.dp),
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

        // Multi-Event Ticket Launcher Icon
        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = if (isLandscape) 120.dp else 24.dp)
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
    onTicketClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(themeColors.background)
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

        // Tilted Photo Strip
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

        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = if (isLandscape) 80.dp else 16.dp)
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
}

@Composable
fun LuxuryGoldHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(themeColors.background)
            .padding(12.dp)
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

        // Tilted photo strip
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
}

@Composable
fun RetroArcadeHomeLayout(
    resolvedEventName: String?,
    onLogoClick: () -> Unit,
    isLandscape: Boolean,
    historyList: List<String>,
    onStartClick: () -> Unit,
    isMultiEventMode: Boolean,
    onTicketClick: () -> Unit
) {
    val themeColors = AppTheme.colors
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(themeColors.background)
            .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(8.dp))
            .padding(16.dp)
    ) {
        androidx.compose.foundation.Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.4f)
                .align(Alignment.BottomCenter)
        ) {
            val width = size.width
            val height = size.height
            val gridColor = Color(0xFFDF00FF).copy(alpha = 0.15f)
            
            val linesCount = 10
            for (i in 0..linesCount) {
                val ratio = i.toFloat() / linesCount
                val y = height * (ratio * ratio)
                drawLine(
                    color = gridColor,
                    start = Offset(0f, y),
                    end = Offset(width, y),
                    strokeWidth = 2f
                )
            }
            
            val columnsCount = 14
            for (i in 0..columnsCount) {
                val xRatio = i.toFloat() / columnsCount
                val startX = width * xRatio
                drawLine(
                    color = gridColor,
                    start = Offset(width / 2f, 0f),
                    end = Offset(startX, height),
                    strokeWidth = 2f
                )
            }
        }

        Text(
            text = "👾",
            fontSize = 28.sp,
            modifier = Modifier
                .align(Alignment.TopStart)
                .offset(x = 60.dp, y = 120.dp)
        )
        Text(
            text = "🍒",
            fontSize = 24.sp,
            modifier = Modifier
                .align(Alignment.CenterStart)
                .offset(x = 40.dp, y = (-80).dp)
        )
        Text(
            text = "🕹️",
            fontSize = 26.sp,
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .offset(x = (-320).dp, y = (-180).dp)
        )

        // Top Left Logo
        Column(
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
            Text(
                text = "JEPRAT // JEPRET",
                color = themeColors.accentColor,
                fontFamily = themeColors.fontFamily,
                fontSize = 26.sp,
                fontWeight = FontWeight.ExtraBold,
                style = androidx.compose.ui.text.TextStyle(
                    shadow = Shadow(
                        color = Color(0xFFDF00FF),
                        offset = Offset(2f, 2f),
                        blurRadius = 4f
                    )
                )
            )
            Text(
                text = resolvedEventName ?: "ARCADE EDITION",
                color = Color.White,
                fontFamily = themeColors.fontFamily,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                letterSpacing = 1.sp
            )
        }

        // Tilted photo strip
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(
                    x = if (isLandscape) 30.dp else 10.dp,
                    y = if (isLandscape) (-150).dp else (-150).dp
                )
                .graphicsLayer {
                    rotationZ = -22f
                    shadowElevation = 20f
                    shape = RoundedCornerShape(4.dp)
                    clip = true
                }
                .requiredWidth(if (isLandscape) 280.dp else 200.dp)
                .requiredHeight(if (isLandscape) 4000.dp else 3000.dp)
                .background(Color.Black)
                .border(BorderStroke(3.dp, themeColors.border), RoundedCornerShape(4.dp))
                .padding(8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList, isLandscape = isLandscape)
        }

        // Center Content
        Column(
            modifier = Modifier
                .fillMaxWidth(if (isLandscape) 0.5f else 0.65f)
                .align(Alignment.CenterStart)
                .padding(start = if (isLandscape) 80.dp else 16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            val flashingAlpha by rememberInfiniteTransition().animateFloat(
                initialValue = 0.2f,
                targetValue = 1.0f,
                animationSpec = infiniteRepeatable(
                    animation = tween(durationMillis = 800, easing = LinearEasing),
                    repeatMode = RepeatMode.Reverse
                ),
                label = "FlashingReady"
            )
            
            Text(
                text = "> READY PLAYER ONE",
                color = themeColors.border,
                fontFamily = themeColors.fontFamily,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.alpha(flashingAlpha)
            )
            
            Text(
                text = "INSERT COIN\nTO START SESSION",
                color = themeColors.onBackground,
                fontSize = if (isLandscape) 38.sp else 30.sp,
                fontWeight = FontWeight.ExtraBold,
                lineHeight = if (isLandscape) 46.sp else 38.sp,
                fontFamily = themeColors.fontFamily,
                style = androidx.compose.ui.text.TextStyle(
                    shadow = Shadow(
                        color = Color(0xFFDF00FF).copy(alpha = 0.8f),
                        offset = Offset(2f, 2f),
                        blurRadius = 6f
                    )
                )
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
                shape = RoundedCornerShape(0.dp),
                border = BorderStroke(3.dp, themeColors.accentColor),
                contentPadding = PaddingValues(horizontal = 36.dp, vertical = 18.dp),
                modifier = Modifier
                    .height(60.dp)
                    .width(220.dp)
            ) {
                Text(
                    text = "PRESS START",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.ExtraBold,
                    fontFamily = themeColors.fontFamily,
                    letterSpacing = 1.sp
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
                    text = "COINS: 99 / FREE PLAY",
                    color = themeColors.accentColor.copy(alpha = 0.7f),
                    fontSize = 11.sp,
                    fontFamily = themeColors.fontFamily
                )
                Text(
                    text = "STAGE 1",
                    color = themeColors.border,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = themeColors.fontFamily
                )
            }
        }

        if (isMultiEventMode) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = if (isLandscape) 80.dp else 16.dp)
                    .size(44.dp)
                    .clip(RoundedCornerShape(4.dp))
                    .background(themeColors.accentColor.copy(alpha = 0.2f))
                    .border(BorderStroke(2.dp, themeColors.accentColor), RoundedCornerShape(4.dp))
                    .clickable { onTicketClick() },
                contentAlignment = Alignment.Center
            ) {
                Text(text = "🎟️", fontSize = 16.sp)
            }
        }
    }
}
