package com.example.photobooth.ui.camera

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.media.MediaActionSound
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.OptIn
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.platform.LocalConfiguration
import android.content.res.Configuration
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.data.FrameConfig
import com.example.photobooth.data.EventInfo
import com.example.photobooth.ui.frame.getFramesForLayout
import com.example.photobooth.theme.AppTheme
import com.example.photobooth.theme.AppThemeType
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.File
import java.io.FileOutputStream
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors
import coil.compose.AsyncImage
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Rect
import com.google.android.gms.tasks.Tasks
import com.google.gson.Gson

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CameraCaptureScreen(
    frameId: String,
    onBackClick: () -> Unit,
    onCaptureComplete: (List<String>) -> Unit,
    modifier: Modifier = Modifier,
    eventId: String = "general",
    sessionId: String = "",
    packageId: String = "",
    characterId: String = ""
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val configManager = remember { ConfigManager(context) }
    
    // Resolve frame configuration
    val frame = remember(frameId, eventId) {
        val allFrames = getFramesForLayout(context, "strip", configManager, eventId) + 
                         getFramesForLayout(context, "grid", configManager, eventId) +
                         getFramesForLayout(context, "postcard", configManager, eventId)
        allFrames.firstOrNull { it.id == frameId } ?: allFrames.firstOrNull() ?: getFramesForLayout(context, "strip", configManager).first()
    }

    // Permission state
    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
        )
    }

    val launcher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission(),
        onResult = { granted -> hasCameraPermission = granted }
    )

    LaunchedEffect(Unit) {
        if (!hasCameraPermission) {
            launcher.launch(Manifest.permission.CAMERA)
        }
    }

    if (!hasCameraPermission) {
        Box(
            modifier = modifier
                .fillMaxSize()
                .background(MaterialTheme.colorScheme.background),
            contentAlignment = Alignment.Center
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp),
                modifier = Modifier.padding(24.dp)
            ) {
                Text("Kamera Diperlukan", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
                Text(
                    text = "Aplikasi photobooth membutuhkan izin kamera untuk mengambil foto Anda.",
                    color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
                    textAlign = TextAlign.Center
                )
                Button(
                    onClick = { launcher.launch(Manifest.permission.CAMERA) },
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary)
                ) {
                    Text("Berikan Izin")
                }
            }
        }
    } else {
        CameraCaptureLayout(
            frame = frame,
            configManager = configManager,
            onBackClick = onBackClick,
            onCaptureComplete = onCaptureComplete,
            modifier = modifier,
            eventId = eventId,
            sessionId = sessionId,
            packageId = packageId,
            characterId = characterId
        )
    }
}

