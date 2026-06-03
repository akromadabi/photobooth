package com.example.photobooth

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
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

        enableEdgeToEdge()
        setContent {
            PhotoboothTheme { 
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) { 
                    MainNavigation() 
                } 
            }
        }
    }

    override fun onResume() {
        super.onResume()
        syncSettings()
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
                            serverConfig.printerType?.let { if (it.isNotEmpty()) configManager.printerType = it }
                            serverConfig.useBiometric?.let { configManager.useBiometric = it }
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }
}

