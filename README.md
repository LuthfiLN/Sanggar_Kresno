# Sanggar Kresno Medan

Toko online kue Lebaran rumahan dengan sistem preorder. Kue mulai dibuat setelah DP 50% dikonfirmasi admin. Pelunasan dilakukan sebelum pesanan dikirim.

## Menjalankan di XAMPP

1. Letakkan folder di `C:\xampp\htdocs\Sanggar_Tresno`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Pastikan database `sanggar_tresno` tersedia di MySQL.
4. Siapkan tabel aplikasi sesuai schema database sebelum membuka aplikasi.
5. Buat akun admin pertama jika belum ada.
6. Buka `http://localhost/Sanggar_Tresno/` untuk katalog atau `http://localhost/Sanggar_Tresno/admin/` untuk dashboard.

Gunakan satu halaman login di `http://localhost/Sanggar_Tresno/login.php`. Setelah email dan kata sandi diverifikasi, sistem membaca role akun dan mengarahkan admin ke dashboard atau pelanggan ke toko. Registrasi publik hanya membuat akun pelanggan. Admin yang sudah masuk dapat membuat admin tambahan melalui menu **Akun admin**.

## Alur preorder

- Pembeli memilih kue dan tanggal pengiriman di Medan, minimal 3 hari setelah pemesanan. Admin dapat menyesuaikan jadwal setelah menyepakatinya dengan pelanggan melalui chat.
- Sistem mencatat total dan nominal DP tepat 50% dari harga kue. Ongkir dikonfirmasi terpisah lewat chat.
- Admin mengirim detail pembayaran melalui chat dan memverifikasi transfer DP secara manual.
- Setelah DP terverifikasi, pesanan masuk tahap produksi.
- Admin menandai pembayaran lunas setelah sisa pembayaran diterima. Pesanan baru dapat dikirim setelah pelunasan 100%.

Belum ada payment gateway yang diintegrasikan. Jangan menandai pembayaran terverifikasi sebelum dana benar-benar diterima. Produk contoh dan ilustrasi kue berada di folder `assets`.

## Struktur aplikasi

Halaman publik berada di root, halaman admin berada di `admin/`, sehingga URL Apache/XAMPP tetap langsung dan tidak membutuhkan router tambahan. Kode bersama dikelompokkan berdasarkan tanggung jawab:

```text
app/
  Bootstrap/       bootstrap aplikasi dan session
  Config/          konfigurasi aplikasi/database
  Database/        koneksi dan schema
  Repositories/    akses data terpusat
  Support/         helper, autentikasi, enkripsi
  Views/           template publik dan admin
admin/             halaman dashboard admin
assets/            CSS dan gambar
api/               router serverless opsional
```

`config.php` adalah compatibility bootstrap tipis untuk mempertahankan URL lama. Semua halaman memuat bootstrap tunggal melalui file tersebut.

## Konfigurasi

Salin `.env.example` menjadi `.env`. Jangan commit `.env`. Generate `APP_KEY` acak dan rahasiakan. Gunakan HTTPS di production.

Database lokal menggunakan MySQL XAMPP. `DATABASE_URL` dapat dipakai untuk provider MySQL-compatible seperti TiDB Cloud.

## Vercel + TiDB

Bisa secara teknis, dengan batasan penting:

1. TiDB Cloud kompatibel dengan MySQL/PDO. Gunakan host, port, database, username, password, dan TLS dari TiDB Cloud. Set `DB_SSL=true` bila provider mewajibkan TLS.
2. Vercel tidak menyediakan runtime PHP resmi seperti Apache/PHP-FPM. PHP perlu runtime komunitas/custom. Uji seluruh route, upload, rewrite, dan extension `pdo_mysql` sebelum production.
3. Vercel bersifat serverless/stateless. Session file lokal, SQLite lokal, dan file upload lokal tidak boleh dianggap persisten.
4. Gunakan session eksternal/database-backed, object storage untuk upload, dan TiDB sebagai database utama. Set `DB_REQUIRE=true` di production agar fallback SQLite tidak dipakai.
5. Untuk aplikasi PHP server-rendered dengan session admin, pilihan lebih stabil: Railway, Render, Fly.io, atau VPS PHP-FPM. Vercel lebih cocok setelah aplikasi diubah menjadi API/serverless atau frontend terpisah.

Checklist production:

- `APP_ENV=production`
- `DB_REQUIRE=true`
- `APP_KEY` unik, panjang, rahasia
- `DB_SSL=true` sesuai konfigurasi TiDB
- HTTPS aktif
- installer dinonaktifkan/dihapus
- backup database aktif
- session tidak memakai filesystem ephemeral
- semua route PHP diuji setelah deploy
