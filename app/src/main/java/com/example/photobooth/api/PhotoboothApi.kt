package com.example.photobooth.api

import androidx.annotation.Keep
import com.example.photobooth.data.FrameConfig
import com.example.photobooth.data.KioskConfigDto
import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Url
import retrofit2.http.FormUrlEncoded
import retrofit2.http.Field

@Keep
data class UploadResponse(
    val success: Boolean,
    val session_id: String,
    val download_url: String?,
    val photo_url: String?,
    val timelapse_url: String?,
    val message: String
)

@Keep
data class HistoryItem(
    @SerializedName("id") val id: String,
    @SerializedName("photo_url") val photoUrl: String,
    @SerializedName("timelapse_url") val timelapseUrl: String?,
    @SerializedName("download_url") val downloadUrl: String,
    @SerializedName("timestamp") val timestamp: Long
)

@Keep
data class KioskCommandResponse(
    val success: Boolean,
    val active: Boolean,
    val session_id: String? = null,
    val queue_number: Int? = null,
    val status: String? = null,
    val command: String? = null,
    val frame_id: String? = null,
    val layout: String? = null,
    val package_id: String? = null
)

@Keep
data class GenericResponse(
    val success: Boolean,
    val message: String
)

interface PhotoboothApi {

    @GET
    suspend fun getFrameConfig(@Url configUrl: String): Response<FrameConfig>

    @Multipart
    @POST("upload.php")
    suspend fun uploadPhotos(
        @Part photo: MultipartBody.Part,
        @Part timelapse: MultipartBody.Part? = null,
        @retrofit2.http.Query("frame_id") frameId: String? = null,
        @retrofit2.http.Query("event_id") eventId: String? = null,
        @retrofit2.http.Query("session_id") sessionId: String? = null,
        @retrofit2.http.Query("package_id") packageId: String? = null
    ): Response<UploadResponse>

    @GET("history.php")
    suspend fun getPhotoHistory(): Response<List<HistoryItem>>

    @GET("settings.json")
    suspend fun getKioskSettings(): Response<KioskConfigDto>

    @GET("kiosk_control.php?action=get_command")
    suspend fun getKioskCommand(): Response<KioskCommandResponse>

    @FormUrlEncoded
    @POST("kiosk_control.php?action=complete_session")
    suspend fun completeSession(
        @Field("session_id") sessionId: String
    ): Response<GenericResponse>
}
