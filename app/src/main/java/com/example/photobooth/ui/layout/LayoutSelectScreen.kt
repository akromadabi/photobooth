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
                        Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = Color(0xFF0F0F12),
                    titleContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFF0F0F12), // Deep Dark Background
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
                color = Color.Gray,
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 40.dp)
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
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
                                .background(Color.White.copy(alpha = 0.05f))
                                .padding(4.dp)
                        ) {
                            repeat(4) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .weight(1f)
                                        .background(Color.White.copy(alpha = 0.2f))
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
                                .background(Color.White.copy(alpha = 0.05f))
                                .padding(4.dp)
                        ) {
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier.weight(1f).fillMaxWidth()
                            ) {
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(Color.White.copy(alpha = 0.2f)))
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(Color.White.copy(alpha = 0.2f)))
                            }
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier.weight(1f).fillMaxWidth()
                            ) {
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(Color.White.copy(alpha = 0.2f)))
                                Box(modifier = Modifier.fillMaxHeight().weight(1f).background(Color.White.copy(alpha = 0.2f)))
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
                                .background(Color.White.copy(alpha = 0.05f))
                                .padding(4.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(RoundedCornerShape(4.dp))
                                    .background(Color.White.copy(alpha = 0.2f))
                            )
                        }
                    },
                    onClick = { onLayoutSelected("postcard") },
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
    Card(
        shape = RoundedCornerShape(24.dp),
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
                    color = Color.White,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = description,
                    color = Color.Gray,
                    fontSize = 10.sp,
                    textAlign = TextAlign.Center,
                    lineHeight = 14.sp
                )
            }
        }
    }
}
