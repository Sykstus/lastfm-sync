package pl.hellyeah.lastfmsync.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// ─── AUTH ─────────────────────────────────────────────────────────────────────

@Serializable
data class LoginRequest(val username: String, val password: String, val device: String = "Android")

@Serializable
data class LoginResponse(val token: String, val user: User)

@Serializable
data class User(
    val id: Int,
    val username: String,
    @SerialName("lastfm_user") val lastfmUser: String? = null,
    val role: String,
)

// ─── API WRAPPER ──────────────────────────────────────────────────────────────

@Serializable
data class ApiResponse<T>(
    val success: Boolean,
    val data: T? = null,
    val error: String? = null,
)

// ─── DASHBOARD ────────────────────────────────────────────────────────────────

@Serializable
data class DashboardData(
    val totals: Totals,
    val today: Today,
    @SerialName("last_run") val lastRun: String? = null,
    @SerialName("last_scrobble") val lastScrobble: LastScrobble? = null,
    @SerialName("last_run_full") val lastRunFull: SyncRun? = null,
    val accounts: Accounts,
)

@Serializable
data class Totals(
    val a2b: Int = 0,
    val b2a: Int = 0,
    val runs: Int = 0,
    val total: Int = 0,
)

@Serializable
data class Today(val a2b: Int = 0, val b2a: Int = 0)

@Serializable
data class LastScrobble(
    val artist: String,
    val track: String,
    val direction: String,
    @SerialName("synced_at") val syncedAt: String,
)

@Serializable
data class Accounts(val a: String? = null, val b: String? = null)

// ─── SYNC RUN ─────────────────────────────────────────────────────────────────

@Serializable
data class SyncRun(
    val id: Int = 0,
    @SerialName("ran_at") val ranAt: String = "",
    @SerialName("np_a") val npA: Int = 0,
    @SerialName("np_b") val npB: Int = 0,
    @SerialName("synced_a2b") val syncedA2B: Int = 0,
    @SerialName("synced_b2a") val syncedB2A: Int = 0,
    val status: String = "ok",
    @SerialName("error_msg") val errorMsg: String? = null,
)

// ─── NOW PLAYING ──────────────────────────────────────────────────────────────

@Serializable
data class NowPlayingData(val a: NowPlayingAccount? = null, val b: NowPlayingAccount? = null)

@Serializable
data class NowPlayingAccount(
    val user: String,
    val nowplaying: Boolean = false,
    val artist: String = "",
    val track: String = "",
    val album: String = "",
    val image: String? = null,
    val date: String? = null,
    val error: String? = null,
)

// ─── LOG ──────────────────────────────────────────────────────────────────────

@Serializable
data class LogEntry(
    val id: Int = 0,
    @SerialName("logged_at") val loggedAt: String = "",
    val side: String = "",
    val message: String = "",
    val type: String = "",
)

// ─── PAUSE ────────────────────────────────────────────────────────────────────

@Serializable
data class PauseStatus(
    val paused: Boolean,
    val info: PauseInfo? = null,
)

@Serializable
data class PauseInfo(
    val by: String = "",
    val reason: String = "",
    val since: String = "",
)

@Serializable
data class PauseRequest(val reason: String = "")

// ─── SCROBBLE ─────────────────────────────────────────────────────────────────

@Serializable
data class Scrobble(
    val id: Int,
    val direction: String,
    val artist: String,
    val track: String,
    val album: String? = null,
    @SerialName("scrobbled_at") val scrobbledAt: String,
    @SerialName("synced_at") val syncedAt: String,
)

@Serializable
data class ScrobblesPage(
    val items: List<Scrobble>,
    val total: Int,
    val page: Int,
    @SerialName("per_page") val perPage: Int,
    val pages: Int,
)

// ─── TOP ARTISTS ──────────────────────────────────────────────────────────────

@Serializable
data class TopArtistsData(
    val a: List<ArtistCount>,
    val b: List<ArtistCount>,
)

@Serializable
data class ArtistCount(val artist: String, val cnt: Int)

// ─── SYNC RESULT ─────────────────────────────────────────────────────────────

@Serializable
data class SyncResult(
    val status: String,
    val a2b: Int = 0,
    val b2a: Int = 0,
    @SerialName("np_a") val npA: Boolean = false,
    @SerialName("np_b") val npB: Boolean = false,
    val msg: String? = null,
    val error: String? = null,
)
