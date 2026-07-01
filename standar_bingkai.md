# Standar Desain Bingkai Photo Booth (Groovy Studio)

Dokumen ini berisi pedoman standar untuk merancang dan membuat bingkai (*frame templates*) baru agar menghasilkan kualitas visual yang premium, kreatif, dan terbaca dengan baik saat dicetak.

---

## 1. Jumlah Foto Sesuai Instruksi
Setiap bingkai harus dikonfigurasi dengan jumlah slot foto yang tepat sesuai dengan pesanan/instruksi klien (misalnya 1, 3, atau 4 foto). 
- Koordinat `x`, `y`, `width`, dan `height` dari setiap slot foto harus dihitung secara presisi di file `config.json`.
- Selalu pastikan rasio aspek slot foto konsisten (misalnya rasio 4:3 untuk foto *landscape* standar).

## 2. Ukuran Font Terbaca pada Cetak Receipt (Thermal Paper)
Kertas struk/receipt thermal memiliki resolusi cetak yang rendah dan kontras hitam-putih yang ekstrem. Teks yang terlalu kecil akan pecah atau buram saat dicetak.
- **Ukuran Font Minimum**: 
  - **Teks Utama/Judul**: Minimal `18pt` hingga `36pt` (Tebal/Bold).
  - **Meta Informasi/Sub-teks**: Minimal `9pt` hingga `12pt`. Jangan pernah menggunakan font di bawah `9pt` untuk informasi penting.
- **Jenis Font**: Gunakan font berserif tegas seperti `Georgia` atau font bertipe mesin ketik seperti `Courier` yang memiliki keterbacaan tinggi pada printer thermal. Hindari font sans-serif tipis untuk detail kecil.

## 3. Konten Overlapping (Cross Foto)
Bingkai yang hanya berupa kotak kosong biasa terlihat membosankan dan kurang premium. Untuk membuat desain yang lebih dinamis dan "hidup", berikan elemen tumpang tindih (*overlapping/cross-photo elements*):
- Letakkan stempel antik, label perekat, pita washi, stiker penawaran, tanda tangan tulisan tangan, atau teks dekoratif yang memotong garis batas (*border*) slot foto.
- **Logo Dinamis Event**: Jika bingkai diperintahkan dinamis, logo event (`logo`) juga dapat diatur secara kreatif agar posisinya memotong/menumpuk batas slot foto (misalnya sebagai stiker segel bulat di sudut foto). Ini memberikan sentuhan integrasi merek yang sangat tinggi dan profesional.
- Teknik ini memberikan kesan berlapis (3D) seperti lembaran kolase (*scrapbook*) atau halaman majalah fisik.

## 4. Desain yang Kreatif & Tematik
Hindari bingkai polos tanpa dekorasi. Selalu tambahkan elemen dekoratif yang relevan dengan tema:
- **Tema Koran**: Tambahkan garis pemisah ganda khas surat kabar lama, judul berita utama (*headline*) yang bombastis, kolom teks artikel opini mini, barcode pembelian, serta stempel merah "APPROVED" atau "BREAKING".
- **Tema Retro/Y2K**: Tambahkan ornamen bintang 4 sudut, elemen kawat industri (*wireframe*), logo stiker transparan, dan efek warna gradien neon.

## 5. Implementasi Bingkai Dinamis (Dynamic Frames)
Jika bingkai dikonfigurasi sebagai dinamis (`"is_dynamic": true` di `config.json`), desainer wajib merencanakan peletakan teks dan logo dinamis (yang diambil dari form event di dashboard admin) secara cerdas dan estetis:
- **Logo Event**: Tempatkan slot logo (`logo`) secara proporsional. Misalnya, letakkan di bagian tengah bawah (di antara info teks), di dalam kotak *branding* utama, atau sebagai elemen overlapping (cross-photo) seperti pedoman pada poin 3. Pastikan ukurannya cukup besar untuk dicetak namun tidak menutupi konten penting.
- **Nama Event**: Gunakan koordinat nama event (`event_name`) sebagai judul/headline sekunder di bagian atas atau bawah bingkai. Set warna dan jenis font tebal yang serasi dengan tema latar belakang.
- **Sub-judul/Tagline & Hashtag**: Letakkan teks dinamis ini (`event_subtitle`, `event_hashtag`) di area footer/kaki bingkai, atau sejajarkan di bawah nama event. Berikan ruang kosong yang cukup agar teks dinamis tidak saling menabrak dengan teks statis latar belakang bingkai jika teks yang diinputkan pengguna cukup panjang.
