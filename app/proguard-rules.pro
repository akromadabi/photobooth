# Gson specific rules to preserve reflection mappings
-keepattributes Signature, *Annotation*, EnclosingMethod, InnerClasses
-keep class com.google.gson.** { *; }

# Retain API endpoint methods and request/response serializable data structures
-keep class com.example.photobooth.api.** { *; }
-keep class com.example.photobooth.data.** { *; }

# Keep models annotated with @Keep to prevent obfuscation/renaming
-keep @androidx.annotation.Keep class * { *; }
-keepclassmembers class * {
    @androidx.annotation.Keep *;
}
