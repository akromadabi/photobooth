package com.example.photobooth.data

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import androidx.core.content.FileProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.io.FileOutputStream
import java.io.InputStream
import java.net.URL
import com.google.gson.Gson

data class UpdateInfo(
    val versionCode: Int,
    val versionName: String,
    val apkUrl: String,
    val changeLog: String
)

class UpdateManager(private val context: Context) {

    /**
     * Gets the current versionCode of the installed app.
     */
    fun getCurrentVersionCode(): Int {
        return try {
            val pInfo = context.packageManager.getPackageInfo(context.packageName, 0)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                pInfo.longVersionCode.toInt()
            } else {
                @Suppress("DEPRECATION")
                pInfo.versionCode
            }
        } catch (e: Exception) {
            0
        }
    }

    /**
     * Gets the current versionName of the installed app.
     */
    fun getCurrentVersionName(): String {
        return try {
            val pInfo = context.packageManager.getPackageInfo(context.packageName, 0)
            pInfo.versionName ?: "1.0.0"
        } catch (e: Exception) {
            "1.0.0"
        }
    }

    /**
     * Checks if an update is available on the server by reading the update.json metadata.
     */
    suspend fun checkUpdate(backendUrl: String): UpdateInfo? = withContext(Dispatchers.IO) {
        try {
            val sanitizedUrl = if (backendUrl.endsWith("/")) backendUrl else "$backendUrl/"
            val url = URL("${sanitizedUrl}update.json")
            val connection = url.openConnection()
            connection.connectTimeout = 10000
            connection.readTimeout = 10000
            val jsonText = connection.getInputStream().bufferedReader().use { it.readText() }
            Gson().fromJson(jsonText, UpdateInfo::class.java)
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }

    /**
     * Clean up any previously downloaded APKs to prevent storage bloat.
     */
    fun clearApkCache() {
        try {
            val apkFile = File(context.cacheDir, "update_app.apk")
            if (apkFile.exists()) {
                apkFile.delete()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    /**
     * Downloads the APK file from the server.
     * Overwrites any existing "update_app.apk" in the cache to avoid disk bloat.
     */
    suspend fun downloadApk(
        apkUrlString: String,
        onProgress: (Float) -> Unit
    ): File? = withContext(Dispatchers.IO) {
        try {
            // Delete old download first to avoid double storage bloat
            clearApkCache()

            val client = OkHttpClient.Builder().build()
            val request = Request.Builder().url(apkUrlString).build()
            val response = client.newCall(request).execute()
            if (!response.isSuccessful) return@withContext null

            val body = response.body ?: return@withContext null
            val contentLength = body.contentLength()
            val inputStream = body.byteStream()

            val apkFile = File(context.cacheDir, "update_app.apk")
            val outputStream = FileOutputStream(apkFile)
            val buffer = ByteArray(4096)
            var bytesRead: Int
            var totalBytesRead = 0L

            while (inputStream.read(buffer).also { bytesRead = it } != -1) {
                outputStream.write(buffer, 0, bytesRead)
                totalBytesRead += bytesRead
                if (contentLength > 0) {
                    val progress = totalBytesRead.toFloat() / contentLength.toFloat()
                    withContext(Dispatchers.Main) {
                        onProgress(progress)
                    }
                }
            }

            outputStream.flush()
            outputStream.close()
            inputStream.close()
            apkFile
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }

    /**
     * Launches the Android Package Installer for the downloaded APK.
     */
    fun installApk(apkFile: File) {
        try {
            val authority = "${context.packageName}.fileprovider"
            val uri: Uri = FileProvider.getUriForFile(context, authority, apkFile)

            val intent = Intent(Intent.ACTION_VIEW).apply {
                setDataAndType(uri, "application/vnd.android.package-archive")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
            }
            context.startActivity(intent)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    /**
     * Checks whether the app has permission to install packages from unknown sources.
     * (Always returns true on SDK < 26)
     */
    fun canRequestPackageInstalls(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.packageManager.canRequestPackageInstalls()
        } else {
            true
        }
    }

    /**
     * Opens the system settings screen to allow installing from unknown sources.
     */
    fun openInstallPermissionSettings() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val intent = Intent(android.provider.Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES).apply {
                data = Uri.parse("package:${context.packageName}")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            context.startActivity(intent)
        }
    }
}
