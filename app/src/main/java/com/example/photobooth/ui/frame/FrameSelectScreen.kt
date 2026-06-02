package com.example.photobooth.ui.frame

import android.content.Context
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.data.FrameConfig
import com.example.photobooth.data.Slot
import com.google.gson.Gson
import java.io.File

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FrameSelectScreen(
    layoutType: String,
    onBackClick: () -> Unit,
    onFrameSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val configManager = remember { ConfigManager(context) }
    
    // Load synced frames or get fallbacks
    val frames = remember(layoutType) {
        getFramesForLayout(context, layoutType, configManager)
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PILIH TEMPLATE BINGKAI", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = Color(0xFF0F0F12),
                    titleContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFF0F0F12),
        modifier = modifier.fillMaxSize()
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "Pilih desain bingkai untuk foto Anda",
                color = Color.Gray,
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 32.dp)
            )

            if (frames.isEmpty()) {
                Box(
                    modifier = Modifier.weight(1f),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Belum ada bingkai yang tersedia.\nHarap sinkronkan bingkai di Menu Admin.",
                        color = Color.Gray,
                        textAlign = TextAlign.Center,
                        fontSize = 15.sp
                    )
                }
            } else {
                LazyVerticalGrid(
                    columns = GridCells.Fixed(2),
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                    modifier = Modifier.weight(1f)
                ) {
                    items(frames) { frame ->
                        FrameCard(
                            frame = frame,
                            onClick = { onFrameSelected(frame.id) }
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun FrameCard(
    frame: Frame,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val frameFile = remember(frame.id) { File(context.cacheDir, "frames/${frame.id}.png") }
    
    // Parse hex color safely
    val parsedColor = remember(frame.backgroundColor) {
        try {
            Color(android.graphics.Color.parseColor(frame.backgroundColor))
        } catch (e: Exception) {
            Color.DarkGray
        }
    }

    Card(
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
        modifier = modifier
            .fillMaxWidth()
            .height(280.dp)
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            // Preview box
            Box(
                modifier = Modifier
                    .weight(1f)
                    .width(90.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(parsedColor)
                    .border(1.dp, Color.White.copy(alpha = 0.1f), RoundedCornerShape(8.dp)),
                contentAlignment = Alignment.Center
            ) {
                if (frameFile.exists()) {
                    AsyncImage(
                        model = frameFile,
                        contentDescription = frame.name,
                        modifier = Modifier.fillMaxSize()
                    )
                } else {
                    // Draw slot place holders procedurally if file is missing
                    Column(
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(6.dp)
                    ) {
                        repeat(frame.slots.size) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .weight(1f)
                                    .background(Color.White.copy(alpha = 0.15f))
                            )
                        }
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = frame.name,
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
        }
    }
}

// Logic to load frames from synced configurations or load fallbacks
fun getFramesForLayout(context: Context, layoutType: String, configManager: ConfigManager): List<Frame> {
    val syncedJson = configManager.syncedFramesJson
    val framesList = mutableListOf<Frame>()
    
    if (syncedJson.isNotEmpty()) {
        try {
            val config = Gson().fromJson(syncedJson, FrameConfig::class.java)
            framesList.addAll(config.frames.filter { it.type.equals(layoutType, ignoreCase = true) })
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
    
    // Add default templates as fallback if empty
    if (framesList.isEmpty()) {
        val stripSlots = listOf(
            Slot(0, 50, 50, 500, 375),
            Slot(1, 50, 455, 500, 375),
            Slot(2, 50, 860, 500, 375),
            Slot(3, 50, 1265, 500, 375)
        )
        
        if (layoutType.equals("strip", ignoreCase = true)) {
            framesList.add(
                Frame(
                    id = "classic_strip_black",
                    name = "Classic Black",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#121212",
                    imageUrl = "frames/classic_strip_black.png",
                    slots = stripSlots
                )
            )
            framesList.add(
                Frame(
                    id = "creative_strip_red",
                    name = "Creative Red",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#e63946",
                    imageUrl = "frames/creative_strip_red.png",
                    slots = stripSlots
                )
            )
        } else if (layoutType.equals("grid", ignoreCase = true)) {
            // Default 2x2 grid fallback
            val gridSlots = listOf(
                Slot(0, 50, 50, 440, 330),
                Slot(1, 510, 50, 440, 330),
                Slot(2, 50, 400, 440, 330),
                Slot(3, 510, 400, 440, 330)
            )
            framesList.add(
                Frame(
                    id = "grid_black",
                    name = "Grid Classic Black",
                    type = "grid",
                    width = 1000,
                    height = 800,
                    backgroundColor = "#121212",
                    imageUrl = "frames/grid_black.png",
                    slots = gridSlots
                )
            )
        }
    }
    
    return framesList
}
