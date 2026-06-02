package com.example.photobooth

import androidx.navigation3.runtime.NavKey
import kotlinx.serialization.Serializable

@Serializable data object Home : NavKey
@Serializable data object Admin : NavKey
@Serializable data object LayoutSelect : NavKey
@Serializable data class FrameSelect(val layoutType: String) : NavKey
@Serializable data class CameraCapture(val frameId: String) : NavKey
@Serializable data class PreviewResult(val photoPaths: List<String>, val frameId: String) : NavKey
@Serializable data class SharePrint(val finalPhotoPath: String, val shouldPrint: Boolean) : NavKey
