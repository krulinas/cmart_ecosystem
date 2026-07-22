# 08 — Phase 2 Clean Rebuild and Laravel Switch Report

**Date:** 2026-07-21 (UTC+8)  
**Operator:** Cursor Agent (authorized Phase 2 / Path 5)  
**Cutover mode:** Option B — `.env` points to `cmart_db_rebuild`; corrupted `cmart_db` retained as forensic archive

---

## Verdict

**Development database restored and usable.**

| Item | Result |
|------|--------|
| New database | `cmart_db_rebuild` on live MariaDB `:3306` |
| Migrations | All ran successfully (batch 1) — **36** tables, **0** broken engines |
| Seed | `DatabaseSeeder` OK (demo users, spaces, events, news, booking, invoices, feedbacks) |
| Laravel `.env` | `DB_DATABASE=cmart_db_rebuild` |
| Login API | **HTTP 200** — `vendor@cmart.com` / `password123` → Bearer token |
| Corrupted `cmart_db` | **Untouched** (21 tables, 0 InnoDB dict entries) |
| Forensic master / clone / L1 attempt | **Intact** |

---

## Scope honored

- Did **not** DROP/RENAME/ALTER corrupted `cmart_db`
- Did **not** modify forensic master, working clone, or Phase 1 attempt
- Did **not** use `migrate:fresh` / `migrate:refresh` / `db:wipe`
- Did **not** import salvage dumps (Phase 1 produced none)
- Live MariaDB remained PID **8992** on port **3306** (`innodb_force_recovery=0`)
- Port **3307** recovery instance not started

---

## Steps executed

1. `CREATE DATABASE cmart_db_rebuild CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
2. `backend/.env`: `DB_DATABASE=cmart_db` → `cmart_db_rebuild`
3. `php artisan config:clear` / `cache:clear`
4. `php artisan migrate --pretend` then `php artisan migrate --force` — exit 0
5. `php artisan db:seed --class=DatabaseSeeder --force` — exit 0
6. SQL + tinker + temporary `artisan serve` login smoke test

---

## Post-rebuild inventory (`cmart_db_rebuild`)

| Metric | Count |
|--------|------:|
| Tables | 36 |
| Tables with broken engine | 0 |
| InnoDB dict entries (`cmart_db_rebuild/%`) | 36 |
| Users | 7 |
| Spaces | 2 |
| Carboot events | 3 |
| News posts | 3 |
| Bookings | 1 |
| Invoices | 1 |
| Feedbacks | 5 |
| Vendor categories (from migration seed) | 7 |

### Demo accounts (password: `password123`)

| Email | Role |
|-------|------|
| `vendor@cmart.com` | community (approved vendor) |
| `vendor_b@cmart.com` | community (approved vendor) |
| `admin@cmart.com` | organizer |
| `staff@cmart.com` | cmart_management |
| `hq@cmart.com` | super_admin |
| `organizer@cmart.com` | organizer |
| `venue@cmart.com` | cmart_management |

### Login smoke test

```text
POST http://127.0.0.1:8000/api/auth/login
{"email":"vendor@cmart.com","password":"password123"}
→ 200 OK, token_type=Bearer, user.email=vendor@cmart.com
```

(Temporary `php artisan serve` used for the test, then stopped. Restart `php artisan serve` in your terminal if needed.)

---

## Laravel connection (current)

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cmart_db_rebuild
DB_USERNAME=root
```

---

## What remains archived (do not delete without approval)

| Artefact | Path |
|----------|------|
| Corrupted live DB | `cmart_db` on XAMPP datadir |
| Forensic master | `D:\cmart_forensic_master_20260721_085107` |
| Working clone | `D:\cmart_recovery_clone_20260721_085107` |
| L1 attempt | `D:\cmart_phase1_L1_attempt_20260721_085107` |

Master `ibdata1` SHA256 still `4DCAA169…CEB984`.

---

## Notes

- Rebuild includes **Phase 3/4 schema** (layouts, categories, item reservations, etc.) that the old `cmart_db` never had — this is expected for a clean migrate of the current codebase.
- Manual/demo data that existed only in the corrupted `.ibd` files was **not** recovered (Level-1 salvage = 0/21). Demo seed restores known accounts and sample content.
- Rollback: set `DB_DATABASE=cmart_db` in `.env` (returns to broken DB — for forensic comparison only). Or `DROP DATABASE cmart_db_rebuild` after approval if rebuild must be discarded (does not restore old data).

---

## Next (optional, needs approval)

- Restart `php artisan serve` / Vite for daily use
- Optionally seed `VendorCategorySeeder` if extra category fixtures needed beyond migration seed
- Later cutover Option A (rename rebuild → `cmart_db`) only after explicit approval to retire the corrupted DB name
- Do **not** delete forensic artefacts until recovery closure is signed off
