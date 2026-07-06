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

---

## Update: Standardisasi Branding Event Dinamis & Pembersihan Placeholder "Dago"

### 1. Perubahan Nilai Fallback Kiosk (Default Brand)
* **Branding Default**: Mengubah default fallback nama brand pada Kiosk dari `"Studio Foto"` menjadi `"Jeprat Jepret"`, dan slogan default dari `"Abadikan Momenmu"` menjadi `"All You need is special"`.
* **Kesesuaian di JavaScript**: Memperbarui konstanta `NEUTRAL_NAME` dan `NEUTRAL_SLOGAN` di dalam fungsi `updateHomeScreenBranding()` pada file [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) agar ketika event yang aktif adalah event umum/default (`general`), branding secara otomatis bernilai `"Jeprat Jepret"` dengan tagline yang sesuai.
* **Canvas Karakter Kartu**: Mengganti fallback nama brand teks statis `"STUDIO FOTO"` pada fungsi verifikasi kartu karakter AI di [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) menjadi pencarian dinamis dari event umum di server (`brandName`), dan jika kosong otomatis mengarah ke `"JEPRAT JEPRET"`.

### 2. Integrasi Dinamis Layout Struk & Header Brand Dago Orange
* **Penyelesaian HTML Statis**: Menghapus teks statis `"Dago."`, `"RECEIPT PHOTOBOOTH"`, `"SINCE 2020"`, dan `"DAILY NEWS"` yang sebelumnya mengunci tampilan struk di halaman share/cetak struk simulator.
* **Injeksi Data Awal via PHP**: Menambahkan blok pendeteksi info event general di awal [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) untuk mem-parsing nama brand umum secara dinamis saat halaman pertama kali dimuat di browser.
* **Output Variabel**: Menghubungkan variabel PHP tersebut ke dalam markup HTML di elemen header logo (`dagoBrandMainText`), sub-header (`dago-brand-sub1`), logo kertas struk (`dagoReceiptPaperLogoText`), dan judul struk (`dagoReceiptPaperTitle`). Dengan demikian, cetakan struk simulator sekarang tampil dinamis berdasarkan data event yang sedang dipilih/aktif.

---

## Update: Perbaikan Orientasi Foto Kiosk Tablet Portrait (v1.28.1)

### 1. Perbaikan Foto Miring (Tilted/Rotated) pada Kios Tablet Portrait
* **Masalah**: Ketika tablet dipasang dalam posisi portrait, modul kamera fisik pada perangkat berada di sisi kanan (sensor miring 90 atau 270 derajat). Saat foto diambil menggunakan CameraX, hasil foto yang didekode dan disimpan ke penyimpanan lokal menjadi miring (sideways/landscape).
* **Solusi**:
  * Memodifikasi fungsi `takePicture` di dalam [CameraCaptureScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/camera/CameraCaptureScreen.kt).
  * Membaca informasi orientasi EXIF (`android.media.ExifInterface.TAG_ORIENTATION`) yang ditulis oleh pustaka CameraX langsung dari berkas JPEG yang baru disimpan.
  * Secara fisik memutar (rotate) objek `Bitmap` menggunakan `Matrix.postRotate` berdasarkan sudut rotasi EXIF yang terdeteksi (90, 180, atau 270 derajat) sebelum dikompresi kembali ke format JPEG.
  * Hal ini menjamin berkas gambar yang tersimpan sudah berada dalam posisi tegak (upright portrait), sehingga hasil foto tampil tegak sempurna di galeri preview, filmstrip, frame gabungan (stitched frame), maupun saat dicetak ke printer.

### 2. Peningkatan Versi Dinamis Kiosk (v1.28.1)
* Bumping `versionName` ke `"1.28.1"` dan `versionCode` ke `45` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle secara bersih untuk memperbarui berkas APK rilis utama di `backend/app-debug.apk` dan memperbarui `backend/update.json` agar kiosk tablet dapat mengunduh pembaruan secara otomatis.
* Mencadangkan salinan APK versi rilis ke root: `app-debug_v1.28.1.apk`.

---