@SuppressLint("RestrictedApi")
@Composable
fun CameraCaptureLayout(
    frame: Frame,
    configManager: ConfigManager,
    onBackClick: () -> Unit,
    onCaptureComplete: (List<String>) -> Unit,
    modifier: Modifier = Modifier,
    eventId: String = "general",
    sessionId: String = "",
    packageId: String = "",
    characterId: String = ""
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    
    val totalShots = if (characterId.isNotEmpty()) 1 else frame.slots.size
    var currentShotIndex by remember { mutableIntStateOf(0) }
    val capturedPaths = remember { mutableStateListOf<String>() }

    // Resolve event details
    val eventInfo = remember(eventId) {
        val syncedJson = configManager.syncedFramesJson
        if (syncedJson.isNotEmpty()) {
            try {
                val config = Gson().fromJson(syncedJson, FrameConfig::class.java)
                config.events?.firstOrNull { it.id == eventId }
            } catch (e: Exception) {
                e.printStackTrace()
                null
            }
        } else {
            null
        }
    }

    // Determine the aspect ratio from the active slot
    val slotAspectRatio = remember(frame, currentShotIndex) {
        val activeSlot = frame.slots.getOrNull(currentShotIndex) ?: frame.slots.firstOrNull()
        if (activeSlot != null && activeSlot.width > 0 && activeSlot.height > 0) {
            activeSlot.width.toFloat() / activeSlot.height.toFloat()
        } else {
            1.3333f // Fallback to 4:3 landscape
        }
    }

    // Initialize Indonesian Voice Assistant Manager
    val voiceManager = remember { VoiceManager(context) }
    DisposableEffect(Unit) {
        onDispose {
            voiceManager.shutdown()
        }
    }
    
    // Timer States
    var countdownValue by remember { mutableIntStateOf(configManager.countdownSeconds) }
    var isTimerActive by remember { mutableStateOf(sessionId.isNotEmpty()) }
    var isWaitingForSmile by remember { mutableStateOf(sessionId.isEmpty()) }

    LaunchedEffect(Unit) {
        if (sessionId.isEmpty()) {
            delay(1500)
            voiceManager.speak("Silakan tersenyum lebar untuk memulai pemotretan otomatis secara hands-free!")
        }
    }
    
    // Flash & Capture States
    var showFlashOverlay by remember { mutableStateOf(false) }
    val mediaSound = remember {
        try {
            MediaActionSound().apply { load(MediaActionSound.SHUTTER_CLICK); load(MediaActionSound.FOCUS_COMPLETE) }
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }

    // CameraX elements
    val cameraProviderFuture = remember { ProcessCameraProvider.getInstance(context) }
    val previewView = remember { PreviewView(context) }
    val imageCapture = remember {
        ImageCapture.Builder()
            .setCaptureMode(ImageCapture.CAPTURE_MODE_MAXIMIZE_QUALITY)
            .setTargetAspectRatio(androidx.camera.core.AspectRatio.RATIO_4_3)
            .build()
    }
    val cameraExecutor: ExecutorService = remember { Executors.newSingleThreadExecutor() }

    LaunchedEffect(cameraProviderFuture) {
        try {
            val cameraProvider = cameraProviderFuture.get()
            val preview = Preview.Builder()
                .setTargetAspectRatio(androidx.camera.core.AspectRatio.RATIO_4_3)
                .build().also {
                    it.setSurfaceProvider(previewView.surfaceProvider)
                }

            val imageAnalysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()

            val options = FaceDetectorOptions.Builder()
                .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                .build()
            val detector = try {
                FaceDetection.getClient(options)
            } catch (e: Exception) {
                e.printStackTrace()
                null
            }

            if (detector == null) {
                // If FaceDetection fails to load (e.g. library architecture mismatch), bypass smile detection
                scope.launch(Dispatchers.Main) {
                    isWaitingForSmile = false
                    isTimerActive = true
                }
            } else {
                imageAnalysis.setAnalyzer(cameraExecutor) { imageProxy ->
                    @OptIn(ExperimentalGetImage::class)
                    val mediaImage = imageProxy.image
                    if (mediaImage != null) {
                        val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)
                        detector.process(image)
                            .addOnSuccessListener { faces ->
                                if (isWaitingForSmile) {
                                    for (face in faces) {
                                        val smileProb = face.smilingProbability ?: 0f
                                        if (smileProb > 0.75f) {
                                            isWaitingForSmile = false
                                            scope.launch(Dispatchers.Main) {
                                                voiceManager.speak("Senyuman terdeteksi! Bersiap...")
                                                delay(800)
                                                isTimerActive = true
                                            }
                                            break
                                        }
                                    }
                                }
                                imageProxy.close()
                            }
                            .addOnFailureListener {
                                imageProxy.close()
                            }
                    } else {
                        imageProxy.close()
                    }
                }
            }

            val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA // Front camera for photobooth

            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                cameraSelector,
                preview,
                imageCapture,
                if (detector != null) imageAnalysis else null
            )
        } catch (e: Exception) {
            e.printStackTrace()
            // If binding fails, try to fallback without image analysis
            try {
                val cameraProvider = cameraProviderFuture.get()
                val preview = Preview.Builder()
                    .setTargetAspectRatio(androidx.camera.core.AspectRatio.RATIO_4_3)
                    .build().also {
                        it.setSurfaceProvider(previewView.surfaceProvider)
                    }
                val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA
                cameraProvider.unbindAll()
                cameraProvider.bindToLifecycle(
                    lifecycleOwner,
                    cameraSelector,
                    preview,
                    imageCapture
                )
            } catch (ex: Exception) {
                ex.printStackTrace()
            }
        }
    }

    // Capture Loop Coroutine
    LaunchedEffect(isTimerActive, currentShotIndex) {
        if (isTimerActive && currentShotIndex < totalShots) {
            countdownValue = configManager.countdownSeconds
            
            // Speak friendly studio preparation voice cue
            if (characterId.isNotEmpty()) {
                voiceManager.speak("Bersiap untuk foto AI wajah Anda!")
            } else {
                val poseIndexStr = when (currentShotIndex) {
                    0 -> "pertama"
                    1 -> "kedua"
                    2 -> "ketiga"
                    else -> "terakhir"
                }
                voiceManager.speak("Bersiap untuk pose ke $poseIndexStr!")
            }
            
            while (countdownValue > 0) {
                if (countdownValue > 0) {
                    try {
                        mediaSound?.play(MediaActionSound.FOCUS_COMPLETE)
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }
                
                // Friendly audio countdown
                if (countdownValue in 1..3) {
                    if (countdownValue == 1) {
                        voiceManager.speak("Satu... Senyum!")
                    } else {
                        voiceManager.speak(countdownValue.toString())
                    }
                }
                
                delay(1000)
                countdownValue--
            }
            
            // Trigger Flash & Capture
            showFlashOverlay = true
            try {
                mediaSound?.play(MediaActionSound.SHUTTER_CLICK)
            } catch (e: Exception) {
                e.printStackTrace()
            }
            
            // Adjust target rotation to match the current display rotation
            try {
                val displayRotation = previewView.display?.rotation ?: android.view.Surface.ROTATION_0
                imageCapture.targetRotation = displayRotation
            } catch (e: Exception) {
                e.printStackTrace()
            }

            val tempFile = File(context.cacheDir, "temp_shot_${currentShotIndex}.jpg")
            val outputOptions = ImageCapture.OutputFileOptions.Builder(tempFile).build()
            
            imageCapture.takePicture(
                outputOptions,
                cameraExecutor,
                object : ImageCapture.OnImageSavedCallback {
                    override fun onImageSaved(outputFileResults: ImageCapture.OutputFileResults) {
                        try {
                            val filePath = tempFile.absolutePath
                            
                            // Read EXIF orientation to correct rotation and mirroring
                            var rotationDegrees = 0
                            var flipHorizontal = false
                            try {
                                val exif = android.media.ExifInterface(filePath)
                                val orientation = exif.getAttributeInt(
                                    android.media.ExifInterface.TAG_ORIENTATION,
                                    android.media.ExifInterface.ORIENTATION_NORMAL
                                )
                                when (orientation) {
                                    android.media.ExifInterface.ORIENTATION_ROTATE_90 -> {
                                        rotationDegrees = 90
                                    }
                                    android.media.ExifInterface.ORIENTATION_ROTATE_180 -> {
                                        rotationDegrees = 180
                                    }
                                    android.media.ExifInterface.ORIENTATION_ROTATE_270 -> {
                                        rotationDegrees = 270
                                    }
                                    android.media.ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> {
                                        flipHorizontal = true
                                    }
                                    android.media.ExifInterface.ORIENTATION_TRANSPOSE -> {
                                        rotationDegrees = 270
                                        flipHorizontal = true
                                    }
                                    android.media.ExifInterface.ORIENTATION_TRANSVERSE -> {
                                        rotationDegrees = 90
                                        flipHorizontal = true
                                    }
                                    android.media.ExifInterface.ORIENTATION_FLIP_VERTICAL -> {
                                        rotationDegrees = 180
                                        flipHorizontal = true
                                    }
                                }
                            } catch (e: Exception) {
                                e.printStackTrace()
                            }

                            val opt = BitmapFactory.Options().apply { inMutable = true }
                            var bitmap = BitmapFactory.decodeFile(filePath, opt)
                            if (bitmap != null) {
                                // Physically rotate and/or flip the bitmap based on EXIF orientation
                                if (rotationDegrees != 0 || flipHorizontal) {
                                    val matrix = android.graphics.Matrix()
                                    if (rotationDegrees != 0) {
                                        matrix.postRotate(rotationDegrees.toFloat())
                                    }
                                    if (flipHorizontal) {
                                        matrix.postScale(-1f, 1f)
                                    }
                                    val rotated = Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
                                    if (rotated != bitmap) {
                                        bitmap.recycle()
                                        bitmap = rotated
                                    }
                                }

                                // Crop the bitmap to match the slot aspect ratio
                                val bmpW = bitmap.width
                                val bmpH = bitmap.height
                                val bmpRatio = bmpW.toFloat() / bmpH.toFloat()
                                val targetRatio = slotAspectRatio
                                
                                val cropW: Int
                                val cropH: Int
                                if (bmpRatio > targetRatio) {
                                    cropH = bmpH
                                    cropW = (bmpH * targetRatio).toInt()
                                } else {
                                    cropW = bmpW
                                    cropH = (bmpW / targetRatio).toInt()
                                }
                                
                                val cropX = kotlin.math.max(0, (bmpW - cropW) / 2)
                                val cropY = kotlin.math.max(0, (bmpH - cropH) / 2)
                                
                                val cropped = Bitmap.createBitmap(bitmap, cropX, cropY, cropW, cropH)
                                if (cropped != bitmap) {
                                    bitmap.recycle()
                                    bitmap = cropped
                                }

                                 val inputImg = InputImage.fromBitmap(bitmap, 0)
                                val faceDetectorOptions = FaceDetectorOptions.Builder()
                                    .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                                    .build()
                                val detector = try {
                                    FaceDetection.getClient(faceDetectorOptions)
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                    null
                                }
                                
                                if (detector != null) {
                                    try {
                                        val faces = Tasks.await(detector.process(inputImg))
                                        val rects = faces.map { it.boundingBox }
                                        val processedBitmap = BeautyFilter.applyBeautyFilter(bitmap, rects)
                                        
                                        FileOutputStream(tempFile).use { out ->
                                            processedBitmap.compress(Bitmap.CompressFormat.JPEG, 95, out)
                                        }
                                        if (processedBitmap != bitmap) {
                                            processedBitmap.recycle()
                                        }
                                    } catch (e: Exception) {
                                        e.printStackTrace()
                                        // Fallback to saving original bitmap
                                        FileOutputStream(tempFile).use { out ->
                                            bitmap.compress(Bitmap.CompressFormat.JPEG, 95, out)
                                        }
                                    } finally {
                                        detector.close()
                                    }
                                } else {
                                    // Fallback to saving original bitmap
                                    FileOutputStream(tempFile).use { out ->
                                        bitmap.compress(Bitmap.CompressFormat.JPEG, 95, out)
                                    }
                                }
                                bitmap.recycle()
                            }
                        } catch (e: Exception) {
                            e.printStackTrace()
                        }

                        scope.launch(Dispatchers.Main) {
                            showFlashOverlay = false
                            capturedPaths.add(tempFile.absolutePath)
                            if (currentShotIndex + 1 < totalShots) {
                                currentShotIndex++
                            } else {
                                isTimerActive = false
                                onCaptureComplete(capturedPaths.toList())
                            }
                        }
                    }

                    override fun onError(exception: ImageCaptureException) {
                        scope.launch(Dispatchers.Main) {
                            showFlashOverlay = false
                            // Try to skip or error
                            exception.printStackTrace()
                        }
                    }
                }
            )
        }
    }

    // Release executor
    DisposableEffect(Unit) {
        onDispose {
            cameraExecutor.shutdown()
            try {
                mediaSound?.release()
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition()

    // Glowing border color animation
    val cameraBorderGlow by infiniteTransition.animateColor(
        initialValue = themeColors.primary.copy(alpha = 0.5f),
        targetValue = themeColors.primary,
        animationSpec = infiniteRepeatable(
            animation = tween(1500, easing = EaseInOut),
            repeatMode = RepeatMode.Reverse
        )
    )

    val configuration = LocalConfiguration.current
    val isLandscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(themeColors.background)
    ) {
        if (isLandscape) {
            // --- LANDSCAPE MODE: Bounded Layout with Side filmstrip ---
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .statusBarsPadding()
                    .navigationBarsPadding()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Left Part: Camera Preview Container
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxHeight(),
                    contentAlignment = Alignment.Center
                ) {
                    Card(
                        shape = RoundedCornerShape(24.dp),
                        border = BorderStroke(3.dp, cameraBorderGlow),
                        colors = CardDefaults.cardColors(containerColor = Color.Black),
                        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
                        modifier = Modifier
                            .fillMaxHeight()
                            .aspectRatio(slotAspectRatio, matchHeightConstraintsFirst = true)
                    ) {
                        Box(modifier = Modifier.fillMaxSize()) {
                            // Camera preview bounded inside the card
                            AndroidView(
                                factory = { 
                                    previewView.apply {
                                        scaleType = PreviewView.ScaleType.FILL_CENTER
                                    }
                                },
                                modifier = Modifier.fillMaxSize()
                            )

                            // Flash Overlay (Inside preview box)
                            if (showFlashOverlay) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .background(Color.White)
                                )
                            }

                            // Floating current slot indicator (e.g. "FOTO 1 / 4")
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(12.dp)
                                    .background(Color.Black.copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                                    .padding(horizontal = 12.dp, vertical = 6.dp)
                            ) {
                                Text(
                                    text = "FOTO ${currentShotIndex + 1} / $totalShots",
                                    color = Color.White,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }

                            // Center Big Countdown Timer (Inside preview box)
                            androidx.compose.animation.AnimatedVisibility(
                                visible = countdownValue > 0 && isTimerActive,
                                enter = fadeIn() + scaleIn(),
                                exit = fadeOut() + scaleOut(),
                                modifier = Modifier.align(Alignment.Center)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(96.dp)
                                        .background(themeColors.primary.copy(alpha = 0.85f), CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = countdownValue.toString(),
                                        color = themeColors.buttonContent,
                                        fontSize = 48.sp,
                                        fontWeight = FontWeight.Black,
                                        textAlign = TextAlign.Center
                                    )
                                }
                            }
                        }
                    }

                    // Floating Close Button (Top-Left of the Camera Preview container)
                    IconButton(
                        onClick = onBackClick,
                        modifier = Modifier
                            .align(Alignment.TopStart)
                            .background(Color.Black.copy(alpha = 0.5f), CircleShape)
                    ) {
                        Icon(imageVector = Icons.Default.Close, contentDescription = "Abort", tint = Color.White)
                    }
                }

                // Right Part: Filmstrip Progress (vertical list of taken photos)
                VerticalFilmstripProgress(
                    totalShots = totalShots,
                    currentShotIndex = currentShotIndex,
                    capturedPaths = capturedPaths,
                    countdownValue = countdownValue,
                    isTimerActive = isTimerActive,
                    slotAspectRatio = slotAspectRatio,
                    modifier = Modifier.fillMaxHeight()
                )
            }
        } else {
            // --- PORTRAIT MODE: Custom Bounded Layout with Header & Filmstrip ---
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .statusBarsPadding()
                    .navigationBarsPadding(),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // 1. Top Part (Event Header & Twinkling Stars Decoration)
                EventHeader(
                    eventInfo = eventInfo,
                    onBackClick = onBackClick
                )

                // 2. Center Part (Camera Preview in aspect ratio box)
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp, vertical = 8.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Card(
                        shape = RoundedCornerShape(24.dp),
                        border = BorderStroke(3.dp, cameraBorderGlow),
                        colors = CardDefaults.cardColors(containerColor = Color.Black),
                        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .aspectRatio(slotAspectRatio)
                    ) {
                        Box(modifier = Modifier.fillMaxSize()) {
                            // Camera preview bounded inside the card
                            AndroidView(
                                factory = { 
                                    previewView.apply {
                                        scaleType = PreviewView.ScaleType.FILL_CENTER
                                    }
                                },
                                modifier = Modifier.fillMaxSize()
                            )

                            // Flash Overlay (Inside preview box)
                            if (showFlashOverlay) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .background(Color.White)
                                )
                            }

                            // Floating current slot indicator (e.g. "FOTO 1 / 4")
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(12.dp)
                                    .background(Color.Black.copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                                    .padding(horizontal = 12.dp, vertical = 6.dp)
                            ) {
                                Text(
                                    text = "POSING: FOTO ${currentShotIndex + 1} / $totalShots",
                                    color = Color.White,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }

                            // Center Big Countdown Timer (Inside preview box)
                            androidx.compose.animation.AnimatedVisibility(
                                visible = countdownValue > 0 && isTimerActive,
                                enter = fadeIn() + scaleIn(),
                                exit = fadeOut() + scaleOut(),
                                modifier = Modifier.align(Alignment.Center)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(96.dp)
                                        .background(themeColors.primary.copy(alpha = 0.85f), CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = countdownValue.toString(),
                                        color = themeColors.buttonContent,
                                        fontSize = 48.sp,
                                        fontWeight = FontWeight.Black,
                                        textAlign = TextAlign.Center
                                    )
                                }
                            }
                        }
                    }
                }

                // 3. Bottom Part (Filmstrip & Control Text)
                FilmstripProgress(
                    totalShots = totalShots,
                    currentShotIndex = currentShotIndex,
                    capturedPaths = capturedPaths,
                    countdownValue = countdownValue,
                    isTimerActive = isTimerActive,
                    slotAspectRatio = slotAspectRatio,
                    modifier = Modifier.padding(bottom = 16.dp)
                )
            }
        }

        // Waiting for Smile overlay (shown on top of everything)
        if (isWaitingForSmile) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.6f)),
                contentAlignment = Alignment.Center
            ) {
                val isCutePastel = AppTheme.type == AppThemeType.CUTE_PASTEL
                Card(
                    shape = RoundedCornerShape(if (isCutePastel) 16.dp else 24.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.95f)),
                    border = BorderStroke(
                        width = if (isCutePastel) 3.dp else 2.dp,
                        color = MaterialTheme.colorScheme.outline
                    ),
                    modifier = Modifier.padding(32.dp).width(360.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "📸 OTOMATIS AKTIF",
                            color = MaterialTheme.colorScheme.primary,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Black
                        )
                        Text(
                            text = "SENYUM LEBAR UNTUK MEMULAI FOTO!",
                            color = MaterialTheme.colorScheme.onSurface,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            textAlign = TextAlign.Center
                        )
                        Text(
                            text = "Kiosk akan mendeteksi senyuman Anda secara otomatis untuk memulai jepretan secara hands-free.",
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                            fontSize = 11.sp,
                            textAlign = TextAlign.Center,
                            lineHeight = 16.sp
                        )
                        
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            CircularProgressIndicator(
                                color = MaterialTheme.colorScheme.primary,
                                modifier = Modifier.size(16.dp),
                                strokeWidth = 2.dp
                            )
                            Text(
                                text = "Menunggu senyuman...",
                                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                                fontSize = 12.sp
                            )
                        }

                        Button(
                            onClick = {
                                isWaitingForSmile = false
                                isTimerActive = true
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text(
                                text = "MULAI FOTO",
                                color = MaterialTheme.colorScheme.onPrimary,
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun EventHeader(
    eventInfo: EventInfo?,
    onBackClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition()
    
    // Animate a set of twinkling star offsets and opacities
    val twinklingAlpha1 by infiniteTransition.animateFloat(
        initialValue = 0.2f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        )
    )
    val twinklingAlpha2 by infiniteTransition.animateFloat(
        initialValue = 0.8f,
        targetValue = 0.1f,
        animationSpec = infiniteRepeatable(
            animation = tween(1600, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        )
    )
    val twinklingScale by infiniteTransition.animateFloat(
        initialValue = 0.6f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(1400, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        )
    )

    Box(
        modifier = modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        themeColors.cardBackground,
                        themeColors.cardBackground.copy(alpha = 0.9f),
                        Color.Transparent
                    )
                )
            )
            .padding(horizontal = 24.dp, vertical = 16.dp)
    ) {
        // Twinkling stars or decorative particles in background
        androidx.compose.foundation.Canvas(modifier = Modifier.matchParentSize()) {
            val width = size.width
            val height = size.height
            
            // Draw a few twinkling cross stars
            val stars = listOf(
                Pair(0.15f, 0.3f) to twinklingAlpha1,
                Pair(0.85f, 0.25f) to twinklingAlpha2,
                Pair(0.75f, 0.7f) to twinklingAlpha1,
                Pair(0.2f, 0.75f) to twinklingAlpha2,
                Pair(0.5f, 0.15f) to twinklingAlpha1
            )
            
            stars.forEach { (pos, alpha) ->
                val x = pos.first * width
                val y = pos.second * height
                // Draw a small cross star
                val starColor = themeColors.accentColor.copy(alpha = alpha)
                drawLine(
                    color = starColor,
                    start = Offset(x - 6.dp.toPx() * twinklingScale, y),
                    end = Offset(x + 6.dp.toPx() * twinklingScale, y),
                    strokeWidth = 2.dp.toPx()
                )
                drawLine(
                    color = starColor,
                    start = Offset(x, y - 6.dp.toPx() * twinklingScale),
                    end = Offset(x, y + 6.dp.toPx() * twinklingScale),
                    strokeWidth = 2.dp.toPx()
                )
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Close / Abort button
            IconButton(
                onClick = onBackClick,
                modifier = Modifier
                    .background(Color.Black.copy(alpha = 0.4f), CircleShape)
                    .size(40.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Close,
                    contentDescription = "Abort",
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
            }

            Spacer(modifier = Modifier.width(16.dp))

            // Branding text / Title
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = eventInfo?.name?.uppercase() ?: "SMILE BOOTH",
                    color = themeColors.onBackground,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Black,
                    letterSpacing = 2.sp
                )
                Text(
                    text = eventInfo?.subtitle ?: eventInfo?.hashtag ?: "#BeautifulMoments",
                    color = themeColors.onBackground.copy(alpha = 0.7f),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium
                )
            }

            // Decorative logo or icon
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .background(themeColors.primary.copy(alpha = 0.2f), RoundedCornerShape(12.dp))
                    .padding(8.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "📸",
                    fontSize = 24.sp
                )
            }
        }
    }
}

