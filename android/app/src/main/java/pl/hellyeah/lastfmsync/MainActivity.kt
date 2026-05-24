package pl.hellyeah.lastfmsync

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import pl.hellyeah.lastfmsync.ui.screens.*
import pl.hellyeah.lastfmsync.ui.theme.*
import pl.hellyeah.lastfmsync.viewmodel.MainViewModel

sealed class Screen(val route: String, val label: String, val icon: String) {
    object Dashboard  : Screen("dashboard",  "Dashboard",  "◎")
    object NowPlaying : Screen("nowplaying", "Teraz",      "▶")
    object Scrobbles  : Screen("scrobbles",  "Scroble",    "♪")
    object Settings   : Screen("settings",   "Ustawienia", "⚙")
}

val screens = listOf(Screen.Dashboard, Screen.NowPlaying, Screen.Scrobbles, Screen.Settings)

class MainActivity : ComponentActivity() {

    private val vm: MainViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            LfmSyncTheme {
                val state by vm.state.collectAsState()

                when {
                    state.isLoading -> LoadingScreen()
                    !state.isLoggedIn -> LoginScreen(vm)
                    else -> MainApp(vm)
                }
            }
        }
    }
}

@Composable
fun LoadingScreen() {
    Surface(Modifier.fillMaxSize(), color = Cream) {
        androidx.compose.foundation.layout.Box(
            Modifier.fillMaxSize(),
            contentAlignment = androidx.compose.ui.Alignment.Center,
        ) {
            CircularProgressIndicator(color = Accent)
        }
    }
}

@Composable
fun MainApp(vm: MainViewModel) {
    val navController = rememberNavController()
    val state by vm.state.collectAsState()

    Scaffold(
        bottomBar = {
            NavigationBar(containerColor = White, tonalElevation = 0.dp) {
                val navBackStackEntry by navController.currentBackStackEntryAsState()
                val current = navBackStackEntry?.destination
                screens.forEach { screen ->
                    val selected = current?.hierarchy?.any { it.route == screen.route } == true
                    NavigationBarItem(
                        selected = selected,
                        onClick  = {
                            navController.navigate(screen.route) {
                                popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                                launchSingleTop = true
                                restoreState    = true
                            }
                        },
                        icon  = { Text(screen.icon, fontSize = if (selected) 18.sp else 16.sp) },
                        label = { Text(screen.label, fontSize = 10.sp, fontFamily = FontFamily.Monospace) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor       = Accent,
                            selectedTextColor       = Accent,
                            indicatorColor          = White,
                            unselectedIconColor     = TextTert,
                            unselectedTextColor     = TextTert,
                        ),
                    )
                }
            }
        }
    ) { padding ->
        NavHost(
            navController    = navController,
            startDestination = Screen.Dashboard.route,
            modifier         = Modifier.padding(padding).fillMaxSize(),
        ) {
            composable(Screen.Dashboard.route)  { DashboardScreen(vm, state) }
            composable(Screen.NowPlaying.route) { NowPlayingScreen(vm, state) }
            composable(Screen.Scrobbles.route)  { ScrobblesScreen(vm, state) }
            composable(Screen.Settings.route)   { SettingsScreen(vm, state) }
        }
    }
}