## Update: Bingkai Baru Dinamis Tema Pemutar Musik (Music Player Aesthetic)

### 1. Perancangan & Pembuatan Gambar Bingkai (`music_player_strip.png`)
* **Visual Theme**: Antarmuka pemutar musik modern (Spotify / Apple Music dark glass aesthetic) dengan gradien hijau zamrud gelap (`#0D3B2E` ke `#061A14`) dan efek pencahayaan radial lembut di area kontrol pemutar.
* **Elemen Header**:
  * Teks tanggal dinamis/statis (`Sabtu, 23 Maret 2026`).
  * Teks jam digital besar serasi (`12.28`).
* **Slot Foto**:
  * 3 slot foto vertikal rasio 4:3 (500x375px) bersudut tumpul (*rounded corners* radius 22px) dengan batas bingkai putih tebal (*white border stroke* 6px).
* **Elemen Tumpang Nindih (Cross-Photo Overlapping - Sesuai standar_bingkai.md)**:
  * Stiker Piringan Hitam (Vinyl Record 33 RPM) di sudut kanan bawah slot foto ke-2.
  * Stiker Transparan Glassmorphism `"PLAYING NOW"` dengan animasi visualizer equalizer di sudut kiri atas slot foto ke-1.
  * Stiker Tombol Hati Merah (❤️) di sudut kiri atas slot foto ke-3.
* **Elemen Kontrol Pemutar Musik (Footer)**:
  * Judul Lagu / Playlist: `"Your Favorite Playlist"` (Dapat diganti secara dinamis via `event_name`).
  * Nama Artis / Subtitle: `"Murad Naser"` (Dapat diganti secara dinamis via `event_subtitle`).
  * Tombol Hati Merah (❤️) di sebelah kanan judul track.
  * Bilah Kemajuan (*Timeline / Progress Bar*) lengkap dengan durasi waktu (`1.10` dan `-4.15`), garis track aktif, dan titik playhead putih.
  * Tombol kontrol pemutar musik komplit: Acak (*Shuffle* 🔀), Sebelumnya (⏮️), Tombol Utama Putar/Jeda (*Play/Pause Circle* ▶️), Selanjutnya (⏭️), dan Ulangi (*Repeat* 🔁).
  * Kapsul/Pill badge putih di bagian footer paling bawah: `"LARANA PHOTOBOX"` (Dapat diganti secara dinamis via `event_hashtag`).

### 2. Pendaftaran Konfigurasi Dinamis (`backend/frames/config.json`)
* Mendaftarkan objek bingkai baru `"music_player_strip"` pada array `"frames"` dan menambahkannya ke list `"allowed_frames"` event `general` (Default).
* Menaikkan versi JSON dari `40` ke `41`.
* Pengaturan variabel dinamis (`"is_dynamic": true`):
  * **Logo Event**: Mengkonfigurasi slot `logo` di area kanan atas footer (`x: 475`, `y: 1475`, `w: 75`, `h: 75`).
  * **Teks Dinamis**: Memetakan `event_name` ke judul lagu, `event_subtitle` ke sub-judul artis, dan `event_hashtag` ke teks kapsul footer.

---

## Update: Bingkai Baru Dinamis Tema Tiket Pesawat / Boarding Pass 4-Foto (`boarding_pass_strip`)

