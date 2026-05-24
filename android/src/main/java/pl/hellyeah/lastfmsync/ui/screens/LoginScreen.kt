package pl.hellyeah.lastfmsync.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel

@Composable
fun LoginScreen(vm: MainViewModel) {
    var username  by remember { mutableStateOf("") }
    var password  by remember { mutableStateOf("") }
    var serverUrl by remember { mutableStateOf(vm.state.value.serverUrl) }
    var isLoading by remember { mutableStateOf(false) }
    var errorMsg  by remember { mutableStateOf<String?>(null) }
    var showServerField by remember { mutableStateOf(false) }
    val focusManager = LocalFocusManager.current

    fun doLogin() {
        if (username.isBlank() || password.isBlank()) { errorMsg = "Wpisz login i hasło"; return }
        if (serverUrl.isBlank()) { errorMsg = "Wpisz adres serwera"; return }
        vm.changeServerUrl(serverUrl)
        isLoading = true
        errorMsg  = null
        vm.login(username.trim(), password) { err ->
            isLoading = false
            errorMsg  = err
        }
    }

    Box(
        Modifier
            .fillMaxSize()
            .background(Cream),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            Modifier
                .fillMaxWidth()
                .padding(horizontal = 28.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            // Logo
            Box(
                Modifier
                    .size(64.dp)
                    .background(Accent, RoundedCornerShape(18.dp)),
                contentAlignment = Alignment.Center,
            ) {
                Text("◎", fontSize = 28.sp, color = White)
            }
            Spacer(Modifier.height(16.dp))
            Text("Last.fm Sync", fontSize = 26.sp, fontWeight = FontWeight.Bold, color = TextPrim)
            Text("Panel synchronizacji scrobbli", fontSize = 13.sp, color = TextSec)
            Spacer(Modifier.height(32.dp))

            // Card
            Card(
                Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = White),
                elevation = CardDefaults.cardElevation(2.dp),
            ) {
                Column(Modifier.padding(22.dp)) {
                    errorMsg?.let {
                        Surface(
                            Modifier.fillMaxWidth(),
                            color = Color(0xFFFEF2F0),
                            shape = RoundedCornerShape(8.dp),
                        ) {
                            Text(it, Modifier.padding(10.dp), fontSize = 13.sp, color = RedErr, fontFamily = FontFamily.Monospace)
                        }
                        Spacer(Modifier.height(12.dp))
                    }

                    // URL SERWERA
                    Row(
                        Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text("SERWER", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp)
                        TextButton(onClick = { showServerField = !showServerField }, contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                            Text(if (showServerField) "Zwiń ↑" else "Zmień ↓", fontSize = 10.sp, color = Accent)
                        }
                    }
                    if (showServerField) {
                        Spacer(Modifier.height(4.dp))
                        OutlinedTextField(
                            value = serverUrl,
                            onValueChange = { serverUrl = it },
                            modifier = Modifier.fillMaxWidth(),
                            placeholder = { Text("https://twojserwer.pl/fm/", fontFamily = FontFamily.Monospace, color = TextTert, fontSize = 12.sp) },
                            singleLine = true,
                            shape = RoundedCornerShape(10.dp),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri, imeAction = ImeAction.Next),
                            keyboardActions = KeyboardActions(onNext = { focusManager.moveFocus(FocusDirection.Down) }),
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Accent, unfocusedBorderColor = Border),
                            textStyle = androidx.compose.ui.text.TextStyle(fontSize = 12.sp, fontFamily = FontFamily.Monospace),
                        )
                        Spacer(Modifier.height(10.dp))
                    } else {
                        Text(serverUrl.take(40) + if (serverUrl.length > 40) "..." else "", fontSize = 11.sp, fontFamily = FontFamily.Monospace, color = TextTert)
                        Spacer(Modifier.height(10.dp))
                    }

                    HorizontalDivider(color = Border, thickness = 0.5.dp)
                    Spacer(Modifier.height(12.dp))

                    Text("LOGIN", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp)
                    Spacer(Modifier.height(5.dp))
                    OutlinedTextField(
                        value = username,
                        onValueChange = { username = it },
                        modifier = Modifier.fillMaxWidth(),
                        placeholder = { Text("sykstus", fontFamily = FontFamily.Monospace, color = TextTert) },
                        singleLine = true,
                        shape = RoundedCornerShape(10.dp),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Next),
                        keyboardActions = KeyboardActions(onNext = { focusManager.moveFocus(FocusDirection.Down) }),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Accent,
                            unfocusedBorderColor = Border,
                        ),
                    )

                    Spacer(Modifier.height(14.dp))
                    Text("HASŁO", fontSize = 10.sp, fontFamily = FontFamily.Monospace, color = TextTert, letterSpacing = 1.sp)
                    Spacer(Modifier.height(5.dp))
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        modifier = Modifier.fillMaxWidth(),
                        placeholder = { Text("••••••••", color = TextTert) },
                        singleLine = true,
                        visualTransformation = PasswordVisualTransformation(),
                        shape = RoundedCornerShape(10.dp),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                        keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus(); doLogin() }),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Accent,
                            unfocusedBorderColor = Border,
                        ),
                    )

                    Spacer(Modifier.height(20.dp))
                    Button(
                        onClick = { focusManager.clearFocus(); doLogin() },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        enabled = !isLoading,
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Accent),
                    ) {
                        if (isLoading) CircularProgressIndicator(Modifier.size(20.dp), color = White, strokeWidth = 2.dp)
                        else Text("Zaloguj się →", fontWeight = FontWeight.SemiBold)
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
            Text("hellyeah-design.pl/fm", fontSize = 11.sp, color = TextTert, fontFamily = FontFamily.Monospace)
        }
    }
}
