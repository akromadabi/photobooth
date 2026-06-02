package com.example.photobooth.ui.camera

import android.content.Context
import android.speech.tts.TextToSpeech
import java.util.*

class VoiceManager(context: Context) : TextToSpeech.OnInitListener {
    private var tts: TextToSpeech? = null
    private var isInitialized = false
    private val speechQueue = LinkedList<String>()

    init {
        tts = TextToSpeech(context, this)
    }

    override fun onInit(status: Int) {
        if (status == TextToSpeech.SUCCESS) {
            val idLocale = Locale("id", "ID")
            val result = tts?.setLanguage(idLocale)
            if (result != TextToSpeech.LANG_MISSING_DATA && result != TextToSpeech.LANG_NOT_SUPPORTED) {
                isInitialized = true
                // Optimizations for a cheerful, friendly voice
                tts?.setPitch(1.05f) // Slightly higher pitch for friendliness
                tts?.setSpeechRate(1.05f) // Slightly faster natural pacing
                
                // Flush queue
                while (speechQueue.isNotEmpty()) {
                    val text = speechQueue.poll()
                    if (text != null) {
                        speakDirectly(text)
                    }
                }
            }
        }
    }

    fun speak(text: String) {
        if (isInitialized) {
            speakDirectly(text)
        } else {
            speechQueue.add(text)
        }
    }

    private fun speakDirectly(text: String) {
        tts?.speak(text, TextToSpeech.QUEUE_FLUSH, null, "KioskVoiceGuidance")
    }

    fun shutdown() {
        tts?.stop()
        tts?.shutdown()
        isInitialized = false
    }
}
