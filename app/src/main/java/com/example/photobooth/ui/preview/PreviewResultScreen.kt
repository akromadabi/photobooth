package com.example.photobooth.ui.preview

import android.content.Context
import android.graphics.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.runtime.snapshots.SnapshotStateList
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.ColorMatrix
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.geometry.Offset
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Undo
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Photo
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Face
import androidx.compose.material.icons.filled.Gesture
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.layout.LayoutCoordinates
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.Image
import androidx.compose.ui.graphics.asImageBitmap
import coil.compose.AsyncImage
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.data.FrameConfig
import com.example.photobooth.data.EventInfo
import com.google.gson.Gson
import com.example.photobooth.ui.frame.getFramesForLayout
import com.example.photobooth.theme.AppTheme
import com.example.photobooth.theme.AppThemeType
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream

enum class PhotoFilter {
    NORMAL, MONO, WARM, COOL, VINTAGE, VIVID, DREAMY, FILM
}

enum class PreviewTab {
    FRAME, FILTER, STICKER, CORETAN
}

data class PhotoState(
    val path: String,
    val normalizedX: Float = 0.5f,
    val normalizedY: Float = 0.5f,
    val scale: Float = 1.0f
)

data class Sticker(
    val id: String = java.util.UUID.randomUUID().toString(),
    val emoji: String,
    val x: Float,          // Relative X (0f to 1f)
    val y: Float,          // Relative Y (0f to 1f)
    val scale: Float = 1.0f,
    val rotation: Float = 0f
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PreviewResultScreen(
    photoPaths: List<String>,
    frameId: String,
    eventId: String = "general",
    onRetakeClick: () -> Unit,
    onConfirmClick: (String, Boolean, String) -> Unit, // finalPath, shouldPrint, finalFrameId
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    
    // Resolve configurations and compatible frames
    val allFrames = remember {
        getFramesForLayout(context, "strip", configManager, eventId) +
        getFramesForLayout(context, "grid", configManager, eventId) +
        getFramesForLayout(context, "postcard", configManager, eventId)
    }

    var activeFrame by remember(frameId) {
        val initialFrame = allFrames.firstOrNull { it.id == frameId } ?: allFrames.firstOrNull() ?: Frame(
            id = "classic_strip_black",
            name = "Classic Black",
            type = "strip",
            width = 600,
            height = 2000,
            backgroundColor = "#121212",
            imageUrl = "frames/classic_strip_black.png",
            slots = emptyList()
        )
        mutableStateOf(initialFrame)
    }

    val compatibleFrames = remember(activeFrame.type) {
        allFrames.filter { it.type.equals(activeFrame.type, ignoreCase = true) }
    }

    val photoStates = remember { mutableStateListOf<PhotoState>() }
    LaunchedEffect(photoPaths) {
        if (photoStates.isEmpty()) {
            photoStates.addAll(photoPaths.map { PhotoState(path = it) })
        }
    }

    var selectedFilter by remember { mutableStateOf(PhotoFilter.NORMAL) }
    var swapMode by remember { mutableStateOf(false) }
    var selectedSwapIndex by remember { mutableStateOf<Int?>(null) }

    // Doodle drawing states
    val doodleLines = remember { mutableStateListOf<DoodleLine>() }
    val penColors = listOf(
        Color.White,
        Color.Black,
        Color(0xFFE63946), // Radiant Red
        Color(0xFFF77F00), // Orange
        Color(0xFFFFB703), // Radiant Neon Yellow
        Color(0xFF80B918), // Lime Green
        Color(0xFF52B788), // Glowing Green
        Color(0xFF2A9D8F), // Teal
        Color(0xFF2196F3), // Electric Blue
        Color(0xFF03045E), // Dark Blue
        Color(0xFF7209B7), // Purple
        Color(0xFFB5179E), // Violet
        Color(0xFFF72585), // Hot Pink
        Color(0xFFFF85A1), // Pastel Pink
        Color(0xFF8B5D5D), // Brown
        Color(0xFF9E9E9E)  // Grey
    )
    var activePenColor by remember { mutableStateOf(penColors[0]) }
    var activeStrokeWidth by remember { mutableFloatStateOf(5f) }
    var isProcessingConfirm by remember { mutableStateOf(false) }

    // Sticker states
    val stickers = remember { mutableStateListOf<Sticker>() }
    var selectedStickerId by remember { mutableStateOf<String?>(null) }
    
    // Customization panel tab
    var activeTab by remember { mutableStateOf(PreviewTab.FRAME) }

    // Clear sticker selection when switching tabs
    LaunchedEffect(activeTab) {
        selectedStickerId = null
    }

    val frameAspectRatio = remember(activeFrame) {
        activeFrame.width.toFloat() / activeFrame.height.toFloat()
    }

    val configuration = LocalConfiguration.current
    val isPortrait = configuration.orientation == android.content.res.Configuration.ORIENTATION_PORTRAIT

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PRATINJAU & EDIT FOTO", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background,
                    titleContentColor = MaterialTheme.colorScheme.onBackground
                )
            )
        },
        containerColor = MaterialTheme.colorScheme.background,
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        // Content Layout based on Orientation
        if (isPortrait) {
            // Portrait Layout
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .padding(16.dp),
                verticalArrangement = Arrangement.SpaceBetween,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Preview Photo Container
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth()
                        .padding(bottom = 12.dp),
                    contentAlignment = Alignment.Center
                ) {
                    PreviewPhotoContainer(
                        photoStates = photoStates,
                        frame = activeFrame,
                        selectedFilter = selectedFilter,
                        activeTab = activeTab,
                        swapMode = swapMode,
                        selectedSwapIndex = selectedSwapIndex,
                        onPhotoClick = { clickedIndex ->
                            val currentSelected = selectedSwapIndex
                            if (currentSelected == null) {
                                selectedSwapIndex = clickedIndex
                            } else {
                                if (currentSelected != clickedIndex) {
                                    val temp = photoStates[currentSelected]
                                    photoStates[currentSelected] = photoStates[clickedIndex]
                                    photoStates[clickedIndex] = temp
                                }
                                selectedSwapIndex = null
                            }
                        },
                        onPhotoTransform = { index, newOffset, newScale ->
                            if (index < photoStates.size) {
                                photoStates[index] = photoStates[index].copy(
                                    normalizedX = newOffset.x,
                                    normalizedY = newOffset.y,
                                    scale = newScale
                                )
                            }
                        },
                        doodleLines = doodleLines,
                        activePenColor = activePenColor,
                        activeStrokeWidth = activeStrokeWidth,
                        stickers = stickers,
                        selectedStickerId = selectedStickerId,
                        onStickerSelected = { selectedStickerId = it }
                    )

                    // Floating Swap Mode Button
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(12.dp)
                    ) {
                        Button(
                            onClick = { 
                                swapMode = !swapMode 
                                selectedSwapIndex = null
                            },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (swapMode) Color(0xFFEF4444) else Color.Black.copy(alpha = 0.7f)
                            ),
                            shape = RoundedCornerShape(50.dp),
                            border = BorderStroke(
                                width = 1.5.dp,
                                color = if (swapMode) Color(0xFFFCA5A5) else AppTheme.colors.primary.copy(alpha = 0.8f)
                            ),
                            contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp),
                            modifier = Modifier
                                .height(38.dp)
                        ) {
                            Icon(
                                imageVector = if (swapMode) Icons.Default.Check else Icons.Default.Refresh,
                                contentDescription = "Tukar",
                                tint = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = if (swapMode) "Selesai" else "Tukar Foto",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Color.White
                            )
                        }
                    }
                }

                // Panel Tabs + Content
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(280.dp)
                        .padding(12.dp)
                ) {
                    HorizontalTabRow(
                        activeTab = activeTab,
                        onTabSelected = { activeTab = it }
                    )
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth()
                    ) {
                        when (activeTab) {
                            PreviewTab.FRAME -> FrameSelectorPanel(
                                compatibleFrames = compatibleFrames,
                                activeFrame = activeFrame,
                                onFrameSelected = { activeFrame = it }
                            )
                            PreviewTab.FILTER -> FilterSelectorPanel(
                                selectedFilter = selectedFilter,
                                onFilterSelected = { selectedFilter = it }
                            )
                            PreviewTab.STICKER -> StickerSelectorPanel(
                                onAddSticker = { emoji ->
                                    stickers.add(Sticker(emoji = emoji, x = 0.5f, y = 0.5f))
                                    activeTab = PreviewTab.STICKER // stay on sticker tab
                                }
                            )
                            PreviewTab.CORETAN -> DoodleSelectorPanel(
                                doodleLines = doodleLines,
                                activePenColor = activePenColor,
                                onColorSelected = { activePenColor = it },
                                activeStrokeWidth = activeStrokeWidth,
                                onStrokeWidthSelected = { activeStrokeWidth = it },
                                penColors = penColors
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Buttons Row
                ActionsRow(
                    isProcessingConfirm = isProcessingConfirm,
                    onRetakeClick = onRetakeClick,
                    onConfirmClick = {
                        isProcessingConfirm = true
                        scope.launch {
                            val finalPath = withContext(Dispatchers.Default) {
                                stitchPhotos(context, photoStates.toList(), activeFrame, selectedFilter, doodleLines.toList(), stickers.toList(), eventId, configManager)
                            }
                            isProcessingConfirm = false
                            val shouldPrint = configManager.printerType != "NONE"
                            onConfirmClick(finalPath, shouldPrint, activeFrame.id)
                        }
                    }
                )
            }
        } else {
            // Landscape Layout
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Left Side: Preview Photo Container
                Box(
                    modifier = Modifier
                        .weight(1.6f)
                        .fillMaxHeight()
                        .padding(start = 16.dp, top = 4.dp, bottom = 4.dp),
                    contentAlignment = Alignment.Center
                ) {
                    PreviewPhotoContainer(
                        photoStates = photoStates,
                        frame = activeFrame,
                        selectedFilter = selectedFilter,
                        activeTab = activeTab,
                        swapMode = swapMode,
                        selectedSwapIndex = selectedSwapIndex,
                        onPhotoClick = { clickedIndex ->
                            val currentSelected = selectedSwapIndex
                            if (currentSelected == null) {
                                selectedSwapIndex = clickedIndex
                            } else {
                                if (currentSelected != clickedIndex) {
                                    val temp = photoStates[currentSelected]
                                    photoStates[currentSelected] = photoStates[clickedIndex]
                                    photoStates[clickedIndex] = temp
                                }
                                selectedSwapIndex = null
                            }
                        },
                        onPhotoTransform = { index, newOffset, newScale ->
                            if (index < photoStates.size) {
                                photoStates[index] = photoStates[index].copy(
                                    normalizedX = newOffset.x,
                                    normalizedY = newOffset.y,
                                    scale = newScale
                                )
                            }
                        },
                        doodleLines = doodleLines,
                        activePenColor = activePenColor,
                        activeStrokeWidth = activeStrokeWidth,
                        stickers = stickers,
                        selectedStickerId = selectedStickerId,
                        onStickerSelected = { selectedStickerId = it }
                    )

                    // Floating Swap Mode Button
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(12.dp)
                    ) {
                        Button(
                            onClick = { 
                                swapMode = !swapMode 
                                selectedSwapIndex = null
                            },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (swapMode) Color(0xFFEF4444) else Color.Black.copy(alpha = 0.7f)
                            ),
                            shape = RoundedCornerShape(50.dp),
                            border = BorderStroke(
                                width = 1.5.dp,
                                color = if (swapMode) Color(0xFFFCA5A5) else AppTheme.colors.primary.copy(alpha = 0.8f)
                            ),
                            contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp),
                            modifier = Modifier
                                .height(38.dp)
                        ) {
                            Icon(
                                imageVector = if (swapMode) Icons.Default.Check else Icons.Default.Refresh,
                                contentDescription = "Tukar",
                                tint = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = if (swapMode) "Selesai" else "Tukar Foto",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Color.White
                            )
                        }
                    }
                }

                // Right Side: Control Panels & Action Buttons
                Column(
                    modifier = Modifier
                        .weight(1.4f)
                        .fillMaxHeight()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.SpaceBetween
                ) {
                    Column(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        // Horizontal Tabs at the top
                        HorizontalTabRow(
                            activeTab = activeTab,
                            onTabSelected = { activeTab = it }
                        )

                        // Active Panel Content below tabs
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .fillMaxWidth()
                        ) {
                            when (activeTab) {
                                PreviewTab.FRAME -> FrameSelectorPanel(
                                    compatibleFrames = compatibleFrames,
                                    activeFrame = activeFrame,
                                    onFrameSelected = { activeFrame = it }
                                )
                                PreviewTab.FILTER -> FilterSelectorPanel(
                                    selectedFilter = selectedFilter,
                                    onFilterSelected = { selectedFilter = it }
                                )
                                PreviewTab.STICKER -> StickerSelectorPanel(
                                    onAddSticker = { emoji ->
                                        stickers.add(Sticker(emoji = emoji, x = 0.5f, y = 0.5f))
                                    }
                                )
                                PreviewTab.CORETAN -> DoodleSelectorPanel(
                                    doodleLines = doodleLines,
                                    activePenColor = activePenColor,
                                    onColorSelected = { activePenColor = it },
                                    activeStrokeWidth = activeStrokeWidth,
                                    onStrokeWidthSelected = { activeStrokeWidth = it },
                                    penColors = penColors
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Bottom Actions
                    ActionsRow(
                        isProcessingConfirm = isProcessingConfirm,
                        onRetakeClick = onRetakeClick,
                        onConfirmClick = {
                            isProcessingConfirm = true
                            scope.launch {
                                val finalPath = withContext(Dispatchers.Default) {
                                    stitchPhotos(context, photoStates.toList(), activeFrame, selectedFilter, doodleLines.toList(), stickers.toList(), eventId, configManager)
                                }
                                isProcessingConfirm = false
                                val shouldPrint = configManager.printerType != "NONE"
                                onConfirmClick(finalPath, shouldPrint, activeFrame.id)
                            }
                        }
                    )
                }
            }
        }
    }
}

