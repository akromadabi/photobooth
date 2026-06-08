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
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.runtime.snapshots.SnapshotStateList
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.Image
import androidx.compose.ui.graphics.asImageBitmap
import coil.compose.AsyncImage
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.ui.frame.getFramesForLayout
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream

enum class PhotoFilter {
    NORMAL, MONO, WARM, COOL
}

enum class PreviewTab {
    FRAME, FILTER, STICKER, CORETAN
}

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

    var selectedFilter by remember { mutableStateOf(PhotoFilter.NORMAL) }
    var previewBitmap by remember { mutableStateOf<Bitmap?>(null) }
    var isStitching by remember { mutableStateOf(true) }
    
    DisposableEffect(Unit) {
        onDispose {
            previewBitmap?.recycle()
        }
    }
    
    // Doodle drawing states
    val doodleLines = remember { mutableStateListOf<DoodleLine>() }
    val penColors = listOf(
        Color.White,
        Color.Black,
        Color(0xFFFFB703), // Radiant Neon Yellow
        Color(0xFFE63946), // Radiant Red
        Color(0xFF52B788), // Glowing Green
        Color(0xFF2196F3)  // Electric Blue
    )
    var activePenColor by remember { mutableStateOf(penColors[0]) }
    var activeStrokeWidth by remember { mutableFloatStateOf(5f) }
    var isProcessingConfirm by remember { mutableStateOf(false) }

    // Sticker states
    val stickers = remember { mutableStateListOf<Sticker>() }
    var selectedStickerId by remember { mutableStateOf<String?>(null) }
    
    // Customization panel tab
    var activeTab by remember { mutableStateOf(PreviewTab.FRAME) }

    var isFirstStitch by remember { mutableStateOf(true) }

    // Clear sticker selection when switching tabs
    LaunchedEffect(activeTab) {
        selectedStickerId = null
    }

    // Re-stitch when filter or frame changes
    LaunchedEffect(selectedFilter, activeFrame) {
        if (previewBitmap == null) {
            isStitching = true
        }
        withContext(Dispatchers.Default) {
            val bmp = stitchPhotosPreview(context, photoPaths, activeFrame, selectedFilter)
            withContext(Dispatchers.Main) {
                previewBitmap?.recycle()
                previewBitmap = bmp
                isStitching = false
                isFirstStitch = false
            }
        }
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
                    containerColor = Color(0xFF0F0F12),
                    titleContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFF0F0F12),
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        if (isStitching && isFirstStitch) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                contentAlignment = Alignment.Center
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    CircularProgressIndicator(color = Color(0xFFE63946))
                    Text("Menyusun strip foto Anda...", color = Color.Gray, fontSize = 14.sp)
                }
            }
        } else {
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
                            previewBitmap = previewBitmap,
                            frameAspectRatio = frameAspectRatio,
                            doodleLines = doodleLines,
                            activePenColor = activePenColor,
                            activeStrokeWidth = activeStrokeWidth,
                            activeTab = activeTab,
                            stickers = stickers,
                            selectedStickerId = selectedStickerId,
                            onStickerSelected = { selectedStickerId = it }
                        )
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
                                    stitchPhotos(context, photoPaths, activeFrame, selectedFilter, doodleLines.toList(), stickers.toList())
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
                        .padding(paddingValues)
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Left Side: Preview Photo Container
                    Box(
                        modifier = Modifier
                            .weight(1.2f)
                            .fillMaxHeight(),
                        contentAlignment = Alignment.Center
                    ) {
                        PreviewPhotoContainer(
                            previewBitmap = previewBitmap,
                            frameAspectRatio = frameAspectRatio,
                            doodleLines = doodleLines,
                            activePenColor = activePenColor,
                            activeStrokeWidth = activeStrokeWidth,
                            activeTab = activeTab,
                            stickers = stickers,
                            selectedStickerId = selectedStickerId,
                            onStickerSelected = { selectedStickerId = it }
                        )
                    }

                    // Right Side: Control Panels & Action Buttons
                    Column(
                        modifier = Modifier
                            .weight(1.8f)
                            .fillMaxHeight(),
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
                                        stitchPhotos(context, photoPaths, activeFrame, selectedFilter, doodleLines.toList(), stickers.toList())
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
    }

    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (isSelected) Color(0xFFE63946) else Color(0xFF18181F))
            .border(
                1.dp,
                if (isSelected) Color(0xFFE63946) else Color(0xFF2A2A35),
                RoundedCornerShape(12.dp)
            )
            .clickable { onClick() }
            .padding(horizontal = 20.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = title,
            color = Color.White,
            fontSize = 13.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
        )
    }
}

