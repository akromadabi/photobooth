package com.example.photobooth.data

import android.content.Context
import android.content.SharedPreferences

class ConfigManager(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("photobooth_prefs", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_ADMIN_PIN = "admin_pin"
        private const val KEY_BACKEND_URL = "backend_url"
        private const val KEY_PRINTER_TYPE = "printer_type" // "NONE", "THERMAL", "COLOR"
        private const val KEY_PRINTER_ADDRESS = "printer_address" // USB VID:PID or BT MAC
        private const val KEY_COUNTDOWN_SECONDS = "countdown_seconds"
        private const val KEY_TOTAL_SHOTS = "total_shots"
        private const val KEY_SYNCED_FRAMES_JSON = "synced_frames_json"
    }

    var adminPin: String
        get() = prefs.getString(KEY_ADMIN_PIN, "1234") ?: "1234"
        set(value) = prefs.edit().putString(KEY_ADMIN_PIN, value).apply()

    var backendUrl: String
        get() = prefs.getString(KEY_BACKEND_URL, "http://localhost/") ?: "http://localhost/"
        set(value) {
            val sanitized = if (value.endsWith("/")) value else "$value/"
            prefs.edit().putString(KEY_BACKEND_URL, sanitized).apply()
        }

    var printerType: String
        get() = prefs.getString(KEY_PRINTER_TYPE, "NONE") ?: "NONE"
        set(value) = prefs.edit().putString(KEY_PRINTER_TYPE, value).apply()

    var printerAddress: String
        get() = prefs.getString(KEY_PRINTER_ADDRESS, "") ?: ""
        set(value) = prefs.edit().putString(KEY_PRINTER_ADDRESS, value).apply()

    var countdownSeconds: Int
        get() = prefs.getInt(KEY_COUNTDOWN_SECONDS, 5)
        set(value) = prefs.edit().putInt(KEY_COUNTDOWN_SECONDS, value).apply()

    var totalShots: Int
        get() = prefs.getInt(KEY_TOTAL_SHOTS, 4)
        set(value) = prefs.edit().putInt(KEY_TOTAL_SHOTS, value).apply()

    var syncedFramesJson: String
        get() = prefs.getString(KEY_SYNCED_FRAMES_JSON, "") ?: ""
        set(value) = prefs.edit().putString(KEY_SYNCED_FRAMES_JSON, value).apply()
}
