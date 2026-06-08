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
        private const val KEY_USE_BIOMETRIC = "use_biometric"
        private const val KEY_ACTIVE_EVENT_ID = "active_event_id"
        private const val KEY_KIOSK_MODE = "kiosk_mode" // "DEDICATED" or "MULTI_EVENT"
        private const val KEY_THERMAL_MODE = "thermal_mode" // "TSPL" or "ESC_POS"
        private const val KEY_PAPER_WIDTH = "printer_paper_width"
        private const val KEY_PRINT_DENSITY = "print_density"
        private const val KEY_AUTO_CUT = "printer_auto_cut"
    }

    var printerPaperWidth: Int
        get() = prefs.getInt(KEY_PAPER_WIDTH, 80)
        set(value) = prefs.edit().putInt(KEY_PAPER_WIDTH, value).apply()

    var printDensity: Int
        get() = prefs.getInt(KEY_PRINT_DENSITY, 3)
        set(value) = prefs.edit().putInt(KEY_PRINT_DENSITY, value).apply()

    var printerAutoCut: Boolean
        get() = prefs.getBoolean(KEY_AUTO_CUT, true)
        set(value) = prefs.edit().putBoolean(KEY_AUTO_CUT, value).apply()

    var thermalMode: String
        get() = prefs.getString(KEY_THERMAL_MODE, "ESC_POS") ?: "ESC_POS"
        set(value) = prefs.edit().putString(KEY_THERMAL_MODE, value).apply()

    var activeEventId: String
        get() = prefs.getString(KEY_ACTIVE_EVENT_ID, "general") ?: "general"
        set(value) = prefs.edit().putString(KEY_ACTIVE_EVENT_ID, value).apply()

    var kioskMode: String
        get() = prefs.getString(KEY_KIOSK_MODE, "MULTI_EVENT") ?: "MULTI_EVENT"
        set(value) = prefs.edit().putString(KEY_KIOSK_MODE, value).apply()

    var adminPin: String
        get() = prefs.getString(KEY_ADMIN_PIN, "1234") ?: "1234"
        set(value) = prefs.edit().putString(KEY_ADMIN_PIN, value).apply()

    var backendUrl: String
        get() = prefs.getString(KEY_BACKEND_URL, "https://photobooth.siapp.in/") ?: "https://photobooth.siapp.in/"
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

    var useBiometric: Boolean
        get() = prefs.getBoolean(KEY_USE_BIOMETRIC, true)
        set(value) = prefs.edit().putBoolean(KEY_USE_BIOMETRIC, value).apply()
}
