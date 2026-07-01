package com.example.photobooth

import androidx.compose.runtime.Composable
import androidx.navigation3.runtime.entryProvider
import androidx.navigation3.runtime.rememberNavBackStack
import androidx.navigation3.ui.NavDisplay
import com.example.photobooth.ui.home.HomeScreen
import com.example.photobooth.ui.admin.AdminScreen
import com.example.photobooth.ui.package_select.PackageSelectScreen
import com.example.photobooth.ui.frame.FrameSelectScreen
import com.example.photobooth.ui.camera.CameraCaptureScreen
import com.example.photobooth.ui.preview.PreviewResultScreen
import com.example.photobooth.ui.share.SharePrintScreen
import com.example.photobooth.ui.character.CharacterSelectScreen

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
                    onStartClick = { eventId -> backStack.add(PackageSelect(eventId)) },
                    onAdminNavigate = { backStack.add(Admin) },
                    onRemoteStartClick = { frameId, eventId, packageId, sessionId ->
                        backStack.add(CameraCapture(frameId = frameId, eventId = eventId, sessionId = sessionId, packageId = packageId))
                    }
                )
            }
            
            // Admin Screen
            entry<Admin> {
                AdminScreen(
                    onBackClick = { backStack.removeLastOrNull() }
                )
            }
            
            // Package Selector Screen
            entry<PackageSelect> { key ->
                PackageSelectScreen(
                    onBackClick = { backStack.removeLastOrNull() },
                    onPackageSelected = { pkgId -> 
                        backStack.add(FrameSelect(pkgId, key.eventId))
                    }
                )
            }
            
            // Character Selector Screen
            entry<CharacterSelect> { key ->
                CharacterSelectScreen(
                    eventId = key.eventId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onCharacterSelected = { charId -> 
                        // For AI Character mode, we capture 1 shot. We use a default frameId "postcard_black" which has 1 slot instead of 4 slots.
                        backStack.add(CameraCapture(frameId = "postcard_black", eventId = key.eventId, characterId = charId))
                    }
                )
            }
            
            // Frame Selector Screen
            entry<FrameSelect> { key ->
                FrameSelectScreen(
                    packageId = key.packageId,
                    eventId = key.eventId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onFrameSelected = { fId, charId -> 
                        backStack.add(CameraCapture(frameId = fId, eventId = key.eventId, packageId = key.packageId, characterId = charId)) 
                    }
                )
            }
            
            // Camera Capture Screen
            entry<CameraCapture> { key ->
                CameraCaptureScreen(
                    frameId = key.frameId,
                    eventId = key.eventId,
                    sessionId = key.sessionId,
                    packageId = key.packageId,
                    characterId = key.characterId,
                    onBackClick = { backStack.removeLastOrNull() },
                    onCaptureComplete = { paths -> 
                        backStack.add(PreviewResult(photoPaths = paths, frameId = key.frameId, eventId = key.eventId, sessionId = key.sessionId, packageId = key.packageId, characterId = key.characterId)) 
                    }
                )
            }
            
            // Preview Result Screen
            entry<PreviewResult> { key ->
                PreviewResultScreen(
                    photoPaths = key.photoPaths,
                    frameId = key.frameId,
                    eventId = key.eventId,
                    sessionId = key.sessionId,
                    packageId = key.packageId,
                    characterId = key.characterId,
                    onRetakeClick = { backStack.removeLastOrNull() },
                    onConfirmClick = { path, print, finalFrameId -> 
                        backStack.add(SharePrint(finalPhotoPath = path, shouldPrint = print, frameId = finalFrameId, eventId = key.eventId, sessionId = key.sessionId, packageId = key.packageId, characterId = key.characterId)) 
                    }
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
                    characterId = key.characterId,
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
