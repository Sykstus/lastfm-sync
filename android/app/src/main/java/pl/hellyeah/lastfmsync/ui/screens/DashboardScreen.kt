package pl.hellyeah.lastfmsync.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
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
import pl.hellyeah.lastfmsync.data.model.*
import pl.hellyeah.lastfmsync.ui.components.*
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(vm: MainViewModel, state: pl.hellyeah.lastfmsync.viewmodel.AppUiState) {
    var showPauseDialog by remember { mutableStateOf(false) }
    var pauseReason     by remember { mutableStateOf("") }

    val paused    = state.pauseStatus?.paused == true
    val pauseInfo = state.pauseStatus?.info
    val dashboard = state.dashboard
    val accounts  = dashboard?.accounts
    val aUser     = accounts?.a ?: "A"
    val bUser     = accounts?.b ?: "B"
    val isAdmin   = state.role == "admin"

    LazyColumn(
        Modifier
            .fillMaxSize()
            .background(Cream),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {

        // ── NAGŁÓWEK ─────────────────────────────────────────────────────────
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text("Cześć, ${state.username} 👋", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextPrim)
                    Text("$aUser ↔ $bUser", fontSize = 12.sp, color = TextSec)
                }
                IconButton(onClick = { vm.refreshAll() }) {
                    Icon(Icons.Default.Refresh, "Odśwież", tint = TextSec)
                }
            }
        }

        // ── KOMUNIKAT SYNC ────────────────────────────────────────────────────
        state.syncResult?.let { msg ->
            item {
                Surface(Modifier.fillMaxWidth(), color = Color(0xFFEBF5EE), shape = RoundedCornerShape(8.dp)) {
                    Text(msg, Modifier.padding(12.dp), fontSize = 13.sp, fontFamily = FontFamily.Monospace, color = GreenOk)
                }
            }
        }

        // ── PASEK PAUZY ──────────────────────────────────────────────────────
        item {
            PauseBar(
                paused    = paused,
                pauseInfo = pauseInfo,
                isAdmin   = isAdmin,
                onPause   = { showPauseDialog = true },
                onResume  = { vm.resumeSync() },
                onSync    = { vm.runSync() },
            )
        }

        // ── STATYSTYKI ────────────────────────────────────────────────────────
        item {
            val totals = dashboard?.totals
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                StatCard(totals?.a2b?.toString() ?: "—", "$aUser→$bUser", AccentA, Modifier.weight(1f))
                StatCard(totals?.b2a?.toString() ?: "—", "$bUser→$aUser", AccentB, Modifier.weight(1f))
            }
            Spacer(Modifier.height(0.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                StatCard(totals?.total?.toString() ?: "—", "Łącznie", Accent, Modifier.weight(1f))
                StatCard(
                    value      = dashboard?.lastRun?.substring(11, 16) ?: "—",
                    label      = "Ostatni sync",
                    valueColor = TextPrim,
                    modifier   = Modifier.weight(1f),
                )
            }
        }

        // ── OSTATNI SCROBBLE ─────────────────────────────────────────────────
        dashboard?.lastScrobble?.let { s ->
            item {
                SectionCard(title = "Ostatni zsynchronizowany") {
                    Column(Modifier.padding(16.dp)) {
                        Text(s.track, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = TextPrim)
                        Text(s.artist, fontSize = 13.sp, color = TextSec)
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                            DirectionBadge(s.direction, aUser, bUser)
                            MonoText(s.syncedAt.substring(11, 16))
                        }
                    }
                }
            }
        }

        // ── OSTATNIE CYKLE ────────────────────────────────────────────────────
        if (state.recentRuns.isNotEmpty()) {
            item {
                SectionCard(title = "Ostatnie cykle crona") {
                    state.recentRuns.forEachIndexed { i, run ->
                        RunRow(run, aUser, bUser)
                        if (i < state.recentRuns.lastIndex)
                            HorizontalDivider(color = Border, thickness = 0.5.dp)
                    }
                }
            }
        }

        // ── LOG ───────────────────────────────────────────────────────────────
        if (state.logs.isNotEmpty()) {
            item {
                SectionCard(title = "Log na żywo") {
                    Column(Modifier.padding(vertical = 4.dp)) {
                        state.logs.takeLast(30).forEach { log ->
                            LogRow(log)
                        }
                    }
                }
            }
        }

        item { Spacer(Modifier.height(80.dp)) }
    }

    // ── DIALOG PAUZY ─────────────────────────────────────────────────────────
    if (showPauseDialog) {
        AlertDialog(
            onDismissRequest = { showPauseDialog = false },
            title = { Text("Wstrzymaj synchronizację") },
            text = {
                Column {
                    Text("Podaj powód (opcjonalnie):", fontSize = 13.sp, color = TextSec)
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(
                        value = pauseReason,
                        onValueChange = { pauseReason = it },
                        placeholder = { Text("np. słucham solo", color = TextTert) },
                        singleLine = true,
                        shape = RoundedCornerShape(8.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Accent, unfocusedBorderColor = Border),
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = { vm.pauseSync(pauseReason); showPauseDialog = false; pauseReason = "" },
                    colors = ButtonDefaults.buttonColors(containerColor = Accent),
                ) { Text("Wstrzymaj") }
            },
            dismissButton = {
                TextButton(onClick = { showPauseDialog = false; pauseReason = "" }) { Text("Anuluj") }
            },
        )
    }
}

