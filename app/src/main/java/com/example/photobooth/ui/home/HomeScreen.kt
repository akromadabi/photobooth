package com.example.photobooth.ui.home

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onStartClick: () -> Unit,
    onAdminNavigate: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val configManager = remember { ConfigManager(context) }
    
    var logoTapCount by remember { mutableIntStateOf(0) }
    var showPinDialog by remember { mutableStateOf(false) }
    var pinInput by remember { mutableStateOf("") }
    var pinError by remember { mutableStateOf(false) }

    // Reset tap count after 3 seconds of inactivity
    LaunchedEffect(logoTapCount) {
        if (logoTapCount > 0) {
            delay(3000)
            logoTapCount = 0
        }
    }

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Color(0xFFE63946)) // Premium Red Background
            .padding(24.dp)
    ) {
        // Top Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(top = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null
                ) {
                    logoTapCount++
                    if (logoTapCount >= 5) {
                        logoTapCount = 0
                        showPinDialog = true
                    }
                }
            ) {
                Text(
                    text = "Creative",
                    color = Color.White,
                    fontSize = 32.sp,
                    fontWeight = FontWeight.Black,
                    lineHeight = 32.sp
                )
                Text(
                    text = "Studio",
                    color = Color.White,
                    fontSize = 32.sp,
                    fontWeight = FontWeight.Light,
                    lineHeight = 32.sp
                )
            }
            
            // Decorative Plus icon at top right
            Text(
                text = "+",
                color = Color.White.copy(alpha = 0.8f),
                fontSize = 38.sp,
                fontWeight = FontWeight.Light
            )
        }

        // Center Content: Slogan and Photo Strip Graphic
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.Center)
                .padding(bottom = 60.dp),
            verticalArrangement = Arrangement.Center
        ) {
            Text(
                text = "All You need\nis special",
                color = Color.White,
                fontSize = 42.sp,
                fontWeight = FontWeight.ExtraBold,
                lineHeight = 48.sp,
                fontFamily = FontFamily.SansSerif,
                modifier = Modifier.padding(bottom = 24.dp)
            )

            // Simple premium layout placeholder representing a hanging Photo Strip
            Box(
                modifier = Modifier
                    .width(140.dp)
                    .height(300.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(Color.White.copy(alpha = 0.15f))
                    .align(Alignment.End)
                    .padding(8.dp)
            ) {
                Column(
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxSize()
                ) {
                    repeat(3) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .weight(1f)
                                .background(Color.White.copy(alpha = 0.25f))
                        )
                    }
                }
            }
        }

        // Bottom CTA and Description
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .align(Alignment.BottomCenter)
                .padding(bottom = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(24.dp)
        ) {
            // Pill shape START Button
            Button(
                onClick = onStartClick,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF121212),
                    contentColor = Color.White
                ),
                shape = RoundedCornerShape(50.dp),
                contentPadding = PaddingValues(horizontal = 40.dp, vertical = 18.dp),
                modifier = Modifier
                    .height(60.dp)
                    .width(220.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center,
                    modifier = Modifier.fillMaxSize()
                ) {
                    Text(
                        text = "START",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Icon(
                        imageVector = Icons.Default.ArrowForward,
                        contentDescription = "Start",
                        tint = Color.White
                    )
                }
            }

            // Description text & branding logo
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Bottom
            ) {
                Text(
                    text = "Our Creative Studio provides a\nprofessional place to capture some\nspecial moments. So that, You need\nto decide choosing us as your first option.",
                    color = Color.White.copy(alpha = 0.7f),
                    fontSize = 10.sp,
                    lineHeight = 14.sp,
                    modifier = Modifier.weight(1f)
                )
                
                Column(
                    horizontalAlignment = Alignment.End,
                    modifier = Modifier.padding(start = 16.dp)
                ) {
                    Text(
                        text = "Receipt",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        lineHeight = 18.sp
                    )
                    Text(
                        text = "Photo",
                        color = Color.White,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Light,
                        lineHeight = 18.sp
                    )
                }
            }
        }

        // Admin PIN Dialog
        AnimatedVisibility(
            visible = showPinDialog,
            enter = fadeIn() + scaleIn(),
            exit = fadeOut() + scaleOut()
        ) {
            Dialog(onDismissRequest = { 
                showPinDialog = false
                pinInput = ""
                pinError = false
            }) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF1E1E24))
                ) {
                    Column(
                        modifier = Modifier.padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "Admin Access",
                            color = Color.White,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Masukkan PIN 4-digit untuk masuk ke menu admin.",
                            color = Color.Gray,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center
                        )

                        OutlinedTextField(
                            value = pinInput,
                            onValueChange = { 
                                if (it.length <= 4 && it.all { char -> char.isDigit() }) {
                                    pinInput = it
                                    pinError = false
                                }
                            },
                            visualTransformation = PasswordVisualTransformation(),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            isError = pinError,
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = Color.White,
                                unfocusedTextColor = Color.White,
                                focusedBorderColor = Color(0xFFE63946),
                                unfocusedBorderColor = Color.Gray
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )

                        if (pinError) {
                            Text(
                                text = "PIN yang Anda masukkan salah!",
                                color = Color(0xFFE63946),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            TextButton(
                                onClick = {
                                    showPinDialog = false
                                    pinInput = ""
                                    pinError = false
                                },
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Batal", color = Color.Gray)
                            }
                            
                            Button(
                                onClick = {
                                    if (pinInput == configManager.adminPin) {
                                        showPinDialog = false
                                        pinInput = ""
                                        onAdminNavigate()
                                    } else {
                                        pinError = true
                                    }
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE63946)),
                                modifier = Modifier.weight(1f)
                            ) {
                                Text("Masuk", color = Color.White)
                            }
                        }
                    }
                }
            }
        }
    }
}
