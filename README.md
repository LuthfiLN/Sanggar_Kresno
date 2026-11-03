# Dapur Tresno Medan

Toko online kue Lebaran rumahan dengan sistem preorder. Kue mulai dibuat setelah DP 50% dikonfirmasi admin. Pelunasan dilakukan sebelum pesanan dikirim.

## Menjalankan di XAMPP

1. Letakkan folder di `C:\xampp\htdocs\Sanggar_Tresno`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Pastikan database `sanggar_tresno` tersedia di MySQL. Installer tidak membuat database baru.
4. Buka `http://localhost/Sanggar_Tresno/install.php`. Installer menyiapkan tabel aplikasi preorder di database tersebut dan menambahkan produk contoh jika katalog masih kosong.
5. Buat akun admin pertama jika belum ada.
6. Buka `http://localhost/Sanggar_Tresno/` untuk katalog atau `http://localhost/Sanggar_Tresno/admin/` untuk dashboard.

## Alur preorder

- Pembeli memilih kue dan tanggal pengiriman di Medan.
- Sistem mencatat total dan nominal DP tepat 50% dari harga kue. Ongkir dikonfirmasi terpisah lewat chat.
- Admin mengirim detail pembayaran melalui chat dan memverifikasi transfer DP secara manual.
- Setelah DP terverifikasi, pesanan masuk tahap produksi.
- Admin menandai pembayaran lunas setelah sisa pembayaran diterima. Pesanan baru dapat dikirim setelah pelunasan 100%.

Belum ada payment gateway yang diintegrasikan. Jangan menandai pembayaran terverifikasi sebelum dana benar-benar diterima. Produk contoh dan ilustrasi kue berada di folder `assets`.

## Konfigurasi

Pengaturan koneksi database `sanggar_tresno` berada di `config.php`. Nilai awal mengikuti XAMPP lokal (`root` tanpa kata sandi). Sebelum dipasang di server publik, ubah kredensial database, gunakan HTTPS, dan amankan akses installer serta akun admin.