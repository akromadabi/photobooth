package com.example.photobooth.print

import android.annotation.SuppressLint
import android.app.PendingIntent
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.graphics.Bitmap
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbDeviceConnection
import android.hardware.usb.UsbEndpoint
import android.hardware.usb.UsbInterface
import android.hardware.usb.UsbManager
import android.os.Build
import com.example.photobooth.data.ConfigManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import java.io.IOException
import java.nio.charset.StandardCharsets
import java.util.UUID
import kotlin.coroutines.resume


class ThermalPrinterDriver : PrinterManager {

    companion object {
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805f9b34fb")
    }

    override suspend fun printBitmap(bitmap: Bitmap, context: Context): PrintResult = withContext(Dispatchers.IO) {
        val configManager = ConfigManager(context)
        val address = configManager.printerAddress // format: "USB:vid,pid" or "BT:mac_address"
        
        if (address.startsWith("BT:")) {
            val mac = address.substring(3)
            return@withContext printViaBluetooth(bitmap, mac, context)
        } else if (address.startsWith("USB:")) {
            val parts = address.substring(4).split(",")
            if (parts.size == 2) {
                val vid = parts[0].toIntOrNull() ?: 0
                val pid = parts[1].toIntOrNull() ?: 0
                return@withContext printViaUsb(bitmap, vid, pid, context)
            }
        }
        
        // Auto-detect fallback: search USB first, then Bluetooth
        val usbResult = printViaUsbAuto(bitmap, context)
        if (usbResult is PrintResult.Success) {
            return@withContext usbResult
        }
        
        PrintResult.Error("Printer tidak terkonfigurasi. Konfigurasi alamat printer di Menu Admin.")
    }

    private fun generateTsplData(bitmap: Bitmap, configManager: ConfigManager): ByteArray {
        val targetWidth = when (configManager.printerPaperWidth) {
            50 -> 320
            58 -> 384
            else -> 576
        }
        val targetHeight = (bitmap.height * targetWidth) / bitmap.width
        val scaledBitmap = if (bitmap.width == targetWidth) bitmap else Bitmap.createScaledBitmap(bitmap, targetWidth, targetHeight, true)

        // Dither the bitmap to monochrome
        val dithered = DitherHelper.ditherFloydSteinberg(scaledBitmap)
        if (scaledBitmap != bitmap) {
            scaledBitmap.recycle()
        }
        val width = dithered.width
        val height = dithered.height
        val widthBytes = (width + 7) / 8
        val raster = DitherHelper.convertTo1BitRaster(dithered)
        dithered.recycle()
        
        // Assume 203 DPI (8 dots/mm). Scale width and height to mm.
        val widthMm = (width / 8).coerceAtLeast(40)
        val heightMm = (height / 8).coerceAtLeast(100)
        
        val sizeText = "SIZE $widthMm mm, $heightMm mm\r\n"
        
        // Print density: Map 1..5 to TSPL density levels (2, 5, 8, 12, 15)
        val densityLevels = intArrayOf(2, 5, 8, 12, 15)
        val densityIndex = (configManager.printDensity - 1).coerceIn(0, 4)
        val densityVal = densityLevels[densityIndex]
        val densityText = "DENSITY $densityVal\r\n"

        val configText = "GAP 0\r\nCLS\r\n"
        val bitmapHeader = "BITMAP 0,0,$widthBytes,$height,0,"
        
        // Auto-cut option
        val printFooter = if (configManager.printerAutoCut) {
            "\r\nPRINT 1,1\r\nCUT\r\n"
        } else {
            "\r\nPRINT 1,1\r\n"
        }
        
        val sizeBytes = sizeText.toByteArray(StandardCharsets.US_ASCII)
        val densityBytes = densityText.toByteArray(StandardCharsets.US_ASCII)
        val configBytes = configText.toByteArray(StandardCharsets.US_ASCII)
        val headerBytes = bitmapHeader.toByteArray(StandardCharsets.US_ASCII)
        val footerBytes = printFooter.toByteArray(StandardCharsets.US_ASCII)
        
        val totalSize = sizeBytes.size + densityBytes.size + configBytes.size + headerBytes.size + raster.size + footerBytes.size
        val command = ByteArray(totalSize)
        
        var offset = 0
        System.arraycopy(sizeBytes, 0, command, offset, sizeBytes.size); offset += sizeBytes.size
        System.arraycopy(densityBytes, 0, command, offset, densityBytes.size); offset += densityBytes.size
        System.arraycopy(configBytes, 0, command, offset, configBytes.size); offset += configBytes.size
        System.arraycopy(headerBytes, 0, command, offset, headerBytes.size); offset += headerBytes.size
        System.arraycopy(raster, 0, command, offset, raster.size); offset += raster.size
        System.arraycopy(footerBytes, 0, command, offset, footerBytes.size)
        
        return command
    }

