package com.example.photobooth

import android.os.Bundle
import android.content.Context
import android.content.SharedPreferences
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.runtime.DisposableEffect
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.Modifier
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.lifecycleScope
import com.example.photobooth.api.NetworkClient
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.theme.PhotoboothTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Request Screen Pinning / Lock Task (Kiosk Mode)
        try {
            startLockTask()
        } catch (e: Exception) {
            e.printStackTrace()
        }

        enableEdgeToEdge()
        setContent {
            val context = LocalContext.current
            val prefs = remember { context.getSharedPreferences("photobooth_prefs", Context.MODE_PRIVATE) }
            var activeTheme by remember { mutableStateOf(prefs.getString("app_theme", "NEON_RED") ?: "NEON_RED") }

            DisposableEffect(prefs) {
                val listener = SharedPreferences.OnSharedPreferenceChangeListener { p, key ->
                    if (key == "app_theme") {
                        activeTheme = p.getString("app_theme", "NEON_RED") ?: "NEON_RED"
                    }
                }
                prefs.registerOnSharedPreferenceChangeListener(listener)
                onDispose {
                    prefs.unregisterOnSharedPreferenceChangeListener(listener)
                }
            }

            PhotoboothTheme(themeType = activeTheme) { 
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) { 
                    MainNavigation() 
                } 
            }
        }
    }

    override fun onResume() {
        super.onResume()
        
        // Re-request Lock Task (Kiosk Mode)
        try {
            startLockTask()
        } catch (e: Exception) {
            e.printStackTrace()
        }

        syncSettings()
        // Auto-select printer based on priority and readiness at startup/resume
        try {
            com.example.photobooth.print.PrinterAutoSelector.autoSelectActivePrinter(this)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun syncSettings() {
        val configManager = ConfigManager(this)
        val url = configManager.backendUrl
        if (url.isNotEmpty()) {
            lifecycleScope.launch(Dispatchers.IO) {
                try {
                    val api = NetworkClient.getApi(url)
                    val response = api.getKioskSettings()
                    if (response.isSuccessful && response.body() != null) {
                        val serverConfig = response.body()!!
                        withContext(Dispatchers.Main) {
                            serverConfig.adminPin?.let { if (it.isNotEmpty()) configManager.adminPin = it }
                            serverConfig.countdownSeconds?.let { configManager.countdownSeconds = it }
                            serverConfig.totalShots?.let { configManager.totalShots = it }
                            serverConfig.printerType?.let { if (it.isNotEmpty() && it != "NONE") configManager.printerType = it }
                            serverConfig.useBiometric?.let { configManager.useBiometric = it }
                            serverConfig.appTheme?.let { if (it.isNotEmpty()) configManager.appTheme = it }
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }
}

