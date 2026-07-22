# 04 — Pilihan Pemulihan dan Matriks Risiko

**Tarikh:** 2026-07-20 (UTC+8)  
**Keadaan semasa:** Semua jadual `cmart_db` → ralat 1932; tiada dump logik ditemui

---

## Matriks laluan pemulihan

| Path | Penerangan | Sesuai? | Risiko | Kebarangkalian kejayaan data | Kebarangkalian kejayaan skema |
|------|------------|---------|--------|------------------------------|-------------------------------|
| **1** | Restore dari dump logik (`.sql`) | ❌ Tiada dump | Rendah | — | — |
| **2** | Dump jadual yang boleh dibaca | ❌ Tiada jadual boleh dibaca | Rendah | 0% | — |
| **3** | `innodb_force_recovery` pada klon datadir | ✅ Disyorkan untuk salvage | Sederhana | 30–60% (tidak pasti) | N/A |
| **4** | `IMPORT TABLESPACE` fizikal | ⚠️ Boleh cuba selepas Path 3 | Tinggi | 20–40% | Perlu skema tepat dahulu |
| **5** | Rebuild dari migration + seeder | ✅ Disyorkan sebagai asas | Rendah | 0% data asal | 95%+ |

---

## Path 1 — Dump logik sedia ada

| Aspek | Penilaian |
|-------|-----------|
| Ketersediaan | ❌ Tiada `.sql` / `.sql.gz` ditemui dalam repo, XAMPP, Downloads, Desktop |
| Risiko | Rendah (jika dump dijumpai kemudian) |
| Cadangan | Teruskan carian manual oleh operator (OneDrive, cakera luar, Time Machine, dll.) |

---

## Path 2 — Export jadual yang boleh dibaca

| Aspek | Penilaian |
|-------|-----------|
| Ketersediaan | ❌ Semua 21 jadual gagal dengan 1932 |
| Risiko | Rendah (read-only) |
| Cadangan | Tidak boleh dilaksanakan pada instance semasa |

---

## Path 3 — `innodb_force_recovery` pada klon (SALVAGE)

| Aspek | Penilaian |
|-------|-----------|
| Ketersediaan | ✅ Fail `.ibd` wujud; mungkin mengandungi data sehingga 2026-07-07 |
| Risiko | **Sederhana** — hanya pada salinan klon, bukan datadir asal |
| Prosedur | Klon penuh datadir → `innodb_force_recovery=1` dalam `my.ini` klon → cuba `SELECT` / `mysqldump` per jadual |
| Had | Level 4+ memerlukan kelulusan eksplisit; jangan gunakan pada fail asal |
| Kebarangkalian | Sederhana — kejayaan bergantung pada sama ada `.ibd` masih konsisten dengan kandungan sebenar |

**Faedah:** Satu-satunya jalan untuk mungkin menyelamatkan data asal tanpa dump logik.

---

## Path 4 — `IMPORT TABLESPACE`

| Aspek | Penilaian |
|-------|-----------|
| Prasyarat | Skema jadual tepat, `innodb_file_per_table=ON`, `.ibd` dari instans yang serasi |
| Risiko | **Tinggi** — mudah gagal jika tablespace ID / page size tidak sepadan |
| Cadangan | Hanya selepas Path 5 membina skema bersih; uji pada DB buangan (`cmart_db_salvage_test`) |
| Nota | **Jangan improvise** tanpa dokumentasi per-jadual |

---

## Path 5 — Rebuild skema dari migration + seeder

| Aspek | Penilaian |
|-------|-----------|
| Ketersediaan | ✅ 65 migrations + `DatabaseSeeder` |
| Risiko | **Rendah** jika dilakukan pada DB baharu (`cmart_db_rebuild`) |
| Kehilangan data | Semua data asal (bukan demo) akan hilang melainkan Path 3 berjaya |
| Cadangan | **Laluan utama** untuk memulihkan fungsi aplikasi |

### Langkah berisiko yang **TIDAK** dibenarkan tanpa kelulusan

- `php artisan migrate:fresh`
- `DROP DATABASE cmart_db`
- `DROP TABLE` pada jadual sedia ada
- Ganti `ibdata1` / `ib_logfile*` pada datadir produksi
- Salin fail recovered ke datadir tunggal sedia ada

---

## Cadangan gabungan (disyorkan)

```
[Fasa 0] Salin forensik datadir + config + log
    ↓
[Fasa 1] Path 3 pada klon — cuba salvage data dari .ibd
    ↓
[Fasa 2] Path 5 — bina cmart_db_rebuild dari migration
    ↓
[Fasa 3] Import data terselamat (jika ada) ke rebuild
    ↓
[Fasa 4] Seed akaun demo
    ↓
[Fasa 5] Validasi aplikasi
    ↓
[Fasa 6] Tukar .env ke DB rebuild (atau rename DB) selepas kelulusan
```

---

## Anggaran kehilangan data

| Data | Status |
|------|--------|
| Akaun demo (`vendor@cmart.com`, dll.) | Boleh dijana semula oleh seeder |
| Events, news, spaces demo | Boleh dijana semula oleh seeder |
| Booking/feedback manual | Mungkin hilang kecuali salvage `.ibd` berjaya |
| Token Sanctum aktif | Hilang — pengguna perlu login semula |
| Jadual Phase 3 | Tidak pernah wujud — tiada kehilangan |

---

## Keperluan kelulusan

| Tindakan | Kelulusan diperlukan? |
|----------|----------------------|
| Salin forensik (read-only copy) | ✅ Disyorkan — kelulusan ringkas |
| Klon + `innodb_force_recovery=1` | ✅ Ya |
| `innodb_force_recovery` level 4+ | ✅ Ya — dengan amaran risiko |
| Cipta `cmart_db_rebuild` + migration | ✅ Ya |
| `DROP` / ganti `cmart_db` sedia ada | ✅ Ya — eksplisit |
| Ubah `backend/.env` | ✅ Ya |
