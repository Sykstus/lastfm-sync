package pl.hellyeah.lastfmsync.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.hellyeah.lastfmsync.ui.theme.*

@Composable
fun StatCard(
    value: String,
    label: String,
    valueColor: Color = TextPrim,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = White),
        border = CardDefaults.outlinedCardBorder(),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(Modifier.padding(14.dp)) {
            Text(
                text = value,
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold,
                color = valueColor,
                lineHeight = 30.sp,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = label.uppercase(),
                fontSize = 10.sp,
                fontFamily = FontFamily.Monospace,
                color = TextTert,
                letterSpacing = 0.8.sp,
            )
        }
    }
}

@Composable
fun SectionCard(
    title: String,
    modifier: Modifier = Modifier,
    action: @Composable (() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = White),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column {
            Row(
                Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = title.uppercase(),
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    fontWeight = FontWeight.Medium,
                    color = TextTert,
                    letterSpacing = 0.8.sp,
                )
                action?.invoke()
            }
            HorizontalDivider(color = Border, thickness = 0.5.dp)
            Column(content = content)
        }
    }
}

@Composable
fun DirectionBadge(direction: String, aUser: String, bUser: String) {
    val isA2B = direction == "a2b"
    val bg    = if (isA2B) BGreenBg else BBlueBg
    val fg    = if (isA2B) AccentA  else AccentB
    val text  = if (isA2B) "$aUser→$bUser" else "$bUser→$aUser"
    Box(
        Modifier
            .clip(RoundedCornerShape(5.dp))
            .background(bg)
            .padding(horizontal = 7.dp, vertical = 3.dp)
    ) {
        Text(text, fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = fg, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
fun StatusBadge(status: String) {
    val (bg, fg) = when (status) {
        "ok"      -> Pair(Color(0xFFEBF5EE), GreenOk)
        "error"   -> Pair(Color(0xFFFEF2F0), RedErr)
        "running" -> Pair(AmberBg, Amber)
        "paused"  -> Pair(AmberBg, Amber)
        else      -> Pair(BgCard, TextTert)
    }
    Box(
        Modifier
            .clip(RoundedCornerShape(5.dp))
            .background(bg)
            .padding(horizontal = 7.dp, vertical = 3.dp)
    ) {
        Text(status, fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = fg, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
fun NpDot(active: Boolean) {
    Box(
        Modifier
            .size(8.dp)
            .clip(RoundedCornerShape(50))
            .background(if (active) GreenOk else TextTert)
    )
}

@Composable
fun MonoText(text: String, color: Color = TextSec, size: Float = 11f) {
    Text(text, fontSize = size.sp, fontFamily = FontFamily.Monospace, color = color)
}