@Composable
fun PauseBar(
    paused: Boolean,
    pauseInfo: PauseInfo?,
    isAdmin: Boolean,
    onPause: () -> Unit,
    onResume: () -> Unit,
    onSync: () -> Unit,
) {
    val bg      = if (paused) Color(0xFFFEF7EC) else White
    val border  = if (paused) Color(0xFFF5C96A) else Border

    Surface(
        Modifier.fillMaxWidth(),
        color = bg,
        shape = RoundedCornerShape(12.dp),
        border = CardDefaults.outlinedCardBorder(),
    ) {
        Row(
            Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Column(Modifier.weight(1f)) {
                if (paused) {
                    Text("⏸ Sync wstrzymany", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Amber)
                    pauseInfo?.let {
                        Text(
                            "przez ${it.by}${if (it.reason.isNotBlank()) " · ${it.reason}" else ""} · ${it.since.substring(11,16)}",
                            fontSize = 11.sp,
                            fontFamily = FontFamily.Monospace,
                            color = Color(0xFFA0660A),
                        )
                    }
                } else {
                    Text("▶ Sync aktywny", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = GreenOk)
                    Text("Scroble kopiowane automatycznie", fontSize = 11.sp, color = TextSec)
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (paused) {
                    FilledTonalButton(
                        onClick = onResume,
                        colors = ButtonDefaults.filledTonalButtonColors(containerColor = Color(0xFFEBF5EE)),
                    ) { Text("▶ Wznów", color = GreenOk, fontSize = 13.sp, fontWeight = FontWeight.SemiBold) }
                } else {
                    OutlinedButton(onClick = onPause, border = ButtonDefaults.outlinedButtonBorder) {
                        Text("⏸ Wstrzymaj", fontSize = 13.sp)
                    }
                    if (isAdmin) {
                        FilledTonalButton(onClick = onSync) {
                            Text("▶", fontSize = 13.sp)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun RunRow(run: SyncRun, aUser: String, bUser: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(run.ranAt.substring(5, 16), fontSize = 12.sp, fontFamily = FontFamily.Monospace, color = TextSec)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                NpDot(run.npA == 1); Text(aUser.take(6), fontSize = 10.sp, color = TextTert)
            }
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                NpDot(run.npB == 1); Text(bUser.take(6), fontSize = 10.sp, color = TextTert)
            }
            if (run.syncedA2B > 0) Text("${run.syncedA2B}↓", fontSize = 11.sp, fontFamily = FontFamily.Monospace, color = AccentA, fontWeight = FontWeight.Bold)
            if (run.syncedB2A > 0) Text("${run.syncedB2A}↓", fontSize = 11.sp, fontFamily = FontFamily.Monospace, color = AccentB, fontWeight = FontWeight.Bold)
            StatusBadge(run.status)
        }
    }
}

@Composable
fun LogRow(log: LogEntry) {
    val color = when (log.type) {
        "ok"   -> GreenOk
        "err"  -> RedErr
        "info" -> AccentB
        "warn" -> Amber
        else   -> TextSec
    }
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 3.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(log.loggedAt.substring(11, 19), fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, modifier = Modifier.width(58.dp))
        Text(log.side.take(6), fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, modifier = Modifier.width(42.dp))
        Text(log.message, fontSize = 11.sp, fontFamily = FontFamily.Monospace, color = color, modifier = Modifier.weight(1f))
    }
}
