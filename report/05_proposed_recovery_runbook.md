# 05 — Runbook Pemulihan yang Dicadangkan (Peringkat B)

**Tarikh:** 2026-07-20 (UTC+8)  
**Status:** CADANGAN — menunggu kelulusan eksplisit  
**Prasyarat:** Baca laporan 00–04 dahulu

---

## Gambaran keseluruhan

Runbook ini menggabungkan:
- **Fasa 0:** Pemeliharaan bukti forensik (wajib)
- **Fasa 1:** Salvage data pada klon terpencil (Path 3)
- **Fasa 2–5:** Pembinaan semula bersih (Path 5) + import + validasi
- **Fasa 6:** Pemotongan (cutover) selepas kelulusan

**Anggaran masa:** 2–4 jam (bergantung kejayaan salvage)

---

## Fasa 0 — Salinan forensik (WAJIB)

### 0.1 Hentikan tulisan ke DB

```powershell
# Pilihan A: Hentikan Apache/backend sementara
# Piliang B: Laravel maintenance mode
Set-Location "d:\Program Files\xampp\htdocs\cmart_ecosystem\backend"
php artisan down
```

### 0.2 Salin timestamped

```powershell
$ts = Get-Date -Format "yyyyMMdd_HHmmss"
$dest = "D:\cmart_forensic_backup_$ts"

# Salin datadir penuh
Copy-Item -Recurse "D:\Program Files\xampp\mysql\data" "$dest\mysql_data"

# Salin config
Copy-Item "D:\Program Files\xampp\mysql\bin\my.ini" "$dest\my.ini"

# Salin error log
Copy-Item "D:\Program Files\xampp\mysql\data\mysql_error.log" "$dest\mysql_error.log"

# Salin .env (untuk rujukan — jangan kongsi)
Copy-Item "d:\Program Files\xampp\htdocs\cmart_ecosystem\backend\.env" "$dest\backend.env.redacted"

# Salin laporan audit
Copy-Item -Recurse "d:\Program Files\xampp\htdocs\cmart_ecosystem\report" "$dest\report"
```

**Fail yang disentuh:** Tiada pada datadir asal (salinan sahaja)  
**Kelulusan:** Disyorkan — operasi baca-salin

---

## Fasa 1 — Salvage pada klon terpencil (Path 3)

### 1.1 Sediakan instance MariaDB kedua (pilihan)

**Pilihan A — Port alternatif pada mesin yang sama:**

1. Salin datadir forensik ke `D:\cmart_recovery_clone\data`
2. Edit `my.ini` klon:
   ```ini
   port=3307
   datadir="D:/cmart_recovery_clone/data"
   innodb_force_recovery=1
   ```
3. Mulakan `mysqld` kedua pada port 3307

**Pilihan B — Docker MariaDB 10.4** (jika tersedia)

### 1.2 Cuba baca setiap jadual

```sql
-- Sambung ke instance klon (port 3307)
USE cmart_db;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM bookings;
SELECT COUNT(*) FROM feedbacks;
-- Ulangi untuk semua 21 jadual
```

### 1.3 Export jadual yang berjaya

```powershell
& "D:\Program Files\xampp\mysql\bin\mysqldump.exe" -P 3307 -u root cmart_db users bookings feedbacks ... > "$dest\salvaged_partial.sql"
```

### 1.4 Jika level 1 gagal

- Tingkatkan ke `innodb_force_recovery=2`, kemudian `3`
- **JANGAN** gunakan level 4+ tanpa kelulusan eksplisit
- Dokumen setiap percubaan dalam `report/06_recovery_execution_log.md`

---

## Fasa 2 — Bina pangkalan data bersih

### 2.1 Cipta DB baharu (jangan sentuh `cmart_db` rosak)

