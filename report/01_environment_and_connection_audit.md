# 01 — Audit Persekitaran dan Sambungan

**Tarikh:** 2026-07-20 (UTC+8)  
**Jenis:** Fakta (disahkan) + andaian (dinyatakan)

---

## 1. Sistem operasi dan persekitaran

| Item | Nilai |
|------|-------|
| OS | Windows 10 (build 10.0.26200) |
| Stack | XAMPP (Apache + MariaDB + PHP) |
| Workspace | `d:\Program Files\xampp\htdocs\cmart_ecosystem` |
| Shell | PowerShell |

**Fakta:** Ini adalah persekitaran pembangunan tempatan Windows/XAMPP, bukan Docker atau cloud.

---

## 2. Pelayan pangkalan data

| Item | Nilai |
|------|-------|
| Produk | **MariaDB** (bukan Oracle MySQL) |
| Versi | `10.4.32-MariaDB` |
| Port | `3306` (PID 11416, `mysqld`) |
| Host | `127.0.0.1` |
| `datadir` | `D:\Program Files\xampp\mysql\data\` |
| `log_error` | `.\mysql_error.log` → `D:\Program Files\xampp\mysql\data\mysql_error.log` |
| `innodb_file_per_table` | `ON` |
| `lower_case_table_names` | `1` |
| Config utama | `D:\Program Files\xampp\mysql\bin\my.ini` |
| `datadir` dalam config | `D:/Program Files/xampp/mysql/data` |

**Fakta:** Hanya satu instance MariaDB dikesan pada port 3306.

---

## 3. Konfigurasi aplikasi Laravel

### `backend/.env` (rahsia diredak)

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cmart_db
DB_USERNAME=[REDACTED]
DB_PASSWORD=[REDACTED]
```

**Fakta:** `DB_USERNAME=root`, `DB_PASSWORD=` (kosong) — nilai sebenar tidak didedahkan dalam laporan.

### `backend/.env.example`

```text
DB_DATABASE=laravel   # default template — BUKAN cmart_db
```

**Fakta:** `.env` sebenar menggunakan `cmart_db`, bukan nilai default `.env.example`.

### `backend/config/database.php`

- Driver default: `mysql`
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Tiada override khas untuk `cmart_db`

### Cache Laravel

| Cache | Status |
|-------|--------|
| `bootstrap/cache/config.php` | **Tidak wujud** — config tidak di-cache |
| Routes | NOT CACHED |
| Views | CACHED |

**Fakta:** Tiada risiko sambungan stale dari config cache.

### `php artisan about` (2026-07-20)

| Item | Nilai |
|------|-------|
| Laravel | 11.51.0 |
| PHP | 8.2.12 |
| Environment | `local` |
| Debug | ENABLED |
| Maintenance Mode | OFF |
| Database driver | mysql |
| Timezone | Asia/Kuala_Lumpur |

---

## 4. Keserasian sambungan aplikasi ↔ phpMyAdmin

| Semakan | Hasil |
|---------|-------|
| Host/port sama? | ✅ `127.0.0.1:3306` |
| Nama pangkalan data sama? | ✅ `cmart_db` |
| Pangkalan data wujud? | ✅ `SHOW DATABASES` memaparkan `cmart_db` |
| Jadual boleh dibuka? | ❌ Semua jadual → ralat 1932 |

**Kesimpulan:** Aplikasi dan phpMyAdmin menunjuk ke **pangkalan data dan instance yang sama**. Masalah bukan sambungan salah, tetapi kerosakan enjin InnoDB.

---

## 5. Autentikasi aplikasi

| Item | Nilai |
|------|-------|
| Model | `App\Models\User` → jadual `users` |
| Controller | `App\Http\Controllers\Api\AuthController` |
| Kaedah login | `User::where('email', ...)->first()` + `Hash::check()` |
| Token | Laravel Sanctum (`personal_access_tokens`) |
| Akaun demo | `vendor@cmart.com` (dari `DatabaseSeeder`) |

**Ralat semasa:** Query `select * from users where email = vendor@cmart.com limit 1` → `1932 doesn't exist in engine`.

---

## 6. Pangkalan data lain pada instance yang sama

| Pangkalan data | Status |
|----------------|--------|
| `cmart_db` | Wujud — semua jadual rosak (1932) |
| `cmart_test` | **Tidak wujud** pada masa audit |
| `cmart_e2e_db` | **Tidak wujud** pada masa audit |
| `mysql`, `phpmyadmin`, `test` | Wujud (sistem) |

**Nota:** Log InnoDB menunjukkan aktiviti lama pada `cmart_test` (FK error 2026-07-20 01:25), tetapi pangkalan data itu tidak lagi wujud — mungkin telah dipadam.

---

## 7. Soalan belum selesai

1. Adakah terdapat salinan datadir lama di lokasi lain (Desktop, OneDrive, cakera luar)?
2. Bilakah tepatnya `cmart_db` dipadam dan dipulihkan pada 2026-06-28 / 2026-07-20?
3. Adakah `python_analytics` (port 8001) masih berjalan dan memerlukan `cmart_db`?
