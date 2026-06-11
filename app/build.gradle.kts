plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.compose.compiler)
  alias(libs.plugins.kotlin.serialization)
}

android {
    namespace = "com.example.photobooth"
    compileSdk = 36
    defaultConfig {
        applicationId = "com.example.photobooth"
        minSdk = 24
        targetSdk = 36
        versionCode = 28
        versionName = "1.20.0"
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures {
      compose = true
      aidl = false
      buildConfig = false
      shaders = false
    }

    packaging {
      resources {
        excludes += "/META-INF/{AL2.0,LGPL2.1}"
      }
    }
}

kotlin {
    jvmToolchain(17)
}

dependencies {
  val composeBom = platform(libs.androidx.compose.bom)
  implementation(composeBom)
  androidTestImplementation(composeBom)

  // Core Android dependencies
  implementation(libs.androidx.core.ktx)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.activity.compose)
  implementation(libs.androidx.biometric)

  // Arch Components
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.viewmodel.compose)

  // Compose
  implementation(libs.androidx.compose.ui)
  implementation(libs.androidx.compose.ui.tooling.preview)
  implementation(libs.androidx.compose.material3)
  implementation("androidx.compose.material:material-icons-core")
  implementation("androidx.compose.material:material-icons-extended")
  // Tooling
  debugImplementation(libs.androidx.compose.ui.tooling)
  // Instrumented tests
  androidTestImplementation(libs.androidx.compose.ui.test.junit4)
  debugImplementation(libs.androidx.compose.ui.test.manifest)

  // Local tests: jUnit, coroutines, Android runner
  testImplementation(libs.junit)
  testImplementation(libs.kotlinx.coroutines.test)

  // Instrumented tests: jUnit rules and runners
  androidTestImplementation(libs.androidx.test.core)
  androidTestImplementation(libs.androidx.test.ext.junit)
  androidTestImplementation(libs.androidx.test.runner)
  androidTestImplementation(libs.androidx.test.espresso.core)

  // Navigation
  implementation(libs.androidx.navigation3.ui)
  implementation(libs.androidx.navigation3.runtime)
  implementation(libs.androidx.lifecycle.viewmodel.navigation3)

  // CameraX
  implementation(libs.androidx.camera.core)
  implementation(libs.androidx.camera.camera2)
  implementation(libs.androidx.camera.lifecycle)
  implementation(libs.androidx.camera.view)

  // Networking
  implementation(libs.retrofit.core)
  implementation(libs.retrofit.converter.gson)
  implementation(libs.okhttp.logging)

  // ZXing & Coil
  implementation(libs.zxing.core)
  implementation(libs.coil.compose)

  // Google ML Kit Face Detection (Smile-to-Trigger offline)
  implementation("com.google.mlkit:face-detection:16.1.7")
}

val rootDirFile = project.rootDir
val apkVersionCode = android.defaultConfig.versionCode
val apkVersionName = android.defaultConfig.versionName

tasks.register<Copy>("copyApkToBackend") {
    from(layout.buildDirectory.dir("outputs/apk/debug"))
    include("app-debug.apk")
    into(rootDirFile.resolve("backend"))
}

tasks.register("generateUpdateJson") {
    val updateJsonFile = rootDirFile.resolve("backend/update.json")
    val content = """
        {
          "versionCode": ${apkVersionCode ?: 0},
          "versionName": "${apkVersionName ?: ""}",
          "apkUrl": "app-debug.apk",
          "changeLog": "Pembaruan otomatis dari Gradle build."
        }
    """.trimIndent()
    
    inputs.property("versionCode", apkVersionCode ?: 0)
    inputs.property("versionName", apkVersionName ?: "")
    outputs.file(updateJsonFile)

    doLast {
        updateJsonFile.writeText(content)
        println("Generated update.json at ${updateJsonFile.absolutePath}")
    }
}

// Automatically execute the finalizers after assembleDebug completes successfully
afterEvaluate {
    tasks.named("assembleDebug") {
        finalizedBy("copyApkToBackend", "generateUpdateJson")
    }
}