@Composable
fun FilterItem(
    filter: PhotoFilter,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val title = when (filter) {
        PhotoFilter.NORMAL -> "Original"
        PhotoFilter.MONO -> "B&W Retro"
        PhotoFilter.WARM -> "Warm Gold"
        PhotoFilter.COOL -> "Cool Cyan"
        PhotoFilter.VINTAGE -> "Vintage Sepia"
        PhotoFilter.VIVID -> "Vivid Contrast"
        PhotoFilter.DREAMY -> "Dreamy Glow"
        PhotoFilter.FILM -> "Analog Film"
    }

    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surface)
            .border(
                1.dp,
                if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline,
                RoundedCornerShape(12.dp)
            )
            .clickable { onClick() }
            .padding(horizontal = 20.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = title,
            color = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurface,
            fontSize = 13.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
        )
    }
}

// Custom stitching logic running on Background Thread
private fun stitchPhotos(
    context: Context,
    photoStates: List<PhotoState>,
    frame: Frame,
    filter: PhotoFilter,
    doodleLines: List<DoodleLine>,
    stickers: List<Sticker>,
    eventId: String,
    configManager: ConfigManager
): String {
    val multiplier = if (frame.width < 800) 3 else 2
    
    // Create base template Bitmap with higher resolution
    val template = Bitmap.createBitmap(frame.width * multiplier, frame.height * multiplier, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(template)
    
    // Draw background color
    val bgColor = try {
        android.graphics.Color.parseColor(frame.backgroundColor)
    } catch (e: Exception) {
        android.graphics.Color.BLACK
    }
    canvas.drawColor(bgColor)
    
    // Configure Paint with appropriate color filter matrix
    val paint = Paint().apply { isAntiAlias = true; isFilterBitmap = true }
    
    when (filter) {
        PhotoFilter.NORMAL -> {}
        PhotoFilter.MONO -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                0.213f, 0.715f, 0.072f, 0f, 0f,
                0.213f, 0.715f, 0.072f, 0f, 0f,
                0.213f, 0.715f, 0.072f, 0f, 0f,
                0f,     0f,     0f,     1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.WARM -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                1.15f, 0f, 0f, 0f, 0f,
                0f, 1.05f, 0f, 0f, 0f,
                0f, 0f, 0.85f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.COOL -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                0.9f, 0f, 0f, 0f, 0f,
                0f, 1.0f, 0f, 0f, 0f,
                0f, 0f, 1.2f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.VINTAGE -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                0.393f, 0.769f, 0.189f, 0f, 0f,
                0.349f, 0.686f, 0.168f, 0f, 0f,
                0.272f, 0.534f, 0.131f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.VIVID -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                1.3148f, -0.286f,  -0.0288f, 0f, 0f,
                -0.0852f, 1.114f,  -0.0288f, 0f, 0f,
                -0.0852f, -0.286f, 1.3712f,  0f, 0f,
                0f,       0f,      0f,       1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.DREAMY -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                1.1f, 0f, 0f, 0f, 10f,
                0f, 0.95f, 0f, 0f, 5f,
                0f, 0f, 1.05f, 0f, 15f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.FILM -> {
            val cm = android.graphics.ColorMatrix(floatArrayOf(
                1.05f, 0f, 0.05f, 0f, -10f,
                0.05f, 1.0f, 0f, 0f, 0f,
                0f, 0.05f, 0.95f, 0f, 10f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
    }

    // Stitch each photo into its corresponding slot coordinates scaled up
    for (i in frame.slots.indices) {
        if (i >= photoStates.size) break
        
        val slot = frame.slots[i]
        val photoState = photoStates[i]
        val photoFile = File(photoState.path)
        if (!photoFile.exists()) continue
        
        // Decode photo and crop to match scaled slot dimensions
        val srcBmp = BitmapFactory.decodeFile(photoFile.absolutePath)
        if (srcBmp != null) {
            val targetW = slot.width * multiplier
            val targetH = slot.height * multiplier
            val cropped = getCroppedBitmapWithState(srcBmp, targetW, targetH, photoState)
            
            // Draw photo to canvas
            val rectDest = Rect(
                slot.x * multiplier,
                slot.y * multiplier,
                (slot.x + slot.width) * multiplier,
                (slot.y + slot.height) * multiplier
            )
            canvas.drawBitmap(cropped, null, rectDest, paint)
            
            srcBmp.recycle()
            cropped.recycle()
        }
    }

    // Overlay frame PNG (on top of photos for professional margins/branding)
    val frameFile = File(context.cacheDir, "frames/${frame.id}.png")
    if (frameFile.exists()) {
        val overlayBmp = BitmapFactory.decodeFile(frameFile.absolutePath)
        if (overlayBmp != null) {
            val destRect = Rect(0, 0, frame.width * multiplier, frame.height * multiplier)
            canvas.drawBitmap(overlayBmp, null, destRect, null)
            overlayBmp.recycle()
        }
    } else {
        // Procedural fallbacks: draw border strokes around slot rectangles
        paint.colorFilter = null
        paint.color = android.graphics.Color.WHITE
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = 6f * multiplier
        for (slot in frame.slots) {
            canvas.drawRect(
                (slot.x * multiplier).toFloat(),
                (slot.y * multiplier).toFloat(),
                ((slot.x + slot.width) * multiplier).toFloat(),
                ((slot.y + slot.height) * multiplier).toFloat(),
                paint
            )
        }
        
        // Procedural text
        paint.style = Paint.Style.FILL
        paint.textSize = 36f * multiplier
        paint.typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD)
        canvas.drawText("CREATIVE STUDIO", 150f * multiplier, 1720f * multiplier, paint)
    }

    // Render dynamic text and logo if frame is marked as dynamic
    if (frame.isDynamic == true) {
        val activeEvent = if (!eventId.isNullOrEmpty()) {
            try {
                val json = configManager.syncedFramesJson
                if (json.isNotEmpty()) {
                    val config = Gson().fromJson(json, FrameConfig::class.java)
                    config?.events?.firstOrNull { it.id == eventId }
                } else null
            } catch (e: Exception) {
                null
            }
        } else null

        val dyn = frame.dynamicElements
        
        // 1. Draw Logo
        val logoRect = dyn?.logo
        if (logoRect != null && activeEvent != null) {
            val logoFile = File(context.cacheDir, "logos/logo_${activeEvent.id}.png")
            if (logoFile.exists()) {
                val logoBmp = BitmapFactory.decodeFile(logoFile.absolutePath)
                if (logoBmp != null) {
                    val destX = logoRect.x * multiplier
                    val destY = logoRect.y * multiplier
                    val destW = logoRect.width * multiplier
                    val destH = logoRect.height * multiplier
                    
                    val rectDest = Rect(destX, destY, destX + destW, destY + destH)
                    canvas.drawBitmap(logoBmp, null, rectDest, paint)
                    logoBmp.recycle()
                }
            }
        }
        
        // 2. Draw Texts
        dyn?.texts?.forEach { textConfig ->
            val textStr = when (textConfig.type) {
                "event_name" -> activeEvent?.name ?: "Creative Photo Studio"
                "event_subtitle" -> activeEvent?.subtitle ?: "Sweet Memories"
                "event_hashtag" -> activeEvent?.hashtag ?: "#photobooth"
                else -> ""
            }
            
            if (textStr.isNotEmpty()) {
                val textPaint = Paint().apply {
                    isAntiAlias = true
                    color = try {
                        android.graphics.Color.parseColor(textConfig.color ?: "#ffffff")
                    } catch (e: Exception) {
                        android.graphics.Color.WHITE
                    }
                    textSize = textConfig.fontSize.toFloat() * multiplier
                    
                    val style = textConfig.fontStyle ?: "normal"
                    val typefaceStyle = when (style) {
                        "bold" -> Typeface.BOLD
                        "italic" -> Typeface.ITALIC
                        "bold_italic" -> Typeface.BOLD_ITALIC
                        else -> Typeface.NORMAL
                    }
                    typeface = Typeface.create(Typeface.SANS_SERIF, typefaceStyle)
                    
                    textAlign = when (textConfig.align ?: "center") {
                        "left" -> Paint.Align.LEFT
                        "right" -> Paint.Align.RIGHT
                        else -> Paint.Align.CENTER
                    }
                }
                
                val xPos = (textConfig.x * multiplier).toFloat()
                val yPos = (textConfig.y * multiplier).toFloat()
                
                val lines = textStr.split("\n")
                var currentY = yPos
                val leading = textPaint.textSize * 1.25f
                lines.forEach { line ->
                    canvas.drawText(line, xPos, currentY, textPaint)
                    currentY += leading
                }
            }
        }
    }

    // Bake relative doodles directly onto final high-resolution Canvas
    if (doodleLines.isNotEmpty()) {
        val doodlePaint = Paint().apply {
            isAntiAlias = true
            strokeCap = Paint.Cap.ROUND
            strokeJoin = Paint.Join.ROUND
        }
        
        doodleLines.forEach { line ->
            doodlePaint.color = line.color.toArgb()
            val scaleFactor = (frame.width * multiplier).toFloat() / 180f
            
            val canvasW = frame.width * multiplier
            val canvasH = frame.height * multiplier
            
            if (line.points.size == 1) {
                val p = line.points[0]
                doodlePaint.style = Paint.Style.FILL
                val x = p.x * canvasW
                val y = p.y * canvasH
                val radius = (p.strokeWidth / 2f) * scaleFactor
                canvas.drawCircle(x, y, radius, doodlePaint)
            } else if (line.points.size > 1) {
                doodlePaint.style = Paint.Style.STROKE
                for (j in 1 until line.points.size) {
                    val p1 = line.points[j - 1]
                    val p2 = line.points[j]
                    
                    val x1 = p1.x * canvasW
                    val y1 = p1.y * canvasH
                    val x2 = p2.x * canvasW
                    val y2 = p2.y * canvasH
                    
                    val segmentWidth = ((p1.strokeWidth + p2.strokeWidth) / 2f) * scaleFactor
                    doodlePaint.strokeWidth = segmentWidth
                    
                    canvas.drawLine(x1, y1, x2, y2, doodlePaint)
                }
            }
        }
    }

    // Bake relative stickers directly onto final high-resolution Canvas
    if (stickers.isNotEmpty()) {
        val stickerPaint = Paint().apply {
            isAntiAlias = true
            textAlign = Paint.Align.CENTER
            typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.NORMAL)
        }
        
        stickers.forEach { sticker ->
            val scaleFactor = (frame.width * multiplier).toFloat() / 180f
            
            val canvasW = frame.width * multiplier
            val canvasH = frame.height * multiplier
            
            canvas.save()
            val x = sticker.x * canvasW
            val y = sticker.y * canvasH
            canvas.translate(x, y)
            canvas.rotate(sticker.rotation)
            
            // Render text emoji
            stickerPaint.textSize = 32f * sticker.scale * scaleFactor
            val fm = stickerPaint.fontMetrics
            val dy = (fm.bottom - fm.top) / 2f - fm.bottom
            canvas.drawText(sticker.emoji, 0f, dy, stickerPaint)
            
            canvas.restore()
        }
    }

    // Clean up old stitched files in cache to avoid clutter
    context.cacheDir.listFiles()?.forEach { file ->
        if (file.name.startsWith("final_stitched_strip_") && file.name.endsWith(".png")) {
            try { file.delete() } catch(e: Exception) {}
        }
    }
    
    // Save final composite strip with a unique timestamp to bypass caching
    val outputFile = File(context.cacheDir, "final_stitched_strip_${System.currentTimeMillis()}.png")
    if (outputFile.exists()) outputFile.delete()
    
    FileOutputStream(outputFile).use { out ->
        template.compress(Bitmap.CompressFormat.PNG, 100, out)
    }
    
    template.recycle()
    return outputFile.absolutePath
}

// Custom cropping using PhotoState translation/scale
private fun getCroppedBitmapWithState(src: Bitmap, targetW: Int, targetH: Int, photoState: PhotoState): Bitmap {
    val srcW = src.width
    val srcH = src.height
    
    val targetRatio = targetW.toFloat() / targetH.toFloat()
    val srcRatio = srcW.toFloat() / srcH.toFloat()
    
    var cropW = srcW
    var cropH = srcH
    
    if (srcRatio > targetRatio) {
        // Src is wider, crop horizontally
        cropH = srcH
        cropW = (srcH * targetRatio).toInt()
    } else {
        // Src is taller, crop vertically
        cropW = srcW
        cropH = (srcW / targetRatio).toInt()
    }
    
    // Apply user scale (zooming in makes the crop window smaller relative to source)
    val actualCropW = (cropW / photoState.scale).toInt().coerceIn(1, srcW)
    val actualCropH = (cropH / photoState.scale).toInt().coerceIn(1, srcH)
    
    // Calculate start positions based on normalized offset (0 to 1)
    val maxX = srcW - actualCropW
    val maxY = srcH - actualCropH
    
    // normalizedX = 1 means show left part (crop start = 0), normalizedX = 0 means show right part (crop start = maxX)
    val x = ((srcW - actualCropW) * (1f - photoState.normalizedX)).toInt().coerceIn(0, maxX)
    val y = ((srcH - actualCropH) * (1f - photoState.normalizedY)).toInt().coerceIn(0, maxY)
    
    val cropped = Bitmap.createBitmap(src, x, y, actualCropW, actualCropH)
    val scaled = Bitmap.createScaledBitmap(cropped, targetW, targetH, true)
    
    if (cropped != src) {
        cropped.recycle()
    }
    return scaled
}

@Composable
fun PreviewPhotoContainer(
    photoStates: List<PhotoState>,
    frame: Frame,
    selectedFilter: PhotoFilter,
    activeTab: PreviewTab,
    swapMode: Boolean,
    selectedSwapIndex: Int?,
    onPhotoClick: (Int) -> Unit,
    onPhotoTransform: (index: Int, offset: Offset, scale: Float) -> Unit,
    doodleLines: SnapshotStateList<DoodleLine>,
    activePenColor: Color,
    activeStrokeWidth: Float,
    stickers: SnapshotStateList<Sticker>,
    selectedStickerId: String?,
    onStickerSelected: (String?) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val frameFile = remember(frame.id) { File(context.cacheDir, "frames/${frame.id}.png") }
    val frameAspectRatio = frame.width.toFloat() / frame.height.toFloat()
    
    val parsedColor = remember(frame.backgroundColor) {
        try {
            Color(android.graphics.Color.parseColor(frame.backgroundColor))
        } catch (e: Exception) {
            Color.Black
        }
    }

    Box(
        modifier = modifier
            .aspectRatio(frameAspectRatio)
            .border(2.dp, MaterialTheme.colorScheme.primary, RoundedCornerShape(12.dp))
            .clip(RoundedCornerShape(12.dp))
            .background(parsedColor),
        contentAlignment = Alignment.Center
    ) {
        // 1. Photo Slots Container
        BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
            val containerWidth = maxWidth
            val containerHeight = maxHeight
            
            val scaleX = containerWidth.value / frame.width.toFloat()
            val scaleY = containerHeight.value / frame.height.toFloat()
            
            // Draw slots
            frame.slots.forEachIndexed { index, slot ->
                if (index < photoStates.size) {
                    val photoState = photoStates[index]
                    
                    val slotLeft = (slot.x * scaleX).dp
                    val slotTop = (slot.y * scaleY).dp
                    val slotWidth = (slot.width * scaleX).dp
                    val slotHeight = (slot.height * scaleY).dp
                    
                    // We need photo aspect ratio to calculate crop dimensions
                    val photoAspectRatio = remember(photoState.path) {
                        val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                        BitmapFactory.decodeFile(photoState.path, options)
                        if (options.outWidth > 0 && options.outHeight > 0) {
                            options.outWidth.toFloat() / options.outHeight.toFloat()
                        } else {
                            1f
                        }
                    }
                    
                    // Compute image size to cover slot
                    val slotRatio = slot.width.toFloat() / slot.height.toFloat()
                    val (imageWidth, imageHeight) = if (photoAspectRatio > slotRatio) {
                        Pair(slotHeight * photoAspectRatio, slotHeight)
                    } else {
                        Pair(slotWidth, slotWidth / photoAspectRatio)
                    }
                    
                    val scaledWidth = imageWidth * photoState.scale
                    val scaledHeight = imageHeight * photoState.scale
                    
                    val minX = slotWidth - scaledWidth
                    val maxX = 0.dp
                    val offsetX = minX + photoState.normalizedX.dp * (maxX.value - minX.value)
                    
                    val minY = slotHeight - scaledHeight
                    val maxY = 0.dp
                    val offsetY = minY + photoState.normalizedY.dp * (maxY.value - minY.value)
                    
                    val isSelectedForSwap = index == selectedSwapIndex
                    
                    Box(
                        modifier = Modifier
                            .offset(x = slotLeft, y = slotTop)
                            .size(slotWidth, slotHeight)
                            .clipToBounds()
                            .background(Color.DarkGray)
                            .then(
                                if (swapMode) {
                                    Modifier
                                        .clickable { onPhotoClick(index) }
                                        .border(
                                            width = if (isSelectedForSwap) 4.dp else 2.dp,
                                            color = if (isSelectedForSwap) Color.Green else Color.Yellow.copy(alpha = 0.7f)
                                        )
                                } else if (activeTab == PreviewTab.FRAME) {
                                    val currentPhotoStateState = rememberUpdatedState(photoState)
                                    val currentOnPhotoTransformState = rememberUpdatedState(onPhotoTransform)
                                    Modifier.pointerInput(index) {
                                        detectTransformGestures { _, pan, zoom, _ ->
                                            val currentPhotoState = currentPhotoStateState.value
                                            val currentScale = currentPhotoState.scale
                                            val newScale = (currentScale * zoom).coerceIn(1.0f, 3.0f)
                                            
                                            val newScaledWidth = imageWidth * newScale
                                            val newScaledHeight = imageHeight * newScale
                                            
                                            val newMinX = slotWidth - newScaledWidth
                                            val newMinY = slotHeight - newScaledHeight
                                            
                                            val rangeX = -newMinX.toPx()
                                            val rangeY = -newMinY.toPx()
                                            
                                            val newNormalizedX = if (rangeX > 0) {
                                                (currentPhotoState.normalizedX + pan.x / rangeX).coerceIn(0f, 1f)
                                            } else {
                                                currentPhotoState.normalizedX
                                            }
                                            
                                            val newNormalizedY = if (rangeY > 0) {
                                                (currentPhotoState.normalizedY + pan.y / rangeY).coerceIn(0f, 1f)
                                            } else {
                                                currentPhotoState.normalizedY
                                            }
                                            
                                            currentOnPhotoTransformState.value(index, Offset(newNormalizedX, newNormalizedY), newScale)
                                        }
                                    }
                                } else {
                                    Modifier
                                }
                            )
                    ) {
                        // Display photo
                        AsyncImage(
                            model = File(photoState.path),
                            contentDescription = "Photo ${index + 1}",
                            contentScale = ContentScale.FillBounds,
                            colorFilter = getColorFilter(selectedFilter),
                            modifier = Modifier
                                .offset(x = offsetX, y = offsetY)
                                .size(scaledWidth, scaledHeight)
                        )
                        
                        // Swap indicator overlay
                        if (swapMode) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .background(
                                        if (isSelectedForSwap) Color.Green.copy(alpha = 0.3f)
                                        else Color.Black.copy(alpha = 0.4f)
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = if (isSelectedForSwap) "Terpilih" else "Sentuh untuk Tukar",
                                    color = Color.White,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    textAlign = TextAlign.Center,
                                    modifier = Modifier
                                        .background(Color.Black.copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                                        .padding(horizontal = 8.dp, vertical = 4.dp)
                                )
                            }
                        }
                    }
                }
            }
        }
        
        // 2. Frame Overlay (on top of photos)
        if (frameFile.exists()) {
            AsyncImage(
                model = frameFile,
                contentDescription = "Frame Overlay",
                modifier = Modifier.fillMaxSize()
            )
        } else {
            // Draw white borders around slots as fallback
            BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
                val containerWidth = maxWidth
                val containerHeight = maxHeight
                val scaleX = containerWidth.value / frame.width.toFloat()
                val scaleY = containerHeight.value / frame.height.toFloat()
                
                androidx.compose.foundation.Canvas(modifier = Modifier.fillMaxSize()) {
                    frame.slots.forEach { slot ->
                        drawRect(
                            color = Color.White,
                            topLeft = Offset(slot.x * scaleX, slot.y * scaleY),
                            size = androidx.compose.ui.geometry.Size(slot.width * scaleX, slot.height * scaleY),
                            style = androidx.compose.ui.graphics.drawscope.Stroke(width = 6f)
                        )
                    }
                }
            }
        }
        
        // 3. Doodle canvas
        DoodleCanvas(
            lines = doodleLines,
            onLinesChanged = { newList ->
                doodleLines.clear()
                doodleLines.addAll(newList)
            },
            activeColor = activePenColor,
            activeStrokeWidth = activeStrokeWidth,
            enabled = activeTab == PreviewTab.CORETAN,
            modifier = Modifier.fillMaxSize()
        )
        
        // 4. Sticker overlay
        StickerOverlay(
            stickers = stickers,
            selectedStickerId = selectedStickerId,
            onStickerSelected = onStickerSelected,
            onStickerUpdated = { updated ->
                val index = stickers.indexOfFirst { it.id == updated.id }
                if (index >= 0) {
                    stickers[index] = updated
                }
            },
            onStickerDelete = { id ->
                stickers.removeAll { it.id == id }
                if (selectedStickerId == id) onStickerSelected(null)
            },
            enabled = activeTab == PreviewTab.STICKER,
            modifier = Modifier.fillMaxSize()
        )
    }
}

