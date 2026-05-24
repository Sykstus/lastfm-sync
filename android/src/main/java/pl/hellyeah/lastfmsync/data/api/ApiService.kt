package pl.hellyeah.lastfmsync.data.api

import pl.hellyeah.lastfmsync.data.model.*
import retrofit2.http.*

interface ApiService {

    @POST("api.php?endpoint=login")
    suspend fun login(@Body request: LoginRequest): ApiResponse<LoginResponse>

    @POST("api.php?endpoint=logout")
    suspend fun logout(@Query("token") token: String): ApiResponse<Map<String, String>>

    @GET("api.php?endpoint=me")
    suspend fun getMe(@Query("token") token: String): ApiResponse<User>

    @GET("api.php?endpoint=dashboard")
    suspend fun getDashboard(@Query("token") token: String): ApiResponse<DashboardData>

    @GET("api.php?endpoint=nowplaying")
    suspend fun getNowPlaying(@Query("token") token: String): ApiResponse<NowPlayingData>

    @GET("api.php?endpoint=logs")
    suspend fun getLogs(
        @Query("token") token: String,
        @Query("limit") limit: Int = 50,
    ): ApiResponse<List<LogEntry>>

    @GET("api.php?endpoint=runs")
    suspend fun getRuns(
        @Query("token") token: String,
        @Query("limit") limit: Int = 10,
    ): ApiResponse<List<SyncRun>>

    @GET("api.php?endpoint=scrobbles")
    suspend fun getScrobbles(
        @Query("token") token: String,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 30,
        @Query("dir") dir: String = "all",
        @Query("artist") artist: String = "",
    ): ApiResponse<ScrobblesPage>

    @GET("api.php?endpoint=top_artists")
    suspend fun getTopArtists(
        @Query("token") token: String,
        @Query("days") days: Int = 30,
    ): ApiResponse<TopArtistsData>

    @GET("api.php?endpoint=pause_status")
    suspend fun getPauseStatus(@Query("token") token: String): ApiResponse<PauseStatus>

    @POST("api.php?endpoint=pause")
    suspend fun pause(
        @Query("token") token: String,
        @Body request: PauseRequest,
    ): ApiResponse<Map<String, String>>

    @POST("api.php?endpoint=resume")
    suspend fun resume(
        @Query("token") token: String,
        @Body request: PauseRequest = PauseRequest(),
    ): ApiResponse<Map<String, String>>

    @POST("api.php?endpoint=sync")
    suspend fun runSync(@Query("token") token: String): ApiResponse<SyncResult>
}

