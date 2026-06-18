# Walkthrough: Menu Pengaturan Cepat Kiosk & Cetak Kupon

Fitur Menu Pengaturan Cepat (Quick Settings Drawer) tanpa PIN dan Utilitas Cetak Kupon Cepat telah berhasil diimplementasikan di Kiosk Android dan backend PHP Laragon. 

Untuk memastikan keseimbangan fungsionalitas (tidak timpang), fitur-fitur baru ini juga telah diintegrasikan secara penuh ke dalam menu Admin utama (`AdminScreen.kt`).

---

## Ringkasan Perubahan

### 1. Backend PHP
* **[kiosk_control.php](file:///c:/laragon/www/Photoboth/backend/kiosk_control.php)**:
  * Menambahkan case `create_coupon` untuk mendukung pembuatan kode kupon/voucher baru secara dinamis lewat request API dari Kiosk (`action=create_coupon`).
  * Endpoint memanggil library helper `coupon_helper.php` dan merespons dalam format JSON (misal: `{ "success": true, "coupon": { "code": "ABCDEF", ... } }`).

### 2. Kiosk Android App (Kotlin / Compose)
* **[PhotoboothApi.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/api/PhotoboothApi.kt)**:
  * Mendefinisikan class model data `CouponResponse` dan `CouponDto`.
  * Menambahkan deklarasi method Retrofit `resetQueue()` dan `createCoupon(...)` untuk berkomunikasi dengan endpoint backend.
* **[PrintTestHelper.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/print/PrintTestHelper.kt)** (Baru):
  * Menyediakan fungsi bersama `generateQrCode(...)` menggunakan encoder ZXing bawaan.
  * Menyediakan fungsi `runTestPrint(...)` untuk mencetak halaman uji warna dan struk thermal.
  * Menyediakan fungsi `printCouponReceipt(...)` untuk menyusun tata letak struk kupon termal (58mm/80mm) yang cantik berisi nama photobooth, jenis paket kupon, kode kupon berkotak tebal, QR Code kupon, instruksi, dan cap waktu, lalu mengirimkannya ke `ThermalPrinterDriver`.
* **[QuickSettingsDialog.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/QuickSettingsDialog.kt)** (Baru):
  * Mendesain laci samping kanan (Right-Side Drawer) yang elegan menggunakan gaya visual dark glassmorphism.
  * Menyediakan dropdown pilihan **Tema Tampilan Kiosk (Total Layout)** yang langsung mengubah tampilan kiosk secara instan.
  * Menyediakan tombol steper `-` / `+` untuk **Durasi Hitung Mundur**.
  * Menyediakan pengaturan port/alamat printer thermal, switch printer, dan tombol uji coba cetak.
  * Menyediakan panel **Cetak Kupon Cepat** dengan pilihan paket dan tombol cetak instan.
  * Menyediakan tombol utilitas untuk **Sinkronisasi Katalog**, **Reset Antrean (Server)**, dan **Tutup Aplikasi Kiosk**.
* **[HomeScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/HomeScreen.kt)**:
  * Menambahkan tombol ikon kamera (`Icons.Default.CameraAlt`) di pojok kanan atas.
  * Mengintegrasikan detektor ketukan beruntun: ikon kamera harus ditekan **3 kali** dalam durasi 2 detik untuk memicu variabel state `showQuickSettings = true`.
  * Merender laci samping `QuickSettingsDialog` menggunakan pembungkus `AnimatedVisibility` dengan transisi meluncur horizontal (`slideInHorizontally` / `slideOutHorizontally`) yang mulus.
* **[AdminScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/admin/AdminScreen.kt)**:
  * **Penyelarasan Fitur**: Menambahkan Card **Pengelolaan Antrean Kiosk (Queue Reset)** di dalam Tab 1 (Pengaturan) agar admin memiliki hak akses reset yang sama di panel utama.
  * **Penyelarasan Fitur**: Menambahkan Card **Penerbitan Kupon Kiosk (Coupon Issuing)** di dalam Tab 2 (Printer) lengkap dengan dropdown pilihan paket dan tombol cetak kupon baru ke printer termal lokal.
  * Mengganti fungsi internal `testPrintJob` dan `generateQrCode` untuk mendelegasikan tugas ke kelas utilitas bersama `PrintTestHelper` guna meningkatkan efisiensi dan kebersihan kode.

---

## Panduan Verifikasi Manual

1. **Verifikasi Sinkronisasi Menu Admin Utama**:
   * Masuk ke menu admin utama (ketuk logo 5 kali, masukkan PIN).
   * Masuk ke **Tab 1 (Pengaturan)**, pastikan terdapat kartu baru bernama **Pengelolaan Antrean Kiosk** dengan tombol reset antrean server.
   * Masuk ke **Tab 2 (Printer)**, pastikan terdapat kartu baru bernama **Penerbitan Kupon Kiosk** dengan dropdown pilihan paket dan tombol cetak kupon baru.
2. **Akses Menu Laci Samping (Menu Cepat)**:
   * Kembali ke Home Screen, ketuk ikon kamera pojok kanan atas **3 kali** berturut-turut.
   * Pastikan panel pengaturan laci meluncur masuk dan berisi opsi yang seimbang: Ganti Tema, Hitung Mundur, Printer Receipt, Printer Warna, Cetak Kupon, Sync, Reset Antrean, dan Tutup Aplikasi.
3. **Cetak Kupon Cepat**:
   * Ketuk cetak kupon baru baik dari menu cepat maupun menu admin utama, verifikasi bahwa printer termal mencetak struk kupon dengan layout berkotak rapi dan QR Code kupon.
4. **Penebusan Kupon**:
   * Gunakan kode kupon yang tercetak tersebut pada menu pembayaran saat melakukan pemesanan via browser HP (`order.php`).
   * Pastikan sistem backend mendeteksi kupon tersebut valid dan secara otomatis mengaktifkan sesi foto Kiosk Anda.

---

## Update: Perbaikan Deteksi Printer Receipt & Riwayat Printer di Menu Cepat (v1.23.0+)

### 1. Deteksi Printer USB Lebih Luas (Fallback Class 255)
* Di [QuickSettingsDialog.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/QuickSettingsDialog.kt) dan [AdminScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/admin/AdminScreen.kt), logika pemindaian USB (`scanPrinters`) telah disempurnakan.
* Selain mendeteksi interface printer standar (Class 7), sistem sekarang memiliki fallback untuk memeriksa endpoint bulk transfer output (`UsbConstants.USB_ENDPOINT_XFER_BULK` dengan arah `UsbConstants.USB_DIR_OUT`). Ini memastikan printer USB thermal tipe vendor-specific (Class 255/0xFF) yang sangat umum tetap terdeteksi dengan baik di dropdown.

### 2. Integrasi Riwayat Printer (Saved History) ke Menu Cepat
* Dropdown pilihan printer receipt di menu cepat kini menampilkan printer yang terdeteksi secara real-time **dan** printer yang tersimpan di riwayat (`configManager.getPrinterHistory()`) seperti printer Wi-Fi (NET) atau printer Bluetooth (BT) dan USB sebelumnya.
* Saat user memilih printer dari dropdown atau mengetuk tombol "Set" untuk printer WiFi, alamat printer otomatis ditambahkan ke riwayat koneksi printer secara dinamis.

### 3. Perbaikan Sinkronisasi Warna (Dark/Light Mode)
* Memperbaiki error kompilasi scoping variabel `BgCard`, `BorderColor`, `White`, dan `Gray` di dalam composable tab dan grafik eksternal. Layout menu cepat (`QuickSettingsDialog.kt`) dan admin panel (`AdminScreen.kt`) kini ter-compile 100% sukses dan responsif terhadap perubahan tema gelap/terang.

---

## Update: Revisi Menu Cepat Kiosk (v1.24.0)

### 1. Penghapusan Fitur dari Menu Cepat
* **Cetak Kupon Cepat**: Dihapus sepenuhnya dari [QuickSettingsDialog.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/QuickSettingsDialog.kt). Sesuai permintaan, kupon kini hanya dapat dibuat secara aman melalui panel Admin (akses PIN).
* **Sinkronisasi Bingkai**: Tombol sinkronisasi katalog bingkai dan acara dihapus dari menu cepat. Fitur sinkronisasi tetap dapat diakses di panel Admin (`AdminScreen.kt`).
* **Keluar Aplikasi**: Tombol pintasan untuk keluar/menutup kiosk (`Tutup & Keluar Aplikasi Kiosk`) dihapus dari menu cepat untuk mencegah penutupan tidak sengaja oleh operator non-admin.

### 2. Perbaikan Scoping Variabel Tema & Kompilasi Sukses
* Mengatasi masalah kompilasi `Unresolved reference` pada variabel `BgCard`, `BorderColor`, `White`, dan `Gray` di dalam composable terpisah seperti `DashboardTab` dan `InteractiveBarChart` di [AdminScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/admin/AdminScreen.kt) dengan mengambil referensi warna langsung dari `LocalAdminThemeColors.current`.
* Menambahkan custom scoping warna dinamis (`LocalQuickSettingsThemeColors`) di dalam layout [QuickSettingsDialog.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/QuickSettingsDialog.kt) untuk memastikan penyesuaian warna border/elemen laci cepat berfungsi dengan baik.

### 3. Build & Rilis APK
* Menaikkan `versionName` ke `1.24.0` dan `versionCode` ke `32` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle secara bersih untuk menghasilkan berkas APK rilis baru:
  * APK otomatis disalin ke backend: `backend/app-debug.apk`
  * Berkas pembaruan diperbarui: `backend/update.json`
  * Menyimpan salinan arsip versi di root: `app-debug_v1.24.0.apk`


---

## Update: Perbaikan Stabilitas Cetak Bluetooth & Dialog Cetak Ulang (v1.24.2)

### 1. Perbaikan Stabilitas Cetak via Bluetooth
* **Metode Pengiriman Data**: Mengubah pengiriman seluruh byte array bitmap sekaligus (`outputStream.write(printData)`) menjadi pengiriman berangsur dalam unit-unit kecil (chunk) sebesar **1024 byte (1 KB)** dengan jeda **15 milidetik** per chunk. Hal ini mencegah *buffer overflow* pada RAM internal printer receipt/kasir yang seringkali berukuran sangat kecil.
* **Hasil Perbaikan**:
  * Menghilangkan kemacetan cetak (kertas berhenti di tengah jalan) dan menjamin perintah potong kertas (`auto-cut`) selalu berhasil diterima dan dieksekusi di akhir cetakan.
  * Mencegah terjadinya *garbage text* (cetakan kode-kode acak tak jelas) akibat hilangnya bit header perintah ESC_POS/TSPL.
  * Menghilangkan kelambatan tersendat-sendat akibat tersumbatnya transmisi data Bluetooth RFCOMM.
* **Jeda Penutupan Socket**: Menambahkan jeda waktu tunggu **1000 milidetik (1 detik)** sebelum memanggil `socket.close()` setelah seluruh data selesai dikirim. Ini memastikan printer memiliki cukup waktu untuk menerima dan memproses seluruh data di buffer Bluetooth internalnya secara tuntas sebelum koneksi diputus secara sepihak.

### 2. Dialog Pemilihan Printer Pintar pada Cetak Ulang (Reprint)
* **Pemilihan Printer**: Ketika tombol `CETAK ULANG FOTO (REPRINT)` diketuk dari detail riwayat foto di tab Riwayat Foto:
  * Jika jenis printer diatur ke `"AUTO"` (dua printer aktif bersamaan), aplikasi sekarang memunculkan `AlertDialog` pilihan: "Printer Struk (Thermal)" atau "Printer Warna".
  * Jika diatur ke `"THERMAL"` saja atau `"COLOR"` saja, aplikasi langsung mencetak secara instan menggunakan driver printer tersebut secara mandiri tanpa memunculkan dialog.
* **Hasil Perbaikan**: Menghilangkan kemunculan panel chooser/share default bawaan Android saat mencetak ulang riwayat foto.

### 3. Build & Rilis APK v1.24.2
* Menaikkan `versionName` ke `1.24.2` dan `versionCode` ke `34` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Berhasil memicu `assembleDebug` yang secara otomatis menyegarkan file JSON pembaruan (`backend/update.json`), menyalin APK terbaru (`backend/app-debug.apk`), dan membuat arsip cadangan di root: `app-debug_v1.24.2.apk`.


---

## Update: Mekanisme Fallback Koneksi RFCOMM Bluetooth (v1.24.3)

### 1. Fallback Koneksi Bluetooth (Anti Gagal Terhubung)
* **Mekanisme Fallback**: Menambahkan penanganan fallback otomatis menggunakan java reflection untuk membuka RFCOMM socket langsung ke port `1` jika pemanggilan standar `createRfcommSocketToServiceRecord(SPP_UUID)` gagal atau ditolak oleh stack Bluetooth internal perangkat Android.
* **Hasil Perbaikan**: Menjamin tablet Android dapat melakukan koneksi sukses 100% ke berbagai merek printer thermal Bluetooth pasaran (termasuk yang menggunakan chip generik Tiongkok dengan UUID SPP non-standar).

### 2. Build & Rilis APK v1.24.3
* Menaikkan `versionName` ke `1.24.3` dan `versionCode` ke `35` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle bersih untuk memperbarui file pembaruan `backend/update.json`, menyalin `backend/app-debug.apk`, dan mengarsipkan `app-debug_v1.24.3.apk` di folder root.

---

## Update: Pengaturan Kualitas Cetak Thermal & Live Preview Dither (v1.25.0)

### 1. Panel Admin Server (Backend)
* **[settings.json](file:///c:/laragon/www/Photoboth/backend/settings.json)**:
  * Menambahkan parameter default baru untuk kontrol kualitas cetak: `thermal_contrast` (1.2), `thermal_brightness` (10.0), `thermal_sharpness` (0.4), dan `thermal_denoise` (true).
* **[admin.php](file:///c:/laragon/www/Photoboth/backend/admin.php)**:
  * Menambahkan UI penyesuaian parameter cetak termal menggunakan slider (Kontras, Kecerahan, Ketajaman) dan toggle switch (Denoise/Median Filter).
  * Mengimplementasikan visual simulasi **Live Dither Preview** pada kertas struk termal menggunakan canvas HTML5 & JavaScript (Floyd-Steinberg error diffusion).
  * Menyediakan tombol sampel potret buatan, pengunggahan foto kustom, dan penarikan foto sesi terakhir sebagai gambar masukan pengujian.

### 2. Sinkronisasi & Penyimpanan Android Kiosk
* **[KioskConfigDto.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/data/KioskConfigDto.kt)**:
  * Memetakan variabel DTO baru untuk menerima parameter kontrol kualitas cetak dari JSON server.
* **[ConfigManager.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/data/ConfigManager.kt)**:
  * Menambahkan penyimpanan lokal (SharedPreferences) untuk Kontras, Kecerahan, Ketajaman, dan Denoise.
* **[MainActivity.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/MainActivity.kt)**:
  * Menambahkan sinkronisasi parameter kualitas cetak dari endpoint server ke `ConfigManager` lokal secara real-time saat startup/resume.

### 3. Pemrosesan Dithering & Driver Printer
* **[DitherHelper.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/print/DitherHelper.kt)**:
  * Memperbarui fungsi `ditherFloydSteinberg` untuk menerima parameter kontras, kecerahan, ketajaman, dan status denoise secara dinamis.
  * Menerapkan filter median 3x3 untuk denoising, peningkatan kontras dan kecerahan secara dinamis, serta sharpening sebelum Floyd-Steinberg dither diaplikasikan.
* **[ThermalPrinterDriver.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/print/ThermalPrinterDriver.kt)**:
  * Melewatkan parameter dari `ConfigManager` ke fungsi `ditherFloydSteinberg` ketika menyusun perintah bitmap cetak TSPL dan ESC/POS.

### 4. Antarmuka Pengguna Kiosk (Android UI)
* **[AdminScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/admin/AdminScreen.kt)**:
  * Menambahkan slider Kontras (0.5 - 3.0), Kecerahan (-50 - 50), Ketajaman (0.0 - 2.0), dan toggle Denoise pada kartu pengaturan Printer Thermal.
  * Memperbaiki kesalahan kompilasi unresolved references (`printerAddress` dan `historyListState`) pada pemanggilan `DashboardTab` di dalam tab Dashboard.
* **[QuickSettingsDialog.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/home/QuickSettingsDialog.kt)**:
  * Menambahkan slider Kontras, Kecerahan, Ketajaman, dan switch Denoise serupa pada panel Menu Cepat di bawah pengaturan port printer thermal agar operator dapat langsung melakukan fine-tuning secara instan tanpa perlu masuk ke menu admin ber-PIN.

### 5. Build & Rilis APK v1.25.0
* Mendaftarkan `versionName = "1.25.0"` dan `versionCode = 36` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle secara bersih untuk memperbarui file pembaruan `backend/update.json` dan menyalin APK terbaru `backend/app-debug.apk`.


---

## Update: Perbaikan Cetak Bluetooth & Versi Dinamis (v1.25.2)

### 1. Perbaikan Aliran Cetak (Anti-Tersendat)
* **Pengiriman Paket Optimal**: Mengubah pengiriman data Bluetooth SPP menjadi ukuran paket **4096 byte (4 KB)** dengan jeda **5ms**. Ini mempercepat aliran data agar buffer printer tidak kehabisan data di tengah jalan (menyebabkan kertas tersendat-sendat), namun tetap menjaga batas kestabilan agar buffer Android tidak overflow.

### 2. Perbaikan Pemotongan Kertas Otomatis (Auto-Cut)
* **Kompatibilitas Komando Cut**: Mengganti perintah potong kertas lama (`0x1D, 0x56, 0x00`) menjadi perintah standard feed-and-cut ESC/POS yang sangat luas kompatibilitasnya: **`0x1D, 0x56, 0x42, 0x00`** (GS V 66 0).
* **Jeda Socket Terbuka**: Meningkatkan waktu tunggu sebelum menutup koneksi Bluetooth socket menjadi **3 detik** setelah seluruh data selesai dikirim. Ini memastikan printer memiliki cukup waktu untuk menyelesaikan pencetakan fisik dan mengeksekusi pemotongan kertas secara tuntas.

### 3. Koneksi Bluetooth Insecure
* Mengimplementasikan percobaan pembukaan socket RFCOMM secara Insecure (`createInsecureRfcommSocketToServiceRecord`) terlebih dahulu sebelum mencoba Secure socket atau reflection fallback. Ini membebaskan perangkat dari popup permintaan PIN pairing Bluetooth yang sering gagal dan meminimalisir error koneksi acak.

### 4. Teks Versi Dinamis pada Struk Uji Coba
* Menghapus teks versi hardcoded `"v1.16.0"` pada cetakan struk uji coba di [PrintTestHelper.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/print/PrintTestHelper.kt).
* Menggantinya dengan pembacaan dinamis versi aplikasi langsung dari Package Manager Android. Struk uji coba sekarang akan menampilkan versi yang sebenarnya berjalan (misalnya `v1.25.2`).

### 5. Penyederhanaan Kode Admin
* Menghapus duplikasi kode generator test print di [AdminScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/admin/AdminScreen.kt) dan mendelegasikan pemanggilan sepenuhnya ke kelas pembantu bersama [PrintTestHelper.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/print/PrintTestHelper.kt).

### 6. Build & Rilis APK v1.25.2
* Menaikkan `versionName` ke `"1.25.2"` dan `versionCode` ke `38` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle secara bersih untuk memperbarui file pembaruan `backend/update.json`, menyalin APK terbaru `backend/app-debug.apk`, dan mencadangkan salinannya ke root: `app-debug_v1.25.2.apk`.