```sql
CREATE DATABASE cmart_db_rebuild
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2.2 Sambung Laravel ke DB baharu (sementara)

Edit `backend/.env`:
```text
DB_DATABASE=cmart_db_rebuild
```

### 2.3 Jalankan migration

```powershell
Set-Location "d:\Program Files\xampp\htdocs\cmart_ecosystem\backend"
php artisan config:clear
php artisan cache:clear
php artisan migrate:status
php artisan migrate --pretend    # Semak dahulu
php artisan migrate              # Laksanakan selepas semak
```

**Amaran:** Jangan gunakan `migrate:fresh` atau `migrate:refresh`.

---

## Fasa 3 — Import data terselamat

Jika Fasa 1 menghasilkan dump:

```powershell
& "D:\Program Files\xampp\mysql\bin\mysql.exe" -u root cmart_db_rebuild < "$dest\salvaged_partial.sql"
```

**Susunan import (FK-safe):**
1. `users`
2. `spaces`, `carboot_events`, `news_posts`
3. `vendor_business_profiles`, `management_profiles`, `vendor_items`
4. `bookings`, `invoices`, `feedbacks`
5. Jadual pivot dan audit

Dokumen kiraan baris dalam `report/recovered_data_manifest.md`.

---

## Fasa 4 — Seed akaun demo

```powershell
php artisan db:seed --class=DatabaseSeeder
```

**Nota:** Seeder menggunakan `updateOrCreate` — selamat jika data sebahagian sudah diimport.

---

## Fasa 5 — Validasi

Semak semua item dalam senarai validasi (akan didokumenkan dalam `report/07_post_recovery_validation.md`):

- [ ] `SELECT 1` berjaya
- [ ] `SELECT * FROM cmart_db_rebuild.users WHERE email='vendor@cmart.com'` — tiada ralat 1932
- [ ] Login API berjaya
- [ ] `php artisan migrate:status` konsisten
- [ ] Tiada jadual dengan `doesn't exist in engine`
- [ ] Log MariaDB tiada ralat kritikal baharu

---

## Fasa 6 — Cutover (selepas kelulusan)

### Pilihan A — Rename (disyorkan jika salvage gagal total)

```sql
-- Hentikan aplikasi dahulu
RENAME TABLE ... -- TIDAK sesuai untuk DB penuh rosak

-- Lebih selamat:
-- 1. Padam / arkib cmart_db rosak (SETUJU dulu)
-- 2. RENAME DATABASE cmart_db_rebuild TO cmart_db;
--    (MariaDB: buat semula atau gunakan mysqldump | mysql)
```

### Pilihan B — Tukar `.env` kekal ke `cmart_db_rebuild`

Kekalkan `cmart_db` rosak sebagai arkib forensik.

### Selepas cutover

```powershell
php artisan config:clear
php artisan cache:clear
php artisan up   # keluar maintenance mode
```

---

## Fail yang akan diubah (Peringkat B)

| Fail/Lokasi | Tindakan |
|-------------|----------|
| `D:\cmart_forensic_backup_*` | Dicipta (salinan) |
| `D:\cmart_recovery_clone\` | Dicipta (klon salvage) |
| `backend/.env` | `DB_DATABASE` → `cmart_db_rebuild` (sementara) |
| Pangkalan data `cmart_db_rebuild` | Dicipta + migration |
| `report/06_*`, `07_*`, `08_*` | Dikemas kini semasa pelaksanaan |

**Fail yang TIDAK akan disentuh tanpa kelulusan:**
- `D:\Program Files\xampp\mysql\data\` (asal) — kecuali salinan
- `ibdata1`, `ib_logfile*` (asal)

---

## Prosedur rollback

1. Kembalikan `backend/.env` ke `DB_DATABASE=cmart_db`
2. Hentikan instance recovery (port 3307) jika berjalan
3. `cmart_db` asal masih utuh sebagai bukti forensik
4. Padam `cmart_db_rebuild` jika pemulihan tidak memuaskan
5. Mulakan semula Apache/backend

---

## Cadangan backup pencegahan (selepas pemulihan)

```powershell
# Dump harian
$date = Get-Date -Format "yyyyMMdd"
& "D:\Program Files\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction --routines --triggers cmart_db > "D:\cmart_backups\cmart_db_$date.sql"
```

Rujukan: `frontend/tests/e2e/README.md`, `docs/phase-3/phase-3-migration-rollout-runbook.md`

---

## Permintaan kelulusan

Sila sahkan langkah yang dibenarkan:

- [ ] Fasa 0 — Salinan forensik
- [ ] Fasa 1 — Salvage `innodb_force_recovery=1` pada klon
- [ ] Fasa 2–5 — Bina `cmart_db_rebuild` + migration + seed
- [ ] Fasa 6 — Cutover / rename

**Balas dengan kelulusan per fasa atau "luluskan semua".**
