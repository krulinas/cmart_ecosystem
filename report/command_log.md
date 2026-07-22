# Command Log — Audit Peringkat A `cmart_db`

**Masa audit:** 2026-07-20 (UTC+8, Asia/Kuala_Lumpur)  
**Auditor:** Cursor Agent (read-only)  
**Working directory asas:** `d:\Program Files\xampp\htdocs\cmart_ecosystem`

---

| # | Masa (anggaran) | Perintah / SQL | CWD / Server | Read-only? | Ringkasan output | Exit |
|---|-----------------|----------------|--------------|------------|------------------|------|
| 1 | 11:00 | `php artisan about` | `backend/` | Ya | Laravel 11.51.0, PHP 8.2.12, env=local, DB=mysql, Config NOT CACHED | 0 |
| 2 | 11:00 | `php artisan migrate:status` | `backend/` | Ya | GAGAL: `SQLSTATE[42S02] 1932 Table 'cmart_db.migrations' doesn't exist in engine` | 1 |
| 3 | 11:00 | `php artisan env` | `backend/` | Ya | `The application environment is [local].` | 0 |
| 4 | 11:01 | `mysql -u root -e "SELECT VERSION()..."` | MariaDB 127.0.0.1:3306 | Ya | MariaDB 10.4.32; datadir=`D:\Program Files\xampp\mysql\data\`; innodb_file_per_table=ON; lower_case_table_names=1 | 0 |
| 5 | 11:02 | `mysql -u root -e "SHOW DATABASES; SHOW TABLE STATUS FROM cmart_db;"` | MariaDB | Ya | `cmart_db` wujud; 21 jadual kelihatan; SEMUA Comment=`doesn't exist in engine`; ENGINE=NULL | 0 |
| 6 | 11:02 | `mysql -u root -e "SELECT ... FROM information_schema.TABLES WHERE TABLE_SCHEMA='cmart_db'"` | MariaDB | Ya | 21 baris; ENGINE=NULL untuk semua jadual | 0 |
| 7 | 11:02 | `mysql -u root -e "SHOW CREATE TABLE cmart_db.users; CHECK TABLE cmart_db.users;"` | MariaDB | Ya | GAGAL: `ERROR 1932 Table 'cmart_db.users' doesn't exist in engine` | 1 |
| 8 | 11:02 | `Get-ChildItem D:\Program Files\xampp\mysql\data\cmart_db` | Windows shell | Ya | 21 pasangan `.frm` + `.ibd` + `db.opt`; jumlah ~2.46 MB | 0 |
| 9 | 11:03 | `mysql -u root -e "SHOW ENGINE INNODB STATUS\G"` | MariaDB | Ya | InnoDB berjalan; tiada ralat baharu berkaitan `cmart_db`; FK error lama pada `cmart_test` | 0 |
| 10 | 11:03 | `Get-ChildItem D:\Program Files\xampp\mysql\data -File` | Windows shell | Ya | `ibdata1` (79.7 MB, modified 20/7/2026 10:43), `ib_logfile0/1`, `mysql_error.log` | 0 |
| 11 | 11:03 | `Get-ChildItem D:\Program Files\xampp -Recurse -Include *.sql,*.sql.gz,*.dump,*.bak` | Windows shell | Ya | Tiada dump `cmart_db` ditemui; hanya fail sistem/vendor | 0 |
| 12 | 11:04 | `mysql -u root -e "SHOW DATABASES LIKE 'cmart%';"` | MariaDB | Ya | Hanya `cmart_db`; tiada `cmart_test` / `cmart_e2e_db` pada masa audit | 0 |
| 13 | 11:04 | Baca `mysql_error.log` (grep) | Fail log | Ya | Ralat 2026-07-08 03:18: "copied InnoDB tablespace but not InnoDB log files"; LSN mismatch | — |
| 14 | 11:05 | `mysql -u root -e "SELECT NAME, SPACE, ROW_FORMAT FROM information_schema.INNODB_SYS_TABLES WHERE NAME LIKE 'cmart_db/%'"` | MariaDB | Ya | **0 baris** — InnoDB dictionary tiada entri untuk `cmart_db` | 0 |
| 15 | 11:05 | `Get-ChildItem Downloads, Desktop, D:\backups -Recurse -Include *cmart*,*.sql` | Windows shell | Ya | Tiada `.sql` backup; hanya PDF laporan | 0 |
| 16 | 11:05 | `netstat -ano \| findstr ":3306"` | Windows shell | Ya | PID 11416 mendengar port 3306 | 0 |
| 17 | 11:05 | `mysql -u root -e "SHOW VARIABLES LIKE 'character_set%'; SELECT 1"` | MariaDB | Ya | `character_set_database=utf8mb4`; `SELECT 1` OK | 0 |
| 18 | 11:05 | `Test-Path backend/bootstrap/cache/config.php` | Windows shell | Ya | `False` — config Laravel tidak di-cache | 0 |
| 19 | 11:05 | `git log --all --oneline -- "*.sql"` | repo root | Ya | Tiada sejarah commit fail `.sql` dalam repo | 0 |
| 20 | 11:06 | Grep `backend/storage/logs/laravel.log` untuk `1932` | Fail log | Ya | Ralat 1932 bermula 2026-07-20 04:31 pada pelbagai jadual | — |

