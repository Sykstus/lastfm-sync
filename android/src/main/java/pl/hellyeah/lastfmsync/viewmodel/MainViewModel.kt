package pl.hellyeah.lastfmsync.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import pl.hellyeah.lastfmsync.data.api.ApiClient
import pl.hellyeah.lastfmsync.data.api.TokenStore
import pl.hellyeah.lastfmsync.data.api.DEFAULT_BASE_URL
import pl.hellyeah.lastfmsync.data.model.*

data class AppUiState(
    val isLoggedIn: Boolean = false,
    val isLoading: Boolean = true,
    val username: String = "",
    val role: String = "",
    val lfmUser: String? = null,
    val serverUrl: String = DEFAULT_BASE_URL,
    val dashboard: DashboardData? = null,
    val nowPlaying: NowPlayingData? = null,
    val pauseStatus: PauseStatus? = null,
    val recentRuns: List<SyncRun> = emptyList(),
    val logs: List<LogEntry> = emptyList(),
    val scrobbles: ScrobblesPage? = null,
    val scrobblesPage: Int = 1,
    val scrobblesDir: String = "all",
    val syncResult: String? = null,
    val error: String? = null,
)

class MainViewModel(application: Application) : AndroidViewModel(application) {

    private val tokenStore = TokenStore(application)
    private var api = ApiClient.service

    private val _state = MutableStateFlow(AppUiState())
    val state: StateFlow<AppUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            // Wczytaj URL serwera
            val savedUrl = tokenStore.getServerUrl()
            _state.update { it.copy(serverUrl = savedUrl) }
            api = ApiClient.buildService(savedUrl)

            // Sprawdź zapisany token
            val token = tokenStore.getToken()
            if (token != null) {
                try {
                    val me = api.getMe(token)
                    if (me.success && me.data != null) {
                        _state.update {
                            it.copy(
                                isLoggedIn = true,
                                isLoading  = false,
                                username   = me.data.username,
                                role       = me.data.role,
                                lfmUser    = me.data.lastfmUser,
                            )
                        }
                        refreshAll()
                        startAutoRefresh()
                        return@launch
                    }
                } catch (e: Exception) {}
                tokenStore.clear()
            }
            _state.update { it.copy(isLoading = false) }
        }
    }

    // ─── ZMIANA SERWERA ───────────────────────────────────────────────────────

    fun changeServerUrl(url: String) {
        val clean = url.trim().trimEnd('/') + "/"
        viewModelScope.launch {
            tokenStore.saveServerUrl(clean)
            api = ApiClient.buildService(clean)
            _state.update { it.copy(serverUrl = clean) }
        }
    }

    // ─── AUTH ─────────────────────────────────────────────────────────────────

    fun login(username: String, password: String, onError: (String) -> Unit) {
        viewModelScope.launch {
            _state.update { it.copy(isLoading = true) }
            try {
                val r = api.login(LoginRequest(username, password))
                if (r.success && r.data != null) {
                    tokenStore.save(r.data.token, r.data.user.username, r.data.user.role, r.data.user.lastfmUser)
                    _state.update {
                        it.copy(
                            isLoggedIn = true,
                            isLoading  = false,
                            username   = r.data.user.username,
                            role       = r.data.user.role,
                            lfmUser    = r.data.user.lastfmUser,
                        )
                    }
                    refreshAll()
                    startAutoRefresh()
                } else {
                    _state.update { it.copy(isLoading = false) }
                    onError(r.error ?: "Nieprawidłowe dane logowania")
                }
            } catch (e: Exception) {
                _state.update { it.copy(isLoading = false) }
                onError("Błąd połączenia: ${e.message}")
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            try { api.logout(tokenStore.rawToken()) } catch (e: Exception) {}
            tokenStore.clear()
            _state.update { AppUiState(isLoading = false, serverUrl = _state.value.serverUrl) }
        }
    }

    // ─── REFRESH ──────────────────────────────────────────────────────────────

    fun refreshAll() {
        viewModelScope.launch {
            refreshDashboard()
            refreshNowPlaying()
            refreshPauseStatus()
            refreshLogs()
            refreshRuns()
        }
    }

    private suspend fun refreshDashboard() {
        try {
            val r = api.getDashboard(tokenStore.rawToken())
            if (r.success) _state.update { it.copy(dashboard = r.data) }
        } catch (e: Exception) {}
    }

    fun refreshNowPlaying() {
        viewModelScope.launch {
            try {
                val r = api.getNowPlaying(tokenStore.rawToken())
                if (r.success) _state.update { it.copy(nowPlaying = r.data) }
            } catch (e: Exception) {}
        }
    }

    fun refreshPauseStatus() {
        viewModelScope.launch {
            try {
                val r = api.getPauseStatus(tokenStore.rawToken())
                if (r.success) _state.update { it.copy(pauseStatus = r.data) }
            } catch (e: Exception) {}
        }
    }

    private suspend fun refreshLogs() {
        try {
            val r = api.getLogs(tokenStore.rawToken(), 60)
            if (r.success) _state.update { it.copy(logs = r.data ?: emptyList()) }
        } catch (e: Exception) {}
    }

    private suspend fun refreshRuns() {
        try {
            val r = api.getRuns(tokenStore.rawToken(), 4)
            if (r.success) _state.update { it.copy(recentRuns = r.data ?: emptyList()) }
        } catch (e: Exception) {}
    }

    fun loadScrobbles(page: Int = 1, dir: String = "all") {
        viewModelScope.launch {
            try {
                val r = api.getScrobbles(tokenStore.rawToken(), page = page, dir = dir)
                if (r.success) _state.update { it.copy(scrobbles = r.data, scrobblesPage = page, scrobblesDir = dir) }
            } catch (e: Exception) {}
        }
    }

    // ─── ACTIONS ──────────────────────────────────────────────────────────────

    fun runSync() {
        viewModelScope.launch {
            try {
                val r = api.runSync(tokenStore.rawToken())
                val msg = when {
                    r.data?.status == "ok"     -> "✓ Sync OK · A→B: ${r.data.a2b} · B→A: ${r.data.b2a}"
                    r.data?.status == "paused" -> "⏸ Sync jest wstrzymany"
                    r.data?.status == "locked" -> "⏳ Cron właśnie działa"
                    else -> "Błąd: ${r.data?.msg ?: r.error}"
                }
                _state.update { it.copy(syncResult = msg) }
                delay(500)
                refreshAll()
            } catch (e: Exception) {
                _state.update { it.copy(syncResult = "Błąd: ${e.message}") }
            }
            delay(4000)
            _state.update { it.copy(syncResult = null) }
        }
    }

    fun pauseSync(reason: String) {
        viewModelScope.launch {
            try {
                api.pause(tokenStore.rawToken(), PauseRequest(reason))
                refreshPauseStatus()
                refreshLogs()
            } catch (e: Exception) {}
        }
    }

    fun resumeSync() {
        viewModelScope.launch {
            try {
                api.resume(tokenStore.rawToken())
                refreshPauseStatus()
                refreshLogs()
            } catch (e: Exception) {}
        }
    }

    // ─── AUTO REFRESH ─────────────────────────────────────────────────────────

    private fun startAutoRefresh() {
        viewModelScope.launch {
            while (true) {
                delay(15_000)
                if (_state.value.isLoggedIn) refreshNowPlaying()
            }
        }
        viewModelScope.launch {
            while (true) {
                delay(30_000)
                if (_state.value.isLoggedIn) {
                    refreshDashboard()
                    refreshLogs()
                    refreshRuns()
                    refreshPauseStatus()
                }
            }
        }
    }
}
