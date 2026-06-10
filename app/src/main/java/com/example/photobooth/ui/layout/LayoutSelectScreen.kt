package com.example.photobooth.ui.layout

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.photobooth.theme.AppTheme
import com.example.photobooth.theme.AppThemeType

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LayoutSelectScreen(
    onBackClick: () -> Unit,
    onLayoutSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PILIH LAYOUT FOTO", fontWeight = FontWeight.Bold, fontSize = 20.sp, letterSpacing = 1.sp) },
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
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "Pilih struktur layout cetak yang Anda inginkan",
                color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f),
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 40.dp)
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                val iconBg = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.05f)
                val iconInner = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.2f)
                
                // Layout 1: Vertical Strip (4 Photos)
                LayoutCard(
                    title = "Vertical Strip",
                    description = "4 Foto vertikal memanjang (Sangat cocok untuk Printer Struk/Thermal)",
                    iconContent = {
                        Column(
                            verticalArrangement = Arrangement.spacedBy(4.dp),
                            modifier = Modifier
                                .width(50.dp)
                                .fillMaxHeight()
                                .background(iconBg)
                                .padding(4.dp)
                        ) {
                            repeat(4) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .weight(1f)
                                        .background(iconInner)
                                )
                            }
                        }
                    },
                    onClick = { onLayoutSelected("strip") },
                    modifier = Modifier.weight(1f)
                )

                // Layout 2: 2x2 Grid Layout
                LayoutCard(
                    title = "2x2 Grid",
                    description = "4 Foto berpasangan persegi (Sangat cocok untuk Printer Warna biasa)",
                    iconContent = {
                        Column(
                            verticalArrangement = Arrangement.spacedBy(4.dp),
                            modifier = Modifier
                                .width(70.dp)
                                .height(70.dp)
                                .background(iconBg)
                                .padding(4.dp)
                        ) {
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier.weight(1f).fillMaxWidth()
                            ) {
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(iconInner))
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(iconInner))
                            }
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier.weight(1f).fillMaxWidth()
                            ) {
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(iconInner))
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(iconInner))
                            }
                        }
                    },
                    onClick = { onLayoutSelected("grid") },
                    modifier = Modifier.weight(1f)
                )

                // Layout 3: Mega Postcard
                LayoutCard(
                    title = "Mega Postcard",
                    description = "1 Foto lanskap besar artistik (Sangat megah & estetik)",
                    iconContent = {
                        Box(
                            modifier = Modifier
                                .width(90.dp)
                                .height(65.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(iconBg)
                                .padding(4.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(RoundedCornerShape(4.dp))
                                    .background(iconInner)
                            )
                        }
                    },
                    onClick = { onLayoutSelected("postcard") },
                    modifier = Modifier.weight(1f)
                )

                // Layout 4: AI Character Catalog
                LayoutCard(
                    title = "AI Karakter",
                    description = "Foto wajah Anda akan diubah menjadi karakter seru pilihan secara ajaib!",
                    iconContent = {
                        Box(
                            modifier = Modifier
                                .width(70.dp)
                                .height(70.dp)
                                .clip(RoundedCornerShape(20.dp))
                                .background(
                                    androidx.compose.ui.graphics.Brush.linearGradient(
                                        colors = listOf(Color(0xFF8A2387), Color(0xFFE94057), Color(0xFFF27121))
                                    )
                                ),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("✨", fontSize = 36.sp)
                        }
                    },
                    onClick = { onLayoutSelected("character_select") },
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
fun LayoutCard(
    title: String,
    description: String,
    iconContent: @Composable () -> Unit,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val isCutePastel = AppTheme.type == AppThemeType.CUTE_PASTEL
    Card(
        shape = RoundedCornerShape(if (isCutePastel) 16.dp else 24.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = BorderStroke(
            width = if (isCutePastel) 3.dp else 1.dp,
            color = MaterialTheme.colorScheme.outline
        ),
        modifier = modifier
            .fillMaxWidth()
            .height(280.dp)
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            Box(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center
            ) {
                iconContent()
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Text(
                    text = title,
                    color = MaterialTheme.colorScheme.onSurface,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = description,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                    fontSize = 10.sp,
                    textAlign = TextAlign.Center,
                    lineHeight = 14.sp
                )
            }
        }
    }
}
