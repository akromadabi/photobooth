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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.ui.frame.getFramesForLayout
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.File
import java.io.FileOutputStream
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Rect
import com.google.android.gms.tasks.Tasks

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CameraCaptureScreen(
    frameId: String,
    onBackClick: () -> Unit,
    onCaptureComplete: (List<String>) -> Unit,
    modifier: Modifier = Modifier,
    eventId: String = "general",
    sessionId: String = "",
    packageId: String = ""
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val configManager = remember { ConfigManager(context) }
    
    // Resolve frame configuration
    val frame = remember(frameId, eventId) {
        val allFrames = getFramesForLayout(context, "strip", configManager, eventId) + 
                         getFramesForLayout(context, "grid", configManager, eventId)
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
                .background(Color(0xFF0F0F12)),
            contentAlignment = Alignment.Center
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp),
                modifier = Modifier.padding(24.dp)
            ) {
                Text("Kamera Diperlukan", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White)
                Text(
                    text = "Aplikasi photobooth membutuhkan izin kamera untuk mengambil foto Anda.",
                    color = Color.Gray,
                    textAlign = TextAlign.Center
                )
                Button(
                    onClick = { launcher.launch(Manifest.permission.CAMERA) },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946))
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
            sessionId = sessionId,
            packageId = packageId
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
    sessionId: String = "",
    packageId: String = ""
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    
    val totalShots = frame.slots.size
    var currentShotIndex by remember { mutableIntStateOf(0) }
    val capturedPaths = remember { mutableStateListOf<String>() }

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
    val mediaSound = remember { MediaActionSound().apply { load(MediaActionSound.SHUTTER_CLICK); load(MediaActionSound.FOCUS_COMPLETE) } }

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
        val detector = FaceDetection.getClient(options)

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

        val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA // Front camera for photobooth

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                cameraSelector,
                preview,
                imageCapture,
                imageAnalysis
            )
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    // Capture Loop Coroutine
    LaunchedEffect(isTimerActive, currentShotIndex) {
        if (isTimerActive && currentShotIndex < totalShots) {
            countdownValue = configManager.countdownSeconds
            
            // Speak friendly studio preparation voice cue
            val poseIndexStr = when (currentShotIndex) {
                0 -> "pertama"
                1 -> "kedua"
                2 -> "ketiga"
                else -> "terakhir"
            }
            voiceManager.speak("Bersiap untuk pose ke $poseIndexStr!")
            
            while (countdownValue > 0) {
                if (countdownValue > 0) {
                    mediaSound.play(MediaActionSound.FOCUS_COMPLETE)
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
            mediaSound.play(MediaActionSound.SHUTTER_CLICK)
            
            val tempFile = File(context.cacheDir, "temp_shot_${currentShotIndex}.jpg")
            val outputOptions = ImageCapture.OutputFileOptions.Builder(tempFile).build()
            
            imageCapture.takePicture(
                outputOptions,
                cameraExecutor,
                object : ImageCapture.OnImageSavedCallback {
                    override fun onImageSaved(outputFileResults: ImageCapture.OutputFileResults) {
                        try {
                            val filePath = tempFile.absolutePath
                            val opt = BitmapFactory.Options().apply { inMutable = true }
                            val bitmap = BitmapFactory.decodeFile(filePath, opt)
                            if (bitmap != null) {
                                val inputImg = InputImage.fromBitmap(bitmap, 0)
                                val faceDetectorOptions = FaceDetectorOptions.Builder()
                                    .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                                    .build()
                                val detector = FaceDetection.getClient(faceDetectorOptions)
                                
                                try {
                                    val faces = Tasks.await(detector.process(inputImg))
                                    val rects = faces.map { it.boundingBox }
                                    val processedBitmap = BeautyFilter.applyBeautyFilter(bitmap, rects)
                                    
                                    FileOutputStream(tempFile).use { out ->
                                        processedBitmap.compress(Bitmap.CompressFormat.JPEG, 95, out)
                                    }
                                    processedBitmap.recycle()
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                } finally {
                                    detector.close()
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
            mediaSound.release()
        }
    }

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Color(0xFF0F0F12))
    ) {
        // Fullscreen Camera Preview
        AndroidView(
            factory = { previewView },
            modifier = Modifier.fillMaxSize()
        )

        // Guide Frame transparent overlay (gives the feeling of a photobooth border)
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color.Black.copy(alpha = 0.2f))
        )

        // Close Button (to abort)
        IconButton(
            onClick = onBackClick,
            modifier = Modifier
                .statusBarsPadding()
                .padding(16.dp)
                .align(Alignment.TopStart)
                .background(Color.Black.copy(alpha = 0.5f), CircleShape)
        ) {
            Icon(imageVector = Icons.Default.Close, contentDescription = "Abort", tint = Color.White)
        }

        // Floating Badge showing current slot (e.g. "FOTO 1 / 4")
        Card(
            modifier = Modifier
                .statusBarsPadding()
                .padding(16.dp)
                .align(Alignment.TopEnd),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Black.copy(alpha = 0.7f))
        ) {
            Text(
                text = "FOTO ${currentShotIndex + 1} / $totalShots",
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
            )
        }

        // Big Countdown Timer Overlay
        AnimatedVisibility(
            visible = countdownValue > 0,
            enter = fadeIn() + scaleIn(),
            exit = fadeOut() + scaleOut(),
            modifier = Modifier.align(Alignment.Center)
        ) {
            Box(
                modifier = Modifier
                    .size(160.dp)
                    .background(Color(0xFFE63946).copy(alpha = 0.85f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = countdownValue.toString(),
                    color = Color.White,
                    fontSize = 80.sp,
                    fontWeight = FontWeight.Black,
                    textAlign = TextAlign.Center
                )
            }
        }

        // Camera Shutter Flash effect (white flash overlay)
        if (showFlashOverlay) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.White)
            )
        }

        // Waiting for Smile overlay
        if (isWaitingForSmile) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.5f)),
                contentAlignment = Alignment.Center
            ) {
                Card(
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24).copy(alpha = 0.95f)),
                    border = BorderStroke(2.dp, Color(0xFFE63946)),
                    modifier = Modifier.padding(32.dp).width(360.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "📸 OTOMATIS AKTIF",
                            color = Color(0xFFE63946),
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Black
                        )
                        Text(
                            text = "SENYUM LEBAR UNTUK MEMULAI FOTO!",
                            color = Color.White,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            textAlign = TextAlign.Center
                        )
                        Text(
                            text = "Kiosk akan mendeteksi senyuman Anda secara otomatis untuk memulai jepretan secara hands-free.",
                            color = Color.Gray,
                            fontSize = 11.sp,
                            textAlign = TextAlign.Center,
                            lineHeight = 16.sp
                        )
                        
                        CircularProgressIndicator(
                            color = Color(0xFFE63946),
                            modifier = Modifier.size(24.dp),
                            strokeWidth = 2.dp
                        )
                    }
                }
            }
        }
    }
}
