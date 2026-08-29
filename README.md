# PackStock WMS - Enterprise Packaging Stock Control & Dispatch Panel

Sistem Manajemen Stok Material Packaging Gudang Terintegrasi dengan Multi-Stage Dynamic Blank Counting, Task Dispatching, Audit Trail Mutasi, dan Penyesuaian Stok (Adjust).

---

## 🚀 Fitur Utama
1. **Dashboard Monitoring Real-time**: KPI Stok Awal, Inbound (+), Outbound (-), Adjust (+/-), Ending Stock.
2. **Master Stok Packaging**: Manajemen SKU, UOM, Safety Stock Alert, Kartu Riwayat Stok, Import & Export Excel.
3. **Stock Opname & Detail Stock Opname**: Metode Blank Count (1st, 2nd, 3rd Count), Live Discrepancy Matrix, Log Breakdown per Putaran.
4. **Dynamic Count (Cycle Counting)**: Penugasan hitung fisik terjadwal per SKU pilihan dengan scan lokasi rak.
5. **Adjustment Opname**: Penyesuaian selisih stok manual dan upload massal Excel.
6. **Task Dispatcher & Operator Mobile App**: Penugasan serah terima barang ke operator lapangan dengan mode mobile touch.
7. **Buku Mutasi & Audit Trail**: Riwayat seluruh pergerakan stok dengan pencatatan user PIC dan referensi dokumen.

---

## 🌐 Cara Deploy Otomatis ke InfinityFree

Repository ini sudah dilengkapi dengan **GitHub Actions Auto-Deploy via FTP**.

### Langkah Setting di GitHub:
1. Masuk ke repository GitHub Anda: `https://github.com/dhanielomarthinz-130/packstock`
2. Buka menu **Settings** > **Secrets and variables** > **Actions**.
3. Klik **New repository secret** dan tambahkan 3 variabel berikut (dilihat dari Control Panel / Client Area InfinityFree):
   - `FTP_SERVER`: misal `ftpupload.net`
   - `FTP_USERNAME`: misal `if0_38123456`
   - `FTP_PASSWORD`: password akun hosting InfinityFree Anda
4. Setiap kali Anda melakukan `git push`, GitHub Actions akan secara otomatis mengunggah perubahan kode ke folder `htdocs/` InfinityFree!

### Setting Database di InfinityFree:
1. Buat database MySQL di Control Panel InfinityFree (misal: `if0_38123456_packstock`).
2. Buat file `config/env.php` di server InfinityFree (bisa via File Manager InfinityFree) dengan format:
```php
<?php
return [
    'DB_HOST' => 'sqlxxx.infinityfree.com', // Lihat di cPanel MySQL Details
    'DB_PORT' => '3306',
    'DB_USER' => 'if0_xxxxxxxx',
    'DB_PASS' => 'password_hosting_anda',
    'DB_NAME' => 'if0_xxxxxxxx_packstock',
];
```
3. Saat pertama kali diakses, sistem secara otomatis menjalankan **Auto-Migration** untuk membuat seluruh tabel dan akun default admin/operator.
