package com.example.photobooth.api

import com.example.photobooth.data.FrameConfig
import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Url

data class UploadResponse(
    val success: Boolean,
    val session_id: String,
    val download_url: String?,
    val photo_url: String?,
    val timelapse_url: String?,
    val message: String
)

data class HistoryItem(
    @SerializedName("id") val id: String,
    @SerializedName("photo_url") val photoUrl: String,
    @SerializedName("timelapse_url") val timelapseUrl: String?,
    @SerializedName("download_url") val downloadUrl: String,
    @SerializedName("timestamp") val timestamp: Long
)

interface PhotoboothApi {

    @GET
    suspend fun getFrameConfig(@Url configUrl: String): Response<FrameConfig>

    @Multipart
    @POST("upload.php")
    suspend fun uploadPhotos(
        @Part photo: MultipartBody.Part,
        @Part timelapse: MultipartBody.Part? = null
    ): Response<UploadResponse>

    @GET("history.php")
    suspend fun getPhotoHistory(): Response<List<HistoryItem>>
}
