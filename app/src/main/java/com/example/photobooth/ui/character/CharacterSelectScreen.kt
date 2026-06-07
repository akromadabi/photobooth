package com.example.photobooth.ui.character

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.photobooth.api.CharacterDto
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CharacterSelectScreen(
    eventId: String,
    onBackClick: () -> Unit,
    onCharacterSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val configManager = remember { ConfigManager(context) }
    
    var isLoading by remember { mutableStateOf(true) }
    var errorMsg by remember { mutableStateOf<String?>(null) }
    var characters by remember { mutableStateOf<List<CharacterDto>>(emptyList()) }
    var reloadTrigger by remember { mutableIntStateOf(0) }

    // Load characters asynchronously
    LaunchedEffect(reloadTrigger) {
        isLoading = true
        errorMsg = null
        val api = NetworkClient.getApi(configManager.backendUrl)
        try {
            val response = api.getCharacters()
            if (response.isSuccessful && response.body() != null) {
                characters = response.body()!!
            } else {
                errorMsg = "Gagal memuat katalog karakter dari server."
            }
        } catch (e: Exception) {
            errorMsg = "Koneksi Gagal: ${e.localizedMessage}"
        } finally {
            isLoading = false
        }
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PILIH KARAKTER AI", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
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
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(24.dp),
            contentAlignment = Alignment.Center
        ) {
            if (isLoading) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    CircularProgressIndicator(color = Color(0xFFE63946))
                    Text("Memuat katalog karakter...", color = Color.Gray, fontSize = 14.sp)
                }
            } else if (errorMsg != null) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                    modifier = Modifier.width(320.dp)
                ) {
                    Text("Terjadi Gangguan", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    Text(text = errorMsg!!, color = Color.LightGray, textAlign = TextAlign.Center, fontSize = 14.sp)
                    Spacer(modifier = Modifier.height(8.dp))
                    Button(
                        onClick = { reloadTrigger++ },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946))
                    ) {
                        Text("Coba Lagi")
                    }
                }
            } else if (characters.isEmpty()) {
                Text("Tidak ada karakter yang terdaftar saat ini.", color = Color.Gray, fontSize = 14.sp)
            } else {
                Column(
                    modifier = Modifier.fillMaxSize(),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = "Pilih salah satu karakter di bawah. Wajah Anda akan digabungkan secara otomatis.",
                        color = Color.Gray,
                        fontSize = 14.sp,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp)
                    )

                    LazyVerticalGrid(
                        columns = GridCells.Fixed(2),
                        horizontalArrangement = Arrangement.spacedBy(20.dp),
                        verticalArrangement = Arrangement.spacedBy(20.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        items(characters) { character ->
                            CharacterCard(
                                character = character,
                                onClick = { onCharacterSelected(character.id) }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun CharacterCard(
    character: CharacterDto,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF18181F)),
        border = BorderStroke(1.dp, Color(0xFF2A2A35)),
        modifier = modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            AsyncImage(
                model = character.templateUrl,
                contentDescription = character.name,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(240.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color.Black.copy(alpha = 0.1f))
            )

            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Text(
                    text = character.name,
                    color = Color.White,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = character.description,
                    color = Color.Gray,
                    fontSize = 11.sp,
                    lineHeight = 15.sp,
                    maxLines = 3
                )
            }
        }
    }
}