private fun getColorFilter(filter: PhotoFilter): ColorFilter? {
    return when (filter) {
        PhotoFilter.NORMAL -> null
        PhotoFilter.MONO -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            0.213f, 0.715f, 0.072f, 0f, 0f,
            0.213f, 0.715f, 0.072f, 0f, 0f,
            0.213f, 0.715f, 0.072f, 0f, 0f,
            0f,     0f,     0f,     1f, 0f
        )))
        PhotoFilter.WARM -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            1.15f, 0f, 0f, 0f, 0f,
            0f, 1.05f, 0f, 0f, 0f,
            0f, 0f, 0.85f, 0f, 0f,
            0f, 0f, 0f, 1f, 0f
        )))
        PhotoFilter.COOL -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            0.9f, 0f, 0f, 0f, 0f,
            0f, 1.0f, 0f, 0f, 0f,
            0f, 0f, 1.2f, 0f, 0f,
            0f, 0f, 0f, 1f, 0f
        )))
        PhotoFilter.VINTAGE -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            0.393f, 0.769f, 0.189f, 0f, 0f,
            0.349f, 0.686f, 0.168f, 0f, 0f,
            0.272f, 0.534f, 0.131f, 0f, 0f,
            0f, 0f, 0f, 1f, 0f
        )))
        PhotoFilter.VIVID -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            1.3148f, -0.286f,  -0.0288f, 0f, 0f,
            -0.0852f, 1.114f,  -0.0288f, 0f, 0f,
            -0.0852f, -0.286f, 1.3712f,  0f, 0f,
            0f,       0f,      0f,       1f, 0f
        )))
        PhotoFilter.DREAMY -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            1.1f, 0f, 0f, 0f, 10f,
            0f, 0.95f, 0f, 0f, 5f,
            0f, 0f, 1.05f, 0f, 15f,
            0f, 0f, 0f, 1f, 0f
        )))
        PhotoFilter.FILM -> ColorFilter.colorMatrix(ColorMatrix(floatArrayOf(
            1.05f, 0f, 0.05f, 0f, -10f,
            0.05f, 1.0f, 0f, 0f, 0f,
            0f, 0.05f, 0.95f, 0f, 10f,
            0f, 0f, 0f, 1f, 0f
        )))
    }
}

