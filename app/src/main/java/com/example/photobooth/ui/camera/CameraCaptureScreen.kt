package com.example.photobooth.ui.camera

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.media.MediaActionSound
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.*
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
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CameraCaptureScreen(
    frameId: String,
    onBackClick: () -> Unit,
    onCaptureComplete: (List<String>) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val configManager = remember { ConfigManager(context) }
    
    // Resolve frame configuration
    val frame = remember(frameId) {
        val allFrames = getFramesForLayout(context, "strip", configManager) + getFramesForLayout(context, "grid", configManager)
        allFrames.firstOrNull { it.id == frameId } ?: allFrames.first()
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
            modifier = modifier
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
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    
    val totalShots = frame.slots.size
    var currentShotIndex by remember { mutableIntStateOf(0) }
    val capturedPaths = remember { mutableStateListOf<String>() }
    
    // Timer States
    var countdownValue by remember { mutableIntStateOf(configManager.countdownSeconds) }
    var isTimerActive by remember { mutableStateOf(false) }
    
    // Flash & Capture States
    var showFlashOverlay by remember { mutableStateOf(false) }
    val mediaSound = remember { MediaActionSound().apply { load(MediaActionSound.SHUTTER_CLICK); load(MediaActionSound.FOCUS_COMPLETE) } }

    // CameraX elements
    val cameraProviderFuture = remember { ProcessCameraProvider.getInstance(context) }
    val previewView = remember { PreviewView(context) }
    val imageCapture = remember { ImageCapture.Builder().setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY).build() }
    val cameraExecutor: ExecutorService = remember { Executors.newSingleThreadExecutor() }

    LaunchedEffect(cameraProviderFuture) {
        val cameraProvider = cameraProviderFuture.get()
        val preview = Preview.Builder().build().also {
            it.setSurfaceProvider(previewView.surfaceProvider)
        }

        val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA // Front camera for photobooth

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                cameraSelector,
                preview,
                imageCapture
            )
            // Start the photobooth session timer once camera is loaded
            isTimerActive = true
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    // Capture Loop Coroutine
    LaunchedEffect(isTimerActive, currentShotIndex) {
        if (isTimerActive && currentShotIndex < totalShots) {
            countdownValue = configManager.countdownSeconds
            while (countdownValue > 0) {
                delay(1000)
                countdownValue--
                if (countdownValue > 0) {
                    mediaSound.play(MediaActionSound.FOCUS_COMPLETE)
                }
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
    }
}
