package com.example.photobooth

import androidx.compose.runtime.Composable
import androidx.navigation3.runtime.entryProvider
import androidx.navigation3.runtime.rememberNavBackStack
import androidx.navigation3.ui.NavDisplay
import com.example.photobooth.ui.home.HomeScreen
import com.example.photobooth.ui.admin.AdminScreen
import com.example.photobooth.ui.layout.LayoutSelectScreen
import com.example.photobooth.ui.frame.FrameSelectScreen
import com.example.photobooth.ui.camera.CameraCaptureScreen
import com.example.photobooth.ui.preview.PreviewResultScreen
import com.example.photobooth.ui.share.SharePrintScreen

@Composable
fun MainNavigation() {
    // Start screen is Home
    val backStack = rememberNavBackStack(Home)

    NavDisplay(
        backStack = backStack,
        onBack = { backStack.removeLastOrNull() },
        entryProvider = entryProvider {
            
            // Home Screen
            entry<Home> {
                HomeScreen(
                    onStartClick = { eventId -> backStack.add(LayoutSelect(eventId)) },
                    onAdminNavigate = { backStack.add(Admin) },
                    onRemoteStartClick = { frameId, eventId, packageId, sessionId ->
                        backStack.add(CameraCapture(frameId, eventId, sessionId, packageId))
                    }
                )
            }
            
            // Admin Screen
            entry<Admin> {
                AdminScreen(
                    onBackClick = { backStack.removeLastOrNull() }
                )
            }
            
            // Layout Selector Screen
            entry<LayoutSelect> { key ->
                LayoutSelectScreen(
                    onBackClick = { backStack.removeLastOrNull() },
                    onLayoutSelected = { type -> backStack.add(FrameSelect(type, key.eventId)) }
                )
            }
            
            // Frame Selector Screen
            entry<FrameSelect> { key ->
                FrameSelectScreen(
                    layoutType = key.layoutType,
                    eventId = key.eventId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onFrameSelected = { fId -> backStack.add(CameraCapture(fId, key.eventId)) }
                )
            }
            
            // Camera Capture Screen
            entry<CameraCapture> { key ->
                CameraCaptureScreen(
                    frameId = key.frameId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onCaptureComplete = { paths -> backStack.add(PreviewResult(paths, key.frameId, key.eventId, key.sessionId, key.packageId)) }
                )
            }
            
            // Preview Result Screen
            entry<PreviewResult> { key ->
                PreviewResultScreen(
                    photoPaths = key.photoPaths,
                    frameId = key.frameId,
                    onRetakeClick = { backStack.removeLastOrNull() },
                    onConfirmClick = { path, print -> backStack.add(SharePrint(path, print, key.frameId, key.eventId, key.sessionId, key.packageId)) }
                )
            }
            
            // Share and Print Screen
            entry<SharePrint> { key ->
                SharePrintScreen(
                    finalPhotoPath = key.finalPhotoPath,
                    shouldPrint = key.shouldPrint,
                    frameId = key.frameId,
                    eventId = key.eventId,
                    sessionId = key.sessionId,
                    packageId = key.packageId,
                    onFinishClick = {
                        // Clear the backstack and return back to Home
                        while (backStack.size > 1) {
                            backStack.removeLastOrNull()
                        }
                    }
                )
            }
        }
    )
}
