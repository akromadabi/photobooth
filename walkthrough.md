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
