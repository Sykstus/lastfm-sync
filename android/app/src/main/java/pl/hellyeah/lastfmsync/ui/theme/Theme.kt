package pl.hellyeah.lastfmsync.ui.theme

import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

// Kolory spójne z panelem www
val Cream     = Color(0xFFF7F5F0)
val White     = Color(0xFFFFFFFF)
val TextPrim  = Color(0xFF1A1A1A)
val TextSec   = Color(0xFF6B6B6B)
val TextTert  = Color(0xFFAAAAAA)
val Border    = Color(0xFFE2DFD8)
val Border2   = Color(0xFFD0CEC7)
val BgCard    = Color(0xFFF2F0EB)
val Accent    = Color(0xFFC8503C)  // ceglasty
val AccentA   = Color(0xFF2B7A3B)  // zielony (konto A)
val AccentB   = Color(0xFF1A5FA8)  // niebieski (konto B)
val BGreenBg  = Color(0xFFEBF5EE)
val BBlueBg   = Color(0xFFEBF2FB)
val GreenOk   = Color(0xFF2D7D4F)
val RedErr    = Color(0xFFC8503C)
val Amber     = Color(0xFFA0660A)
val AmberBg   = Color(0xFFFEF7EC)

private val LightColors = lightColorScheme(
    primary          = Accent,
    onPrimary        = White,
    primaryContainer = Color(0xFFFEF2F0),
    background       = Cream,
    surface          = White,
    onBackground     = TextPrim,
    onSurface        = TextPrim,
    outline          = Border,
    outlineVariant   = Border2,
    error            = RedErr,
)

@Composable
fun LfmSyncTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColors,
        typography  = Typography(
            headlineLarge  = MaterialTheme.typography.headlineLarge.copy(fontWeight  = FontWeight.Bold),
            titleLarge     = MaterialTheme.typography.titleLarge.copy(fontWeight     = FontWeight.SemiBold),
            bodyMedium     = MaterialTheme.typography.bodyMedium.copy(color          = TextPrim),
            labelSmall     = MaterialTheme.typography.labelSmall.copy(fontFamily     = FontFamily.Monospace),
        ),
        content = content
    )
}