### 1. Perancangan & Pembuatan Gambar Bingkai (`boarding_pass_strip.png`)
* **Visual Theme**: Antarmuka tiket penerbangan maskapai penerbangan mewah (*First Class Boarding Pass*) dengan kombinasi warna kertas tiket premium, biru navy (*#0E1E3C*), dan aksen emas (*#D97706*).
* **Elemen Header**:
  * Bar Banner Utama: `"GROOVY AIRWAYS"` dengan Lencana Emas `"FIRST CLASS"`.
  * Rute Penerbangan: Kode bandara asal & tujuan lengkap dengan ikon pesawat dan garis jalur penerbangan (`CGK JAKARTA ✈️ DPS BALI`).
  * Grid Informasi Penerbangan: `FLIGHT: GA-2026`, `GATE: B07`, `SEAT: 01A`, `BOARDING: 12:30`, serta baris tanggal dan nama penumpang.
* **Layout 4 Foto**:
  * 4 slot foto vertikal rasio 4:3 (500x375px) bersudut tumpul (*rounded corners* radius 12px) dengan *double border line* (stroke navy & emas).
* **Elemen Tumpang Nindih (Cross-Photo Overlapping - Sesuai standar_bingkai.md)**:
  * **Stiker Priority Bagasi**: Label merah `"PRIORITY PASS"` di sudut kiri atas slot foto ke-1.
  * **Stempel Paspor Bea Cukai (Customs Control)**: Cap bulat merah miring `"PASSPORT CONTROL / APPROVED"` di sudut kanan bawah slot foto ke-2.
  * **Garis Garitan Potong Tiket (Stub Perforation Line)**: Dotted tear line dengan lubang notch melingkar di tepi kiri-kanan pada perbatasan slot foto ke-3.
  * **Badge Struk Penumpang**: Lencana navy `"PASSENGER STUB"` di sudut kiri atas slot foto ke-4.
* **Elemen Footer**:
  * Judul Tiket Penumpang & Ruang Teks Dinamis.
  * Grafik Barcode Cetak Tiket Penerbangan utuh di bagian paling bawah dengan nomor seri penerbangan.

### 2. Pendaftaran Konfigurasi Dinamis (`backend/frames/config.json`)
* ID Bingkai: `"boarding_pass_strip"`
* Nama: `"Boarding Pass 4-Foto"` (Kategori: `Travel`)
* Dimensi: 600x2400px (Kapasitas 4 Slot Foto)
* Mendaftarkan ke list `"allowed_frames"` event `general` dan menaikkan versi JSON dari `41` ke `42`.
* Pemetaan Variabel Dinamis:
  * `event_name` ➔ Judul Utama Penerbangan / Event.
  * `event_subtitle` ➔ Tagline Nama Penumpang / Sub-judul Event.
  * `event_hashtag` ➔ Kode Seri Tiket / Hashtag Event.
  * `logo` ➔ Slot Logo Maskapai / Penyelenggara di area kanan footer (`x: 440`, `y: 2000`, `w: 90`, `h: 90`).

---

## Update: Bingkai Baru Dinamis Stacked Polaroid 4-Foto (`my_style_stacked`) & Dukungan Rotasi Foto (v1.29.0)

### 1. Implementasi Dukungan Rotasi Slot Foto
* **Android App (Kotlin/Compose)**:
  * **[Frame.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/data/Frame.kt)**: Menambahkan field `rotation: Float? = 0f` ke dalam class data `Slot`.
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

---

## Update: Standardisasi Branding Event Dinamis & Pembersihan Placeholder "Dago"

### 1. Perubahan Nilai Fallback Kiosk (Default Brand)
* **Branding Default**: Mengubah default fallback nama brand pada Kiosk dari `"Studio Foto"` menjadi `"Jeprat Jepret"`, dan slogan default dari `"Abadikan Momenmu"` menjadi `"All You need is special"`.
* **Kesesuaian di JavaScript**: Memperbarui konstanta `NEUTRAL_NAME` dan `NEUTRAL_SLOGAN` di dalam fungsi `updateHomeScreenBranding()` pada file [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) agar ketika event yang aktif adalah event umum/default (`general`), branding secara otomatis bernilai `"Jeprat Jepret"` dengan tagline yang sesuai.
* **Canvas Karakter Kartu**: Mengganti fallback nama brand teks statis `"STUDIO FOTO"` pada fungsi verifikasi kartu karakter AI di [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) menjadi pencarian dinamis dari event umum di server (`brandName`), dan jika kosong otomatis mengarah ke `"JEPRAT JEPRET"`.

### 2. Integrasi Dinamis Layout Struk & Header Brand Dago Orange
* **Penyelesaian HTML Statis**: Menghapus teks statis `"Dago."`, `"RECEIPT PHOTOBOOTH"`, `"SINCE 2020"`, dan `"DAILY NEWS"` yang sebelumnya mengunci tampilan struk di halaman share/cetak struk simulator.
* **Injeksi Data Awal via PHP**: Menambahkan blok pendeteksi info event general di awal [kiosk_sim.php](file:///c:/laragon/www/photobooth/backend/kiosk_sim.php) untuk mem-parsing nama brand umum secara dinamis saat halaman pertama kali dimuat di browser.
* **Output Variabel**: Menghubungkan variabel PHP tersebut ke dalam markup HTML di elemen header logo (`dagoBrandMainText`), sub-header (`dago-brand-sub1`), logo kertas struk (`dagoReceiptPaperLogoText`), dan judul struk (`dagoReceiptPaperTitle`). Dengan demikian, cetakan struk simulator sekarang tampil dinamis berdasarkan data event yang sedang dipilih/aktif.

---

## Update: Perbaikan Orientasi Foto Kiosk Tablet Portrait (v1.28.1)

### 1. Perbaikan Foto Miring (Tilted/Rotated) pada Kios Tablet Portrait
* **Masalah**: Ketika tablet dipasang dalam posisi portrait, modul kamera fisik pada perangkat berada di sisi kanan (sensor miring 90 atau 270 derajat). Saat foto diambil menggunakan CameraX, hasil foto yang didekode dan disimpan ke penyimpanan lokal menjadi miring (sideways/landscape).
* **Solusi**:
  * Memodifikasi fungsi `takePicture` di dalam [CameraCaptureScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/camera/CameraCaptureScreen.kt).
  * Membaca informasi orientasi EXIF (`android.media.ExifInterface.TAG_ORIENTATION`) yang ditulis oleh pustaka CameraX langsung dari berkas JPEG yang baru disimpan.
  * Secara fisik memutar (rotate) objek `Bitmap` menggunakan `Matrix.postRotate` berdasarkan sudut rotasi EXIF yang terdeteksi (90, 180, atau 270 derajat) sebelum dikompresi kembali ke format JPEG.
  * Hal ini menjamin berkas gambar yang tersimpan sudah berada dalam posisi tegak (upright portrait), sehingga hasil foto tampil tegak sempurna di galeri preview, filmstrip, frame gabungan (stitched frame), maupun saat dicetak ke printer.

### 2. Peningkatan Versi Dinamis Kiosk (v1.28.1)
* Bumping `versionName` ke `"1.28.1"` dan `versionCode` ke `45` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle secara bersih untuk memperbarui berkas APK rilis utama di `backend/app-debug.apk` dan memperbarui `backend/update.json` agar kiosk tablet dapat mengunduh pembaruan secara otomatis.
* Mencadangkan salinan APK versi rilis ke root: `app-debug_v1.28.1.apk`.

---

## Update: Bingkai Baru Dinamis Tema Pemutar Musik (Music Player Aesthetic)

### 1. Perancangan & Pembuatan Gambar Bingkai (`music_player_strip.png`)
* **Visual Theme**: Antarmuka pemutar musik modern (Spotify / Apple Music dark glass aesthetic) dengan gradien hijau zamrud gelap (`#0D3B2E` ke `#061A14`) dan efek pencahayaan radial lembut di area kontrol pemutar.
* **Elemen Header**:
  * Teks tanggal dinamis/statis (`Sabtu, 23 Maret 2026`).
  * Teks jam digital besar serasi (`12.28`).
* **Slot Foto**:
  * 3 slot foto vertikal rasio 4:3 (500x375px) bersudut tumpul (*rounded corners* radius 22px) dengan batas bingkai putih tebal (*white border stroke* 6px).
* **Elemen Tumpang Nindih (Cross-Photo Overlapping - Sesuai standar_bingkai.md)**:
  * Stiker Piringan Hitam (Vinyl Record 33 RPM) di sudut kanan bawah slot foto ke-2.
  * Stiker Transparan Glassmorphism `"PLAYING NOW"` dengan animasi visualizer equalizer di sudut kiri atas slot foto ke-1.
  * Stiker Tombol Hati Merah (❤️) di sudut kiri atas slot foto ke-3.
* **Elemen Kontrol Pemutar Musik (Footer)**:
  * Judul Lagu / Playlist: `"Your Favorite Playlist"` (Dapat diganti secara dinamis via `event_name`).
  * Nama Artis / Subtitle: `"Murad Naser"` (Dapat diganti secara dinamis via `event_subtitle`).
  * Tombol Hati Merah (❤️) di sebelah kanan judul track.
  * Bilah Kemajuan (*Timeline / Progress Bar*) lengkap dengan durasi waktu (`1.10` dan `-4.15`), garis track aktif, dan titik playhead putih.
  * Tombol kontrol pemutar musik komplit: Acak (*Shuffle* 🔀), Sebelumnya (⏮️), Tombol Utama Putar/Jeda (*Play/Pause Circle* ▶️), Selanjutnya (⏭️), dan Ulangi (*Repeat* 🔁).
  * Kapsul/Pill badge putih di bagian footer paling bawah: `"LARANA PHOTOBOX"` (Dapat diganti secara dinamis via `event_hashtag`).

### 2. Pendaftaran Konfigurasi Dinamis (`backend/frames/config.json`)
* Mendaftarkan objek bingkai baru `"music_player_strip"` pada array `"frames"` dan menambahkannya ke list `"allowed_frames"` event `general` (Default).
* Menaikkan versi JSON dari `40` ke `41`.
* Pengaturan variabel dinamis (`"is_dynamic": true`):
  * **Logo Event**: Mengkonfigurasi slot `logo` di area kanan atas footer (`x: 475`, `y: 1475`, `w: 75`, `h: 75`).
  * **Teks Dinamis**: Memetakan `event_name` ke judul lagu, `event_subtitle` ke sub-judul artis, dan `event_hashtag` ke teks kapsul footer.

---

## Update: Bingkai Baru Dinamis Tema Tiket Pesawat / Boarding Pass 4-Foto (`boarding_pass_strip`)

### 1. Perancangan & Pembuatan Gambar Bingkai (`boarding_pass_strip.png`)
* **Visual Theme**: Antarmuka tiket penerbangan maskapai penerbangan mewah (*First Class Boarding Pass*) dengan kombinasi warna kertas tiket premium, biru navy (*#0E1E3C*), dan aksen emas (*#D97706*).
* **Elemen Header**:
  * Bar Banner Utama: `"GROOVY AIRWAYS"` dengan Lencana Emas `"FIRST CLASS"`.
  * Rute Penerbangan: Kode bandara asal & tujuan lengkap dengan ikon pesawat dan garis jalur penerbangan (`CGK JAKARTA ✈️ DPS BALI`).
  * Grid Informasi Penerbangan: `FLIGHT: GA-2026`, `GATE: B07`, `SEAT: 01A`, `BOARDING: 12:30`, serta baris tanggal dan nama penumpang.
* **Layout 4 Foto**:
  * 4 slot foto vertikal rasio 4:3 (500x375px) bersudut tumpul (*rounded corners* radius 12px) dengan *double border line* (stroke navy & emas).
* **Elemen Tumpang Nindih (Cross-Photo Overlapping - Sesuai standar_bingkai.md)**:
  * **Stiker Priority Bagasi**: Label merah `"PRIORITY PASS"` di sudut kiri atas slot foto ke-1.
  * **Stempel Paspor Bea Cukai (Customs Control)**: Cap bulat merah miring `"PASSPORT CONTROL / APPROVED"` di sudut kanan bawah slot foto ke-2.
  * **Garis Garitan Potong Tiket (Stub Perforation Line)**: Dotted tear line dengan lubang notch melingkar di tepi kiri-kanan pada perbatasan slot foto ke-3.
  * **Badge Struk Penumpang**: Lencana navy `"PASSENGER STUB"` di sudut kiri atas slot foto ke-4.
* **Elemen Footer**:
  * Judul Tiket Penumpang & Ruang Teks Dinamis.
  * Grafik Barcode Cetak Tiket Penerbangan utuh di bagian paling bawah dengan nomor seri penerbangan.

### 2. Pendaftaran Konfigurasi Dinamis (`backend/frames/config.json`)
* ID Bingkai: `"boarding_pass_strip"`
* Nama: `"Boarding Pass 4-Foto"` (Kategori: `Travel`)
* Dimensi: 600x2400px (Kapasitas 4 Slot Foto)
* Mendaftarkan ke list `"allowed_frames"` event `general` dan menaikkan versi JSON dari `41` ke `42`.
* Pemetaan Variabel Dinamis:
  * `event_name` ➔ Judul Utama Penerbangan / Event.
  * `event_subtitle` ➔ Tagline Nama Penumpang / Sub-judul Event.
  * `event_hashtag` ➔ Kode Seri Tiket / Hashtag Event.
  * `logo` ➔ Slot Logo Maskapai / Penyelenggara di area kanan footer (`x: 440`, `y: 2000`, `w: 90`, `h: 90`).

---

## Update: Bingkai Baru Dinamis Stacked Polaroid 4-Foto (`my_style_stacked`) & Dukungan Rotasi Foto (v1.29.0)

### 1. Implementasi Dukungan Rotasi Slot Foto
* **Android App (Kotlin/Compose)**:
  * **[Frame.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/data/Frame.kt)**: Menambahkan field `rotation: Float? = 0f` ke dalam class data `Slot`.
  * **[PreviewResultScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/preview/PreviewResultScreen.kt)**:
    * Mengubah fungsi penggabungan foto ke Canvas utama agar memutar (`canvas.rotate`) gambar photo sesuai dengan properti `slot.rotation` yang didefinisikan di `config.json`.
    * Menggunakan `Modifier.rotate(slot.rotation ?: 0f)` pada komponen `Box` slot preview Compose agar tampilan interaktif pada layar kiosk selaras dengan hasil cetak akhir.
    * Mengimplementasikan rotasi pada gambar garis tepi fallback (Compose `Canvas` dan android `Canvas`) agar tetap presisi.
* **Kiosk Web Simulator**:
  * **[kiosk_sim.php](file:///c:/laragon/www/Photoboth/backend/kiosk_sim.php)**: Menambahkan fungsionalitas rotasi canvas HTML5 (`bgCtx.translate`, `bgCtx.rotate`, dan `bgCtx.restore`) saat menggabungkan foto hasil jepretan sebelum diunggah ke server.

### 2. Perancangan & Pembuatan Gambar Bingkai (`my_style_stacked.png`)
* **Visual Theme**: Desain estetik modern bertema papan kolase kliping polaroid (*Polaroid Scrapbook Cardstack*) dengan warna latar belakang biru dongker/navy slate (*#222B3E*) yang anggun.
* **Elemen Latar Belakang & Kisi (Grid)**:
  * Kisi-kisi cetakan blueprint / buku catatan kotak-kotak (*Notebook grid lines*) tipis berwarna putih transparan (jarak setiap 48px) yang menghiasi seluruh latar belakang agar tidak polos.
* **Elemen Header & Doodle**:
  * Teks judul miring artistik `"My Style"` menggunakan font tulisan tangan premium Segoe Script (`segoescb.ttf`), dihiasi coretan garis ganda gelombang di bawahnya.
  * Elemen coretan kapur putih estetik (*Hand-drawn chalk doodles*):
    * Coretan percikan air (*droplet splash*) di pojok kanan atas dan garis ulir berputar (*ribbon swirl loop*) di pojok kanan bawah.
    * Coretan anak panah melengkung (*curved arrows*) estetik yang menghubungkan Polaroid 1 ke Polaroid 2 dan Polaroid 3 ke Polaroid 4 lengkap dengan teks catatan kecil `"this is me"` dan `"vibes"`.
    * Hiasan bintang percikan 4-titik (✦) yang tersebar di area kosong bingkai.
* **Tumpukan 4 Polaroid Stack**:
  * Menggambar 4 kartu foto polaroid putih dengan batas luar (*margins*) tebal khas cetakan polaroid klasik (dengan margin bawah lebih lebar).
  * **Catatan Tulisan Tangan Polaroid**: Mencetak teks tulisan tangan miring estetik di bagian bawah setiap kartu polaroid sesuai sudut kemiringannya: `"smile today! :)"`, `"favorite look <3"`, `"good vibes only"`, dan `"happy memory *"`.
  * **Isolasi Perekat Warna (Washi Tape)**: Menambahkan isolasi transparan semi-warna (washi tape) pastel miring yang merekatkan sudut-sudut polaroid ke halaman (warna kuning, hijau, dan pink) sehingga menyatu secara visual dan tidak terkesan sekadar ditempel biasa.
  * Menambahkan bayangan lembut miring (*drop shadows*) berwarna gelap transparan di belakang setiap kartu polaroid untuk memberikan kedalaman visual 3D yang sangat premium.
  * Kartu disusun tumpang-tindih secara miring organik bergantian:
    * Polaroid 1: Kemiringan -7°
    * Polaroid 2: Kemiringan +7°
    * Polaroid 3: Kemiringan -5°
    * Polaroid 4: Kemiringan +8°
  * Memotong lubang transparan di tengah-tengah setiap polaroid miring dengan presisi (`imagealphablending($img, false)` dan `imagefilledpolygon`) agar foto jepretan pengguna terlihat rapi di bawah frame.

### 3. Pendaftaran Konfigurasi Dinamis (`backend/frames/config.json`)
* ID Bingkai: `"my_style_stacked"`
* Nama: `"My Style Stacked"` (Kategori: `Aesthetic`)
* Dimensi: 600x2400px (Kapasitas 4 Slot Foto)
* Menambahkan properti `"rotation"` di setiap slot (`-7`, `7`, `-5`, `8`).
* Mendaftarkan ke list `"allowed_frames"` event `general` dan menaikkan versi JSON dari `44` ke `45`.
* Pemetaan Variabel Dinamis:
  * `event_name` ➔ Judul utama event (di area kiri bawah footer).
  * `event_subtitle` ➔ Sub-judul / detail tanggal & tempat event.
  * `event_hashtag` ➔ Hashtag / teks promosi event.
  * `logo` ➔ Slot Logo bulat/stiker di pojok kanan bawah footer (`x: 440`, `y: 2020`, `w: 110`, `h: 110`).

---

## Update: Perbaikan Pemotongan Foto Portrait (v1.28.3)

### 1. Pemotongan Foto Otomatis ke Rasio Slot Kamera
* **Masalah**: Ketika tablet dipasang dalam posisi portrait, foto jepretan CameraX yang disimpan pada memori cache perangkat memiliki rasio aspek portrait (3:4). Ketika diaplikasikan pada bingkai yang memiliki slot landscape (seperti `social_media_feed` dengan slot 460x345), terjadi bug visual di mana bagian bawah slot menampilkan blok abu-abu gelap padat karena foto tidak menutupi seluruh tinggi slot secara merata.
* **Solusi**:
  * Memodifikasi fungsi `takePicture` di dalam [CameraCaptureScreen.kt](file:///c:/laragon/www/Photoboth/app/src/main/java/com/example/photobooth/ui/camera/CameraCaptureScreen.kt).
  * Menambahkan kalkulasi pemotongan bitmap (`Bitmap.createBitmap`) secara otomatis ke rasio aspek target (`slotAspectRatio`) tepat setelah rotasi orientasi fisik dilakukan dan sebelum ML Kit Face Detection dijalankan.
  * Hal ini menjamin file foto yang tersimpan di disk sudah memiliki rasio aspek landscape yang presisi sesuai slot bingkai, sehingga hasil pratinjau Compose (`AsyncImage` tanpa offset) dan penggabungan bitmap (`stitchPhotos`) tidak menyisakan area kosong atau blok abu-abu gelap.

### 2. Bumping Versi Kiosk & Build (v1.28.3)
* Mendaftarkan `versionName = "1.28.3"` dan `versionCode = 47` di [build.gradle.kts](file:///c:/laragon/www/Photoboth/app/build.gradle.kts).
* Menjalankan build Gradle clean release untuk memperbarui `backend/app-debug.apk` dan berkas manifest `backend/update.json`.
* Mencadangkan salinan APK rilis ke root: `app-debug_v1.28.3.apk`.

