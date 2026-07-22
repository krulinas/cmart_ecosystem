# 06 — Phase 0B Forensic Copy Execution Report

**Date:** 2026-07-21 (UTC+8, Asia/Kuala_Lumpur)  
**Operator:** Cursor Agent (authorized Phase 0B)  
**Repository:** `krulinas/cmart_ecosystem`  
**Stamp:** `20260721_085107`

---

## Outcome

Phase 0B completed successfully. A cold forensic master of the full MariaDB datadir was created, cryptographically verified, and used to produce a separate working clone. **No salvage, repair, rebuild, migration, seeding, tablespace import, or recovery-instance startup was performed.**

Live XAMPP MariaDB and Apache were restored after the copy. Laravel maintenance mode was lifted. Corruption state of `cmart_db` is unchanged (21 tables visible; 0 InnoDB dictionary entries).

---

## Paths

| Role | Path |
|------|------|
| **Forensic master (immutable)** | `D:\cmart_forensic_master_20260721_085107` |
| **Working clone (disposable)** | `D:\cmart_recovery_clone_20260721_085107` |
| Live XAMPP datadir | `D:\Program Files\xampp\mysql\data` |
| SHA256 manifest | `D:\cmart_forensic_master_20260721_085107\SHA256SUMS.txt` |
| Verification record | `D:\cmart_forensic_master_20260721_085107\VERIFICATION.txt` |
| Clone README | `D:\cmart_recovery_clone_20260721_085107\README_CLONE.txt` |

---

## Execution timeline

| Step | Action | Result |
|------|--------|--------|
| 1 | Stop `php artisan serve` (:8000) | Stopped (PID 2140) |
| 2 | `php artisan down` | Maintenance ON |
| 3 | Graceful Apache stop | STOPPED (httpd processes cleared) |
| 4 | `mysqladmin -u root shutdown` | STOPPED; `ibdata1` unlocked; `mysql.pid` removed |
| 5 | Cold copy datadir → forensic master | 181 files / 98,347,136 bytes in ~3.1 s |
| 6 | Preserve `my.ini`, `backend.env`, `laravel.log`, Phase A `report/` | OK under `preserved/` |
| 7 | Structural verify (count/bytes/paths) | `STRUCTURAL_OK=True` (0 missing/extra/mismatch) |
| 8 | SHA256 manifest | 193 entries; spot-checks OK |
| 9 | Working clone from master | 181 files / 98,347,136 bytes; critical hashes match |
| 10 | Restart live `mysqld` | PID 8992, port 3306, `SELECT 1` OK |
| 11 | Restart Apache `httpd` | PIDs 10628, 10916 |
| 12 | `php artisan up` | Maintenance OFF |

---

## Forensic master layout

```text
D:\cmart_forensic_master_20260721_085107\
  mysql_data\          # full cold datadir copy
  preserved\
    my.ini
    backend.env
    laravel.log
    report_phaseA\     # Phase A audit reports
  SHA256SUMS.txt
  SOURCE_STATE.txt
  VERIFICATION.txt
```

### Cold source inventory (at copy time)

| Metric | Value |
|--------|-------|
| Source | `D:\Program Files\xampp\mysql\data` |
| File count | **181** |
| Byte sum | **98,347,136** (~93.8 MB) |
| `cmart_db/` files | 43 (~2.46 MB) |
| mysqld / Apache | STOPPED |
| Laravel maintenance | ON |

Note: Pre-shutdown hot inventory was ~183 files / ~106 MB. Difference is expected after graceful shutdown (`ibtmp1` recreation lifecycle, `mysql.pid` removal, etc.). The cold copy matches the cold source exactly.

---

## Verification summary

| Check | Result |
|-------|--------|
| File count src = dst | ✅ 181 = 181 |
| Byte sum src = dst | ✅ 98,347,136 = 98,347,136 |
| Missing / extra / size mismatch | ✅ 0 / 0 / 0 |
| Critical paths present | ✅ `cmart_db`, `ibdata1`, `ib_logfile0/1`, `mysql_error.log`, `users.frm/.ibd`, `db.opt` |
| Spot SHA256 re-hash | ✅ `ibdata1`, `users.ibd`, `my.ini` |

### Key SHA256 hashes (forensic master)

| File | Bytes | SHA256 |
|------|------:|--------|
| `mysql_data\ibdata1` | 79,691,776 | `4DCAA169D0201FA45BA5380E3EC7A726E1D967592CF779B41C369949D8CEB984` |
| `mysql_data\ib_logfile0` | 5,242,880 | `5207098537FAD992397E125174D2056F79AA17145804F944D2F77DDCCB844F4D` |
| `mysql_data\ib_logfile1` | 5,242,880 | `5B9E81084E8C51210C46703014B26E9DFD8B9F89B4F1A5E665F107885FA8BC5B` |
| `mysql_data\mysql_error.log` | 174,428 | `0D4EFDC28AB645CE4B2C8D328FE613DEFD9AAF10019F25A3D67E301160C37356` |
| `mysql_data\cmart_db\users.ibd` | 81,920 | `7445B55A60E8924D04F330F23774DA435AE5B95A1585959F0DC95D2C629005F8` |
| `mysql_data\cmart_db\users.frm` | 6,272 | `AC92B4C5FF77A068FD5691079F70D105D24224EAC4989FE92D92582AAAA54134` |
| `preserved\my.ini` | 5,958 | `0D1F157EBE1EAC4B332ACAEA4AA73023DDB04CB94919D9832870E46353D99B12` |
| `preserved\backend.env` | 1,427 | `ABAD8622D4579C9F3D2FB2CDF6CF4E1385D09FEA469E149290C252F286082ED5` |

Full file list: `SHA256SUMS.txt` (193 lines including preserved artifacts).

---

## Working clone

| Metric | Value |
|--------|-------|
| Path | `D:\cmart_recovery_clone_20260721_085107` |
| Contents | `mysql_data\` + `my.ini.template` + `README_CLONE.txt` + `CLONE_MANIFEST.txt` |
| File count / bytes | 181 / 98,347,136 (matches master datadir) |
| Spot hash match | ✅ `ibdata1`, `users.ibd`, `mysql_error.log` |

**Rules enforced by README:** do not modify the forensic master; do not retarget live XAMPP datadir to the clone; do not start recovery `mysqld` until a later authorized phase.

---

## Post-copy live environment

| Component | Status |
|-----------|--------|
| MariaDB 10.4.32 | Running (PID 8992, :3306) |
| Apache httpd | Running |
| Laravel maintenance | OFF |
| `cmart_db` tables in information_schema | 21 |
| `INNODB_SYS_TABLES` for `cmart_db/%` | **0** (corruption unchanged) |

---

## Explicitly NOT done (out of Phase 0B scope)

- `innodb_force_recovery` / salvage reads
- `IMPORT TABLESPACE` / `.ibd` repair
- Schema rebuild / migrate / seed
- Starting a second MariaDB on port 3307
- Modifying live datadir contents (copy-only of live files; services restarted to prior operational posture)

---

## Next gate

**STOP.** Await explicit approval before Phase 1 (isolated salvage on the working clone) or Path 5 rebuild planning.

Recommended next inputs from operator:

1. Approve Phase 1 salvage on `D:\cmart_recovery_clone_20260721_085107` only (never the master).
2. Or skip salvage and proceed to clean `cmart_db_rebuild` from migrations.