    private fun generateEscPosData(bitmap: Bitmap, configManager: ConfigManager): ByteArray {
        val targetWidth = when (configManager.printerPaperWidth) {
            50 -> 320
            58 -> 384
            else -> 576
        }
        val targetHeight = (bitmap.height * targetWidth) / bitmap.width
        val scaledBitmap = if (bitmap.width == targetWidth) bitmap else Bitmap.createScaledBitmap(bitmap, targetWidth, targetHeight, true)

        val dithered = DitherHelper.ditherFloydSteinberg(scaledBitmap)
        if (scaledBitmap != bitmap) {
            scaledBitmap.recycle()
        }
        val width = dithered.width
        val height = dithered.height
        val widthBytes = (width + 7) / 8
        val raster = DitherHelper.convertTo1BitRaster(dithered)
        dithered.recycle()
        
        // Initialize printer: ESC @ (0x1B, 0x40)
        val initCmd = byteArrayOf(0x1B, 0x40)
        
        // Print density: GS ~ n (0x1D, 0x7E, n) where n is 0..4 (mapped from 1..5)
        val densityVal = (configManager.printDensity - 1).coerceIn(0, 4).toByte()
        val densityCmd = byteArrayOf(0x1D, 0x7E, densityVal)

        // Print raster bit image: GS v 0 0 (0x1D, 0x76, 0x30, 0x00)
        val headerCmd = byteArrayOf(
            0x1D, 0x76, 0x30, 0x00,
            (widthBytes % 256).toByte(),
            (widthBytes / 256).toByte(),
            (height % 256).toByte(),
            (height / 256).toByte()
        )
        
        // Feed 4 lines: ESC d 4 (0x1B, 0x64, 0x04)
        // If auto-cut is enabled, send GS V 0 (0x1D, 0x56, 0x00)
        val cutCmd = if (configManager.printerAutoCut) {
            byteArrayOf(
                0x1B, 0x64, 0x04,
                0x1D, 0x56, 0x00
            )
        } else {
            byteArrayOf(
                0x1B, 0x64, 0x04
            )
        }
        
        val totalSize = initCmd.size + densityCmd.size + headerCmd.size + raster.size + cutCmd.size
        val command = ByteArray(totalSize)
        
        var offset = 0
        System.arraycopy(initCmd, 0, command, offset, initCmd.size); offset += initCmd.size
        System.arraycopy(densityCmd, 0, command, offset, densityCmd.size); offset += densityCmd.size
        System.arraycopy(headerCmd, 0, command, offset, headerCmd.size); offset += headerCmd.size
        System.arraycopy(raster, 0, command, offset, raster.size); offset += raster.size
        System.arraycopy(cutCmd, 0, command, offset, cutCmd.size)
        
        return command
    }

