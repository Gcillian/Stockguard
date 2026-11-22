# Stockguard Laravel

Implementasi alur pengambilan barang berdasarkan [rancangan sistem](../docs/stockguard-rancangan.md). Versi lokal saat ini memakai PHP 8.0 dan Laravel 9.52 karena itu versi yang tersedia di XAMPP. Rancangan menargetkan PHP 8.3 dan Laravel 11 untuk tahap produksi.

## Menjalankan aplikasi

1. Buat database MySQL `stockguard_ai` dan salin `.env.example` menjadi `.env`.
2. Jalankan perintah berikut dari folder `laravel-app`:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Jika database sudah pernah diisi, jalankan `php artisan migrate` tanpa `--seed` agar akun dan master data tidak diduplikasi. Akses aplikasi di `http://127.0.0.1:8000`.

| Role | Email | Kata sandi demo |
|---|---|---|
| Staff | `raka@stockguard.id` | `password` |
| Supervisor | `nadia@stockguard.id` | `password` |
| Admin | `arif@stockguard.id` | `password` |

## Alur yang sudah tersedia

1. Staff membuat permintaan dengan memilih SKU dan jumlah box; status menjadi `submitted`.
2. Staff memindai satu SKU/barcode untuk setiap produk melalui kamera browser (`BarcodeDetector`) atau mengetik kode. Kode yang sama hanya diberi peringatan dan tidak dihitung sebagai scan baru; setelah semua SKU terverifikasi status menjadi `scanned`.
3. Staff membawa barang melewati kamera checkpoint yang terpasang di pintu keluar gudang. Staff tidak mengambil atau mengunggah foto.
4. Kamera checkpoint dan layanan YOLO menghitung jumlah barang, confidence, dan bounding boxes. Selisih jumlah atau confidence rendah menghasilkan `review`.
5. Supervisor/Admin memantau hasil kamera, memeriksa bukti, lalu menyetujui atau menolak. Penolakan mewajibkan alasan. Stok dikurangi tepat satu kali saat transaksi selesai; penolakan tidak mengurangi stok.
6. Admin mengelola produk dan stok, pengguna dan role, gudang dan kamera, serta melihat audit trail.

Mode demo lokal diaktifkan dengan `YOLO_DEMO_MODE=true`. Integrasi checkpoint dapat mengirim frame dan nilai jumlah/confidence untuk simulasi presentasi; nilai tersebut **bukan inferensi YOLO**. Staff tetap tidak perlu mengunggah foto. Untuk memakai model, set `YOLO_DEMO_MODE=false`, konfigurasi `YOLO_SERVICE_URL`, dan jalankan [layanan vision](../vision-service/README.md).

Pada detail transaksi Staff yang sudah selesai memindai seluruh SKU, tombol **Demonstrasi** memakai aset privat `storage/app/demo/4boxdetected.jpeg`. Deteksi selalu menghasilkan 4 box: permintaan 4 box selesai otomatis, sedangkan jumlah lain masuk ke Pending review.

## Job dan konfigurasi

Secara default `QUEUE_CONNECTION=sync` sehingga analisis berjalan langsung di request. Tabel `jobs` dan `failed_jobs` tidak digunakan oleh konfigurasi lokal ini dan dihapus oleh migration `2026_11_22_000002_drop_unused_queue_tables.php`.

`YOLO_CONFIDENCE_THRESHOLD=0.80` menetapkan ambang review. `YOLO_SERVICE_TOKEN` opsional tetapi sebaiknya diisi dengan nilai acak yang sama pada Laravel dan layanan Python. Service mengembalikan `detected_qty`, `confidence` (0–1), `bounding_boxes`, `sku`, dan `expected_qty`.

Kamera checkpoint pada browser membutuhkan HTTPS atau `localhost`; di HP melalui alamat LAN, gunakan HTTPS. Bila `BarcodeDetector` tidak tersedia, staff dapat memasukkan SKU/barcode secara manual.

## Pengujian

```powershell
php vendor/phpunit/phpunit/phpunit
php artisan view:cache
```

Pengujian memakai SQLite in-memory sehingga tidak mengubah data MySQL lokal. Termasuk kasus hasil cocok, anomali, keputusan supervisor, transaksi multi-item, batas akses, dan respons service YOLO.

## Batas implementasi

Model YOLO dan dataset box gudang belum tersedia. Service Python memerlukan bobot model terlatih sebelum dapat menghasilkan hitungan nyata. Penyimpanan bukti masih disk lokal privat; untuk deployment multi-server, pindahkan ke S3/MinIO dan sesuaikan metode akses bukti. Reverse proxy HTTPS, Redis, backup, dan monitoring eksternal pada rancangan belum dikonfigurasi untuk lingkungan lokal ini.