---

## Nota keselamatan (Peringkat A)

- **Tiada** perintah destruktif dijalankan semasa Peringkat A (`DROP`, `TRUNCATE`, `migrate:fresh`, dll.).
- Salinan forensik dilaksanakan kemudian dalam **Phase 0B** (lihat bahagian di bawah dan `06_phase0b_forensic_copy_execution.md`).

---

# Command Log — Phase 0B Forensic Copy

**Masa:** 2026-07-21 ~08:48–08:57 (UTC+8)  
**Stamp:** `20260721_085107`  
**Master:** `D:\cmart_forensic_master_20260721_085107`  
**Clone:** `D:\cmart_recovery_clone_20260721_085107`

| # | Masa | Perintah | Read-only? | Ringkasan | Exit |
|---|------|----------|------------|-----------|------|
| B1 | 08:48 | Stop `php artisan serve` (:8000) | Tidak (quiesce) | PID 2140 dihentikan | 0 |
| B2 | 08:48 | `php artisan down --retry=60` | Tidak (quiesce) | Maintenance ON | 0 |
| B3 | 08:49 | Apache stop (`httpd` processes) | Tidak | Apache STOPPED | 0 |
| B4 | 08:50 | `mysqladmin -u root shutdown` | Tidak | MariaDB STOPPED; `ibdata1` unlocked | 0 |
| B5 | 08:51 | Cold `Copy-Item` datadir → master | Salinan | 181 fail / 98,347,136 bait; ~3.1 s | 0 |
| B6 | 08:51 | Salin `my.ini`, `.env`, `laravel.log`, `report/` | Salinan | Di bawah `preserved/` | 0 |
| B7 | 08:51 | Structural verify + SHA256 manifest | Ya (pada salinan) | STRUCTURAL_OK; 193 hash; spot-check OK | 0 |
| B8 | 08:52 | Clone master → `cmart_recovery_clone_*` | Salinan | 181 fail; hash kritikal sepadan | 0 |
| B9 | 08:54 | Restart live `mysqld` (XAMPP asal) | Tidak (restore) | PID 8992; `SELECT 1` OK | 0 |
| B10 | 08:57 | Restart `httpd` + `php artisan up` | Tidak (restore) | Apache RUNNING; maintenance OFF | 0 |
| B11 | 08:57 | Spot-check `cmart_db` / InnoDB dict | Ya | 21 jadual; 0 entri InnoDB — tiada perubahan | 0 |

## Nota keselamatan (Phase 0B)

- Forensic master dan working clone di luar tree XAMPP live.
- **Tiada** salvage, `innodb_force_recovery`, `IMPORT TABLESPACE`, migrate, atau seed (semasa 0B).
- **Tiada** instance recovery (port 3307) dimulakan (semasa 0B).
- Live datadir tidak diubah kandungannya; hanya disalin semasa cold.

---

# Command Log — Phase 1 Level-1 Salvage

**Masa:** 2026-07-21 ~12:00–12:02 (UTC+8)  
**Attempt:** `D:\cmart_phase1_L1_attempt_20260721_085107`  
**Laporan:** `07_phase1_level1_salvage_report.md`

| # | Perintah | Ringkasan | Exit |
|---|----------|-----------|------|
| P1-1 | Salin clone → L1 attempt + tulis `my.ini` (3307, force_recovery=1) | Disposable attempt dicipta | 0 |
| P1-2 | `mysqld --defaults-file=...\my.ini` | Recovery PID 6692 pada 127.0.0.1:3307 | 0 |
| P1-3 | `SELECT COUNT(*)` semua 21 jadual pada 3307 | **0 OK / 21 FAIL (1932)** | 1×21 |
| P1-4 | `mysqldump` | Dilangkau — tiada jadual boleh dibaca | — |
| P1-5 | `mysqladmin -P 3307 shutdown` | Recovery dihentikan; live 8992/:3306 kekal | 0 |

**Live isolation:** PID 8992, datadir XAMPP, `force_recovery=0` sepanjang fasa. Master `ibdata1` hash tidak berubah.

---

# Command Log — Phase 2 Clean Rebuild

**Masa:** 2026-07-21 ~13:20+ (UTC+8)  
**Laporan:** `08_phase2_clean_rebuild_report.md`

| # | Perintah | Ringkasan | Exit |
|---|----------|-----------|------|
| P2-1 | `CREATE DATABASE cmart_db_rebuild ...` | DB baharu; `cmart_db` kekal 21/0 InnoDB | 0 |
| P2-2 | Edit `backend/.env` → `DB_DATABASE=cmart_db_rebuild` | Laravel beralih ke rebuild | — |
| P2-3 | `config:clear` / `cache:clear` | Config resolved = `cmart_db_rebuild` | 0 |
| P2-4 | `php artisan migrate --force` | Semua migration Ran (36 jadual) | 0 |
| P2-5 | `php artisan db:seed --class=DatabaseSeeder` | 7 users, spaces, events, dll. | 0 |
| P2-6 | SQL + tinker + `POST /api/auth/login` | Login 200 + Bearer token | 0 |

**Tidak dijalankan:** `migrate:fresh`, DROP `cmart_db`, ubah forensik, recovery `:3307`.
