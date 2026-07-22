# 00 — Ringkasan Audit Pemulihan Pangkalan Data `cmart_db`

**Tarikh audit:** 2026-07-20 (UTC+8)  
**Peringkat:** A — Audit baca-sahaja (belum ada pemulihan)  
**Status:** Menunggu kelulusan sebelum Peringkat B

---

## Kesimpulan ringkas

Pangkalan data `cmart_db` mengalami **kerosakan metadata InnoDB yang menyeluruh**: semua 21 jadual kelihatan dalam `information_schema` dan phpMyAdmin, tetapi **tiada satu pun boleh dibaca** oleh enjin InnoDB (ralat `1932 — doesn't exist in engine`). Fail fizikal `.frm` dan `.ibd` masih wujud (~2.46 MB), tetapi **InnoDB dictionary (`INNODB_SYS_TABLES`) tidak mempunyai sebarang entri** untuk `cmart_db/*`.

Ini bukan masalah konfigurasi aplikasi Laravel — aplikasi disambungkan dengan betul ke MariaDB 10.4.32 pada `127.0.0.1:3306`, pangkalan data `cmart_db`.

---

## Punca akar yang paling mungkin

**Pemulihan fizikal folder pangkalan data yang tidak lengkap / tidak serasi** selepas pangkalan data dipadam pada 2026-06-28.

### Bukti menyokong

| Bukti | Sumber |
|-------|--------|
| `Unknown database 'cmart_db'` pada 2026-06-28 09:20 | `backend/storage/logs/laravel.log` |
| Folder `cmart_db` dicipta semula ~28/6/2026 09:21 (timestamp `.frm`) | Inventori fizikal |
| Ralat InnoDB 2026-07-08 03:18: *"copied InnoDB tablespace but not InnoDB log files"* | `mysql_error.log` |
| `ENGINE=NULL` untuk semua jadual; `INNODB_SYS_TABLES` kosong untuk `cmart_db` | SQL audit |
| `ibdata1` dan folder `cmart_db` diubah 2026-07-20 ~10:35–10:43 (percubaan pemulihan terkini?) | Timestamp fail |
| Ralat 1932 bermula 2026-07-20 04:31 | `laravel.log` |

---

## Apa yang mungkin boleh dipulihkan

| Kategori | Status | Catatan |
|----------|--------|---------|
| **Skema (schema)** | ✅ Boleh dibina semula | 65 migration Laravel tersedia; skema penuh termasuk Phase 3–4 |
| **Data demo (akaun seed)** | ✅ Boleh dibina semula | `DatabaseSeeder` — `vendor@cmart.com`, admin, organizer, dll. |
| **Data pengeluaran/manual asal** | ❓ Tidak disahkan | Tiada dump `.sql` ditemui; fail `.ibd` mungkin mengandungi data tetapi tidak boleh dibaca tanpa pemulihan InnoDB |
| **Data Phase 3** | ❌ Tidak pernah ada dalam `cmart_db` | Dokumentasi projek mengesahkan 14 migration Phase 3 belum pernah dijalankan pada `cmart_db` |

---

## Apa yang belum boleh disahkan

- Sama ada fail `.ibd` sedia ada masih mengandungi baris data yang boleh diselamatkan melalui `innodb_force_recovery` atau `IMPORT TABLESPACE`
- Sama ada terdapat salinan `ibdata1` / datadir lama di lokasi lain pada mesin ini (carian terhad — lihat laporan 02)
- Kiraan baris tepat data yang hilang vs data demo

---

## Laluan pemulihan yang dicadangkan (Peringkat B)

**Laluan utama yang disyorkan: Path 5 (schema rebuild) + percubaan salvage terhad (Path 3) pada salinan klon**

1. **Salin forensik** penuh datadir + config + log (wajib sebelum apa-apa)
2. **Cuba salvage** data dari `.ibd` pada salinan klon dengan `innodb_force_recovery=1` (baca-sahaja)
3. **Bina semula** pangkalan data bersih `cmart_db_rebuild` dari migration
4. **Import** data yang berjaya diselamatkan (jika ada)
5. **Seed** akaun demo dari `DatabaseSeeder`
6. **Sahkan** aplikasi + tukar sambungan selepas kelulusan

---

## Fail laporan Peringkat A

| Fail | Kandungan |
|------|-----------|
| `01_environment_and_connection_audit.md` | Persekitaran OS, MariaDB, Laravel, `.env` |
| `02_schema_and_data_source_inventory.md` | Migration, seeder, sumber data |
| `03_database_corruption_findings.md` | Bukti kerosakan terperinci |
| `04_recovery_options_and_risk_matrix.md` | Matriks risiko laluan pemulihan |
| `05_proposed_recovery_runbook.md` | Langkah operasi Peringkat B |
| `command_log.md` | Log perintah audit |

---

## Status Phase 0B (2026-07-21)

**Selesai.** Salinan forensik cold + clone kerja telah dibuat. Lihat `06_phase0b_forensic_copy_execution.md`.

| Artefak | Laluan |
|---------|--------|
| Forensic master | `D:\cmart_forensic_master_20260721_085107` |
| Working clone | `D:\cmart_recovery_clone_20260721_085107` |

## Status Phase 1 Level-1 (2026-07-21)

**Selesai — salvage gagal.** Instance recovery `:3307` dengan `innodb_force_recovery=1`: **0/21** jadual boleh dibaca (semua 1932). Lihat `07_phase1_level1_salvage_report.md`.

| Artefak | Laluan |
|---------|--------|
| L1 attempt (disposable) | `D:\cmart_phase1_L1_attempt_20260721_085107` |

## Status Phase 2 Rebuild (2026-07-21)

**Selesai — DB pembangunan boleh digunakan.** `cmart_db_rebuild` dimigrate + seed; `.env` → `cmart_db_rebuild`. Login API 200. `cmart_db` rosak + forensik **tidak disentuh**. Lihat `08_phase2_clean_rebuild_report.md`.

## Tindakan seterusnya

Sistem pembangunan sudah diarahkan ke `cmart_db_rebuild`. Kekalkan arkib forensik sehingga penutupan recovery diluluskan. Mulakan semula `php artisan serve` jika perlu.
