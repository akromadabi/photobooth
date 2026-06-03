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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Undo
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PreviewResultScreen(
    photoPaths: List<String>,
    frameId: String,
    onRetakeClick: () -> Unit,
    onConfirmClick: (String, Boolean) -> Unit, // finalPath, shouldPrint
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    
    // Resolve frame configuration
    val frame = remember(frameId) {
        val allFrames = getFramesForLayout(context, "strip", configManager) + getFramesForLayout(context, "grid", configManager)
        allFrames.firstOrNull { it.id == frameId } ?: allFrames.first()
    }

    var selectedFilter by remember { mutableStateOf(PhotoFilter.NORMAL) }
    var stitchedPhotoPath by remember { mutableStateOf("") }
    var isStitching by remember { mutableStateOf(true) }
    
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

    // Re-stitch when filter changes
    LaunchedEffect(selectedFilter) {
        isStitching = true
        withContext(Dispatchers.Default) {
            val outputPath = stitchPhotos(context, photoPaths, frame, selectedFilter, emptyList())
            withContext(Dispatchers.Main) {
                stitchedPhotoPath = outputPath
                isStitching = false
            }
        }
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PRATINJAU & TANDA TANGAN", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = Color(0xFF0F0F12),
                    titleContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFF0F0F12),
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        if (isStitching) {
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
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .padding(16.dp),
                verticalArrangement = Arrangement.SpaceBetween,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                
                // Photo Strip & Doodle canvas split row
                Row(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth()
                        .padding(bottom = 12.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Left: The Photo Strip scrollable preview with Doodle Overlay
                    Box(
                        modifier = Modifier
                            .fillMaxHeight()
                            .width(180.dp)
                            .border(2.dp, Color(0xFFE63946), RoundedCornerShape(12.dp))
                            .clip(RoundedCornerShape(12.dp))
                            .background(Color.Black)
                    ) {
                        AsyncImage(
                            model = File(stitchedPhotoPath),
                            contentDescription = "Preview Strip",
                            modifier = Modifier.fillMaxSize()
                        )
                        
                        // Hand drawings layer
                        DoodleCanvas(
                            lines = doodleLines,
                            onLinesChanged = { newList ->
                                doodleLines.clear()
                                doodleLines.addAll(newList)
                            },
                            activeColor = activePenColor,
                            activeStrokeWidth = activeStrokeWidth,
                            modifier = Modifier.fillMaxSize()
                        )
                    }

                    Spacer(modifier = Modifier.width(16.dp))

                    // Right: Floating Doodle Controls Card
                    Card(
                        modifier = Modifier
                            .width(80.dp)
                            .fillMaxHeight()
                            .padding(vertical = 8.dp),
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
                        border = BorderStroke(1.dp, Color(0xFF2A2A35))
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxSize()
                                .padding(vertical = 12.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.SpaceBetween
                        ) {
                            // Undo button
                            IconButton(
                                onClick = {
                                    if (doodleLines.isNotEmpty()) {
                                        doodleLines.removeAt(doodleLines.size - 1)
                                    }
                                },
                                enabled = doodleLines.isNotEmpty()
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Undo,
                                    contentDescription = "Undo",
                                    tint = if (doodleLines.isNotEmpty()) Color.White else Color.DarkGray
                                )
                            }

                            // Delete/Clear button
                            IconButton(
                                onClick = { doodleLines.clear() },
                                enabled = doodleLines.isNotEmpty()
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Delete,
                                    contentDescription = "Clear All",
                                    tint = if (doodleLines.isNotEmpty()) Color(0xFFE63946) else Color.DarkGray
                                )
                            }

                            HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(horizontal = 8.dp))

                            // Stroke weight selector
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("PENA", color = Color.Gray, fontSize = 8.sp, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(4.dp))
                                listOf(3f, 6f, 10f).forEach { size ->
                                    Box(
                                        modifier = Modifier
                                            .size(24.dp)
                                            .clip(CircleShape)
                                            .background(if (activeStrokeWidth == size) Color(0xFFE63946).copy(alpha = 0.3f) else Color.Transparent)
                                            .clickable { activeStrokeWidth = size },
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .size((size * 1.2f).dp)
                                                .clip(CircleShape)
                                                .background(Color.White)
                                        )
                                    }
                                    Spacer(modifier = Modifier.height(8.dp))
                                }
                            }

                            HorizontalDivider(color = Color(0xFF2A2A35), modifier = Modifier.padding(horizontal = 8.dp))

                            // Color palettes
                            Column(
                                verticalArrangement = Arrangement.spacedBy(8.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                penColors.forEach { color ->
                                    Box(
                                        modifier = Modifier
                                            .size(24.dp)
                                            .clip(CircleShape)
                                            .background(color)
                                            .border(
                                                width = if (activePenColor == color) 2.dp else 1.dp,
                                                color = if (activePenColor == color) Color.White else Color(0xFF2A2A35),
                                                shape = CircleShape
                                            )
                                            .clickable { activePenColor = color }
                                    )
                                }
                            }
                        }
                    }
                }

                // Filter Selector Section
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text(
                        text = "Pilih Filter Estetik:",
                        color = Color.White,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(start = 4.dp)
                    )

                    LazyRow(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        items(PhotoFilter.values()) { filter ->
                            FilterItem(
                                filter = filter,
                                isSelected = filter == selectedFilter,
                                onClick = { selectedFilter = filter }
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Bottom Action buttons
                Row(
                    modifier = Modifier.fillMaxWidth(),
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
                        Text("Retake")
                    }

                    // Confirm and print/share Button with overlay baking
                    Button(
                        onClick = {
                            isProcessingConfirm = true
                            scope.launch {
                                // Bake drawings into the high-res bitmap
                                val finalPath = withContext(Dispatchers.Default) {
                                    stitchPhotos(context, photoPaths, frame, selectedFilter, doodleLines.toList())
                                }
                                isProcessingConfirm = false
                                val shouldPrint = configManager.printerType != "NONE"
                                onConfirmClick(finalPath, shouldPrint)
                            }
                        },
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

// Custom stitching logic running on Background Thread
private fun stitchPhotos(
    context: Context,
    photoPaths: List<String>,
    frame: Frame,
    filter: PhotoFilter,
    doodleLines: List<DoodleLine>
): String {
    // Create base template Bitmap
    val template = Bitmap.createBitmap(frame.width, frame.height, Bitmap.Config.ARGB_8888)
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

    // Stitch each photo into its corresponding slot coordinates
    for (i in frame.slots.indices) {
        if (i >= photoPaths.size) break
        
        val slot = frame.slots[i]
        val photoFile = File(photoPaths[i])
        if (!photoFile.exists()) continue
        
        // Decode photo and crop to match slot dimensions (Center-Crop)
        val srcBmp = BitmapFactory.decodeFile(photoFile.absolutePath)
        if (srcBmp != null) {
            val cropped = getCenterCroppedBitmap(srcBmp, slot.width, slot.height)
            
            // Draw photo to canvas
            val rectDest = Rect(slot.x, slot.y, slot.x + slot.width, slot.y + slot.height)
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
            canvas.drawBitmap(overlayBmp, 0f, 0f, null)
            overlayBmp.recycle()
        }
    } else {
        // Procedural fallbacks: draw border strokes around slot rectangles
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
        
        // Procedural text
        paint.style = Paint.Style.FILL
        paint.textSize = 36f
        paint.typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD)
        canvas.drawText("CREATIVE STUDIO", 150f, 1720f, paint)
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
            val scaleFactor = frame.width.toFloat() / 180f
            
            if (line.points.size == 1) {
                val p = line.points[0]
                doodlePaint.style = Paint.Style.FILL
                val x = p.x * frame.width
                val y = p.y * frame.height
                val radius = (p.strokeWidth / 2f) * scaleFactor
                canvas.drawCircle(x, y, radius, doodlePaint)
            } else if (line.points.size > 1) {
                doodlePaint.style = Paint.Style.STROKE
                for (j in 1 until line.points.size) {
                    val p1 = line.points[j - 1]
                    val p2 = line.points[j]
                    
                    val x1 = p1.x * frame.width
                    val y1 = p1.y * frame.height
                    val x2 = p2.x * frame.width
                    val y2 = p2.y * frame.height
                    
                    val segmentWidth = ((p1.strokeWidth + p2.strokeWidth) / 2f) * scaleFactor
                    doodlePaint.strokeWidth = segmentWidth
                    
                    canvas.drawLine(x1, y1, x2, y2, doodlePaint)
                }
            }
        }
    }

    // Save final composite strip
    val outputFile = File(context.cacheDir, "final_stitched_strip.png")
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
