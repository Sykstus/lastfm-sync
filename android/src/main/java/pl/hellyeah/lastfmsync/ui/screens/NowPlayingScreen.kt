package pl.hellyeah.lastfmsync.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.hellyeah.lastfmsync.data.model.NowPlayingAccount
import pl.hellyeah.lastfmsync.ui.components.NpDot
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.AppUiState
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel

@Composable
fun NowPlayingScreen(vm: MainViewModel, state: AppUiState) {
    val np    = state.nowPlaying
    val aUser = state.dashboard?.accounts?.a ?: "A"
    val bUser = state.dashboard?.accounts?.b ?: "B"

    LaunchedEffect(Unit) { vm.refreshNowPlaying() }

    Column(
        Modifier
            .fillMaxSize()
            .background(Cream)
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Teraz gra", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextPrim)
            TextButton(onClick = { vm.refreshNowPlaying() }) {
                Text("↻ Odśwież", color = Accent, fontSize = 13.sp)
            }
        }
        Text("Auto-odświeżanie co 15s", fontSize = 11.sp, color = TextTert, fontFamily = FontFamily.Monospace)

        NowPlayingCard(account = np?.a, label = aUser, accentColor = AccentA, bgColor = BGreenBg)
        NowPlayingCard(account = np?.b, label = bUser, accentColor = AccentB, bgColor = BBlueBg)

        Spacer(Modifier.height(80.dp))
    }
}

@Composable
fun NowPlayingCard(account: NowPlayingAccount?, label: String, accentColor: Color, bgColor: Color) {
    val isNp = account?.nowplaying == true

    Card(
        Modifier.fillMaxWidth(),
        shape     = RoundedCornerShape(16.dp),
        colors    = CardDefaults.cardColors(containerColor = White),
        elevation = CardDefaults.cardElevation(if (isNp) 3.dp else 1.dp),
        border    = if (isNp) CardDefaults.outlinedCardBorder() else null,
    ) {
        Column(Modifier.padding(18.dp)) {
            // Status badge
            Row(
                Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(if (isNp) bgColor else BgCard)
                    .padding(horizontal = 10.dp, vertical = 5.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                NpDot(isNp)
                Text(
                    if (isNp) "Słucha teraz" else "Offline",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isNp) accentColor else TextTert,
                )
            }

            Spacer(Modifier.height(14.dp))
            Text(label, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextPrim)
            Spacer(Modifier.height(14.dp))

            if (account == null) {
                Text("Brak danych", fontSize = 14.sp, color = TextTert)
                return@Column
            }

            // Album art placeholder
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(180.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isNp) bgColor else BgCard),
                contentAlignment = Alignment.Center,
            ) {
                Text("♪", fontSize = 56.sp, color = if (isNp) accentColor else TextTert)
            }

            Spacer(Modifier.height(14.dp))
            Text(
                account.track.ifBlank { "—" },
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = TextPrim,
                lineHeight = 24.sp,
            )
            Text(account.artist.ifBlank { "—" }, fontSize = 14.sp, color = TextSec, modifier = Modifier.padding(top = 3.dp))
            if (account.album.isNotBlank()) {
                Text(account.album, fontSize = 12.sp, color = TextTert, modifier = Modifier.padding(top = 2.dp))
            }
            if (!isNp && account.date != null) {
                Spacer(Modifier.height(8.dp))
                Text("Ostatnio: ${account.date}", fontSize = 11.sp, color = TextTert, fontFamily = FontFamily.Monospace)
            }
        }
    }
}
