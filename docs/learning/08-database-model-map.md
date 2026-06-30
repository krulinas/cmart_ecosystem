# 08 — Database Model Map

> **Bahasa:** Bahasa Melayu / Manglish | **ORM:** Laravel Eloquent

---

## Apa modul ini buat (What this module does)

Dokumen ini map **semua database tables** melalui Eloquent models dan relationships mereka. Models ada dalam `backend/app/Models/`.

Database: **MySQL** (XAMPP local: `127.0.0.1:3306`)

---

## Fail penting (Important files)

| Fail | Fungsi |
|------|--------|
| `backend/app/Models/*.php` | 15 Eloquent models |
| `backend/database/migrations/` | 36 migration files |
| `backend/database/seeders/DatabaseSeeder.php` | Demo data |
| `backend/database/factories/` | Test factories (jika ada) |

---

## Model relationship map

### User (pusat sistem)

**Table:** `users`  
**Model:** `backend/app/Models/User.php`

| Column penting | Type/Values |
|----------------|-------------|
| `role` | `community`, `staff`, `manager`, `super_admin`, `uum` |
| `vendor_status` | `none`, `pending`, `approved`, `suspended` |

**Relationships:**
```
User
 ├── hasMany → Booking
 ├── hasMany → Feedback
 ├── hasMany → VendorItem
 ├── hasOne  → VendorBusinessProfile
 ├── hasOne  → ManagementProfile
 ├── hasOne  → UserBookingPreference
 └── belongsToMany → CarbootEvent (pivot: event_user, registered_at)
```

---

### Booking (core workflow)

**Table:** `bookings`  
**Model:** `backend/app/Models/Booking.php`

| Column penting | Values/Notes |
|----------------|--------------|
| `approval_status` | Pending_Staff, Pending_Boss, Needs_Revision, Approved, Rejected, Cancelled, Withdrawn |
| `product_category` | Kategori jualan |
| `product_details` | Butiran produk |
| `vendor_request_type` | Post-approval request |
| `vendor_request_note` | Nota request |
| `checked_in_at` | Check-in timestamp |
| `withdrawn_at`, `withdrawal_reason`, `withdrawn_by` | Withdrawal data |

**Relationships:**
```
Booking
 ├── belongsTo → User
 ├── belongsTo → Space
 ├── belongsTo → CarbootEvent (carboot_event_id)
 ├── belongsTo → User (withdrawn_by)
 ├── hasOne    → Invoice
 └── hasMany   → BookingAuditLog
```

**Migrations utama:**
- `2026_05_09_052330_create_bookings_table.php`
- `2026_06_14_000003_add_cancelled_status_and_vendor_requests_to_bookings.php`
- `2026_06_21_000003_add_withdrawal_fields_to_bookings_table.php`
- `2026_06_18_000002_add_checked_in_at_to_bookings_table.php`
- `2026_06_21_000001_add_carboot_event_id_to_bookings_table.php`

---

### Invoice (payment)

**Table:** `invoices`  
**Model:** `backend/app/Models/Invoice.php`

| Column penting | Values |
|----------------|--------|
| `payment_status` | Unpaid, Pending Verification, Paid, Refunded |
| `amount` | RM (tapak × 20) |
| `payment_proof_path` | File path |
| `payment_submitted_at` | Upload timestamp |

**Relationships:**
```
Invoice
 └── belongsTo → Booking
```

---

### Space (tapak)

**Table:** `spaces`  
**Model:** `backend/app/Models/Space.php`

| Column | Values |
|--------|--------|
| `space_size` | Saiz tapak |
| `price` | Harga |
| `status` | Available, Full |

**Relationships:**
```
Space
 └── hasMany → Booking
```

---

### CarbootEvent

**Table:** `carboot_events`  
**Model:** `backend/app/Models/CarbootEvent.php`

| Column | Notes |
|--------|-------|
| `starts_at`, `ends_at` | Event timing |
| `status` | Open, Closed, etc. |
| `max_slots` | Slot limit |

**Relationships:**
```
CarbootEvent
 ├── hasMany → EventImage (images)
 ├── hasOne  → EventImage (primaryImage)
 ├── hasMany → Booking
 └── belongsToMany → User (registeredUsers via event_user)
```

---

### EventImage & NewsImage

| Model | Table | Parent |
|-------|-------|--------|
| EventImage | `event_images` | CarbootEvent |
| NewsImage | `news_images` | NewsPost |

Columns: `image_path`, `sort_order`, `is_primary`

---

### NewsPost

**Table:** `news_posts`  
**Model:** `backend/app/Models/NewsPost.php`

**Relationships:**
```
NewsPost
 ├── belongsTo → User (author_id)
 ├── hasMany   → NewsImage
 └── hasOne    → NewsImage (primaryImage)
```