@Composable
fun StickerOverlay(
    stickers: SnapshotStateList<Sticker>,
    selectedStickerId: String?,
    onStickerSelected: (String?) -> Unit,
    onStickerUpdated: (Sticker) -> Unit,
    onStickerDelete: (String) -> Unit,
    enabled: Boolean,
    modifier: Modifier = Modifier
) {
    val density = androidx.compose.ui.platform.LocalDensity.current
    var containerCoordinates by remember { mutableStateOf<LayoutCoordinates?>(null) }
    
    val tapModifier = if (enabled) {
        Modifier.pointerInput(Unit) {
            detectTapGestures {
                onStickerSelected(null)
            }
        }
    } else {
        Modifier
    }

    BoxWithConstraints(
        modifier = modifier
            .then(tapModifier)
            .onGloballyPositioned { containerCoordinates = it }
    ) {
        val containerWidth = maxWidth
        val containerHeight = maxHeight
        val containerWidthPx = with(density) { containerWidth.toPx() }
        val containerHeightPx = with(density) { containerHeight.toPx() }

        stickers.forEach { sticker ->
            val isSelected = sticker.id == selectedStickerId
            val currentStickerState = rememberUpdatedState(sticker)
            var handleCoordinates by remember { mutableStateOf<LayoutCoordinates?>(null) }

            val stickerDragModifier = if (enabled) {
                Modifier.pointerInput(sticker.id) {
                    detectTransformGestures { _, pan, zoom, rotation ->
                        val currentSticker = currentStickerState.value
                        onStickerSelected(currentSticker.id)
                        
                        if (pan != androidx.compose.ui.geometry.Offset.Zero || zoom != 1f || rotation != 0f) {
                            val newX = (currentSticker.x + pan.x / containerWidthPx).coerceIn(0f, 1f)
                            val newY = (currentSticker.y + pan.y / containerHeightPx).coerceIn(0f, 1f)
                            val newScale = (currentSticker.scale * zoom).coerceIn(0.5f, 3.0f)
                            val newRotation = currentSticker.rotation + rotation
                            onStickerUpdated(
                                currentSticker.copy(
                                    x = newX,
                                    y = newY,
                                    scale = newScale,
                                    rotation = newRotation
                                )
                            )
                        }
                    }
                }
            } else {
                Modifier
            }

            Box(
                modifier = Modifier
                    .offset(
                        x = (sticker.x * containerWidth.value).dp - 40.dp,
                        y = (sticker.y * containerHeight.value).dp - 40.dp
                    )
                    .size(80.dp)
                    .then(stickerDragModifier)
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .graphicsLayer(
                            scaleX = sticker.scale,
                            scaleY = sticker.scale,
                            rotationZ = sticker.rotation
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    if (isSelected) {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .border(1.5.dp, MaterialTheme.colorScheme.primary, RoundedCornerShape(8.dp))
                        )
                    }

                    Text(
                        text = sticker.emoji,
                        fontSize = 40.sp,
                        textAlign = TextAlign.Center
                    )

                    if (isSelected && enabled) {
                        // Delete Button (top-right) counter-scaled
                        IconButton(
                            onClick = { onStickerDelete(sticker.id) },
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .offset(x = 6.dp, y = (-6).dp)
                                .graphicsLayer(
                                    scaleX = 1f / sticker.scale,
                                    scaleY = 1f / sticker.scale
                                )
                                .size(24.dp)
                                .background(MaterialTheme.colorScheme.primary, CircleShape)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Hapus Stiker",
                                tint = Color.White,
                                modifier = Modifier.size(12.dp)
                            )
                        }

                        // Resize / Rotate Handle (bottom-left) counter-scaled
                        Box(
                            modifier = Modifier
                                .align(Alignment.BottomStart)
                                .offset(x = (-6).dp, y = 6.dp)
                                .graphicsLayer(
                                    scaleX = 1f / sticker.scale,
                                    scaleY = 1f / sticker.scale
                                )
                                .size(24.dp)
                                .background(MaterialTheme.colorScheme.secondary, CircleShape)
                                .onGloballyPositioned { handleCoordinates = it }
                                .pointerInput(sticker.id) {
                                    detectDragGestures(
                                        onDrag = { change, dragAmount ->
                                            change.consume()
                                            val container = containerCoordinates
                                            val handle = handleCoordinates
                                            if (container != null && handle != null && container.isAttached && handle.isAttached) {
                                                val touchPos = container.localPositionOf(handle, change.position)
                                                val centerX = sticker.x * containerWidthPx
                                                val centerY = sticker.y * containerHeightPx
                                                
                                                val dx = touchPos.x - centerX
                                                val dy = touchPos.y - centerY
                                                val currentDistance = kotlin.math.sqrt(dx * dx + dy * dy)
                                                
                                                val initialHalfSizePx = with(density) { 40.dp.toPx() }
                                                val initialDistance = kotlin.math.sqrt(initialHalfSizePx * initialHalfSizePx * 2)
                                                
                                                val newScale = (currentDistance / initialDistance).coerceIn(0.5f, 3.0f)
                                                
                                                val currentAngleRad = kotlin.math.atan2(dy, dx)
                                                val currentAngleDeg = Math.toDegrees(currentAngleRad.toDouble()).toFloat()
                                                val newRotation = (currentAngleDeg - 135f)
                                                
                                                onStickerUpdated(
                                                    sticker.copy(
                                                        scale = newScale,
                                                        rotation = newRotation
                                                    )
                                                )
                                            }
                                        }
                                    )
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            // Custom canvas to draw a premium diagonal double-ended arrow
                            androidx.compose.foundation.Canvas(modifier = Modifier.fillMaxSize().padding(6.dp)) {
                                val w = size.width
                                val h = size.height
                                // Draw diagonal line
                                drawLine(
                                    color = Color.White,
                                    start = Offset(0f, h),
                                    end = Offset(w, 0f),
                                    strokeWidth = 1.5.dp.toPx()
                                )
                                // Top-right arrow head
                                drawLine(
                                    color = Color.White,
                                    start = Offset(w, 0f),
                                    end = Offset(w - 3.dp.toPx(), 0f),
                                    strokeWidth = 1.5.dp.toPx()
                                )
                                drawLine(
                                    color = Color.White,
                                    start = Offset(w, 0f),
                                    end = Offset(w, 3.dp.toPx()),
                                    strokeWidth = 1.5.dp.toPx()
                                )
                                // Bottom-left arrow head
                                drawLine(
                                    color = Color.White,
                                    start = Offset(0f, h),
                                    end = Offset(3.dp.toPx(), h),
                                    strokeWidth = 1.5.dp.toPx()
                                )
                                drawLine(
                                    color = Color.White,
                                    start = Offset(0f, h),
                                    end = Offset(0f, h - 3.dp.toPx()),
                                    strokeWidth = 1.5.dp.toPx()
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

val emojiList = listOf(
    // Faces & Emoticons (12)
    "😎", "😍", "🥰", "🤪", "🥳", "🤡", "😭", "🥵", "🥶", "🥺", "🤠", "👽",
    // Hearts & Love (8)
    "❤️", "💖", "💝", "💕", "💌", "💋", "🫶", "🎀",
    // Celebrations & Magic (8)
    "✨", "🌟", "⭐", "💫", "🔥", "⚡", "🌈", "🍀",
    // Party & Decor (8)
    "👑", "🎈", "🎉", "🎁", "🧸", "🔔", "💎", "🌸",
    // Food & Drinks (10)
    "🍕", "🧁", "🍩", "🍦", "🥤", "🍹", "🍿", "🍓", "🍒", "🥑",
    // Music, Hobbies & Tech (8)
    "📸", "🎧", "🎵", "🎸", "👾", "🎮", "🐱", "🐶",
    // Spooky & Fun (6)
    "🎃", "👻", "💀", "😈", "🚀", "🛸"
)

@Composable
fun HorizontalTabRow(
    activeTab: PreviewTab,
    onTabSelected: (PreviewTab) -> Unit,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surfaceVariant, RoundedCornerShape(12.dp))
            .padding(4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        PreviewTab.values().forEach { tab ->
            val isSelected = tab == activeTab
            val (title, icon) = when (tab) {
                PreviewTab.FRAME -> Pair("Bingkai", Icons.Default.Photo)
                PreviewTab.FILTER -> Pair("Filter", Icons.Default.AutoAwesome)
                PreviewTab.STICKER -> Pair("Stiker", Icons.Default.Face)
                PreviewTab.CORETAN -> Pair("Coretan", Icons.Default.Gesture)
            }
            
            Box(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(8.dp))
                    .background(if (isSelected) MaterialTheme.colorScheme.primary else Color.Transparent)
                    .clickable { onTabSelected(tab) }
                    .padding(vertical = 8.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        imageVector = icon,
                        contentDescription = title,
                        tint = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = title,
                        color = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurfaceVariant,
                        fontSize = 10.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                    )
                }
            }
        }
    }
}

@Composable
fun VerticalTabColumn(
    activeTab: PreviewTab,
    onTabSelected: (PreviewTab) -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .width(80.dp)
            .fillMaxHeight()
            .background(MaterialTheme.colorScheme.surfaceVariant, RoundedCornerShape(16.dp))
            .padding(6.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        PreviewTab.values().forEach { tab ->
            val isSelected = tab == activeTab
            val (title, icon) = when (tab) {
                PreviewTab.FRAME -> Pair("Bingkai", Icons.Default.Photo)
                PreviewTab.FILTER -> Pair("Filter", Icons.Default.AutoAwesome)
                PreviewTab.STICKER -> Pair("Stiker", Icons.Default.Face)
                PreviewTab.CORETAN -> Pair("Coretan", Icons.Default.Gesture)
            }
            
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isSelected) MaterialTheme.colorScheme.primary else Color.Transparent)
                    .clickable { onTabSelected(tab) }
                    .padding(vertical = 12.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        imageVector = icon,
                        contentDescription = title,
                        tint = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = title,
                        color = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurfaceVariant,
                        fontSize = 9.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                        textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}

@Composable
fun FrameSelectorPanel(
    compatibleFrames: List<Frame>,
    activeFrame: Frame,
    onFrameSelected: (Frame) -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = "Pilih Desain Bingkai Baru:",
            color = MaterialTheme.colorScheme.onBackground,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold
        )
        
        LazyRow(
            modifier = Modifier.fillMaxWidth().weight(1f),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(vertical = 4.dp)
        ) {
            items(compatibleFrames) { frame ->
                MiniFrameCard(
                    frame = frame,
                    isSelected = frame.id == activeFrame.id,
                    onClick = { onFrameSelected(frame) }
                )
            }
        }
    }
}

@Composable
fun FilterSelectorPanel(
    selectedFilter: PhotoFilter,
    onFilterSelected: (PhotoFilter) -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = "Pilih Filter Estetik:",
            color = MaterialTheme.colorScheme.onBackground,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold
        )
        
        LazyVerticalGrid(
            columns = GridCells.Adaptive(minSize = 100.dp),
            modifier = Modifier.fillMaxWidth().weight(1f),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(vertical = 4.dp)
        ) {
            items(PhotoFilter.values()) { filter ->
                FilterItem(
                    filter = filter,
                    isSelected = filter == selectedFilter,
                    onClick = { onFilterSelected(filter) }
                )
            }
        }
    }
}

@Composable
fun StickerSelectorPanel(
    onAddSticker: (String) -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = "Sentuh Stiker untuk Menambahkan ke Foto:",
            color = MaterialTheme.colorScheme.onBackground,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold
        )
        
        LazyVerticalGrid(
            columns = GridCells.Adaptive(minSize = 58.dp),
            modifier = Modifier.fillMaxWidth().weight(1f),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(vertical = 4.dp)
        ) {
            items(emojiList) { emoji ->
                Box(
                    modifier = Modifier
                        .size(54.dp)
                        .clip(CircleShape)
                        .background(MaterialTheme.colorScheme.surfaceVariant)
                        .clickable { onAddSticker(emoji) },
                    contentAlignment = Alignment.Center
                ) {
                    Text(text = emoji, fontSize = 28.sp)
                }
            }
        }
        
        Text(
            text = "Tips: Geser dengan 1 jari untuk memindahkan. Gunakan 2 jari (cubit) untuk memutar atau memperbesar.",
            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
            fontSize = 10.sp,
            lineHeight = 14.sp
        )
    }
}

