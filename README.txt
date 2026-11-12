KAKANG SENDUK PONOROGO
======================

INSTALASI
---------
1. Letakkan folder proyek di C:\xamppp\htdocs\kakang_senduk.
2. Jalankan Apache dan MySQL melalui XAMPP.
3. Buka phpMyAdmin, buat/impor database kakang_senduk menggunakan database.sql.
4. Sesuaikan host, nama database, user, dan password MySQL di config.php jika perlu.
5. Setelah database.sql diimpor, tinjau lalu jalankan migration_001_voting_profiles.sql
   melalui phpMyAdmin. Migration menambahkan nomor HP pengguna, relasi peserta pada
   transaksi lama/baru, dan menormalkan status menjadi pending, paid, failed.
   Migration tidak menghapus data. Buat backup database terlebih dahulu.
6. Buat akun administrator melalui Command Prompt:
   cd /d C:\xamppp\htdocs\kakang_senduk
   C:\xamppp\php\php.exe setup.php
   Isi nama, email, password (minimal 12 karakter), dan konfirmasi.
7. Hapus atau amankan setup.php setelah akun administrator tersedia.
8. Buka http://localhost/kakang_senduk/ lalu masuk sebagai administrator.
9. Beri Apache izin menulis ke folder uploads\participants untuk menyimpan foto.

FITUR
-----
- Peserta, profil peserta, peringkat, pencarian, serta filter kategori.
- Voting dengan batas 1–100 vote per transaksi.
- Pembayaran simulasi DEMO Rp1.000 per vote; tidak ada uang sungguhan yang ditagihkan.
- Vote hanya dicatat setelah pembayaran simulasi berhasil.
- Dashboard admin, ringkasan statistik, grafik, data pengguna/transaksi/voting,
  pengelolaan peserta, status aktif, foto, pencarian, filter, dan pagination.
- Upload foto JPG/JPEG/PNG/WEBP maksimal 2 MB.

KEAMANAN DAN DATA
-----------------
- Password akun disimpan menggunakan password_hash() dan diverifikasi dengan
  password_verify().
- Formulir yang mengubah data menggunakan token CSRF.
- Foto disimpan dengan nama acak dan validasi ekstensi, MIME, serta isi gambar.
- Peserta yang memiliki histori pembayaran atau vote dinonaktifkan saat diminta
  dihapus agar relasi dan histori tetap aman.
- Database migration tidak dijalankan otomatis oleh aplikasi. Impor sebelum
  memakai fitur-fitur yang bergantung pada kolom baru.
- Ganti kredensial database sesuai lingkungan dan jangan publikasikan config.php.

Website ini adalah demo/pembelajaran. Jangan gunakan untuk pemilihan resmi atau
transaksi sungguhan tanpa audit keamanan, verifikasi pembayaran, dan perlindungan
anti-kecurangan yang sesuai.
