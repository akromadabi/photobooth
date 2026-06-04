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

    private fun generateTsplData(bitmap: Bitmap): ByteArray {
        // Dither the bitmap to monochrome
        val dithered = DitherHelper.ditherFloydSteinberg(bitmap)
        val width = dithered.width
        val height = dithered.height
        val widthBytes = (width + 7) / 8
        val raster = DitherHelper.convertTo1BitRaster(dithered)
        
        // Assume 203 DPI (8 dots/mm). Scale width and height to mm.
        val widthMm = (width / 8).coerceAtLeast(40)
        val heightMm = (height / 8).coerceAtLeast(100)
        
        val sizeText = "SIZE $widthMm mm, $heightMm mm\r\n"
        val configText = "GAP 0\r\nCLS\r\n"
        val bitmapHeader = "BITMAP 0,0,$widthBytes,$height,0,"
        val printFooter = "\r\nPRINT 1,1\r\n"
        
        val sizeBytes = sizeText.toByteArray(StandardCharsets.US_ASCII)
        val configBytes = configText.toByteArray(StandardCharsets.US_ASCII)
        val headerBytes = bitmapHeader.toByteArray(StandardCharsets.US_ASCII)
        val footerBytes = printFooter.toByteArray(StandardCharsets.US_ASCII)
        
        val totalSize = sizeBytes.size + configBytes.size + headerBytes.size + raster.size + footerBytes.size
        val command = ByteArray(totalSize)
        
        var offset = 0
        System.arraycopy(sizeBytes, 0, command, offset, sizeBytes.size); offset += sizeBytes.size
        System.arraycopy(configBytes, 0, command, offset, configBytes.size); offset += configBytes.size
        System.arraycopy(headerBytes, 0, command, offset, headerBytes.size); offset += headerBytes.size
        System.arraycopy(raster, 0, command, offset, raster.size); offset += raster.size
        System.arraycopy(footerBytes, 0, command, offset, footerBytes.size)
        
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
            
            val tsplData = generateTsplData(bitmap)
            val outputStream = socket.outputStream
            outputStream.write(tsplData)
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
            
            val tsplData = generateTsplData(bitmap)
            
            // Transfer in chunks to prevent buffer issues
            val maxChunk = 8192
            var sent = 0
            while (sent < tsplData.size) {
                val length = (tsplData.size - sent).coerceAtMost(maxChunk)
                val chunk = ByteArray(length)
                System.arraycopy(tsplData, sent, chunk, 0, length)
                
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

