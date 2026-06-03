package com.example.photobooth

import androidx.navigation3.runtime.NavKey
import kotlinx.serialization.Serializable

@Serializable data object Home : NavKey
@Serializable data object Admin : NavKey
@Serializable data class LayoutSelect(val eventId: String = "general") : NavKey
@Serializable data class FrameSelect(val layoutType: String, val eventId: String = "general") : NavKey
@Serializable data class CameraCapture(
    val frameId: String,
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = ""
) : NavKey
@Serializable data class PreviewResult(
    val photoPaths: List<String>,
    val frameId: String,
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = ""
) : NavKey
@Serializable data class SharePrint(
    val finalPhotoPath: String,
    val shouldPrint: Boolean,
    val frameId: String = "",
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = ""
) : NavKey
