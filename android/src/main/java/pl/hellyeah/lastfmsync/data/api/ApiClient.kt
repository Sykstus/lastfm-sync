package pl.hellyeah.lastfmsync.data.api

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

const val DEFAULT_BASE_URL = "https://hellyeah-design.pl/fm/"

val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "lfm_prefs")

object PrefKeys {
    val TOKEN      = stringPreferencesKey("api_token")
    val USERNAME   = stringPreferencesKey("username")
    val ROLE       = stringPreferencesKey("role")
    val LFM_USER   = stringPreferencesKey("lastfm_user")
    val SERVER_URL = stringPreferencesKey("server_url")
}

class TokenStore(private val context: Context) {
    val token:     Flow<String?> = context.dataStore.data.map { it[PrefKeys.TOKEN] }
    val username:  Flow<String?> = context.dataStore.data.map { it[PrefKeys.USERNAME] }
    val role:      Flow<String?> = context.dataStore.data.map { it[PrefKeys.ROLE] }
    val serverUrl: Flow<String?> = context.dataStore.data.map { it[PrefKeys.SERVER_URL] }

    suspend fun save(token: String, username: String, role: String, lfmUser: String?) {
        context.dataStore.edit { prefs ->
            prefs[PrefKeys.TOKEN]    = token
            prefs[PrefKeys.USERNAME] = username
            prefs[PrefKeys.ROLE]     = role
            if (lfmUser != null) prefs[PrefKeys.LFM_USER] = lfmUser
        }
    }

    suspend fun saveServerUrl(url: String) {
        context.dataStore.edit { it[PrefKeys.SERVER_URL] = url.trimEnd('/') + "/" }
    }

    suspend fun clear() {
        context.dataStore.edit { prefs ->
            // Zachowaj SERVER_URL przy wylogowaniu
            val url = prefs[PrefKeys.SERVER_URL]
            prefs.clear()
            if (url != null) prefs[PrefKeys.SERVER_URL] = url
        }
    }

    suspend fun getToken(): String? = token.first()
    suspend fun rawToken(): String = getToken() ?: ""
    suspend fun getServerUrl(): String = serverUrl.first() ?: DEFAULT_BASE_URL
}

object ApiClient {
    private val json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
    }

    private val okhttp = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .addInterceptor(HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        })
        .build()

    // Tworzy serwis z dynamicznym URL
    fun buildService(baseUrl: String): ApiService = Retrofit.Builder()
        .baseUrl(baseUrl)
        .client(okhttp)
        .addConverterFactory(json.asConverterFactory("application/json; charset=UTF8".toMediaType()))
        .build()
        .create(ApiService::class.java)

    // Domyślny serwis (używany przed załadowaniem URL z DataStore)
    val service: ApiService = buildService(DEFAULT_BASE_URL)
}
