# 02 — Inventori Skema dan Sumber Data

**Tarikh:** 2026-07-20 (UTC+8)

---

## 1. Framework dan versi

| Komponen | Versi |
|----------|-------|
| Laravel | 11.51.0 |
| PHP | 8.2.12 |
| MariaDB | 10.4.32 |
| Python analytics | `python_analytics/` — juga menggunakan `DB_DATABASE=cmart_db` |

---

## 2. Sumber skema

### Migration files

- **Lokasi:** `backend/database/migrations/`
- **Jumlah:** 65 fail migration
- **Status dalam DB:** `migrations` jadual wujud dalam kamus tetapi **tidak boleh dibaca** (1932)

### Jadual yang dijangka dari migration (Schema::create)

| Jadual | Migration sumber |
|--------|------------------|
| `users` | `2014_10_12_000000_create_users_table.php` |
| `password_resets` | `2014_10_12_100000_create_password_resets_table.php` |
| `failed_jobs` | `2019_08_19_000000_create_failed_jobs_table.php` |
| `personal_access_tokens` | `2019_12_14_000001_create_personal_access_tokens_table.php` |
| `spaces` | `2026_05_09_051839_create_spaces_table.php` |
| `bookings` | `2026_05_09_052330_create_bookings_table.php` |
| `invoices` | `2026_05_09_052404_create_invoices_table.php` |
| `feedbacks` | `2026_05_09_052518_create_feedbacks_table.php` |
| `carboot_events` | `2026_05_21_000002_create_carboot_events_table.php` |
| `news_posts` | `2026_05_21_000003_create_news_posts_table.php` |
| `event_user` | `2026_06_11_000001_create_event_user_table.php` |
| `jobs` | `2026_06_11_000002_create_jobs_table.php` |
| `booking_audit_logs` | `2026_06_03_000001_create_booking_audit_logs_table.php` |
| `vendor_business_profiles` | `2026_06_15_000001_create_vendor_business_profiles_table.php` |
| `vendor_items` | `2026_06_15_000002_create_vendor_items_table.php` |
| `management_profiles` | `2026_06_15_000003_create_management_profiles_table.php` |
| `reuse_item_images` | `2026_06_16_000001_create_reuse_item_images_table.php` |
| `event_images` | `2026_06_18_000001_create_event_and_news_images_tables.php` |
| `news_images` | `2026_06_18_000001_create_event_and_news_images_tables.php` |
| `user_booking_preferences` | `2026_06_21_000002_create_user_booking_preferences_table.php` |
| `vendor_categories` | `2026_07_16_000001_create_vendor_categories_table.php` |
| `event_sites` | `2026_07_14_000001_create_event_sites_table.php` |
| `event_days` | `2026_07_14_000003_create_event_days_table.php` |
| `booking_day_allocations` | `2026_07_14_000004_create_booking_day_allocations_table.php` |
| `booking_attendance_exceptions` | `2026_07_15_000001_create_booking_attendance_exceptions_tables.php` |
| `booking_attendance_exception_days` | `2026_07_15_000001_create_booking_attendance_exceptions_tables.php` |
| `category_migration_audits` | `2026_07_16_000003_create_category_migration_audits_table.php` |
| `event_layout_rows` | `2026_07_16_000006_create_event_layout_rows_table.php` |
| `event_layout_audit_logs` | `2026_07_16_000012_create_event_layout_audit_logs_table.php` |
| `booking_category_overrides` | `2026_07_17_000001_create_booking_category_overrides_table.php` |
| `item_reservations` | `2026_07_19_000001_create_item_reservations_table.php` |
| `item_reservation_audits` | `2026_07_19_000002_create_item_reservation_audits_table.php` |

**Jadual audit sementara:** `role_cleanup_audit_202607_pr2` (dicipta oleh migration `2026_07_12_000002_finalize_canonical_user_roles.php`)

---

## 3. Pemetaan migration → status pangkalan data semasa

