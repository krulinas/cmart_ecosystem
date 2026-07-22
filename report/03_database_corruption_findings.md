# 03 — Penemuan Kerosakan Pangkalan Data

**Tarikh:** 2026-07-20 (UTC+8)  
**Klasifikasi:** Orphaned InnoDB dictionary / metadata-engine mismatch (ralat 1932)

---

## 1. Simptom aplikasi

```text
SQLSTATE[42S02]: Base table or view not found: 1932
Table 'cmart_db.users' doesn't exist in engine
(Connection: mysql, SQL: select * from `users` where `email` = vendor@cmart.com limit 1)
```

**Fakta:** Ralat yang sama berlaku pada **semua 21 jadual** dalam `cmart_db`, termasuk `migrations`.

---

## 2. Klasifikasi setiap jadual

Semua 21 jadual diklasifikasikan sebagai:

> **Metadata wujud dalam kamus SQL (`.frm` / `information_schema`) tetapi tablespace InnoDB tidak terikat / tidak dikenali oleh enjin**

| Jadual | ENGINE (information_schema) | Ralat akses | Fail fizikal |
|--------|----------------------------|-------------|--------------|
| `users` | NULL | 1932 | `users.frm` (6272 B), `users.ibd` (81920 B) |
| `migrations` | NULL | 1932 | `migrations.frm`, `migrations.ibd` |
| `bookings` | NULL | 1932 | `bookings.frm`, `bookings.ibd` (196608 B) |
| `personal_access_tokens` | NULL | 1932 | `.frm`, `.ibd` (393216 B) |
| *(17 jadual lain)* | NULL | 1932 | `.frm` + `.ibd` wujud |

**Tiada jadual** diklasifikasikan sebagai "sihat dan boleh dibaca".

---

## 3. Bukti SQL

### `SHOW TABLE STATUS FROM cmart_db`

Semua jadual: `Engine=NULL`, `Comment=Table 'cmart_db.<name>' doesn't exist in engine`

### `information_schema.TABLES`

21 baris, semua `ENGINE=NULL`, `TABLE_COLLATION=NULL`

### `information_schema.INNODB_SYS_TABLES WHERE NAME LIKE 'cmart_db/%'`

**0 baris** — ini bukti kritikal bahawa InnoDB **tidak mempunyai entri dictionary** untuk sebarang jadual `cmart_db`.

### `SHOW CREATE TABLE cmart_db.users`

```
ERROR 1932 (42S02): Table 'cmart_db.users' doesn't exist in engine
```

### `CHECK TABLE cmart_db.users`

Tidak dapat dijalankan (gagal sebelum CHECK kerana 1932).

---

## 4. Inventori fizikal

**Direktori:** `D:\Program Files\xampp\mysql\data\cmart_db\`

| Item | Nilai |
|------|-------|
| Jumlah saiz | ~2,459,245 bytes (~2.35 MB) |
| `db.opt` | `utf8mb4` / `utf8mb4_unicode_ci` |
| Bilangan jadual | 21 (21 × `.frm` + 21 × `.ibd`) |
| Tarikh cipta `.frm` | 2026-06-28 09:21 (kebanyakan) |
| Tarikh terakhir `.ibd` | 2026-07-07 (beberapa jadual) |
| Folder diubah | 2026-07-20 10:35:00 |

### Fail sistem InnoDB (datadir root)

| Fail | Saiz | Last modified |
|------|------|---------------|
| `ibdata1` | 79,691,776 B (~76 MB) | 2026-07-20 10:43:58 |
| `ib_logfile0` | 5,242,880 B | 2026-07-20 10:43:59 |
| `ib_logfile1` | 5,242,880 B | 2026-07-20 08:51:18 |
| `mysql_error.log` | 172,643 B | 2026-07-20 01:11:31 |

**Andaian:** `ibdata1` diubah hari ini (~10:43) hampir serentak dengan folder `cmart_db` (~10:35), menunjukkan percubaan pemulihan fizikal terkini.

---

## 5. Bukti log ralat

### `mysql_error.log` — 2026-07-08 03:18:24

```text
[ERROR] InnoDB: Page [page id: space=0, page number=7] log sequence number 5477507 is in the future!
         Current system log sequence number 300306.
[ERROR] InnoDB: Your database may be corrupt or you may have copied the InnoDB
         tablespace but not the InnoDB log files.
```

**Diulang berpuluh kali** — corak klasik pemulihan fizikal tidak lengkap.

### `laravel.log` — garis masa

| Tarikh | Ralat | Interpretasi |
|--------|-------|--------------|
| 2026-06-28 09:20 | `Unknown database 'cmart_db'` | Pangkalan data dipadam |
| 2026-06-28 09:21+ | Jadual tidak wujud (1146) | DB dibina semula, migration berjalan separa |
| 2026-07-07 | Query berjaya (data ditulis) | DB berfungsi seketika |
| 2026-07-20 04:31 | Ralat 1932 mula muncul | Kerosakan metadata InnoDB aktif |
| 2026-07-20 10:26 | 1932 pada `carboot_events`, `news_posts` | Semua jadual terjejas |

---

## 6. Diagnosis punca akar

### Bukan punca ini

| Hipotesis | Bukti menolak |
|-----------|---------------|
| Sambungan aplikasi salah | `.env` → `cmart_db` @ 127.0.0.1:3306; sama dengan phpMyAdmin |
| Jadual benar-benar hilang | `.frm` + `.ibd` wujud untuk semua jadual |
| Config cache stale | `bootstrap/cache/config.php` tidak wujud |
| Instance DB berbeza | Hanya satu listener port 3306 |

### Punca paling mungkin (disokong bukti)

**Pemulihan fizikal folder `cmart_db` tanpa pasangan `ibdata1` + `ib_logfile*` yang serasi**, atau **`ibdata1` diganti/direset** sementara folder jadual lama dikekalkan.

Ini menghasilkan:
1. Kamus SQL (`.frm`) masih merujuk jadual
2. Fail `.ibd` masih ada di disk
3. InnoDB dictionary (`INNODB_SYS_TABLES`) **tidak** mengenali tablespace tersebut
4. Semua query → ralat 1932

---

## 7. Pemisahan: pemulihan skema vs data

| Aspek | Status | Sumber pemulihan |
|-------|--------|------------------|
| **Skema** (jadual, lajur, indeks, FK) | Boleh dibina semula | 65 Laravel migrations |
| **Data asal** (baris pengguna, booking, feedback) | Tidak boleh dibaca sekarang | Mungkin dalam `.ibd` — perlu salvage |
| **Data demo** | Boleh dibina semula | `DatabaseSeeder` |
| **Data Phase 3** | Tidak pernah wujud dalam `cmart_db` | Migration akan cipta skema baru |

---

## 8. Risiko jika dibiarkan

- Semua operasi aplikasi yang menyentuh DB akan gagal
- Percubaan "fix" tanpa salinan forensik (DROP TABLE, padam `.ibd`, `migrate:fresh`) akan **memusnahkan bukti** dan kemungkinan data dalam `.ibd`
- `python_analytics` (jika berjalan) juga tidak dapat query `cmart_db`