---

### VendorItem (reuse marketplace)

**Table:** `vendor_items`  
**Model:** `backend/app/Models/VendorItem.php`

| Column | Notes |
|--------|-------|
| `pricing_type` | Pricing model |
| `condition` | Item condition |
| `status` | Listing status |

**Relationships:**
```
VendorItem
 ├── belongsTo → User
 ├── hasMany   → ReuseItemImage
 └── hasOne    → ReuseItemImage (primaryImage)
```

---

### VendorBusinessProfile

**Table:** `vendor_business_profiles`  
**Model:** `backend/app/Models/VendorBusinessProfile.php`

One-to-one dengan User. Columns: `business_name`, `business_phone`, `business_category`, `logo_path`

---

### ManagementProfile

**Table:** `management_profiles`  
**Model:** `backend/app/Models/ManagementProfile.php`

One-to-one dengan CMart workers. Columns: `staff_code`, `tier`, `position_title`, `department`, `branch_name`

---

### UserBookingPreference

**Table:** `user_booking_preferences`  
**Model:** `backend/app/Models/UserBookingPreference.php`

Simpan form defaults vendor: `product_category`, `tapak_count`, `remember_enabled`

---

### BookingAuditLog

**Table:** `booking_audit_logs`  
**Model:** `backend/app/Models/BookingAuditLog.php`

| Column | Notes |
|--------|-------|
| `action` | Action type |
| `from_status`, `to_status` | Status transition |
| `actor_user_id` | Who did it |
| `ip_address` | Audit metadata |

---

### Feedback

**Table:** `feedbacks`  
**Model:** `backend/app/Models/Feedback.php`

**Relationships:**
```
Feedback
 └── belongsTo → User (nullable)
```

Columns include: `rating`, `comments`, `is_hidden`, community feedback extensions

---

### Pivot table (no model)

**Table:** `event_user`
- `carboot_event_id`, `user_id`, `registered_at`
- Unique pair constraint

---

## Workflow step-by-step (data flow)

### Booking create
```
INSERT users (existing)
  → INSERT bookings (Pending_Staff)
    → INSERT invoices (Unpaid)
      → INSERT booking_audit_logs
```

### Approval transition
```
UPDATE bookings.approval_status
  → INSERT booking_audit_logs
```

### Payment
```
UPDATE invoices (proof path, Pending Verification)
  → UPDATE invoices (Paid) by staff
```

### Check-in
```
UPDATE bookings.checked_in_at
```

---

## Access control / permission notes

- Models sendiri **tiada** authorization — logic dalam controllers/middleware
- Foreign keys enforce referential integrity
- `user_id` pada booking/items = ownership boundary untuk vendor

---

## Apa nak cakap kalau lecturer tanya

> "Database design kami center around User dan Booking. Setiap booking ada one-to-one Invoice untuk payment tracking. BookingAuditLog provide audit trail untuk compliance. Vendor features extend User dengan VendorBusinessProfile dan VendorItem. Events dan News ada image tables untuk multi-image support. Tiada separate Pass atau Receipt table — pass computed dari booking state, receipt generated as PDF on demand."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Orphan invoices | Booking delete (manager) — check cascade behavior **Needs verification** |
| Migration order | Must run `php artisan migrate` in order |
| Enum mismatch | Old DB may have legacy role values — migration `standardize_management_roles` fixes |
| Stale seeded events | `ends_at` in past breaks E2E |
| Nullable feedback user_id | Anonymous feedback allowed |

---

## Macam mana verify ia berfungsi

```bash
cd backend

# Run migrations
php artisan migrate

# Seed demo data
php artisan db:seed

# Inspect tables
php artisan tinker
>>> \App\Models\Booking::with('invoice','user')->first()
>>> \App\Models\User::where('role','staff')->first()
```

Check migration status:
```bash
php artisan migrate:status
```

---

## ER diagram (ringkas)

```
┌─────────┐     ┌──────────┐     ┌─────────┐
│  User   │────<│ Booking  │>────│  Space  │
└────┬────┘     └────┬─────┘     └─────────┘
     │               │
     │          ┌────▼─────┐     ┌──────────────┐
     │          │ Invoice  │     │ CarbootEvent │
     │          └──────────┘     └──────┬───────┘
     │                                  │
     ├──── VendorBusinessProfile        ├──── EventImage
     ├──── VendorItem ── ReuseItemImage │
     ├──── ManagementProfile            └──── Booking (FK)
     ├──── UserBookingPreference
     ├──── Feedback
     └──── event_user (pivot) ────────── CarbootEvent

Booking ──< BookingAuditLog
```
