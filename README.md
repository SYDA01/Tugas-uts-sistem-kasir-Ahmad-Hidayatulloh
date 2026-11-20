# BPHOMES - Pencatatan Barang Masuk/Keluar & Laba

Aplikasi web sederhana (PHP + MySQL) untuk mencatat barang masuk, barang keluar (terjual), stok, dan laba usaha retail kecil. Berjalan lokal di XAMPP, tanpa internet dan tanpa biaya server.

## Fitur

- Login berbasis database (password disimpan ter-hash bcrypt)
- Kelola pengguna, satuan barang, dan nama toko (menu **Pengaturan**)
- Daftar barang (kode, nama, satuan, stok, modal rata-rata)
- Catat barang masuk (pembelian) dan barang keluar (penjualan)
- Stok, modal rata-rata (moving average), dan laba dihitung otomatis
- Stok tidak boleh minus; hapus transaksi akan menghitung ulang stok dan laba
- Laporan dengan filter tanggal, jenis, dan barang
- **Download Excel (.xlsx)** untuk laporan dan stok
- Cetak langsung dari browser

## Kebutuhan

- XAMPP (Apache + MySQL/MariaDB), PHP 7.4 atau lebih baru
- Ekstensi PHP `zip` aktif (untuk export Excel; biasanya sudah aktif)

## Instalasi

1. Install dan buka **XAMPP Control Panel**, lalu **Start** Apache dan MySQL.
2. Salin `bphomes.php` ke folder `C:\xampp\htdocs\bphomes\` (buat foldernya).
3. Buat database, pilih salah satu cara:
   - **Otomatis**: langsung buka aplikasi, database dan tabel dibuat sendiri.
   - **Manual**: buka `http://localhost/phpmyadmin`, tab **Import**, pilih `database.sql`, klik **Import**.
4. Buka `http://localhost/bphomes/bphomes.php`.

> Koneksi database memakai user `root` tanpa password (default XAMPP). Kalau berbeda, ubah baris `new PDO(...)` di bagian atas `bphomes.php`.

## Login awal

| Username | Password |
|----------|----------|
| BPHOMES  | BP2026   |

Segera ganti password di menu **Pengaturan > Ganti Password Saya**.

## Cara pakai

1. **Pengaturan**: isi nama toko, tambah satuan atau pengguna bila perlu.
2. **Daftar Barang**: tambah barang (kode unik, nama, satuan).
3. **Barang Masuk**: catat pembelian (jumlah dan harga beli per satuan).
4. **Barang Keluar**: catat penjualan (jumlah dan harga jual per satuan). Laba dihitung dari modal rata-rata saat dijual.
5. **Laporan**: pilih periode lalu klik **Tampilkan**, kemudian **Download Excel** atau **Cetak**.
6. **Ringkasan**: lihat stok, nilai stok, penjualan, dan laba bulan ini.

## Struktur database (`bphomes`)

| Tabel        | Isi                                                     |
|--------------|---------------------------------------------------------|
| `pengguna`   | Akun login (username, nama, password_hash)              |
| `satuan`     | Daftar satuan barang                                    |
| `pengaturan` | Pengaturan aplikasi (nama toko)                         |
| `barang`     | Master barang, stok, modal rata-rata                    |
| `transaksi`  | Barang masuk/keluar, harga, HPP (modal saat terjual)    |

## Rumus laba

- Modal rata-rata = total nilai stok / jumlah stok (diperbarui tiap barang masuk)
- Laba per penjualan = jumlah x (harga jual - modal rata-rata saat dijual)

## Troubleshooting

- **"Database tidak bisa diakses"**: pastikan MySQL di XAMPP sudah **Start**.
- **Export Excel error soal zip**: buka `php.ini` (XAMPP Control Panel > Apache > Config > PHP), aktifkan `extension=zip`, restart Apache.
- **Lupa password**: di phpMyAdmin, hapus semua isi tabel `pengguna`, lalu buka aplikasi lagi. Akun awal `BPHOMES` / `BP2026` dibuat ulang.
- **Backup data**: di phpMyAdmin, pilih database `bphomes` lalu tab **Export**.
