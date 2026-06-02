package com.example.photobooth.api

import com.example.photobooth.data.FrameConfig
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

interface PhotoboothApi {

    @GET
    suspend fun getFrameConfig(@Url configUrl: String): Response<FrameConfig>

    @Multipart
    @POST("upload.php")
    suspend fun uploadPhotos(
        @Part photo: MultipartBody.Part,
        @Part timelapse: MultipartBody.Part? = null
    ): Response<UploadResponse>
}