// Optimized preview-stitching running instantly in background thread (no file I/O, optimized downsampling)
private fun stitchPhotosPreview(
    context: Context,
    photoPaths: List<String>,
    frame: Frame,
    filter: PhotoFilter
): Bitmap {
    val template = Bitmap.createBitmap(frame.width, frame.height, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(template)
    
    val bgColor = try {
        android.graphics.Color.parseColor(frame.backgroundColor)
    } catch (e: Exception) {
        android.graphics.Color.BLACK
    }
    canvas.drawColor(bgColor)
    
    val paint = Paint().apply { isAntiAlias = true; isFilterBitmap = true }
    
    when (filter) {
        PhotoFilter.NORMAL -> {}
        PhotoFilter.MONO -> {
            val cm = ColorMatrix().apply { setSaturation(0f) }
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.WARM -> {
            val cm = ColorMatrix(floatArrayOf(
                1.15f, 0f, 0f, 0f, 0f,
                0f, 1.05f, 0f, 0f, 0f,
                0f, 0f, 0.85f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.COOL -> {
            val cm = ColorMatrix(floatArrayOf(
                0.9f, 0f, 0f, 0f, 0f,
                0f, 1.0f, 0f, 0f, 0f,
                0f, 0f, 1.2f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
    }

    for (i in frame.slots.indices) {
        if (i >= photoPaths.size) break
        val slot = frame.slots[i]
        val photoFile = File(photoPaths[i])
        if (!photoFile.exists()) continue
        
        // Use inSampleSize to decode only what is necessary
        val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeFile(photoFile.absolutePath, options)
        val srcW = options.outWidth
        val srcH = options.outHeight
        
        var inSampleSize = 1
        val reqW = slot.width
        val reqH = slot.height
        if (srcH > reqH || srcW > reqW) {
            val halfHeight = srcH / 2
            val halfWidth = srcW / 2
            while (halfHeight / inSampleSize >= reqH && halfWidth / inSampleSize >= reqW) {
                inSampleSize *= 2
            }
        }
        
        val decodeOptions = BitmapFactory.Options().apply {
            inSampleSize = inSampleSize
        }
        val srcBmp = BitmapFactory.decodeFile(photoFile.absolutePath, decodeOptions)
        if (srcBmp != null) {
            val cropped = getCenterCroppedBitmap(srcBmp, slot.width, slot.height)
            val rectDest = Rect(slot.x, slot.y, slot.x + slot.width, slot.y + slot.height)
            canvas.drawBitmap(cropped, null, rectDest, paint)
            srcBmp.recycle()
            cropped.recycle()
        }
    }

    // Overlay frame PNG
    val frameFile = File(context.cacheDir, "frames/${frame.id}.png")
    if (frameFile.exists()) {
        val overlayBmp = BitmapFactory.decodeFile(frameFile.absolutePath)
        if (overlayBmp != null) {
            val destRect = Rect(0, 0, frame.width, frame.height)
            canvas.drawBitmap(overlayBmp, null, destRect, null)
            overlayBmp.recycle()
        }
    } else {
        paint.colorFilter = null
        paint.color = android.graphics.Color.WHITE
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = 6f
        for (slot in frame.slots) {
            canvas.drawRect(
                slot.x.toFloat(),
                slot.y.toFloat(),
                (slot.x + slot.width).toFloat(),
                (slot.y + slot.height).toFloat(),
                paint
            )
        }
        
        paint.style = Paint.Style.FILL
        paint.textSize = 36f
        paint.typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD)
        canvas.drawText("CREATIVE STUDIO", 150f, 1720f, paint)
    }

    return template
}

// Custom stitching logic running on Background Thread
private fun stitchPhotos(
    context: Context,
    photoPaths: List<String>,
    frame: Frame,
    filter: PhotoFilter,
    doodleLines: List<DoodleLine>,
    stickers: List<Sticker>
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
            val cm = ColorMatrix().apply { setSaturation(0f) }
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.WARM -> {
            // Boost red, reduce blue
            val cm = ColorMatrix(floatArrayOf(
                1.15f, 0f, 0f, 0f, 0f,
                0f, 1.05f, 0f, 0f, 0f,
                0f, 0f, 0.85f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
        PhotoFilter.COOL -> {
            // Boost blue, reduce red
            val cm = ColorMatrix(floatArrayOf(
                0.9f, 0f, 0f, 0f, 0f,
                0f, 1.0f, 0f, 0f, 0f,
                0f, 0f, 1.2f, 0f, 0f,
                0f, 0f, 0f, 1f, 0f
            ))
            paint.colorFilter = ColorMatrixColorFilter(cm)
        }
    }

    // Stitch each photo into its corresponding slot coordinates scaled up
    for (i in frame.slots.indices) {
        if (i >= photoPaths.size) break
        
        val slot = frame.slots[i]
        val photoFile = File(photoPaths[i])
        if (!photoFile.exists()) continue
        
        // Decode photo and crop to match scaled slot dimensions (Center-Crop)
        val srcBmp = BitmapFactory.decodeFile(photoFile.absolutePath)
        if (srcBmp != null) {
            val targetW = slot.width * multiplier
            val targetH = slot.height * multiplier
            val cropped = getCenterCroppedBitmap(srcBmp, targetW, targetH)
            
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

// Custom Center-Crop logic
private fun getCenterCroppedBitmap(src: Bitmap, targetW: Int, targetH: Int): Bitmap {
    val srcW = src.width
    val srcH = src.height
    
    val targetRatio = targetW.toFloat() / targetH.toFloat()
    val srcRatio = srcW.toFloat() / srcH.toFloat()
    
    var cropW = srcW
    var cropH = srcH
    var x = 0
    var y = 0
    
    if (srcRatio > targetRatio) {
        // Src is wider, crop horizontally
        cropW = (srcH * targetRatio).toInt()
        x = (srcW - cropW) / 2
    } else {
        // Src is taller, crop vertically
        cropH = (srcW / targetRatio).toInt()
        y = (srcH - cropH) / 2
    }
    
    val cropped = Bitmap.createBitmap(src, x, y, cropW, cropH)
    val scaled = Bitmap.createScaledBitmap(cropped, targetW, targetH, true)
    
    if (cropped != src) {
        cropped.recycle()
    }
    return scaled
}

@Composable
fun PreviewPhotoContainer(
    previewBitmap: Bitmap?,
    frameAspectRatio: Float,
    doodleLines: SnapshotStateList<DoodleLine>,
    activePenColor: Color,
    activeStrokeWidth: Float,
    activeTab: PreviewTab,
    stickers: SnapshotStateList<Sticker>,
    selectedStickerId: String?,
    onStickerSelected: (String?) -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .fillMaxHeight()
            .aspectRatio(frameAspectRatio)
            .border(2.dp, Color(0xFFE63946), RoundedCornerShape(12.dp))
            .clip(RoundedCornerShape(12.dp))
            .background(Color.Black),
        contentAlignment = Alignment.Center
    ) {
        if (previewBitmap != null) {
            Image(
                bitmap = previewBitmap.asImageBitmap(),
                contentDescription = "Preview Strip",
                modifier = Modifier.fillMaxSize()
            )
        }
        
        // Doodle canvas
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
        
        // Sticker overlay
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
        modifier = modifier.then(tapModifier)
    ) {
        val containerWidth = maxWidth
        val containerHeight = maxHeight
        val containerWidthPx = with(density) { containerWidth.toPx() }
        val containerHeightPx = with(density) { containerHeight.toPx() }

        stickers.forEach { sticker ->
            val isSelected = sticker.id == selectedStickerId
            val currentStickerState = rememberUpdatedState(sticker)

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
                                .border(1.5.dp, Color(0xFFE63946), RoundedCornerShape(8.dp))
                        )
                    }

                    Text(
                        text = sticker.emoji,
                        fontSize = 40.sp,
                        textAlign = TextAlign.Center
                    )
                }

                if (isSelected && enabled) {
                    IconButton(
                        onClick = { onStickerDelete(sticker.id) },
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .offset(x = 6.dp, y = (-6).dp)
                            .size(24.dp)
                            .background(Color(0xFFE63946), CircleShape)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Hapus Stiker",
                            tint = Color.White,
                            modifier = Modifier.size(12.dp)
                        )
                    }
                }
            }
        }
    }
}

val emojiList = listOf(
    "😎", "❤️", "✨", "👑", "🌟", "🎈", "🎉", "🎀", "🍕", "🧁",
    "📸", "🎧", "🧸", "🐱", "🐶", "🌸", "⚡", "🍀", "🎃", "👻", "👽"
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
            .background(Color(0xFF18181F), RoundedCornerShape(12.dp))
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
                    .background(if (isSelected) Color(0xFFE63946) else Color.Transparent)
                    .clickable { onTabSelected(tab) }
                    .padding(vertical = 8.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(imageVector = icon, contentDescription = title, tint = Color.White, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(text = title, color = Color.White, fontSize = 10.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal)
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
            .background(Color(0xFF0F0F12), RoundedCornerShape(16.dp))
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
                    .background(if (isSelected) Color(0xFFE63946) else Color.Transparent)
                    .clickable { onTabSelected(tab) }
                    .padding(vertical = 12.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(imageVector = icon, contentDescription = title, tint = Color.White, modifier = Modifier.size(20.dp))
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(text = title, color = Color.White, fontSize = 9.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal, textAlign = TextAlign.Center)
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
            color = Color.White,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold
        )
        
        LazyVerticalGrid(
            columns = GridCells.Fixed(3),
            modifier = Modifier.fillMaxWidth().weight(1f),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
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
            color = Color.White,
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
            color = Color.White,
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
                        .background(Color(0xFF2A2A35))
                        .clickable { onAddSticker(emoji) },
                    contentAlignment = Alignment.Center
                ) {
                    Text(text = emoji, fontSize = 28.sp)
                }
            }
        }
        
        Text(
            text = "Tips: Geser dengan 1 jari untuk memindahkan. Gunakan 2 jari (cubit) untuk memutar atau memperbesar.",
            color = Color.Gray,
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
                    .background(Color(0xFF2A2A35), CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Undo,
                    contentDescription = "Undo",
                    tint = if (doodleLines.isNotEmpty()) Color.White else Color.DarkGray
                )
            }
            
            IconButton(
                onClick = { doodleLines.clear() },
                enabled = doodleLines.isNotEmpty(),
                modifier = Modifier
                    .size(44.dp)
                    .background(Color(0xFF2A2A35), CircleShape)
            ) {
                Icon(
                    imageVector = Icons.Default.Delete,
                    contentDescription = "Clear All",
                    tint = if (doodleLines.isNotEmpty()) Color(0xFFE63946) else Color.DarkGray
                )
            }
        }
        
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(Color(0xFF2A2A35)))
        
        // Middle part: Stroke widths
        Column(
            modifier = Modifier.fillMaxHeight(),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text("PENA", color = Color.Gray, fontSize = 8.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(3f, 6f, 10f).forEach { size ->
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(if (activeStrokeWidth == size) Color(0xFFE63946).copy(alpha = 0.3f) else Color.Transparent)
                            .clickable { onStrokeWidthSelected(size) },
                        contentAlignment = Alignment.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .size((size * 1.2f).dp)
                                .clip(CircleShape)
                                .background(Color.White)
                        )
                    }
                }
            }
        }
        
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(Color(0xFF2A2A35)))
        
        // Right part: Colors
        Column(
            modifier = Modifier.weight(1f).fillMaxHeight(),
            verticalArrangement = Arrangement.Center
        ) {
            Text("WARNA", color = Color.Gray, fontSize = 8.sp, fontWeight = FontWeight.Bold)
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
                                color = if (activePenColor == color) Color.White else Color(0xFF2A2A35),
                                shape = CircleShape
                            )
                            .clickable { onColorSelected(color) }
                    )
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
    val parsedColor = remember(frame.backgroundColor) {
        try {
            Color(android.graphics.Color.parseColor(frame.backgroundColor))
        } catch (e: Exception) {
            Color.DarkGray
        }
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .aspectRatio(0.67f)
            .clip(RoundedCornerShape(12.dp))
            .background(parsedColor)
            .border(
                width = if (isSelected) 3.dp else 0.dp,
                color = if (isSelected) Color(0xFFE63946) else Color.Transparent,
                shape = RoundedCornerShape(12.dp)
            )
            .clickable { onClick() },
        contentAlignment = Alignment.Center
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(4.dp)
        ) {
            val frameWidth = frame.width.coerceAtLeast(1).toFloat()
            val frameHeight = frame.height.coerceAtLeast(1).toFloat()
            
            BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
                val previewWidth = maxWidth
                val previewHeight = maxHeight
                
                frame.slots.forEach { slot ->
                    val slotLeft = (slot.x.toFloat() / frameWidth * previewWidth.value).dp
                    val slotTop = (slot.y.toFloat() / frameHeight * previewHeight.value).dp
                    val slotWidth = (slot.width.toFloat() / frameWidth * previewWidth.value).dp
                    val slotHeight = (slot.height.toFloat() / frameHeight * previewHeight.value).dp
                    
                    Box(
                        modifier = Modifier
                            .offset(x = slotLeft, y = slotTop)
                            .size(slotWidth, slotHeight)
                            .background(Color.White.copy(alpha = 0.2f))
                    )
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
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .background(Color.Black.copy(alpha = 0.6f))
                .padding(vertical = 2.dp)
        ) {
            Text(
                text = frame.name,
                color = Color.White,
                fontSize = 8.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
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
            border = BorderStroke(1.dp, Color(0xFF2A2A35)),
            shape = RoundedCornerShape(16.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
            modifier = Modifier
                .weight(1f)
                .height(56.dp),
            enabled = !isProcessingConfirm
        ) {
            Icon(imageVector = Icons.Default.Refresh, contentDescription = "Retake")
            Spacer(modifier = Modifier.width(8.dp))
            Text("Ulangi Foto")
        }

        // Confirm and print/share Button
        Button(
            onClick = onConfirmClick,
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier
                .weight(1.5f)
                .height(56.dp),
            enabled = !isProcessingConfirm
        ) {
            if (isProcessingConfirm) {
                CircularProgressIndicator(color = Color.White, modifier = Modifier.size(24.dp))
            } else {
                Icon(imageVector = Icons.Default.Check, contentDescription = "Confirm")
                Spacer(modifier = Modifier.width(8.dp))
                Text("Cetak & Download", fontWeight = FontWeight.Bold)
            }
        }
    }
}