    @SuppressLint("MissingPermission")
    private fun printViaBluetooth(bitmap: Bitmap, macAddress: String, context: Context): PrintResult {
        val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
            ?: return PrintResult.Error("Bluetooth tidak didukung perangkat ini")
        val adapter = bluetoothManager.adapter
            ?: return PrintResult.Error("Bluetooth mati atau tidak didukung")
            
        if (!adapter.isEnabled) {
            return PrintResult.Error("Silakan aktifkan Bluetooth terlebih dahulu")
        }
        
        val device: BluetoothDevice = try {
            adapter.getRemoteDevice(macAddress)
        } catch (e: Exception) {
            return PrintResult.Error("MAC Address printer tidak valid")
        }
        
        var socket: BluetoothSocket? = null
        return try {
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket.connect()
            
            val configManager = ConfigManager(context)
            val printData = if (configManager.thermalMode == "ESC_POS") {
                generateEscPosData(bitmap, configManager)
            } else {
                generateTsplData(bitmap, configManager)
            }
            val outputStream = socket.outputStream
            outputStream.write(printData)
            outputStream.flush()
            
            PrintResult.Success
        } catch (e: IOException) {
            PrintResult.Error("Koneksi Bluetooth ke printer gagal: ${e.message}")
        } finally {
            try {
                socket?.close()
            } catch (e: Exception) {}
        }
    }

    private suspend fun printViaUsb(bitmap: Bitmap, vid: Int, pid: Int, context: Context): PrintResult {
        val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
            ?: return PrintResult.Error("USB Host API tidak didukung")
            
        val deviceList = usbManager.deviceList
        var targetDevice: UsbDevice? = null
        for (device in deviceList.values) {
            if (device.vendorId == vid && device.productId == pid) {
                targetDevice = device
                break
            }
        }
        
        if (targetDevice == null) {
            return PrintResult.Error("Printer USB dengan VID:$vid PID:$pid tidak terdeteksi")
        }
        
        return sendTsplToUsb(bitmap, targetDevice, usbManager, context)
    }

    private suspend fun printViaUsbAuto(bitmap: Bitmap, context: Context): PrintResult {
        val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
            ?: return PrintResult.Error("USB Host API tidak didukung")
            
        val deviceList = usbManager.deviceList
        var targetDevice: UsbDevice? = null
        
        for (device in deviceList.values) {
            // Check for typical thermal printer interfaces
            for (i in 0 until device.interfaceCount) {
                val usbInterface = device.getInterface(i)
                if (usbInterface.interfaceClass == UsbConstants.USB_CLASS_PRINTER) {
                    targetDevice = device
                    break
                }
            }
            if (targetDevice != null) break
        }
        
        if (targetDevice == null) {
            return PrintResult.Error("Printer USB tidak ditemukan")
        }
        
        return sendTsplToUsb(bitmap, targetDevice, usbManager, context)
    }

    private suspend fun sendTsplToUsb(bitmap: Bitmap, device: UsbDevice, usbManager: UsbManager, context: Context): PrintResult {
        if (!usbManager.hasPermission(device)) {
            val granted = requestUsbPermission(device, context, usbManager)
            if (!granted) {
                return PrintResult.Error("Izin akses USB untuk printer ditolak oleh pengguna.")
            }
        }
        
        var usbInterface: UsbInterface? = null
        var outEndpoint: UsbEndpoint? = null
        
        // 1. Try to find standard Printer class interface first
        for (i in 0 until device.interfaceCount) {
            val intr = device.getInterface(i)
            if (intr.interfaceClass == UsbConstants.USB_CLASS_PRINTER) {
                for (j in 0 until intr.endpointCount) {
                    val ep = intr.getEndpoint(j)
                    if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_OUT) {
                        usbInterface = intr
                        outEndpoint = ep
                        break
                    }
                }
            }
            if (usbInterface != null) break
        }
        
