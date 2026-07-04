package com.example.photobooth.data

import com.google.gson.annotations.SerializedName

data class FrameConfig(
    @SerializedName("version") val version: Int,
    @SerializedName("events") val events: List<EventInfo>? = null,
    @SerializedName("frames") val frames: List<Frame>
)

data class EventInfo(
    @SerializedName("id") val id: String,
    @SerializedName("name") val name: String,
    @SerializedName("code") val code: String,
    @SerializedName("subtitle") val subtitle: String? = null,
    @SerializedName("hashtag") val hashtag: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null,
    @SerializedName("secondary_color") val secondaryColor: String? = null
)

data class Frame(
    @SerializedName("id") val id: String,
    @SerializedName("name") val name: String,
    @SerializedName("type") val type: String, // "strip", "grid", etc.
    @SerializedName("width") val width: Int,
    @SerializedName("height") val height: Int,
    @SerializedName("background_color") val backgroundColor: String,
    @SerializedName("image_url") val imageUrl: String, // Relative URL
    @SerializedName("slots") val slots: List<Slot>,
    @SerializedName("event_id") val eventId: String? = "general",
    @SerializedName("category") val category: String? = "Classic",
    @SerializedName("print_flows") val printFlows: List<String>? = null,
    @SerializedName("is_dynamic") val isDynamic: Boolean? = false,
    @SerializedName("dynamic_elements") val dynamicElements: DynamicElements? = null
)

data class Slot(
    @SerializedName("index") val index: Int,
    @SerializedName("x") val x: Int,
    @SerializedName("y") val y: Int,
    @SerializedName("width") val width: Int,
    @SerializedName("height") val height: Int,
    @SerializedName("rotation") val rotation: Float? = 0f
)

data class DynamicElements(
    @SerializedName("logo") val logo: ElementRect? = null,
    @SerializedName("texts") val texts: List<DynamicText>? = null
)

data class ElementRect(
    @SerializedName("x") val x: Int,
    @SerializedName("y") val y: Int,
    @SerializedName("width") val width: Int,
    @SerializedName("height") val height: Int,
    @SerializedName("align") val align: String? = "center" // "left", "center", "right"
)

data class DynamicText(
    @SerializedName("type") val type: String, // "event_name", "event_subtitle", "event_hashtag"
    @SerializedName("x") val x: Int,
    @SerializedName("y") val y: Int,
    @SerializedName("font_size") val fontSize: Int,
    @SerializedName("font_style") val fontStyle: String? = "normal", // "normal", "bold", "italic", "bold_italic"
    @SerializedName("color") val color: String? = "#ffffff",
    @SerializedName("align") val align: String? = "center"
)
