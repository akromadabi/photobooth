package com.example.photobooth

import androidx.navigation3.runtime.NavKey
import kotlinx.serialization.Serializable

@Serializable data object Home : NavKey
@Serializable data object Admin : NavKey
@Serializable data class PackageSelect(val eventId: String = "general") : NavKey
@Serializable data class CharacterSelect(val eventId: String = "general") : NavKey
@Serializable data class FrameSelect(val packageId: String, val eventId: String = "general") : NavKey
@Serializable data class CameraCapture(
    val frameId: String,
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = "",
    val characterId: String = ""
) : NavKey
@Serializable data class PreviewResult(
    val photoPaths: List<String>,
    val frameId: String,
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = "",
    val characterId: String = ""
) : NavKey
@Serializable data class SharePrint(
    val finalPhotoPath: String,
    val shouldPrint: Boolean,
    val frameId: String = "",
    val eventId: String = "general",
    val sessionId: String = "",
    val packageId: String = "",
    val characterId: String = ""
) : NavKey
