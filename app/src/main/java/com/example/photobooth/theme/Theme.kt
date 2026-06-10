package com.example.photobooth.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily

enum class AppThemeType {
    NEON_RED, CUTE_PASTEL, LUXURY_GOLD, RETRO_ARCADE
}

data class AppThemeColors(
    val type: AppThemeType,
    val name: String,
    val isDark: Boolean,
    val background: Color,
    val onBackground: Color,
    val primary: Color,
    val accentColor: Color,
    val cardBackground: Color,
    val onCardBackground: Color,
    val border: Color,
    val buttonBackground: Color,
    val buttonContent: Color,
    val fontFamily: FontFamily
)

val NeonRedColors = AppThemeColors(
    type = AppThemeType.NEON_RED,
    name = "Neon Red (Modern)",
    isDark = true,
    background = Color(0xFFE63946), // Red Background for Home
    onBackground = Color.White,
    primary = Color(0xFFE63946),
    accentColor = Color.White,
    cardBackground = Color(0xFF1E1E24),
    onCardBackground = Color.White,
    border = Color(0xFF2A2A35),
    buttonBackground = Color(0xFF121212),
    buttonContent = Color.White,
    fontFamily = FontFamily.SansSerif
)

val CutePastelColors = AppThemeColors(
    type = AppThemeType.CUTE_PASTEL,
    name = "Cute Pastel (Handmade Wood)",
    isDark = false,
    background = Color(0xFFFBF8EB), // Warm ivory
    onBackground = Color(0xFF4E3629), // Warm dark brown
    primary = Color(0xFFF7D070), // Yellow pastel
    accentColor = Color(0xFFE57C5D), // Terracotta/orange accent
    cardBackground = Color(0xFFFFFDF5),
    onCardBackground = Color(0xFF4E3629),
    border = Color(0xFF4E3629), // Thick dark brown border
    buttonBackground = Color(0xFFF7D070),
    buttonContent = Color(0xFF4E3629),
    fontFamily = FontFamily.Cursive
)

val LuxuryGoldColors = AppThemeColors(
    type = AppThemeType.LUXURY_GOLD,
    name = "Luxury Gold (Elegant Wedding)",
    isDark = true,
    background = Color(0xFF0B132B), // Deep midnight navy
    onBackground = Color(0xFFF4E0A5), // Soft gold text
    primary = Color(0xFFD4AF37), // Metallic gold
    accentColor = Color(0xFFD4AF37),
    cardBackground = Color(0xFF152238),
    onCardBackground = Color(0xFFF4E0A5),
    border = Color(0xFFD4AF37),
    buttonBackground = Color(0xFFD4AF37),
    buttonContent = Color(0xFF0B132B),
    fontFamily = FontFamily.Serif
)

val RetroArcadeColors = AppThemeColors(
    type = AppThemeType.RETRO_ARCADE,
    name = "Retro Arcade (8-Bit Vaporwave)",
    isDark = true,
    background = Color(0xFF0B001A), // Dark violet
    onBackground = Color(0xFF00F0FF), // Neon cyan text
    primary = Color(0xFFDF00FF), // Neon magenta
    accentColor = Color(0xFF00F0FF),
    cardBackground = Color(0xFF160030),
    onCardBackground = Color(0xFF00F0FF),
    border = Color(0xFFDF00FF),
    buttonBackground = Color(0xFFDF00FF),
    buttonContent = Color.Black,
    fontFamily = FontFamily.Monospace
)

val LocalAppThemeColors = staticCompositionLocalOf { NeonRedColors }

object AppTheme {
    val colors: AppThemeColors
        @Composable
        get() = LocalAppThemeColors.current
    
    val type: AppThemeType
        @Composable
        get() = LocalAppThemeColors.current.type
}

@Composable
fun PhotoboothTheme(
  themeType: String = "NEON_RED",
  content: @Composable () -> Unit,
) {
  val themeColors = when (themeType) {
    "CUTE_PASTEL" -> CutePastelColors
    "LUXURY_GOLD" -> LuxuryGoldColors
    "RETRO_ARCADE" -> RetroArcadeColors
    else -> NeonRedColors
  }

  val colorScheme = if (themeColors.isDark) {
      darkColorScheme(
          primary = themeColors.primary,
          background = if (themeColors.type == AppThemeType.NEON_RED) Color(0xFF0F0F12) else themeColors.background,
          surface = themeColors.cardBackground,
          onBackground = themeColors.onBackground,
          onSurface = themeColors.onCardBackground,
          outline = themeColors.border
      )
  } else {
      lightColorScheme(
          primary = themeColors.primary,
          background = themeColors.background,
          surface = themeColors.cardBackground,
          onBackground = themeColors.onBackground,
          onSurface = themeColors.onCardBackground,
          outline = themeColors.border
      )
  }

  val typography = androidx.compose.material3.Typography(
      displayLarge = MaterialTheme.typography.displayLarge.copy(fontFamily = themeColors.fontFamily),
      displayMedium = MaterialTheme.typography.displayMedium.copy(fontFamily = themeColors.fontFamily),
      displaySmall = MaterialTheme.typography.displaySmall.copy(fontFamily = themeColors.fontFamily),
      headlineLarge = MaterialTheme.typography.headlineLarge.copy(fontFamily = themeColors.fontFamily),
      headlineMedium = MaterialTheme.typography.headlineMedium.copy(fontFamily = themeColors.fontFamily),
      headlineSmall = MaterialTheme.typography.headlineSmall.copy(fontFamily = themeColors.fontFamily),
      titleLarge = MaterialTheme.typography.titleLarge.copy(fontFamily = themeColors.fontFamily),
      titleMedium = MaterialTheme.typography.titleMedium.copy(fontFamily = themeColors.fontFamily),
      titleSmall = MaterialTheme.typography.titleSmall.copy(fontFamily = themeColors.fontFamily),
      bodyLarge = MaterialTheme.typography.bodyLarge.copy(fontFamily = themeColors.fontFamily),
      bodyMedium = MaterialTheme.typography.bodyMedium.copy(fontFamily = themeColors.fontFamily),
      bodySmall = MaterialTheme.typography.bodySmall.copy(fontFamily = themeColors.fontFamily),
      labelLarge = MaterialTheme.typography.labelLarge.copy(fontFamily = themeColors.fontFamily),
      labelMedium = MaterialTheme.typography.labelMedium.copy(fontFamily = themeColors.fontFamily),
      labelSmall = MaterialTheme.typography.labelSmall.copy(fontFamily = themeColors.fontFamily)
  )

  CompositionLocalProvider(LocalAppThemeColors provides themeColors) {
      MaterialTheme(
          colorScheme = colorScheme,
          typography = typography,
          content = content
      )
  }
}

