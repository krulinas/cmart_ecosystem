# 07 — Phase 1 Isolated Level-1 Salvage Report

**Date:** 2026-07-21 (UTC+8)  
**Operator:** Cursor Agent (authorized Phase 1)  
**Stamp:** `20260721_085107`  
**Mode:** `innodb_force_recovery=1` on disposable attempt only

---

## Verdict

**Level-1 salvage failed: 0 of 21 tables readable.**

Recovery instance started successfully on port **3307** with `innodb_force_recovery=1`. Every `cmart_db` table still returns:

```text
ERROR 1932 (42S02): Table 'cmart_db.<name>' doesn't exist in engine
```

InnoDB dictionary entries for `cmart_db/%`: **0**  
Logical export: **not produced** (nothing readable)

Live XAMPP MariaDB (**PID 8992**, port **3306**, `force_recovery=0`) and Apache remained running and untouched. Forensic master `ibdata1` hash unchanged.

---

## Isolation validation (pre-flight)

| Check | Result |
|-------|--------|
| Live mysqld PID | **8992** on `:3306` |
| Live datadir | `D:\Program Files\xampp\mysql\data\` |
| Live `innodb_force_recovery` | **0** |
| Port 3307 free before start | ✅ |
| Forensic master present | `D:\cmart_forensic_master_20260721_085107` |
| Working clone present | `D:\cmart_recovery_clone_20260721_085107` |
| Master ↔ clone `ibdata1` hash match | ✅ `4DCAA169…CEB984` |

---

## Disposable Level-1 attempt

| Item | Path / value |
|------|----------------|
| Attempt root | `D:\cmart_phase1_L1_attempt_20260721_085107` |
| Datadir | `...\mysql_data\` (copied from working clone; clone left intact for later levels) |
| Config | `...\my.ini` — port **3307**, bind `127.0.0.1`, `innodb_force_recovery=1` |
| Error log | `...\logs\mysql_recovery_error.log` |
| Exports | `...\exports\` |
| Forensic master | **Not modified / not used as datadir** |

Startup log confirmed:

```text
InnoDB: !!! innodb_force_recovery is set to 1 !!!
... ready / listening on 127.0.0.1:3307 (PID 6692)
```

Recovery identity while running:

| Variable | Value |
|----------|-------|
| `@@port` | 3307 |
| `@@datadir` | `D:\cmart_phase1_L1_attempt_20260721_085107\mysql_data\` |
| `@@innodb_force_recovery` | 1 |

---

## Per-table read test (`SELECT COUNT(*)`)

| Table | Status | Error |
|-------|--------|-------|
| bookings | FAIL | 1932 doesn't exist in engine |
| booking_audit_logs | FAIL | 1932 |
| carboot_events | FAIL | 1932 |
| event_images | FAIL | 1932 |
| event_user | FAIL | 1932 |
| failed_jobs | FAIL | 1932 |
| feedbacks | FAIL | 1932 |
| invoices | FAIL | 1932 |
| jobs | FAIL | 1932 |
| management_profiles | FAIL | 1932 |
| migrations | FAIL | 1932 |
| news_images | FAIL | 1932 |
| news_posts | FAIL | 1932 |
| password_resets | FAIL | 1932 |
| personal_access_tokens | FAIL | 1932 |
| reuse_item_images | FAIL | 1932 |
| spaces | FAIL | 1932 |
| users | FAIL | 1932 |
| user_booking_preferences | FAIL | 1932 |
| vendor_business_profiles | FAIL | 1932 |
| vendor_items | FAIL | 1932 |

**Readable:** 0 / 21  
**CSV:** `D:\cmart_phase1_L1_attempt_20260721_085107\exports\table_read_results.csv`  
**Marker:** `...\exports\NO_SALVAGE_L1.txt`

`SHOW TABLE STATUS FROM cmart_db`: all engines `NULL`, comment `doesn't exist in engine` (sample saved under `exports\show_table_status.txt`).

---

## Export

`mysqldump` **skipped** — no readable tables. No `salvaged_partial_L1.sql` created.

---

## Shutdown and post-checks

| Step | Result |
|------|--------|
| `mysqladmin -h 127.0.0.1 -P 3307 shutdown` | Exit 0; PID 6692 exited |
| Port 3307 listening | ❌ gone (TIME_WAIT only) |
| Live mysqld | Still **PID 8992** on `:3306` |
| Live `force_recovery` | Still **0** |
| Apache httpd | Still running (10628, 10916) |
| Master `ibdata1` SHA256 | Unchanged |

---

## Interpretation

`innodb_force_recovery=1` allows the server to start despite some corruption, but it **cannot recreate missing InnoDB data dictionary entries**. The `.frm` / `.ibd` files remain orphaned from InnoDB’s view — consistent with Phase 0A/0B findings (dictionary empty for `cmart_db/*`).

Level-1 alone is therefore insufficient for row salvage via normal SQL.

---

## Explicitly NOT done

- Levels 2–6 force recovery  
- `IMPORT TABLESPACE` / discard tablespace  
- Live datadir changes  
- Forensic master mutation  
- Schema rebuild / migrate / seed  
- Leaving recovery instance running  

---

## Next gate (requires approval)

| Option | Description |
|--------|-------------|
| **A — Level-2/3 force recovery** | New disposable attempt from pristine clone; `innodb_force_recovery=2` then `3` only; still isolated on 3307 |
| **B — Advanced orphan `.ibd` salvage** | Separate authorized phase (discard/import / external tools) — higher risk, clone-only |
| **C — Skip salvage → Path 5 rebuild** | Create `cmart_db_rebuild` from Laravel migrations + seed; keep corrupted `cmart_db` as forensic archive |

**Recommendation:** Given 0/21 at Level-1 and empty dictionary, **Option C (rebuild)** is the most reliable path for restoring a working development database. Levels 2–3 may still be tried for completeness if you want exhaustiveness before abandoning SQL-level salvage.

**STOP** — await explicit approval for the next option.
