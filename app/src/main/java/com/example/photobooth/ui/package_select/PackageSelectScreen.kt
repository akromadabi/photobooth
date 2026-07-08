package com.example.photobooth.ui.package_select

import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.api.PackageDto
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.theme.AppTheme
import com.example.photobooth.theme.AppThemeType
import kotlinx.coroutines.launch
import java.text.NumberFormat
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PackageSelectScreen(
    onBackClick: () -> Unit,
    onPackageSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configManager = remember { ConfigManager(context) }
    
    var isLoading by remember { mutableStateOf(true) }
    var packagesList by remember { mutableStateOf<List<PackageDto>>(emptyList()) }
    var hasRedirected by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(false) }
    
    LaunchedEffect(configManager.backendUrl) {
        if (configManager.backendUrl.isNotEmpty()) {
            try {
                val api = NetworkClient.getApi(configManager.backendUrl)
                val response = api.getPackages()
                if (response.isSuccessful && response.body() != null) {
                    val list = response.body()!!
                    
                    // Filter based on active printers in kiosk settings
                    val filteredList = list.filter { pkg ->
                        val flow = pkg.printFlow ?: ""
                        when (configManager.printerType) {
                            "AUTO" -> true
                            "THERMAL" -> flow == "RECEIPT"
                            "COLOR" -> flow != "RECEIPT"
                            else -> true
                        }
                    }
                    
                    packagesList = filteredList
                    
                    // If only 1 package is available, auto-select it and skip this screen
                    if (filteredList.size == 1) {
                        if (!hasRedirected) {
                            hasRedirected = true
                            onPackageSelected(filteredList[0].id)
                        } else {
                            hasRedirected = false
                            onBackClick()
                        }
                    }
                } else {
                    Toast.makeText(context, "Gagal memuat paket: ${response.code()}", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                e.printStackTrace()
                Toast.makeText(context, "Kesalahan koneksi: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
            } finally {
                isLoading = false
            }
        } else {
            isLoading = false
        }
    }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PILIH PAKET FOTO", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
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
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(24.dp),
            contentAlignment = Alignment.Center
        ) {
            if (isLoading) {
                CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
            } else if (packagesList.isEmpty()) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Text(
                        text = "Tidak ada paket cetak yang tersedia.",
                        color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
                        fontSize = 16.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Silakan periksa konfigurasi printer di menu admin.",
                        color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.4f),
                        fontSize = 13.sp
                    )
                }
            } else {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "Pilih paket cetak yang ingin Anda gunakan untuk sesi foto ini",
                        color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
                        fontSize = 14.sp,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.padding(bottom = 32.dp)
                    )

                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                        modifier = Modifier.fillMaxWidth(),
                        contentPadding = PaddingValues(horizontal = 8.dp)
                    ) {
                        items(packagesList) { pkg ->
                            PackageCard(
                                pkg = pkg,
                                onClick = { onPackageSelected(pkg.id) },
                                modifier = Modifier.width(280.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun PackageCard(
    pkg: PackageDto,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val isCutePastel = AppTheme.type == AppThemeType.CUTE_PASTEL
    val locale = Locale("id", "ID")
    val formatter = NumberFormat.getCurrencyInstance(locale).apply {
        maximumFractionDigits = 0
    }
    val priceText = formatter.format(pkg.price)
    
    val accentColor = when (pkg.printFlow) {
        "RECEIPT" -> Color(0xFFF7B801) // Gold/Yellow
        "COLOR_PRINT" -> Color(0xFFE63946) // Red
        "ID_CARD" -> Color(0xFF4F46E5) // Indigo
        else -> MaterialTheme.colorScheme.primary
    }

    val iconChar = when (pkg.printFlow) {
        "RECEIPT" -> "📄"
        "COLOR_PRINT" -> "🖼️"
        "ID_CARD" -> "🪪"
        else -> "✨"
    }

    Card(
        shape = RoundedCornerShape(if (isCutePastel) 16.dp else 24.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = BorderStroke(
            width = if (isCutePastel) 3.dp else 1.dp,
            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
        ),
        modifier = modifier
            .padding(vertical = 8.dp)
            .clickable(onClick = onClick)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(accentColor.copy(alpha = 0.1f)),
                contentAlignment = Alignment.Center
            ) {
                Text(iconChar, fontSize = 32.sp)
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = pkg.name,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                color = MaterialTheme.colorScheme.onSurface,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = priceText,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 22.sp,
                color = accentColor,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f))
            Spacer(modifier = Modifier.height(20.dp))
            
            Column(
                verticalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                val printFeature = pkg.features["print"] ?: false
                FeatureItem(text = "Cetak Fisik", enabled = printFeature)
                
                val downloadFeature = pkg.features["download"] ?: false
                FeatureItem(text = "Download Foto Digital", enabled = downloadFeature)
                
                val gifFeature = pkg.features["gif"] ?: false
                FeatureItem(text = "Live Animated GIF", enabled = gifFeature)
                
                val stickerFeature = pkg.features["sticker"] ?: false
                FeatureItem(text = "Filter Stiker Kreatif", enabled = stickerFeature)
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onClick,
                colors = ButtonDefaults.buttonColors(containerColor = accentColor),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("PILIH PAKET", fontWeight = FontWeight.Bold, color = Color.White)
            }
        }
    }
}

@Composable
fun FeatureItem(text: String, enabled: Boolean) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Start,
        modifier = Modifier.fillMaxWidth()
    ) {
        Text(
            text = if (enabled) "✓" else "✗",
            color = if (enabled) Color(0xFF10B981) else Color(0xFFEF4444),
            fontWeight = FontWeight.Bold,
            fontSize = 14.sp,
            modifier = Modifier.width(18.dp)
        )
        Text(
            text = text,
            color = if (enabled) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.4f),
            fontSize = 13.sp,
            fontWeight = if (enabled) FontWeight.Normal else FontWeight.Light
        )
    }
}
