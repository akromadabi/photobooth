package com.example.photobooth.ui.frame

import android.content.Context
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import android.content.SharedPreferences
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.api.CharacterDto
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.Frame
import com.example.photobooth.data.FrameConfig
import com.example.photobooth.data.Slot
import com.example.photobooth.theme.AppTheme
import com.example.photobooth.theme.AppThemeType
import com.example.photobooth.ui.character.CharacterCard
import com.google.gson.Gson
import java.io.File

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FrameSelectScreen(
    packageId: String,
    eventId: String,
    onBackClick: () -> Unit,
    onFrameSelected: (frameId: String, characterId: String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val configManager = remember { ConfigManager(context) }
    
    val configuration = LocalConfiguration.current
    val isPortrait = configuration.orientation == android.content.res.Configuration.ORIENTATION_PORTRAIT
    
    val prefs = remember { context.getSharedPreferences("photobooth_prefs", Context.MODE_PRIVATE) }
    var syncedJsonState by remember { mutableStateOf(prefs.getString("synced_frames_json", "") ?: "") }
    DisposableEffect(prefs) {
        val listener = SharedPreferences.OnSharedPreferenceChangeListener { p, key ->
            if (key == "synced_frames_json") {
                syncedJsonState = p.getString("synced_frames_json", "") ?: ""
            }
        }
        prefs.registerOnSharedPreferenceChangeListener(listener)
        onDispose {
            prefs.unregisterOnSharedPreferenceChangeListener(listener)
        }
    }

    // Resolve print flow from packageId
    val api = remember { NetworkClient.getApi(configManager.backendUrl) }
    var printFlow by remember { mutableStateOf<String?>(null) }
    
    LaunchedEffect(packageId, configManager.backendUrl) {
        if (packageId.isNotEmpty() && configManager.backendUrl.isNotEmpty()) {
            try {
                val response = api.getPackages()
                if (response.isSuccessful && response.body() != null) {
                    val pkg = response.body()!!.find { it.id == packageId }
                    printFlow = pkg?.printFlow
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    // Fetch AI Characters if color printing is selected
    var aiCharacters by remember { mutableStateOf<List<CharacterDto>>(emptyList()) }
    LaunchedEffect(printFlow, configManager.backendUrl) {
        if (printFlow == "COLOR_PRINT" && configManager.backendUrl.isNotEmpty()) {
            try {
                val response = api.getCharacters()
                if (response.isSuccessful && response.body() != null) {
                    aiCharacters = response.body()!!
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    // Load all event frames
    val allFrames = remember(eventId, syncedJsonState) {
        getAllEventFrames(context, configManager, eventId)
    }

    // Filter frames that support printFlow and resolve category
    val resolvedFrames = remember(allFrames, printFlow) {
        val compatible = allFrames.filter { f ->
            val pf = f.printFlows ?: emptyList()
            if (pf.isEmpty()) {
                // Fallback legacy frames
                if (printFlow == "RECEIPT") {
                    f.type.equals("strip", ignoreCase = true)
                } else if (printFlow == "ID_CARD") {
                    f.type.equals("idcard", ignoreCase = true)
                } else {
                    true // Color supports everything by default
                }
            } else {
                if (printFlow != null) pf.contains(printFlow) else true
            }
        }
        
        compatible.map {
            val cat = when {
                !it.category.isNullOrEmpty() -> it.category
                it.id.contains("classic", ignoreCase = true) || it.id.contains("black", ignoreCase = true) || it.id.contains("grid", ignoreCase = true) -> "Classic"
                it.id.contains("creative", ignoreCase = true) || it.id.contains("red", ignoreCase = true) || it.id.contains("blue", ignoreCase = true) || it.id.contains("pink", ignoreCase = true) -> "Creative"
                it.id.contains("aesthetic", ignoreCase = true) || it.id.contains("seoul", ignoreCase = true) -> "Aesthetic"
                it.id.contains("love", ignoreCase = true) || it.id.contains("cyber", ignoreCase = true) || it.id.contains("neon", ignoreCase = true) -> "Y2K"
                it.id.contains("magazine", ignoreCase = true) || it.id.contains("travel", ignoreCase = true) || it.id.contains("manga", ignoreCase = true) || it.id.contains("sports", ignoreCase = true) || it.id.contains("music", ignoreCase = true) || it.id.contains("gaming", ignoreCase = true) -> "Magazine"
                it.id.contains("receipt", ignoreCase = true) || it.id.contains("ticket", ignoreCase = true) || it.id.contains("slip", ignoreCase = true) || it.id.contains("prescription", ignoreCase = true) || it.id.contains("bank", ignoreCase = true) || it.id.contains("cinema", ignoreCase = true) || it.id.contains("coffee", ignoreCase = true) || it.id.contains("clinic", ignoreCase = true) || it.id.contains("supermarket", ignoreCase = true) -> "Receipt"
                it.isDynamic == true -> "Dynamic"
                else -> "Classic"
            }
            it.copy(category = cat)
        }
    }

    val categories = remember(resolvedFrames, aiCharacters) {
        val cats = resolvedFrames.map { it.category ?: "Classic" }.distinct().sorted().toMutableList()
        if (aiCharacters.isNotEmpty()) {
            cats.add("AI Karakter")
        }
        if (cats.size > 1) {
            cats.add(0, "Semua")
        }
        cats.toList()
    }

    var selectedCategory by remember(categories) {
        mutableStateOf(if (categories.contains("Semua")) "Semua" else categories.firstOrNull() ?: "")
    }

    val columns = remember(selectedCategory, isPortrait) {
        if (isPortrait) GridCells.Fixed(2) else GridCells.Fixed(4)
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PILIH TEMPLATE BINGKAI", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back", tint = MaterialTheme.colorScheme.onBackground)
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background,
                    titleContentColor = MaterialTheme.colorScheme.onBackground
                )
            )
        },
        containerColor = MaterialTheme.colorScheme.background,
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
                color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 20.dp)
            )

            if (categories.size > 1) {
                androidx.compose.foundation.lazy.LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    contentPadding = PaddingValues(horizontal = 4.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 24.dp)
                ) {
                    items(categories) { category ->
                        val isSelected = category == selectedCategory
                        val containerColor = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
                        val contentColor = if (isSelected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurfaceVariant
                        
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(20.dp))
                                .background(containerColor)
                                .clickable { selectedCategory = category }
                                .padding(horizontal = 16.dp, vertical = 8.dp)
                        ) {
                            Text(
                                text = category,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = contentColor
                            )
                        }
                    }
                }
            }

            if (selectedCategory == "AI Karakter") {
                LazyVerticalGrid(
                    columns = columns,
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                    modifier = Modifier.weight(1f)
                ) {
                    items(aiCharacters) { character ->
                        CharacterCard(
                            character = character,
                            onClick = { onFrameSelected("postcard_black", character.id) }
                        )
                    }
                }
            } else {
                val filteredFrames = remember(resolvedFrames, selectedCategory) {
                    if (selectedCategory == "Semua" || selectedCategory.isEmpty()) {
                        resolvedFrames
                    } else {
                        resolvedFrames.filter { it.category == selectedCategory }
                    }
                }
                
                if (filteredFrames.isEmpty()) {
                    Box(
                        modifier = Modifier.weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "Belum ada bingkai dalam kategori ini.",
                            color = Color.Gray,
                            textAlign = TextAlign.Center,
                            fontSize = 15.sp
                        )
                    }
                } else {
                    LazyVerticalGrid(
                        columns = columns,
                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        modifier = Modifier.weight(1f)
                    ) {
                        items(filteredFrames) { frame ->
                            FrameCard(
                                frame = frame,
                                onClick = { onFrameSelected(frame.id, "") }
                            )
                        }
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
    
    val parsedColor = remember(frame.backgroundColor) {
        try {
            Color(android.graphics.Color.parseColor(frame.backgroundColor))
        } catch (e: Exception) {
            Color.DarkGray
        }
    }

    val isStrip = remember(frame.type) { frame.type.equals("strip", ignoreCase = true) }
    val cardAspectRatio = if (isStrip) 0.5f else 1.2f
    val padding = if (isStrip) 8.dp else 16.dp

    val isCutePastel = AppTheme.type == AppThemeType.CUTE_PASTEL
    Card(
        shape = RoundedCornerShape(if (isCutePastel) 12.dp else 20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = null,
        modifier = modifier
            .fillMaxWidth()
            .aspectRatio(cardAspectRatio)
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            BoxWithConstraints(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .padding(horizontal = padding),
                contentAlignment = Alignment.Center
            ) {
                val frameWidth = frame.width.coerceAtLeast(1).toFloat()
                val frameHeight = frame.height.coerceAtLeast(1).toFloat()
                val frameAspectRatio = frameWidth / frameHeight
                
                val constraintWidth = maxWidth
                val constraintHeight = maxHeight
                
                val previewWidth: androidx.compose.ui.unit.Dp
                val previewHeight: androidx.compose.ui.unit.Dp
                
                if (constraintWidth.value / frameAspectRatio <= constraintHeight.value) {
                    previewWidth = constraintWidth
                    previewHeight = constraintWidth / frameAspectRatio
                } else {
                    previewWidth = constraintHeight * frameAspectRatio
                    previewHeight = constraintHeight
                }
                
                Box(
                    modifier = Modifier
                        .size(previewWidth, previewHeight)
                        .shadow(elevation = 6.dp, shape = RoundedCornerShape(8.dp))
                        .clip(RoundedCornerShape(8.dp))
                        .background(parsedColor)
                ) {
                    frame.slots.forEach { slot ->
                        val slotLeft = (slot.x.toFloat() / frameWidth * previewWidth.value).dp
                        val slotTop = (slot.y.toFloat() / frameHeight * previewHeight.value).dp
                        val slotWidth = (slot.width.toFloat() / frameWidth * previewWidth.value).dp
                        val slotHeight = (slot.height.toFloat() / frameHeight * previewHeight.value).dp
                        
                        Box(
                            modifier = Modifier
                                .offset(x = slotLeft, y = slotTop)
                                .size(slotWidth, slotHeight)
                                .background(MaterialTheme.colorScheme.onSurface.copy(alpha = 0.05f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = "👤",
                                fontSize = (slotHeight.value * 0.45f).sp,
                                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.25f),
                                textAlign = TextAlign.Center
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
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = frame.name,
                color = MaterialTheme.colorScheme.onSurface,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
        }
    }
}

fun getAllEventFrames(context: Context, configManager: ConfigManager, eventId: String = "general"): List<Frame> {
    val syncedJson = configManager.syncedFramesJson
    val framesList = mutableListOf<Frame>()
    
    if (syncedJson.isNotEmpty()) {
        try {
            val config = Gson().fromJson(syncedJson, FrameConfig::class.java)
            val filteredByEvent = if (eventId.isNotEmpty() && eventId != "general") {
                config.frames.filter { it.eventId == eventId }
            } else {
                config.frames.filter { it.eventId == "general" || it.eventId.isNullOrEmpty() }
            }
            
            if (filteredByEvent.isEmpty() && eventId != "general") {
                framesList.addAll(config.frames.filter { it.eventId == "general" || it.eventId.isNullOrEmpty() })
            } else {
                framesList.addAll(filteredByEvent)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
    
    if (framesList.isEmpty()) {
        // Fallbacks
        framesList.addAll(getFramesForLayout(context, "strip", configManager, eventId))
        framesList.addAll(getFramesForLayout(context, "grid", configManager, eventId))
        framesList.addAll(getFramesForLayout(context, "postcard", configManager, eventId))
    }
    
    return framesList
}

fun getFramesForLayout(context: Context, layoutType: String, configManager: ConfigManager, eventId: String = "general"): List<Frame> {
    val syncedJson = configManager.syncedFramesJson
    val framesList = mutableListOf<Frame>()
    
    if (syncedJson.isNotEmpty()) {
        try {
            val config = Gson().fromJson(syncedJson, FrameConfig::class.java)
            val matchingTypeFrames = config.frames.filter { it.type.equals(layoutType, ignoreCase = true) }
            val filteredByEvent = if (eventId.isNotEmpty() && eventId != "general") {
                matchingTypeFrames.filter { it.eventId == eventId }
            } else {
                matchingTypeFrames.filter { it.eventId == "general" || it.eventId.isNullOrEmpty() }
            }
            
            if (filteredByEvent.isEmpty() && eventId != "general") {
                framesList.addAll(matchingTypeFrames.filter { it.eventId == "general" || it.eventId.isNullOrEmpty() })
            } else {
                framesList.addAll(filteredByEvent)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
    
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
            framesList.add(
                Frame(
                    id = "creative_strip_blue",
                    name = "Modern Blue",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#1d3557",
                    imageUrl = "frames/creative_strip_blue.png",
                    slots = stripSlots
                )
            )
            framesList.add(
                Frame(
                    id = "creative_strip_pink",
                    name = "Sweet Pink",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#ffb7b2",
                    imageUrl = "frames/creative_strip_pink.png",
                    slots = stripSlots
                )
            )
            framesList.add(
                Frame(
                    id = "seoul_aesthetic",
                    name = "Seoul Aesthetic",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#f5f2eb",
                    imageUrl = "frames/seoul_aesthetic.png",
                    slots = stripSlots
                )
            )
            framesList.add(
                Frame(
                    id = "love_factory",
                    name = "Love Factory Y2K",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#18181b",
                    imageUrl = "frames/love_factory.png",
                    slots = stripSlots
                )
            )
            framesList.add(
                Frame(
                    id = "cyber_neon",
                    name = "Cyber Neon Y2K",
                    type = "strip",
                    width = 600,
                    height = 2000,
                    backgroundColor = "#0a0b10",
                    imageUrl = "frames/cyber_neon.png",
                    slots = stripSlots
                )
            )
        }
    }
    
    return framesList
}