| Jadual | Migration | Kelihatan dalam DB | Fail fizikal | Boleh dibaca | Kebolehpulihan | Tindakan dicadangkan |
|--------|-----------|-------------------|--------------|--------------|----------------|-------------------|
| `users` | 2014_10_12_000000 | ✅ | `.frm` + `.ibd` | ❌ 1932 | Schema: migration; Data: mungkin dalam `.ibd` | Salvage `.ibd` atau seed semula |
| `password_resets` | 2014_10_12_100000 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `failed_jobs` | 2019_08_19_000000 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `personal_access_tokens` | 2019_12_14_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `spaces` | 2026_05_09_051839 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: seeder | Rebuild + seed |
| `bookings` | 2026_05_09_052330 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: mungkin dalam `.ibd` | Salvage atau seed |
| `invoices` | 2026_05_09_052404 | ✅ | ✅ | ❌ 1932 | Schema: migration | Salvage atau seed |
| `feedbacks` | 2026_05_09_052518 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: mungkin dalam `.ibd` | Salvage atau seed |
| `carboot_events` | 2026_05_21_000002 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: seeder | Rebuild + seed |
| `news_posts` | 2026_05_21_000003 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: seeder | Rebuild + seed |
| `event_user` | 2026_06_11_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `jobs` | 2026_06_11_000002 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `booking_audit_logs` | 2026_06_03_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `vendor_business_profiles` | 2026_06_15_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `vendor_items` | 2026_06_15_000002 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `management_profiles` | 2026_06_15_000003 | ✅ | ✅ | ❌ 1932 | Schema: migration; Data: seeder | Rebuild + seed |
| `reuse_item_images` | 2026_06_16_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `event_images` | 2026_06_18_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `news_images` | 2026_06_18_000001 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `user_booking_preferences` | 2026_06_21_000002 | ✅ | ✅ | ❌ 1932 | Schema: migration | Rebuild |
| `migrations` | (Laravel) | ✅ | ✅ | ❌ 1932 | Tiada | Rebuild (migration semula) |
| `vendor_categories` | 2026_07_16_000001 | ❌ | ❌ | — | Schema: migration + seeder | Migration baru |
| `event_sites` | 2026_07_14_000001 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `event_days` | 2026_07_14_000003 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `booking_day_allocations` | 2026_07_14_000004 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `booking_attendance_exceptions` | 2026_07_15_000001 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `booking_attendance_exception_days` | 2026_07_15_000001 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `category_migration_audits` | 2026_07_16_000003 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `event_layout_rows` | 2026_07_16_000006 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `event_layout_audit_logs` | 2026_07_16_000012 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `booking_category_overrides` | 2026_07_17_000001 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `item_reservations` | 2026_07_19_000001 | ❌ | ❌ | — | Schema: migration | Migration baru |
| `item_reservation_audits` | 2026_07_19_000002 | ❌ | ❌ | — | Schema: migration | Migration baru |

**Fakta:** `cmart_db` semasa hanya mengandungi 21 jadual (pra-Phase 3). 12+ jadual Phase 3/4 belum pernah wujud dalam pangkalan data ini — selaras dengan dokumentasi projek.

---

## 4. Skema jadual `users` (dijangka)

### Lajur asas (`2014_10_12_000000_create_users_table.php`)

- `id`, `name`, `email` (unique), `email_verified_at`, `password`
- `phone_number`, `role`, `vendor_status`, `remember_token`, `timestamps`

### Perubahan melalui migration seterusnya

- `2026_05_12_000001` — RBAC: role enum → `community|cmart_staff|cmart_admin|uum`, tambah `vendor_status`
- `2026_07_09_000001` — tambah `organizer`, `cmart_management`
- `2026_07_12_000001` — remap legacy roles
- `2026_07_12_000002` — final roles: `community|organizer|cmart_management|super_admin`

### Peranan akhir yang dijangka

`community`, `organizer`, `cmart_management`, `super_admin`

---

## 5. Sumber data (seeders & fixtures)

### `backend/database/seeders/DatabaseSeeder.php`

Akaun demo (idempotent `updateOrCreate`):

| Email | Peranan | vendor_status |
|-------|---------|---------------|
| `vendor@cmart.com` | community | approved |
| `vendor_b@cmart.com` | community | approved |
| `admin@cmart.com` | organizer | none |
| `staff@cmart.com` | cmart_management | none |
| `hq@cmart.com` | super_admin | none |
| `organizer@cmart.com` | organizer | none |
| `venue@cmart.com` | cmart_management | none |

Password demo: `password123` (bcrypt dalam seeder)

Juga mencipta: spaces, carboot events, news posts, demo booking + invoice, management profiles.

### Seeder lain

- `FeedbackSeeder.php`
- `VendorCategorySeeder.php`

### Factories

- `UserFactory.php`, `EventLayoutRowFactory.php`, `VendorCategoryFactory.php`, `BookingDayAllocationFactory.php`

---

## 6. Sumber backup / dump

| Lokasi dicari | Hasil |
|---------------|-------|
| Repo `cmart_ecosystem` | ❌ Tiada `.sql`, `.sql.gz`, `.dump` |
| `D:\Program Files\xampp\` (rekursif) | ❌ Tiada dump `cmart_db` |
| `C:\Users\PC\Downloads` | ❌ Tiada `.sql` |
| `C:\Users\PC\Desktop` | ❌ Tiada |
| `D:\backups`, `D:\Backup` | ❌ Tidak wujud |
| Git history `*.sql` | ❌ Tiada commit |

**Dokumentasi rujukan backup:** `frontend/tests/e2e/README.md` mencadangkan `mysqldump -u root --databases cmart_db > backups/cmart_db_backup.sql` tetapi folder `backups/` tidak wujud dalam repo.

---

## 7. Data asal vs data demo

| Sumber | Boleh pulihkan baris asal? |
|--------|---------------------------|
| Migration | ❌ Hanya skema |
| Seeder | ⚠️ Data demo sahaja, bukan data pengeluaran |
| Fail `.ibd` sedia ada | ❓ Mungkin, jika salvage InnoDB berjaya |
| Dump logik | ❌ Tidak ditemui |

**Fakta dari dokumentasi:** Sebelum kerosakan, `cmart_db` dijangka mempunyai ~4 users, 6 events, 2 spaces, 0 bookings (baseline Phase 3). Data manual/E2E mungkin lebih banyak (lihat `docs/phase-2/phase-2a3-local-dummy-booking-cleanup-report.md`).