@Composable
fun DoodleSelectorPanel(
    doodleLines: SnapshotStateList<DoodleLine>,
    activePenColor: Color,
    onColorSelected: (Color) -> Unit,
    activeStrokeWidth: Float,
    onStrokeWidthSelected: (Float) -> Unit,
    penColors: List<Color>
) {
    Row(
        modifier = Modifier.fillMaxSize(),
        horizontalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Left part: Undo and Clear buttons
        Column(
            modifier = Modifier.fillMaxHeight(),
            verticalArrangement = Arrangement.spacedBy(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            IconButton(
                onClick = { if (doodleLines.isNotEmpty()) doodleLines.removeAt(doodleLines.size - 1) },
                enabled = doodleLines.isNotEmpty(),
                modifier = Modifier
                    .size(44.dp)
                    .background(MaterialTheme.colorScheme.surfaceVariant, CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Undo,
                    contentDescription = "Undo",
                    tint = if (doodleLines.isNotEmpty()) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f)
                )
            }
            
            IconButton(
                onClick = { doodleLines.clear() },
                enabled = doodleLines.isNotEmpty(),
                modifier = Modifier
                    .size(44.dp)
                    .background(MaterialTheme.colorScheme.surfaceVariant, CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Delete,
                    contentDescription = "Clear All",
                    tint = if (doodleLines.isNotEmpty()) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f)
                )
            }
        }
        
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(MaterialTheme.colorScheme.outline))
        
        // Middle part: Stroke widths
        Column(
            modifier = Modifier.fillMaxHeight(),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text("PENA", color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f), fontSize = 8.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(3f, 6f, 10f).forEach { size ->
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(if (activeStrokeWidth == size) MaterialTheme.colorScheme.primary.copy(alpha = 0.3f) else Color.Transparent)
                            .clickable { onStrokeWidthSelected(size) },
                        contentAlignment = Alignment.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .size((size * 1.2f).dp)
                                .clip(CircleShape)
                                .background(MaterialTheme.colorScheme.onBackground)
                        )
                    }
                }
            }
        }
        
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(MaterialTheme.colorScheme.outline))
        
        // Right part: Colors
        Column(
            modifier = Modifier.weight(1f).fillMaxHeight(),
            verticalArrangement = Arrangement.Center
        ) {
            Text("WARNA", color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f), fontSize = 8.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.height(4.dp))
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                items(penColors) { color ->
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(color)
                            .border(
                                width = if (activePenColor == color) 2.dp else 1.dp,
                                color = if (activePenColor == color) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline,
                                shape = CircleShape
                            )
                            .clickable { onColorSelected(color) },
                        contentAlignment = Alignment.Center
                    ) {
                        if (activePenColor == color) {
                            Icon(
                                imageVector = Icons.Default.Check,
                                contentDescription = "Selected",
                                tint = if (color == Color.White || color == Color(0xFFFFB703)) Color.Black else Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun MiniFrameCard(
    frame: Frame,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val frameFile = remember(frame.id) { File(context.cacheDir, "frames/${frame.id}.png") }
    val frameAspectRatio = frame.width.toFloat() / frame.height.toFloat()
    
    val parsedColor = remember(frame.backgroundColor) {
        try {
            Color(android.graphics.Color.parseColor(frame.backgroundColor))
        } catch (e: Exception) {
            Color.DarkGray
        }
    }

    Column(
        modifier = modifier
            .fillMaxHeight()
            .aspectRatio(frameAspectRatio)
            .clip(RoundedCornerShape(12.dp))
            .background(parsedColor)
            .border(
                width = if (isSelected) 3.dp else 1.dp,
                color = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline.copy(alpha = 0.2f),
                shape = RoundedCornerShape(12.dp)
            )
            .clickable { onClick() },
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.SpaceBetween
    ) {
        Box(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(4.dp),
            contentAlignment = Alignment.Center
        ) {
            val frameWidth = frame.width.coerceAtLeast(1).toFloat()
            val frameHeight = frame.height.coerceAtLeast(1).toFloat()
            
            BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
                val previewWidth = maxWidth
                val previewHeight = maxHeight
                
                if (!frameFile.exists()) {
                    frame.slots.forEach { slot ->
                        val slotLeft = (slot.x.toFloat() / frameWidth * previewWidth.value).dp
                        val slotTop = (slot.y.toFloat() / frameHeight * previewHeight.value).dp
                        val slotWidth = (slot.width.toFloat() / frameWidth * previewWidth.value).dp
                        val slotHeight = (slot.height.toFloat() / frameHeight * previewHeight.value).dp
                        
                        Box(
                            modifier = Modifier
                                .offset(x = slotLeft, y = slotTop)
                                .size(slotWidth, slotHeight)
                                .background(MaterialTheme.colorScheme.onSurface.copy(alpha = 0.2f))
                        )
                    }
                }
                
                if (frameFile.exists()) {
                    AsyncImage(
                        model = frameFile,
                        contentDescription = frame.name,
                        modifier = Modifier.fillMaxSize()
                    )
                }
            }
        }
        
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.8f))
                .padding(vertical = 4.dp)
        ) {
            Text(
                text = frame.name,
                color = MaterialTheme.colorScheme.onSurface,
                fontSize = 10.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
                maxLines = 1
            )
        }
    }
}

@Composable
fun ActionsRow(
    isProcessingConfirm: Boolean,
    onRetakeClick: () -> Unit,
    onConfirmClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Retake Button
        OutlinedButton(
            onClick = onRetakeClick,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
            shape = RoundedCornerShape(16.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.onBackground),
            modifier = Modifier
                .weight(1f)
                .height(56.dp),
            enabled = !isProcessingConfirm
        ) {
            Icon(
                imageVector = Icons.Default.Refresh,
                contentDescription = "Retake",
                tint = MaterialTheme.colorScheme.onBackground
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text("Ulangi Foto", color = MaterialTheme.colorScheme.onBackground)
        }

        // Confirm and print/share Button
        Button(
            onClick = onConfirmClick,
            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier
                .weight(1.5f)
                .height(56.dp),
            enabled = !isProcessingConfirm
        ) {
            if (isProcessingConfirm) {
                CircularProgressIndicator(color = MaterialTheme.colorScheme.onPrimary, modifier = Modifier.size(24.dp))
            } else {
                Icon(
                    imageVector = Icons.Default.Check,
                    contentDescription = "Confirm",
                    tint = MaterialTheme.colorScheme.onPrimary
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Cetak & Download", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimary)
            }
        }
    }
}
