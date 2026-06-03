# Panduan Penggunaan & Optimasi Kiosk Photobooth

Dokumen ini berisi panduan untuk mengakses **Dashboard Admin Web** (kontrol jarak jauh) dan cara melakukan build **APK Rilis Teroptimasi (12 - 18 MB)** menggunakan R8 Minification.

---

## 1. Dashboard Admin Web & Kontrol Kiosk Jarak Jauh

Dashboard Admin Web memungkinkan Anda memantau aktivitas kiosk, melihat riwayat foto, mengunduh file asli, menghapus data, dan mengatur konfigurasi kiosk (seperti *countdown* atau *total shots*) secara nirkabel dari PC atau HP.

### Cara Mengakses Dashboard
1. Jalankan server lokal Anda (Laragon/XAMPP) atau pastikan server hosting web Anda aktif.
2. Buka browser di PC/Laptop Anda dan kunjungi url berikut:
   - **Localhost (Laragon)**: `http://localhost/Photoboth/backend/admin.php`
   - **Online / Production IP**: `http://<domain-atau-ip-server>/backend/admin.php`
3. Masukkan **PIN Admin Keamanan** (Default awal: `1234`).
4. Setelah masuk, Anda dapat melihat total sesi, kapasitas memori, log grafik, galeri foto, dan panel kontrol konfigurasi kiosk.

### Cara Kerja Sinkronisasi Pengaturan
Saat Anda mengubah pengaturan di Dashboard Web (misal: merubah *Countdown* menjadi 6 detik) dan menekan **Simpan & Sinkron Kiosk**:
1. Server akan menulis konfigurasi baru ke file `backend/settings.json`.
2. Ketika aplikasi Android Kiosk dibuka atau kembali aktif (*resume*), aplikasi akan secara otomatis memanggil API `settings.json` di server.
3. Aplikasi Android akan mendownload pengaturan baru tersebut dan memperbarui parameternya secara real-time tanpa perlu menginstall ulang aplikasi.

---

## 2. Cara Mem-Build APK Rilis Teroptimasi (12 - 18 MB)

Secara default, saat Anda membuat APK versi debug (`assembleDebug`), Android Studio atau Gradle tidak melakukan kompresi dan tidak membuang library yang tidak digunakan, sehingga ukuran APK membengkak menjadi **~60 MB**.

Kami telah mengonfigurasi **R8 Kotlin Gradle Code & Resource Shrinking** untuk build rilis. Ini akan membuang ribuan aset ikon dan modul ML Kit yang tidak terpakai dari pustaka tanpa mengurangi kualitas atau fungsionalitas program.

### Langkah-langkah Melakukan Build Rilis

1. Buka terminal (PowerShell) di direktori root proyek (`c:\laragon\www\Photoboth`).
2. Tentukan variabel `JAVA_HOME` ke JDK 17 lokal Anda dan jalankan perintah build rilis berikut:
   ```powershell
   $env:JAVA_HOME="C:\Users\Mazin Si Kecil\.jdk\jdk-17.0.19+10"; .\gradlew.bat assembleRelease
   ```
3. Gradle akan memproses kompilasi rilis, membuang kode/aset yang tidak terpakai, dan menerapkan aturan optimasi keamanan (`proguard-rules.pro`).
4. Setelah build berhasil, file APK hasil optimasi yang berukuran sangat kecil (**12 - 18 MB**) akan disimpan di:
   `c:\laragon\www\Photoboth\app\build\outputs\apk\release\app-release-unsigned.apk` (atau `app-release.apk` jika sudah ditandatangani).

---

## Aturan Pengaman Proguard (`proguard-rules.pro`)

Pengaman telah dikonfigurasi di file [proguard-rules.pro](file:///c:/laragon/www/Photoboth/app/proguard-rules.pro) untuk menjaga kelas-kelas data internal (seperti model respons server JSON) agar tetap terbaca dengan baik dan tidak terganggu oleh proses minifikasi:
```proguard
# Gson specific rules to preserve reflection mappings
-keepattributes Signature, *Annotation*, EnclosingMethod, InnerClasses
-keep class com.google.gson.** { *; }

# Retain API endpoint methods and request/response serializable data structures
-keep class com.example.photobooth.api.** { *; }
-keep class com.example.photobooth.data.** { *; }

# Keep models annotated with @Keep to prevent obfuscation/renaming
-keep @androidx.annotation.Keep class * { *; }
```
Hal ini memastikan sinkronisasi data dengan server web berjalan aman 100%!