@Composable
fun FilmstripProgress(
    totalShots: Int,
    currentShotIndex: Int,
    capturedPaths: List<String>,
    countdownValue: Int,
    isTimerActive: Boolean,
    slotAspectRatio: Float,
    modifier: Modifier = Modifier
) {
    val themeColors = AppTheme.colors
    
    Card(
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = themeColors.cardBackground.copy(alpha = 0.9f)),
        border = BorderStroke(1.dp, themeColors.border.copy(alpha = 0.3f)),
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text(
                text = "GALERI POSE",
                color = themeColors.onCardBackground.copy(alpha = 0.6f),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.sp
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly,
                verticalAlignment = Alignment.CenterVertically
            ) {
                for (index in 0 until totalShots) {
                    val isCaptured = index < capturedPaths.size
                    val isActive = index == currentShotIndex

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .padding(horizontal = 4.dp)
                    ) {
                        FilmstripSlotCard(
                            index = index,
                            isCaptured = isCaptured,
                            isActive = isActive,
                            capturedPath = capturedPaths.getOrNull(index),
                            countdownValue = countdownValue,
                            isTimerActive = isTimerActive,
                            slotAspectRatio = slotAspectRatio
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun FilmstripSlotCard(
    index: Int,
    isCaptured: Boolean,
    isActive: Boolean,
    capturedPath: String?,
    countdownValue: Int,
    isTimerActive: Boolean,
    slotAspectRatio: Float
) {
    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition()

    // Glowing border for active slot card
    val activeBorderGlow by infiniteTransition.animateColor(
        initialValue = themeColors.primary.copy(alpha = 0.4f),
        targetValue = themeColors.primary,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = EaseInOut),
            repeatMode = RepeatMode.Reverse
        )
    )

    val activeScale by infiniteTransition.animateFloat(
        initialValue = 0.97f,
        targetValue = 1.03f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = EaseInOut),
            repeatMode = RepeatMode.Reverse
        )
    )

    val cardBorder = when {
        isCaptured -> BorderStroke(1.5.dp, themeColors.primary)
        isActive -> BorderStroke(2.dp, activeBorderGlow)
        else -> BorderStroke(1.dp, themeColors.border.copy(alpha = 0.5f))
    }

    val cardScale = if (isActive && isTimerActive) activeScale else 1f

    Card(
        shape = RoundedCornerShape(12.dp),
        border = cardBorder,
        colors = CardDefaults.cardColors(
            containerColor = when {
                isCaptured -> Color.Black
                isActive -> themeColors.primary.copy(alpha = 0.15f)
                else -> Color.Black.copy(alpha = 0.3f)
            }
        ),
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(slotAspectRatio)
            .graphicsLayer {
                scaleX = cardScale
                scaleY = cardScale
            }
    ) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            if (isCaptured && capturedPath != null) {
                androidx.compose.animation.AnimatedVisibility(
                    visible = true,
                    enter = fadeIn(animationSpec = tween(500))
                ) {
                    AsyncImage(
                        model = File(capturedPath),
                        contentDescription = "Pose ${index + 1}",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize()
                    )
                }
            } else if (isActive) {
                if (isTimerActive && countdownValue > 0) {
                    // Show animated countdown text
                    Text(
                        text = countdownValue.toString(),
                        color = themeColors.primary,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Black
                    )
                } else {
                    // Pulse camera icon waiting to start
                    val pulseAlpha by infiniteTransition.animateFloat(
                        initialValue = 0.4f,
                        targetValue = 1f,
                        animationSpec = infiniteRepeatable(
                            animation = tween(800, easing = EaseInOut),
                            repeatMode = RepeatMode.Reverse
                        )
                    )
                    Text(
                        text = "📸",
                        fontSize = 18.sp,
                        modifier = Modifier.graphicsLayer { alpha = pulseAlpha }
                    )
                }
            } else {
                // Future slot: show index
                Text(
                    text = (index + 1).toString(),
                    color = themeColors.onCardBackground.copy(alpha = 0.3f),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
fun VerticalFilmstripProgress(
    totalShots: Int,
    currentShotIndex: Int,
    capturedPaths: List<String>,
    countdownValue: Int,
    isTimerActive: Boolean,
    slotAspectRatio: Float,
    modifier: Modifier = Modifier
) {
    val themeColors = AppTheme.colors
    val sidebarWidth = remember(slotAspectRatio) {
        if (slotAspectRatio > 1f) {
            160.dp
        } else {
            120.dp
        }
    }
    
    Card(
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = themeColors.cardBackground.copy(alpha = 0.9f)),
        border = BorderStroke(1.dp, themeColors.border.copy(alpha = 0.3f)),
        modifier = modifier
            .width(sidebarWidth)
            .padding(vertical = 8.dp)
    ) {
        Column(
            modifier = Modifier
                .padding(12.dp)
                .fillMaxHeight(),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = "GALERI POSE",
                color = themeColors.onCardBackground.copy(alpha = 0.6f),
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.sp,
                textAlign = TextAlign.Center
            )

            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                verticalArrangement = Arrangement.SpaceEvenly,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                for (index in 0 until totalShots) {
                    val isCaptured = index < capturedPaths.size
                    val isActive = index == currentShotIndex

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .padding(vertical = 4.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        FilmstripSlotCardVertical(
                            index = index,
                            isCaptured = isCaptured,
                            isActive = isActive,
                            capturedPath = capturedPaths.getOrNull(index),
                            countdownValue = countdownValue,
                            isTimerActive = isTimerActive,
                            slotAspectRatio = slotAspectRatio
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun FilmstripSlotCardVertical(
    index: Int,
    isCaptured: Boolean,
    isActive: Boolean,
    capturedPath: String?,
    countdownValue: Int,
    isTimerActive: Boolean,
    slotAspectRatio: Float
) {
    val themeColors = AppTheme.colors
    val infiniteTransition = rememberInfiniteTransition()

    val activeBorderGlow by infiniteTransition.animateColor(
        initialValue = themeColors.primary.copy(alpha = 0.4f),
        targetValue = themeColors.primary,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = EaseInOut),
            repeatMode = RepeatMode.Reverse
        )
    )

    val activeScale by infiniteTransition.animateFloat(
        initialValue = 0.97f,
        targetValue = 1.03f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = EaseInOut),
            repeatMode = RepeatMode.Reverse
        )
    )

    val cardBorder = when {
        isCaptured -> BorderStroke(1.5.dp, themeColors.primary)
        isActive -> BorderStroke(2.dp, activeBorderGlow)
        else -> BorderStroke(1.dp, themeColors.border.copy(alpha = 0.5f))
    }

    val cardScale = if (isActive && isTimerActive) activeScale else 1f

    Card(
        shape = RoundedCornerShape(12.dp),
        border = cardBorder,
        colors = CardDefaults.cardColors(
            containerColor = when {
                isCaptured -> Color.Black
                isActive -> themeColors.primary.copy(alpha = 0.15f)
                else -> Color.Black.copy(alpha = 0.3f)
            }
        ),
        modifier = Modifier
            .fillMaxHeight()
            .aspectRatio(slotAspectRatio, matchHeightConstraintsFirst = true)
            .graphicsLayer {
                scaleX = cardScale
                scaleY = cardScale
            }
    ) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            if (isCaptured && capturedPath != null) {
                androidx.compose.animation.AnimatedVisibility(
                    visible = true,
                    enter = fadeIn(animationSpec = tween(500))
                ) {
                    AsyncImage(
                        model = File(capturedPath),
                        contentDescription = "Pose ${index + 1}",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize()
                    )
                }
            } else if (isActive) {
                if (isTimerActive && countdownValue > 0) {
                    Text(
                        text = countdownValue.toString(),
                        color = themeColors.primary,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Black
                    )
                } else {
                    val pulseAlpha by infiniteTransition.animateFloat(
                        initialValue = 0.4f,
                        targetValue = 1f,
                        animationSpec = infiniteRepeatable(
                            animation = tween(800, easing = EaseInOut),
                            repeatMode = RepeatMode.Reverse
                        )
                    )
                    Text(
                        text = "📸",
                        fontSize = 16.sp,
                        modifier = Modifier.graphicsLayer { alpha = pulseAlpha }
                    )
                }
            } else {
                Text(
                    text = (index + 1).toString(),
                    color = themeColors.onCardBackground.copy(alpha = 0.3f),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}
