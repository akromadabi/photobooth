package com.example.photobooth.print

import android.bluetooth.BluetoothManager
import android.content.Context
import android.hardware.usb.UsbManager
import com.example.photobooth.data.ConfigManager
import com.example.photobooth.data.HistoryPrinter

object PrinterAutoSelector {

    /**
     * Scans the printer history list in order of priority (index 0 first)
     * and checks if any printer is currently online/ready.
     * The first available printer found is set as the active printer address.
     * Returns the selected printer address, or null if no printer in history is ready.
     */
    fun autoSelectActivePrinter(context: Context): String? {
        val configManager = ConfigManager(context)
        val historyList = configManager.getPrinterHistory()
        
        if (historyList.isEmpty()) {
            return null
        }

        // Get connected USB devices
        val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
        val usbDevices = usbManager?.deviceList?.values ?: emptyList()

        // Get Bluetooth details
        val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        val bluetoothAdapter = bluetoothManager?.adapter

        for (printer in historyList) {
            if (printer.type == "USB") {
                val parts = printer.address.substring(4).split(",")
                if (parts.size == 2) {
                    val vid = parts[0].toIntOrNull() ?: 0
                    val pid = parts[1].toIntOrNull() ?: 0
                    
                    // Check if this USB device is currently plugged in
                    val isPluggedIn = usbDevices.any { it.vendorId == vid && it.productId == pid }
                    if (isPluggedIn) {
                        configManager.printerAddress = printer.address
                        return printer.address
                    }
                }
            } else if (printer.type == "BT") {
                val mac = printer.address.substring(3)
                // Check if Bluetooth is enabled and the MAC address is in paired devices list
                val isPaired = bluetoothAdapter?.isEnabled == true &&
                        bluetoothAdapter.bondedDevices.any { it.address.equals(mac, ignoreCase = true) }
                if (isPaired) {
                    configManager.printerAddress = printer.address
                    return printer.address
                }
            }
        }
        return null
    }
}
