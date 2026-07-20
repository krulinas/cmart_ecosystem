# Rekod Kemajuan Projek dan Pemulihan Database

**Tarikh rekod:** 2026-07-20 (UTC+8)  
**Repositori:** `krulinas/cmart_ecosystem`  
**Status:** Peringkat audit selesai; pemulihan belum dilaksanakan

---

## 1. Tujuan rekod

Fail ini menyimpan gambaran semasa projek Carboot@CMart dan status insiden database `cmart_db` sebelum sebarang tindakan pemulihan dibuat. Ia bertujuan menjadi rujukan masa hadapan untuk pembangunan, audit, pemulihan, dan kesinambungan projek.

Tiada kata laluan, token, atau rahsia `.env` direkodkan dalam fail ini.

---

## 2. Gambaran projek

Nama projek:

> **CARBOOT@CMART DIGITAL PLATFORM FOR SMART COMMUNITY ENGAGEMENT**

Matlamat utama sistem adalah mendigitalkan pengurusan program Carboot@CMart melalui pendaftaran dan penyertaan vendor, pengurusan acara, rekod item guna semula, data transaksi anggaran, serta analitik ekonomi dan ESG.

Dokumen cadangan menerangkan tiga objektif utama:

1. Membangunkan platform digital untuk koordinasi, penyertaan dan pengumpulan data Carboot@CMart.
2. Menguji fungsi dan kebolehgunaan platform bersama vendor, pelajar dan komuniti.
3. Menganalisis penyertaan, hasil ekonomi dan indikator ESG menggunakan analitik serta visualisasi.

Skop semasa tidak termasuk pemprosesan pembayaran sebenar. Sistem merekodkan data dan anggaran, bukan menjadi payment gateway.

---

## 3. Keperluan pemegang taruh yang telah dikenal pasti

Maklum balas mesyuarat menetapkan beberapa keperluan penting:

- Vendor boleh menarik diri selepas bayaran, tanpa refund; tapak perlu tersedia semula.
- Item boleh ditempah dengan caj tambahan.
- Layout acara perlu menyokong pengasingan kategori mengikut row, seperti pakaian dan makanan.
- Aliran utama perlu mempunyai kandungan Bahasa Melayu; sistem dwibahasa penuh bukan keutamaan segera.
- Sistem perlu menonjolkan konsep reuse dan recycle.
- Maklumat vendor perlu mencukupi untuk tujuan pemprosesan dan tadbir urus.
- Acara berbilang hari perlu mengutamakan tempahan untuk keseluruhan tempoh, dengan pengecualian yang jelas.
- Perlu mengambil kira vendor walk-in.
- Role lama berkaitan CMart perlu disusun semula kepada peranan `organizer`, `cmart_management`, dan peranan pengguna lain yang sesuai.
- `cmart_management` mempunyai skop terhad untuk CRUD program atau aktiviti, tanpa akses analitik penuh.
- Organizer menjana laporan kepada CMart tanpa memberi CMart akses langsung kepada semua analitik.
- Antaramuka perlu mengelakkan fungsi yang noisy atau overloaded dan kekal mudah digunakan oleh orang awam.
- Analitik perlu merangkumi impak alam sekitar dan sosial, termasuk jumlah item guna semula dan anggaran pengurangan pembaziran.

---

## 4. Seni bina pembangunan semasa

Workspace utama mengandungi:

- `backend/` — Laravel 11.51.0
- `frontend/`
- `python_analytics/` — menggunakan data `cmart_db`
- `report/` — laporan audit dan pemulihan

Persekitaran tempatan:

