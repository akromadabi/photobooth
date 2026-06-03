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
    @SerializedName("code") val code: String
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
    @SerializedName("event_id") val eventId: String? = "general"
)

data class Slot(
    @SerializedName("index") val index: Int,
    @SerializedName("x") val x: Int,
    @SerializedName("y") val y: Int,
    @SerializedName("width") val width: Int,
    @SerializedName("height") val height: Int
)
