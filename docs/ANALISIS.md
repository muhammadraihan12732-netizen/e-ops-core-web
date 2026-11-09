# Analisis E-Ops Core (diperbarui)

Dokumen ini melanjutkan pemeriksaan pertama (`analisis-awal.txt`, tidak diubah). Bagian 1 memetakan setiap temuan awal ke kondisi sekarang. Bagian 2 menjelaskan dengan jujur apa yang **sudah diuji** dan apa yang **belum**.

## 1. Status temuan awal

| # | Temuan awal | Prioritas | Status | Yang dikerjakan |
|---|---|---|---|---|
| 1 | User Management belum ada | Tinggi | Dibuat | `UserController`, halaman Users: tambah, ubah, ganti role, nonaktifkan. Akun tidak dihapus (transaksi mengacu ke user). Admin tidak bisa menurunkan/menonaktifkan dirinya sendiri. Akun nonaktif tidak bisa login dan token lamanya dicabut. |
| 2 | Pengolahan CSV belum ada | Tinggi | Dibuat | `data-analyst/`: `raw_sales.csv` (1.240 baris, sengaja kotor), `clean_sales.py`, `cleaned_sales.csv` (1.145 baris), `cleaning_report.md`, `analyze_sales.py`, `analysis_summary.md`, 4 grafik, 15 unit test. |
| 3 | Kategori tanpa CRUD | Tinggi | Dibuat | `CategoryController` + halaman Kategori. Kategori yang masih dipakai produk (termasuk yang di-soft-delete) tidak bisa dihapus. |
| 4 | Monitoring belum lengkap | Menengah | Sebagian | Ditambah CPU, memori sistem, disk, waktu respons DB, cache, uptime server, error 24 jam, dan riwayat metrik (tabel `system_metrics`, grafik, scheduler). **Pemantauan jaringan belum ada.** |
| 5 | Label Dashboard keliru | Menengah | Diperbaiki | Kartu "Total Products" dipecah: *Jenis Produk* (grafik per kategori) dan *Barang Terjual* (grafik unit per bulan). |
| 6 | Ekspor CSV tidak cek kegagalan | Menengah | Diperbaiki | Helper `download()` hanya menyimpan file bila respons sukses; galat tampil di layar. Ditambah filter tanggal dan perlindungan CSV injection. |
| 7 | Error handling lemah | Menengah | Diperbaiki | Semua halaman memakai loading, pesan galat, dan tombol "Coba lagi". Respons 401 mengembalikan pengguna ke login. |
| 8 | Struktur ZIP / setup Laravel | n/a | Diperbaiki | Folder `{app/Models,...}` dihapus, `bootstrap/app.php` disertakan, ada `scripts/setup-backend.sh`. |

Poin "transaksi & database" dari analisis awal:

| Catatan awal | Status |
|---|---|
| Produk sama di beberapa baris item | Baris digabung sebelum cek stok, jadi stok tidak bisa dilewati dengan memecah baris. Dicakup test. |
| Nomor invoice rawan bentrok | Format `INV-yymmdd-XXXXXX` (acak) + cek keunikan, menggantikan `rand(10,99)`. |
| Halaman detail dan filter tanggal | Ditambah (modal detail, filter tanggal dan invoice, paginasi). |
| Uji rollback stok | Test `test_failure_rolls_back_everything` ditulis. |
| Data seeder vs operasional | Kolom `transactions.is_demo`, label di UI, kolom *Sumber* di CSV. |

Tambahan di luar daftar awal: token login kedaluwarsa 12 jam; angka pendapatan di Dashboard disembunyikan dari kasir dan support; penolakan karena stok kurang kini masuk Activity Log, bukan Error Log (itu keputusan bisnis, bukan error sistem); beberapa aksi (logout, balasan tiket) kini ikut tercatat.

## 2. Apa yang benar-benar diuji

| Bagian | Hasil | Catatan |
|---|---|---|
| Pipeline Python | **Dijalankan, 15/15 test lulus** | Script cleaning dan analisis berjalan dan menghasilkan semua keluaran di folder. |
| Sintaks frontend | **Lulus** (14 file, diparse dengan esbuild) | Ini hanya pengecekan sintaks. |
| Build frontend (`vite build`) | **Belum diuji** | `node_modules` tidak bisa dipasang di lingkungan ini (tanpa akses jaringan). |
| Kode PHP | **Belum dijalankan** | Tidak ada PHP di lingkungan ini. Hanya diperiksa keseimbangan tanda kurung (34 file). Itu **bukan** pengganti `php -l`. |
| Test PHPUnit (`backend/tests`, 4 kelas, 23 test) | **Ditulis, belum pernah dijalankan** | Bisa saja ada kesalahan kecil yang baru tampak saat dijalankan. |
| Integrasi frontend, API, MySQL | **Belum diuji** | Aplikasi belum pernah dijalankan utuh. |
| Jalur Windows pada `SystemMetrics` (PowerShell) | **Belum diuji** | Bila gagal, CPU/memori tampil "n/a", bukan angka palsu. |

Jadi status proyek tetap **prototype**: banyak ditambahkan, tetapi bukti bahwa semuanya berjalan bersama baru ada untuk bagian Python.

## 3. Hasil analisis data (data simulasi)

Dari `analysis_summary.md`: total omzet Rp 77.311.000 dari 1.145 transaksi; produk terlaris Eraser (473 unit); kategori terbesar Kitchen Appliances (55%); hari paling ramai Sabtu.

**Prediksi penjualan belum meyakinkan.** Pada backtest 28 hari, model (tren linier + pola hari-dalam-minggu) hanya selisih sekitar 1% dari baseline rata-rata (MAE Rp 183.992 vs Rp 186.423), dan galat total mingguan masih 64%. Data simulasi memang berisik, namun hasilnya berarti prediksi ini baru layak sebagai gambaran arah, bukan angka target. Perlu diuji ulang dengan data nyata.

Catatan data: `raw_sales.csv` dibuat oleh `generate_raw_sales.py`, bukan data bisnis nyata. Aturan cleaning membuang baris bermasalah (qty kosong, negatif, outlier > 50, tanggal mustahil) dan tidak menebak nilainya. Semua dihitung di `cleaning_report.md` (92,3% baris dipertahankan).

## 4. Sisa pekerjaan (urut prioritas)

1. Jalankan backend dengan MySQL, jalankan `php artisan test`, dan perbaiki kegagalan yang muncul.
2. `npm install && npm run build`, lalu uji tiap halaman untuk tiap role.
3. Uji alur penuh: login, transaksi, stok berkurang, laporan, ekspor CSV.
4. Pemantauan jaringan dan notifikasi ambang pada Monitoring.
5. Ganti data simulasi dengan data operasional dan ulangi backtest prediksi.
6. Tambahkan `package-lock.json` setelah `npm install` agar versi dependensi terkunci.
