package com.example.photobooth.api

import android.content.Context
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.net.URL

object CatalogSync {
    suspend fun syncFramesFromBackend(context: Context, baseUrl: String, configManager: ConfigManager): String {
        return withContext(Dispatchers.IO) {
            try {
                val finalUrl = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
                val api = NetworkClient.getApi(finalUrl)
                
                // Fetch JSON configuration
                val response = api.getFrameConfig("frames/config.json")
                if (!response.isSuccessful || response.body() == null) {
                    return@withContext "Gagal mengunduh config.json: Code ${response.code()}"
                }
                
                val frameConfig = response.body()!!
                
                // Save config JSON to SharedPreferences
                val gson = com.google.gson.Gson()
                val jsonStr = gson.toJson(frameConfig)
                configManager.syncedFramesJson = jsonStr
                
                // Download all frame files
                val framesDir = File(context.cacheDir, "frames")
                if (!framesDir.exists()) framesDir.mkdirs()
                
                for (frame in frameConfig.frames) {
                    val relativePath = frame.imageUrl // e.g. "frames/classic_strip_black.png"
                    val fileUrl = URL("$finalUrl$relativePath")
                    val connection = fileUrl.openConnection()
                    connection.connectTimeout = 10000
                    connection.readTimeout = 15000
                    
                    val localFile = File(framesDir, frame.id + ".png")
                    
                    connection.getInputStream().use { input ->
                        FileOutputStream(localFile).use { output ->
                            input.copyTo(output)
                        }
                    }
                }
                
                "Sinkronisasi berhasil! Berhasil mendownload ${frameConfig.frames.size} bingkai secara offline."
            } catch (e: Exception) {
                "Kesalahan sinkronisasi: ${e.localizedMessage}"
            }
        }
    }
}