- Windows 10
- XAMPP
- PHP 8.2.12
- MariaDB 10.4.32
- Host database: `127.0.0.1`
- Port: `3306`
- Database aplikasi: `cmart_db`
- Datadir MariaDB: `D:\Program Files\xampp\mysql\data\`

Aplikasi Laravel dan phpMyAdmin telah disahkan menunjuk kepada instance dan database yang sama.

---

## 5. Status insiden database

### Simptom

Semua 21 jadual yang kelihatan dalam `cmart_db` gagal dibaca dengan ralat:

```text
ERROR 1932: Table 'cmart_db.<table>' doesn't exist in engine
```

Contoh query aplikasi:

```sql
select * from `users`
where `email` = 'vendor@cmart.com'
limit 1;
```

### Penemuan utama

- 21 pasangan fail `.frm` dan `.ibd` masih wujud dalam folder fizikal `cmart_db`.
- `SHOW TABLE STATUS` melaporkan `ENGINE=NULL` untuk semua jadual.
- `information_schema.INNODB_SYS_TABLES` tidak mempunyai entri untuk `cmart_db/*`.
- Fail fizikal masih kelihatan, tetapi InnoDB tidak mengenali atau mengikat tablespace tersebut.
- Log MariaDB menunjukkan ketidakpadanan log sequence number dan mesej bahawa tablespace mungkin telah disalin tanpa log InnoDB yang sepadan.
- Audit tidak menemui dump logik `.sql` untuk `cmart_db` dalam repo, XAMPP, Desktop, Downloads atau Git history yang diperiksa.

### Diagnosis semasa

Punca paling mungkin ialah pemulihan fizikal folder database yang tidak lengkap atau tidak serasi selepas database dipadam. Folder jadual lama mungkin telah dikembalikan tanpa pasangan `ibdata1` dan `ib_logfile*` yang betul, atau fail sistem InnoDB telah diganti/reset.

Ini adalah masalah metadata/dictionary InnoDB, bukan masalah connection Laravel.

---

## 6. Apa yang boleh dibina semula

### Skema

Repositori mempunyai 65 migration Laravel. Kebarangkalian untuk membina semula skema bersih adalah sangat tinggi.

### Data demo

`DatabaseSeeder` boleh mencipta semula akaun dan data demo, termasuk akaun seperti:

- `vendor@cmart.com`
- `vendor_b@cmart.com`
- `admin@cmart.com`
- `staff@cmart.com`
- `hq@cmart.com`
- `organizer@cmart.com`
- `venue@cmart.com`

Seeder turut merangkumi beberapa spaces, events, news, booking, invoice dan management profiles.

### Data manual/original

Data manual asal belum boleh disahkan. Ia mungkin masih berada dalam fail `.ibd`, tetapi hanya boleh diketahui melalui proses salvage pada salinan klon. Migration dan seeder tidak boleh mengembalikan row asal yang tidak terdapat dalam source code atau backup.

---

## 7. Pelan pemulihan yang dipersetujui setakat ini

### Fasa 0 — Pemeliharaan bukti

- Hentikan penulisan aplikasi ke database.
- Buat salinan timestamped bagi keseluruhan datadir, konfigurasi dan log.
- Jangan mengubah datadir asal.

### Fasa 1 — Salvage pada klon

- Cipta instance MariaDB kedua menggunakan salinan datadir.
- Gunakan port alternatif, contohnya `3307`.
- Cuba `innodb_force_recovery=1` pada klon sahaja.
- Jika perlu, cuba tahap 2 dan 3 secara berperingkat.
- Tahap 4 ke atas memerlukan penilaian dan kelulusan berasingan.
- Export setiap jadual yang berjaya dibaca.

### Fasa 2 — Rebuild bersih

- Cipta database sementara `cmart_db_rebuild`.
- Jalankan migrations tanpa menggunakan `migrate:fresh` pada database rosak.
- Import data yang berjaya diselamatkan.
- Jalankan seeder yang telah disemak.

### Fasa 3 — Validasi

- Sahkan semua jadual boleh dibaca.
- Sahkan login dan query `vendor@cmart.com`.
- Sahkan migration status, foreign key, index, API dan log MariaDB.

### Fasa 4 — Cutover kemudian

Selepas `cmart_db_rebuild` lulus validasi, database akhir boleh dikembalikan kepada nama `cmart_db` melalui export/import terkawal. Database rosak dan salinan forensik perlu dikekalkan sehingga proses benar-benar selesai.

---

## 8. Larangan keselamatan semasa

Jangan jalankan tindakan berikut pada database asal:

```text
php artisan migrate:fresh
php artisan migrate:refresh
DROP DATABASE cmart_db
DROP TABLE ...
TRUNCATE ...
```

Jangan padam atau mengganti:

```text
ibdata1
ib_logfile0
ib_logfile1
*.ibd
*.frm
```

Semua eksperimen pemulihan perlu dibuat pada salinan klon.

---

## 9. Penilaian kesukaran semasa

Anggaran berdasarkan audit:

- Memulihkan aplikasi development kepada keadaan berfungsi: **peluang 90–97%**.
- Membina semula skema melalui migration: **peluang 95%+**.
- Membina semula data demo: **peluang 95%+**.
- Memulihkan semua data manual asal secara tepat: **peluang sekitar 30–60%**, bergantung pada keadaan fail `.ibd`.
- `IMPORT TABLESPACE` sebagai pilihan lanjutan: berisiko tinggi dan hanya sesuai selepas percubaan salvage asas.

Kesimpulan: projek development sangat mungkin dapat diteruskan walaupun pemulihan data asal mungkin hanya separa.

---

## 10. Status tindakan

- [x] Audit read-only selesai.
- [x] Punca paling mungkin dikenal pasti.
- [x] Migration dan seeder dikenal pasti sebagai asas rebuild.
- [x] Pelan salvage + rebuild disediakan.
- [x] Rekod kemajuan disimpan dalam GitHub.
- [ ] Salinan forensik datadir dibuat.
- [ ] Instance recovery klon dimulakan.
- [ ] Percubaan salvage dilaksanakan.
- [ ] `cmart_db_rebuild` dicipta.
- [ ] Migration dan seeder dijalankan.
- [ ] Validasi aplikasi selesai.
- [ ] Cutover kepada nama akhir `cmart_db` selesai.

---

## 11. Dokumen sumber yang digunakan untuk gambaran projek

Rekod ini disediakan berdasarkan:

- laporan audit database `00` hingga `05` dan `command_log.md`;
- minit maklum balas ICC;
- proposal projek Carboot@CMart yang dikemas kini;
- draft proposal projek;
- source code, migrations, seeders dan log yang diperiksa semasa audit Cursor.

Dokumen proposal dan minit asal mungkin mengandungi maklumat institusi atau identiti peribadi; dokumen tersebut tidak dimasukkan ke dalam commit ini. Hanya ringkasan projek yang relevan direkodkan.