        // 2. Fallback: Check any interface if standard printer class was not found (for Vendor-Specific class 255)
        if (usbInterface == null || outEndpoint == null) {
            for (i in 0 until device.interfaceCount) {
                val intr = device.getInterface(i)
                for (j in 0 until intr.endpointCount) {
                    val ep = intr.getEndpoint(j)
                    if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_OUT) {
                        usbInterface = intr
                        outEndpoint = ep
                        break
                    }
                }
                if (usbInterface != null) break
            }
        }
        
        if (usbInterface == null || outEndpoint == null) {
            return PrintResult.Error("Interface printer USB bulk-out tidak ditemukan")
        }
        
        var connection: UsbDeviceConnection? = null
        return try {
            connection = usbManager.openDevice(device)
                ?: return PrintResult.Error("Gagal membuka koneksi USB printer")
                
            val claimed = connection.claimInterface(usbInterface, true)
            if (!claimed) {
                return PrintResult.Error("Gagal mengklaim interface printer USB")
            }
            
            val configManager = ConfigManager(context)
            val printData = if (configManager.thermalMode == "ESC_POS") {
                generateEscPosData(bitmap, configManager)
            } else {
                generateTsplData(bitmap, configManager)
            }
            
            // Transfer in chunks to prevent buffer issues
            val maxChunk = 8192
            var sent = 0
            while (sent < printData.size) {
                val length = (printData.size - sent).coerceAtMost(maxChunk)
                val chunk = ByteArray(length)
                System.arraycopy(printData, sent, chunk, 0, length)
                
                val result = connection.bulkTransfer(outEndpoint, chunk, length, 10000)
                if (result < 0) {
                    return PrintResult.Error("Gagal mengirim data cetak USB: bulkTransfer error $result")
                }
                sent += length
            }
            
            PrintResult.Success
        } catch (e: Exception) {
            PrintResult.Error("Kesalahan saat mencetak USB: ${e.message}")
        } finally {
            try {
                connection?.releaseInterface(usbInterface)
                connection?.close()
            } catch (e: Exception) {}
        }
    }

    private suspend fun requestUsbPermission(device: UsbDevice, context: Context, usbManager: UsbManager): Boolean = withContext(Dispatchers.Main) {
        if (usbManager.hasPermission(device)) return@withContext true

        val ACTION_USB_PERMISSION = "com.example.photobooth.USB_PERMISSION"
        val activity = context.findActivity()
        
        val wasPinned = try {
            val am = context.getSystemService(Context.ACTIVITY_SERVICE) as? android.app.ActivityManager
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_LOCKED ||
                am?.lockTaskModeState == android.app.ActivityManager.LOCK_TASK_MODE_PINNED
            } else {
                @Suppress("DEPRECATION")
                am?.isInLockTaskMode ?: false
            }
        } catch (e: Exception) {
            false
        }

        if (wasPinned) {
            try {
                activity?.stopLockTask()
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
        
        suspendCancellableCoroutine { continuation ->
            val usbReceiver = object : BroadcastReceiver() {
                override fun onReceive(c: Context, intent: Intent) {
                    val action = intent.action
                    if (ACTION_USB_PERMISSION == action) {
                        synchronized(this) {
                            @Suppress("DEPRECATION")
                            val dev = intent.getParcelableExtra<UsbDevice>(UsbManager.EXTRA_DEVICE)
                            val granted = intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false)
                            
                            if (wasPinned) {
                                try {
                                    activity?.startLockTask()
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                }
                            }
                            
                            if (granted && dev != null && dev.deviceId == device.deviceId) {
                                if (continuation.isActive) continuation.resume(true)
                            } else {
                                if (continuation.isActive) continuation.resume(false)
                            }
                        }
                    }
                    try {
                        context.unregisterReceiver(this)
                    } catch (e: Exception) {}
                }
            }

            val filter = IntentFilter(ACTION_USB_PERMISSION)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                context.registerReceiver(usbReceiver, filter, Context.RECEIVER_EXPORTED)
            } else {
                context.registerReceiver(usbReceiver, filter)
            }

            val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                PendingIntent.FLAG_MUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            } else {
                PendingIntent.FLAG_UPDATE_CURRENT
            }
            val permissionIntent = PendingIntent.getBroadcast(
                context,
                0,
                Intent(ACTION_USB_PERMISSION).apply {
                    setPackage(context.packageName)
                },
                flags
            )

            continuation.invokeOnCancellation {
                if (wasPinned) {
                    try {
                        activity?.startLockTask()
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }
                try {
                    context.unregisterReceiver(usbReceiver)
                } catch (e: Exception) {}
            }

            usbManager.requestPermission(device, permissionIntent)
        }
    }
}

private fun Context.findActivity(): android.app.Activity? {
    var currentContext = this
    while (currentContext is android.content.ContextWrapper) {
        if (currentContext is android.app.Activity) {
            return currentContext
        }
        currentContext = currentContext.baseContext
    }
    return null
}

