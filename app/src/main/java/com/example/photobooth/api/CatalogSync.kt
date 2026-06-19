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
                
                // Download all event logo files if any
                val logosDir = File(context.cacheDir, "logos")
                if (!logosDir.exists()) logosDir.mkdirs()
                
                frameConfig.events?.forEach { event ->
                    val relativePath = event.logoUrl
                    if (!relativePath.isNullOrEmpty()) {
                        try {
                            val fileUrl = URL("$finalUrl$relativePath")
                            val connection = fileUrl.openConnection()
                            connection.connectTimeout = 10000
                            connection.readTimeout = 15000
                            
                            // We can use a fixed logo_{event.id}.png, or map it. Let's save it as logo_{event.id}.png
                            val localFile = File(logosDir, "logo_${event.id}.png")
                            
                            connection.getInputStream().use { input ->
                                FileOutputStream(localFile).use { output ->
                                    input.copyTo(output)
                                }
                            }
                        } catch (e: Exception) {
                            e.printStackTrace()
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
