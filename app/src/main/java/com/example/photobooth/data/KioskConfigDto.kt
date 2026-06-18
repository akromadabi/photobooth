package com.example.photobooth.data

import androidx.annotation.Keep
import com.google.gson.annotations.SerializedName

@Keep
data class KioskConfigDto(
    @SerializedName("admin_pin") val adminPin: String?,
    @SerializedName("countdown_seconds") val countdownSeconds: Int?,
    @SerializedName("total_shots") val totalShots: Int?,
    @SerializedName("printer_type") val printerType: String?,
    @SerializedName("use_biometric") val useBiometric: Boolean?,
    @SerializedName("app_theme") val appTheme: String?,
    @SerializedName("thermal_contrast") val thermalContrast: Float?,
    @SerializedName("thermal_brightness") val thermalBrightness: Float?,
    @SerializedName("thermal_sharpness") val thermalSharpness: Float?,
    @SerializedName("thermal_denoise") val thermalDenoise: Boolean?
)
