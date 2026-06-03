package com.example.photobooth.ui.home

import android.content.Context
import android.content.ContextWrapper
import android.widget.Toast
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
import androidx.fragment.app.FragmentActivity
import coil.compose.AsyncImage
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.delay

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
    
    var logoTapCount by remember { mutableIntStateOf(0) }
    var showPinDialog by remember { mutableStateOf(false) }
    var pinInput by remember { mutableStateOf("") }
    var pinError by remember { mutableStateOf(false) }

    // Event states
    var showEventCodeDialog by remember { mutableStateOf(false) }
    var eventCodeInput by remember { mutableStateOf("") }
    var eventCodeError by remember { mutableStateOf<String?>(null) }
    var unlockedEventId by remember { mutableStateOf("general") }
    var showUnlockSuccessAnim by remember { mutableStateOf(false) }

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
        if (resolvedEventName.isNullOrEmpty()) "Creative"
        else {
            val words = resolvedEventName.split(" ")
            if (words.size >= 2) words.take(words.size / 2).joinToString(" ")
            else words.first()
        }
    }
    
    val logoTextPart2 = remember(resolvedEventName) {
        if (resolvedEventName.isNullOrEmpty()) "Studio"
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
                            if (frameId.isNotEmpty()) {
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
            .background(Color(0xFFE63946)) // Premium Red Background
            .padding(24.dp)
    ) {
        // Top Left Logo
        Column(
            modifier = Modifier
                .statusBarsPadding()
                .padding(top = 16.dp)
                .align(Alignment.TopStart)
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null
                ) {
                    logoTapCount++
                    if (logoTapCount >= 5) {
                        logoTapCount = 0
                        if (configManager.useBiometric) {
                            checkAndShowBiometric(
                                context = context,
                                onSuccess = {
                                    onAdminNavigate()
                                },
                                onFallbackPin = {
                                    showPinDialog = true
                                }
                            )
                        } else {
                            showPinDialog = true
                        }
                    }
                }
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
                .offset(x = 35.dp, y = (-20).dp)
                .graphicsLayer {
                    rotationZ = -10f
                    shadowElevation = 16f
                    shape = RoundedCornerShape(16.dp)
                    clip = true
                }
                .width(140.dp)
                .height(420.dp)
                .background(Color.White)
                .padding(top = 16.dp, bottom = 16.dp, start = 8.dp, end = 8.dp),
            contentAlignment = Alignment.TopCenter
        ) {
            InfiniteScrollingPhotoList(photoUrls = historyList)
        }

        // Center Content: Slogan (Left side)
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.Center)
                .padding(bottom = 60.dp),
            verticalArrangement = Arrangement.Center
        ) {
            Text(
                text = "All You need\nis special",
                color = Color.White,
                fontSize = 46.sp,
                fontWeight = FontWeight.ExtraBold,
                lineHeight = 52.sp,
                fontFamily = FontFamily.SansSerif,
                modifier = Modifier
                    .fillMaxWidth(0.6f) // Keep slogan on the left, clear of the top-right tilted photo strip
                    .offset { IntOffset(0, dy.dp.roundToPx()) }
            )
        }

        // Bottom CTA and Description
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .align(Alignment.BottomCenter)
                .padding(bottom = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(24.dp)
        ) {
            // Pill shape START Button
            Button(
                onClick = {
                    val finalEventId = if (configManager.kioskMode == "DEDICATED") configManager.activeEventId else unlockedEventId
                    onStartClick(finalEventId)
                },
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
                        text = "Receipt",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        lineHeight = 18.sp
                    )
                    Text(
                        text = "Photo",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Light,
                        lineHeight = 18.sp
                    )
                }
            }
        }

        // Admin PIN Dialog
        AnimatedVisibility(
            visible = showPinDialog,
            enter = fadeIn() + scaleIn(),
            exit = fadeOut() + scaleOut()
        ) {
            Dialog(onDismissRequest = { 
                showPinDialog = false
                pinInput = ""
                pinError = false
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
                            text = "Admin Access",
                            color = Color.White,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Masukkan PIN 4-digit untuk masuk ke menu admin.",
                            color = Color.Gray,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center
                        )

                        OutlinedTextField(
                            value = pinInput,
                            onValueChange = { 
                                if (it.length <= 4 && it.all { char -> char.isDigit() }) {
                                    pinInput = it
                                    pinError = false
                                }
                            },
                            visualTransformation = PasswordVisualTransformation(),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            isError = pinError,
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = Color.White,
                                unfocusedTextColor = Color.White,
                                focusedBorderColor = Color(0xFFE63946),
                                unfocusedBorderColor = Color.Gray
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )

                        if (pinError) {
                            Text(
                                text = "PIN yang Anda masukkan salah!",
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
                                    showPinDialog = false
                                    pinInput = ""
                                    pinError = false
                                },
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Batal", color = Color.Gray)
                            }
                            
                            Button(
                                onClick = {
                                    if (pinInput == configManager.adminPin) {
                                        showPinDialog = false
                                        pinInput = ""
                                        onAdminNavigate()
                                    } else {
                                        pinError = true
                                    }
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Masuk", color = Color.White)
                            }
                        }
                    }
                }
            }
        }

        // Multi-Event Ticket Launcher (Scenario B)
        if (configManager.kioskMode == "MULTI_EVENT") {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(bottom = 120.dp, start = 24.dp) // Offset above description and left aligned
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color.White.copy(alpha = 0.15f))
                    .border(BorderStroke(1.dp, Color.White.copy(alpha = 0.25f)), RoundedCornerShape(16.dp))
                    .clickable { showEventCodeDialog = true }
                    .padding(horizontal = 16.dp, vertical = 10.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(text = "🎟️", fontSize = 16.sp)
                    Text(
                        text = if (unlockedEventId == "general") "MASUKKAN KODE EVENT" else "EVENT: ${resolvedEventName?.uppercase() ?: "TERKUNCI"}",
                        color = if (unlockedEventId == "general") Color.White else Color(0xFFF7B801),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp
                    )
                }
            }
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
    modifier: Modifier = Modifier
) {
    val items = if (photoUrls.isNotEmpty()) photoUrls else listOf("mock1", "mock2", "mock3", "mock4")
    
    val originalSize = items.size
    val repeatedItems = remember(items) {
        var list = items
        while (list.size < 6) {
            list = list + items
        }
        list
    }
    
    val itemHeight = 93.dp
    val itemSpacing = 8.dp
    val singleCycleHeight = (itemHeight + itemSpacing) * originalSize
    
    val infiniteTransition = rememberInfiniteTransition(label = "StripScroll")
    val scrollProgress by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 12000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "ScrollProgress"
    )
    
    val density = androidx.compose.ui.platform.LocalDensity.current
    val singleCycleHeightPx = with(density) { singleCycleHeight.toPx() }
    
    Box(
        modifier = modifier
            .fillMaxSize()
            .clip(RoundedCornerShape(8.dp))
    ) {
        val animatedOffset = -scrollProgress * singleCycleHeightPx
        
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .offset { IntOffset(0, animatedOffset.toInt()) },
            verticalArrangement = Arrangement.spacedBy(itemSpacing)
        ) {
            val doubleList = repeatedItems + repeatedItems
            doubleList.forEach { item ->
                StripItem(item = item)
            }
        }
    }
}

@Composable
fun StripItem(item: String) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(93.dp)
            .clip(RoundedCornerShape(6.dp))
            .background(Color(0xFFEAEAEA)),
        contentAlignment = Alignment.Center
    ) {
        if (item.startsWith("http")) {
            AsyncImage(
                model = item,
                contentDescription = "History Photo",
                contentScale = ContentScale.Crop,
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
