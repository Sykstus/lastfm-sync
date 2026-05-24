package pl.hellyeah.lastfmsync.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.AppUiState
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel
import androidx.compose.material3.AlertDialog
import pl.hellyeah.lastfmsync.data.api.ApiClient
import pl.hellyeah.lastfmsync.data.api.DEFAULT_BASE_URL

@Composable
fun SettingsScreen(vm: MainViewModel, state: AppUiState) {
    var currentPass  by remember { mutableStateOf("") }
    var newPass      by remember { mutableStateOf("") }
    var changingPass by remember { mutableStateOf(false) }
    var syncing      by remember { mutableStateOf(false) }
    var clearing     by remember { mutableStateOf(false) }
    var serverUrl    by remember { mutableStateOf(state.serverUrl) }
    var serverSaved  by remember { mutableStateOf(false) }

    Column(
        Modifier
            .fillMaxSize()
            .background(Cream)
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Text("Ustawienia", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextPrim, modifier = Modifier.padding(top = 4.dp))

        // ── PROFIL ────────────────────────────────────────────────────────────
        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = White), elevation = CardDefaults.cardElevation(1.dp)) {
            Column {
                Text("PROFIL", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp, modifier = Modifier.padding(14.dp, 12.dp, 14.dp, 8.dp))
                HorizontalDivider(color = Border, thickness = 0.5.dp)
                InfoRow("Login", state.username)
                if (state.lfmUser != null) InfoRow("Last.fm", state.lfmUser, Accent)
                InfoRow("Rola", state.role)
            }
        }

        // ── SERWER ────────────────────────────────────────────────────────────
        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = White), elevation = CardDefaults.cardElevation(1.dp)) {
            Column(Modifier.padding(14.dp)) {
                Text("ADRES SERWERA API", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp, modifier = Modifier.padding(bottom = 8.dp))
                OutlinedTextField(
                    value = serverUrl,
                    onValueChange = { serverUrl = it; serverSaved = false },
                    modifier = Modifier.fillMaxWidth(),
                    placeholder = { Text("https://twojserwer.pl/fm/", fontFamily = FontFamily.Monospace, color = TextTert, fontSize = 12.sp) },
                    singleLine = true,
                    shape = RoundedCornerShape(8.dp),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Accent, unfocusedBorderColor = Border),
                    textStyle = androidx.compose.ui.text.TextStyle(fontSize = 12.sp, fontFamily = FontFamily.Monospace),
                )
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(
                        onClick = {
                            vm.changeServerUrl(serverUrl)
                            serverSaved = true
                        },
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Accent),
                    ) { Text("Zapisz URL", fontSize = 13.sp) }
                    if (serverSaved) {
                        Text("✓ Zapisano", fontSize = 12.sp, color = GreenOk, modifier = Modifier.align(Alignment.CenterVertically), fontFamily = FontFamily.Monospace)
                    }
                }
                Spacer(Modifier.height(4.dp))
                Text("Domyślny: $DEFAULT_BASE_URL", fontSize = 10.sp, color = TextTert, fontFamily = FontFamily.Monospace)
            }
        }

        // ── ZMIEŃ HASŁO ───────────────────────────────────────────────────────
        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = White), elevation = CardDefaults.cardElevation(1.dp)) {
            Column(Modifier.padding(14.dp)) {
                Text("ZMIEŃ HASŁO", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp, modifier = Modifier.padding(bottom = 8.dp))
                OutlinedTextField(value = currentPass, onValueChange = { currentPass = it }, modifier = Modifier.fillMaxWidth(), placeholder = { Text("Aktualne hasło", color = TextTert) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), shape = RoundedCornerShape(8.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Accent, unfocusedBorderColor = Border))
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = newPass, onValueChange = { newPass = it }, modifier = Modifier.fillMaxWidth(), placeholder = { Text("Nowe hasło (min. 6 znaków)", color = TextTert) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), shape = RoundedCornerShape(8.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Accent, unfocusedBorderColor = Border))
                Spacer(Modifier.height(10.dp))
                Button(onClick = {}, shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = Accent), modifier = Modifier.fillMaxWidth()) {
                    Text("Zmień hasło", fontWeight = FontWeight.SemiBold)
                }
            }
        }

        // ── ADMIN ─────────────────────────────────────────────────────────────
        if (state.role == "admin") {
            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = White), elevation = CardDefaults.cardElevation(1.dp)) {
                Column {
                    Text("ADMINISTRACJA", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp, modifier = Modifier.padding(14.dp, 12.dp, 14.dp, 8.dp))
                    HorizontalDivider(color = Border, thickness = 0.5.dp)
                    ActionRow("▶ Uruchom sync", "Wymuś natychmiastową synchronizację", TextPrim) { vm.runSync() }
                    HorizontalDivider(color = Border, thickness = 0.5.dp)
                    ActionRow("✕ Wyczyść log", "Usuń wszystkie wpisy z dziennika", RedErr) {}
                }
            }
        }

        // ── WYLOGUJ ───────────────────────────────────────────────────────────
        OutlinedButton(
            onClick = { vm.logout() },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = RedErr),
            border = ButtonDefaults.outlinedButtonBorder,
        ) {
            Text("Wyloguj się", fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
        }

        Text("Last.fm Sync · ${state.serverUrl}", fontSize = 10.sp, color = TextTert, fontFamily = FontFamily.Monospace, modifier = Modifier.align(Alignment.CenterHorizontally))
        Spacer(Modifier.height(80.dp))
    }
}

@Composable
fun InfoRow(label: String, value: String, valueColor: androidx.compose.ui.graphics.Color = TextPrim) {
    Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Text(label, fontSize = 14.sp, color = TextSec)
        Text(value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = valueColor)
    }
}

@Composable
fun ActionRow(title: String, subtitle: String, titleColor: androidx.compose.ui.graphics.Color = TextPrim, onClick: () -> Unit) {
    Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = titleColor)
            Text(subtitle, fontSize = 12.sp, color = TextTert)
        }
        TextButton(onClick = onClick) { Text("→", color = titleColor) }
    }
}
