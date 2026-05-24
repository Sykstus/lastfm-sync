package pl.hellyeah.lastfmsync.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.hellyeah.lastfmsync.data.model.Scrobble
import pl.hellyeah.lastfmsync.ui.components.DirectionBadge
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.AppUiState
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel

@Composable
fun ScrobblesScreen(vm: MainViewModel, state: AppUiState) {
    val aUser = state.dashboard?.accounts?.a ?: "A"
    val bUser = state.dashboard?.accounts?.b ?: "B"
    val page  = state.scrobbles
    var dir   by remember { mutableStateOf("all") }

    LaunchedEffect(Unit) { vm.loadScrobbles() }

    Column(
        Modifier
            .fillMaxSize()
            .background(Cream),
    ) {
        // Header
        Column(Modifier.padding(horizontal = 16.dp, vertical = 16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Historia scrobbli", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextPrim)
                page?.let { Text("${it.total} rekordów", fontSize = 12.sp, color = TextTert, fontFamily = FontFamily.Monospace) }
            }
            Spacer(Modifier.height(10.dp))
            // Filter buttons
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("all" to "Wszystkie", "a2b" to "A→B", "b2a" to "B→A").forEach { (value, label) ->
                    FilterChip(
                        selected = dir == value,
                        onClick  = { dir = value; vm.loadScrobbles(dir = value) },
                        label    = { Text(label, fontSize = 12.sp) },
                        colors   = FilterChipDefaults.filterChipColors(
                            selectedContainerColor       = Accent,
                            selectedLabelColor           = White,
                        ),
                    )
                }
            }
        }

        HorizontalDivider(color = Border, thickness = 0.5.dp)

        if (page == null) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Accent)
            }
        } else {
            LazyColumn(contentPadding = PaddingValues(bottom = 100.dp)) {
                items(page.items) { scrobble ->
                    ScrobbleRow(scrobble, aUser, bUser)
                    HorizontalDivider(color = Border, thickness = 0.5.dp)
                }

                // Paginacja
                if (page.pages > 1) {
                    item {
                        Row(
                            Modifier.fillMaxWidth().padding(16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            OutlinedButton(
                                onClick  = { vm.loadScrobbles(state.scrobblesPage - 1, dir) },
                                enabled  = state.scrobblesPage > 1,
                                shape    = RoundedCornerShape(8.dp),
                            ) { Text("← Poprzednia") }

                            Text(
                                "${state.scrobblesPage} / ${page.pages}",
                                fontSize = 13.sp,
                                fontFamily = FontFamily.Monospace,
                                color = TextSec,
                            )

                            OutlinedButton(
                                onClick  = { vm.loadScrobbles(state.scrobblesPage + 1, dir) },
                                enabled  = state.scrobblesPage < page.pages,
                                shape    = RoundedCornerShape(8.dp),
                            ) { Text("Następna →") }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ScrobbleRow(scrobble: Scrobble, aUser: String, bUser: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .background(White)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(scrobble.track, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = TextPrim, maxLines = 1)
            Text(scrobble.artist, fontSize = 12.sp, color = TextSec, maxLines = 1)
            if (!scrobble.album.isNullOrBlank()) {
                Text(scrobble.album, fontSize = 11.sp, color = TextTert, maxLines = 1)
            }
        }
        Spacer(Modifier.width(8.dp))
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
            DirectionBadge(scrobble.direction, aUser, bUser)
            Text(scrobble.scrobbledAt.substring(5, 16), fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert)
        }
    }
}
