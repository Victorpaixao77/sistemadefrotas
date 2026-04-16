package com.sistemafrotas.motorista.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.core.view.WindowCompat

private val AppTypography = androidx.compose.material3.Typography(
    displayLarge = TextStyle(fontWeight = FontWeight.Bold, fontSize = 32.sp, lineHeight = 40.sp),
    headlineLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 24.sp, lineHeight = 32.sp),
    headlineMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 20.sp, lineHeight = 28.sp),
    titleLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 18.sp, lineHeight = 24.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.Medium, fontSize = 16.sp, lineHeight = 22.sp),
    bodyLarge = TextStyle(fontWeight = FontWeight.Normal, fontSize = 16.sp, lineHeight = 24.sp),
    bodyMedium = TextStyle(fontWeight = FontWeight.Normal, fontSize = 14.sp, lineHeight = 20.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 14.sp, lineHeight = 20.sp),
)

// Cores padrão do sistema web (styles.css / theme.css)
// Primary: #3b82f6 | Background dark: #0f1824, #1a2332 | Light: #f3f4f6, #ffffff
private val WebPrimary = Color(0xFF3B82F6)
private val WebPrimaryDark = Color(0xFF2563EB)
private val WebBgDark = Color(0xFF0F1824)
private val WebBgSecondary = Color(0xFF1A2332)
private val WebBgTertiary = Color(0xFF243041)
private val WebTextPrimary = Color(0xFFFFFFFF)
private val WebTextSecondary = Color(0xFFB8C2D0)
private val WebTextMuted = Color(0xFF64748B)
private val WebBorder = Color(0xFF2D3748)
private val WebCardDark = Color(0xFF1E293B)

private val WebBgLight = Color(0xFFF3F4F6)
private val WebSurfaceLight = Color(0xFFFFFFFF)
private val WebTextDark = Color(0xFF1F2937)
private val WebTextSecondaryLight = Color(0xFF4B5563)
private val WebBorderLight = Color(0xFFE5E7EB)
private val WebError = Color(0xFFEF4444)

private val DarkColorScheme = darkColorScheme(
    primary = WebPrimary,
    onPrimary = Color.White,
    primaryContainer = WebPrimaryDark,
    onPrimaryContainer = Color.White,
    secondary = WebBgTertiary,
    onSecondary = WebTextPrimary,
    background = WebBgDark,
    onBackground = WebTextPrimary,
    surface = WebBgSecondary,
    onSurface = WebTextPrimary,
    surfaceVariant = WebCardDark,
    onSurfaceVariant = WebTextSecondary,
    error = WebError,
    onError = Color.White,
    outline = WebBorder,
)

private val LightColorScheme = lightColorScheme(
    primary = WebPrimary,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFDBEAFE),
    onPrimaryContainer = WebPrimaryDark,
    secondary = WebPrimaryDark,
    onSecondary = Color.White,
    background = WebBgLight,
    onBackground = WebTextDark,
    surface = WebSurfaceLight,
    onSurface = WebTextDark,
    surfaceVariant = WebBorderLight,
    onSurfaceVariant = WebTextSecondaryLight,
    error = WebError,
    onError = Color.White,
    outline = WebBorderLight,
)

@Composable
fun SistemaFrotasMotoristaTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = colorScheme.primary.toArgb()
            @Suppress("DEPRECATION")
            window.navigationBarColor = colorScheme.surface.toArgb()
            val insets = WindowCompat.getInsetsController(window, view)
            insets.isAppearanceLightStatusBars = !darkTheme
            insets.isAppearanceLightNavigationBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = AppTypography,
        content = content,
    )
}
