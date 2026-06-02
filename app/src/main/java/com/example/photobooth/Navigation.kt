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
                    onStartClick = { backStack.add(LayoutSelect) },
                    onAdminNavigate = { backStack.add(Admin) }
                )
            }
            
            // Admin Screen
            entry<Admin> {
                AdminScreen(
                    onBackClick = { backStack.removeLastOrNull() }
                )
            }
            
            // Layout Selector Screen
            entry<LayoutSelect> {
                LayoutSelectScreen(
                    onBackClick = { backStack.removeLastOrNull() },
                    onLayoutSelected = { type -> backStack.add(FrameSelect(type)) }
                )
            }
            
            // Frame Selector Screen
            entry<FrameSelect> { key ->
                FrameSelectScreen(
                    layoutType = key.layoutType,
                    onBackClick = { backStack.removeLastOrNull() },
                    onFrameSelected = { fId -> backStack.add(CameraCapture(fId)) }
                )
            }
            
            // Camera Capture Screen
            entry<CameraCapture> { key ->
                CameraCaptureScreen(
                    frameId = key.frameId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onCaptureComplete = { paths -> backStack.add(PreviewResult(paths, key.frameId)) }
                )
            }
            
            // Preview Result Screen
            entry<PreviewResult> { key ->
                PreviewResultScreen(
                    photoPaths = key.photoPaths,
                    frameId = key.frameId,
                    onRetakeClick = { backStack.removeLastOrNull() },
                    onConfirmClick = { path, print -> backStack.add(SharePrint(path, print)) }
                )
            }
            
            // Share and Print Screen
            entry<SharePrint> { key ->
                SharePrintScreen(
                    finalPhotoPath = key.finalPhotoPath,
                    shouldPrint = key.shouldPrint,
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
