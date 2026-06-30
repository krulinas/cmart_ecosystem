# 04 — Payment, Receipt & Event Pass Workflow

> **Bahasa:** Bahasa Melayu / Manglish | **Fokus:** Bayar → verify → receipt PDF → QR pass → check-in

---

## Apa modul ini buat (What this module does)

Selepas booking **Approved**, vendor mesti:

1. **Bayar** tapak (upload bukti bayaran)
2. **Staff verify** payment
3. **Download receipt** (PDF)
4. **Guna event pass** (QR code) untuk check-in hari event

**Penting:** Tiada model `Receipt` atau `Pass` berasingan —
- Receipt = PDF generated dari booking + invoice
- Pass = computed payload dari `VendorEventPassService` (bukan table DB)

---

## Fail penting (Important files)

### Backend

| Fail | Fungsi |
|------|--------|
| `backend/app/Models/Invoice.php` | `payment_status`, `payment_proof_path` |
| `backend/app/Http/Controllers/Api/BookingController.php` | `vendorSubmitPayment`, `verifyBookingPayment`, `generatePdf` |
| `backend/app/Http/Controllers/Api/InvoiceController.php` | Staff manual invoice update |
| `backend/app/Http/Controllers/Api/VendorHistoryController.php` | `historyReceipts` list |
| `backend/app/Http/Controllers/Api/VendorEventPassController.php` | Pass list & detail |
| `backend/app/Http/Controllers/Api/BookingPassVerificationController.php` | Staff verify + check-in |
| `backend/app/Services/VendorEventPassService.php` | Compute pass status, QR rules |
| `backend/resources/views/invoices/booking.blade.php` | PDF template |
| `backend/database/migrations/2026_06_24_000001_add_payment_proof_to_invoices_table.php` | Payment proof columns |

### Frontend

| Fail | Fungsi |
|------|--------|
| `frontend/src/components/VendorPaymentModal.vue` | Upload payment proof |
| `frontend/src/components/VendorHistoryReceipts.vue` | Payment history & receipt links |
| `frontend/src/components/VendorEventPassesPanel.vue` | Pass list |
| `frontend/src/components/VendorPassModal.vue` | Full-screen pass + QR |
| `frontend/src/views/staff/StaffVerifyBooking.vue` | Staff scan QR page |
| `frontend/src/utils/vendorPass.js` | Pass badges, QR URL builder |
| `frontend/src/views/dashboards/staff/StaffBookingsPanel.vue` | Staff "Verify Paid" button |

---

## Payment status values (`invoices.payment_status`)

| Status | Maksud |
|--------|--------|
| `Unpaid` | Default bila booking create |
| `Pending Verification` | Vendor dah upload proof, tunggu staff |
| `Paid` | Staff verify — receipt & pass unlock |
| `Refunded` | Manual override oleh staff |

---

## Pass status values (computed, bukan DB column)

Dari `VendorEventPassService`:

| Status | Maksud |
|--------|--------|
| `pending_approval` | Booking belum approved |
| `approved` | Approved, luar check-in window |
| `checkin_open` | Dalam window check-in |
| `checked_in` | Staff dah check-in |
| `event_active` | Event sedang berjalan |
| `completed` | Event tamat |
| `expired` | Missed check-in window |
| `cancelled` | Booking cancelled/rejected/withdrawn |

### Pass unlock rules

| Flag | Syarat |
|------|--------|
| `show_qr` | `Approved` + `payment_status === Paid` |
| `qr_active` | Above + dalam check-in window + not cancelled/expired/completed |
| `show_booth` | `Approved` (booth label visible even before payment) |

**Check-in window:** event start − 3 jam → event end + 2 jam
- Default time 09:00–17:00 MYT kalau event tiada linked times — **Needs verification** untuk edge cases

**QR encodes URL:** `/staff/verify-booking/{bookingId}` (built in `vendorPass.js`)

---

## Workflow step-by-step

### Langkah 1: Precondition
- `booking.approval_status === Approved`
- `invoice.payment_status === Unpaid`

### Langkah 2: Vendor submit payment proof
1. Vendor buka Payment History di `/dashboard`
2. Klik submit payment → modal upload
3. `POST /api/vendor/bookings/{id}/submit-payment`
   - Multipart file: `payment_proof` (jpg/jpeg/png/webp, max 5MB)
   - File simpan: `storage/app/public/payment-proofs/`
4. Invoice update:
   - `payment_status = Pending Verification`
   - `payment_proof_path` set
   - `payment_submitted_at = now()`

### Langkah 3: Staff verify payment
1. Staff buka `/admin#bookings` → cari booking
2. View payment proof → klik "Verify Paid"
3. `PATCH /api/bookings/{id}/verify-payment`
   - Requires `payment_status === Pending Verification`
   - Set `payment_status = Paid`
4. Response message: *"Vendor receipt and event pass are now available."*

### Langkah 4: Vendor access receipt
1. `GET /api/vendor/history-receipts` — list dengan flags:
   - `invoice_available` — ada invoice (pre-payment)
   - `receipt_available` — invoice exists + `Paid`
2. `GET /api/bookings/{id}/pdf` — download PDF
   - Filename: `carboot-cmart-booking-{id padded}.pdf`
   - Auth: owner (approved vendor) ATAU CMart worker
3. UI label: "View Invoice" sebelum paid → "View Receipt" selepas paid

### Langkah 5: Vendor view event pass
1. `GET /api/vendor/event-passes` — upcoming/archived passes
2. `GET /api/vendor/event-passes/{booking}` — single pass detail
3. Bila paid + approved → QR visible
4. Message kalau belum paid: *"Event pass unlocks after CMart verifies your payment"*

### Langkah 6: Staff check-in (hari event)
1. Staff scan QR → navigate `/staff/verify-booking/:bookingId`
2. `GET /api/staff/bookings/{id}/verify` — load pass verification data
3. Staff confirm → `POST /api/staff/bookings/{id}/check-in`
4. Set `bookings.checked_in_at`

### Manual override (staff)
- `PUT/PATCH /api/invoices/{invoice}` — boleh set `payment_status` manually
- Untuk edge cases / refund

---

## Access control / permission notes

| Action | Siapa |
|--------|-------|
| Submit payment proof | Vendor owner, booking approved |
| Verify payment | CMart worker (staff+) |
| View PDF | Vendor owner atau CMart worker |
| View passes | Vendor owner (`role:community`) |
| Staff verify/check-in | CMart worker |
| Manual invoice update | CMart worker |

---

## Apa nak cakap kalau lecturer tanya

> "Payment flow kami guna invoice 1:1 dengan booking. Vendor upload proof selepas approval, status jadi Pending Verification. Staff verify manually — tak ada payment gateway integration lagi. Bila Paid, PDF receipt generate dari Blade template, dan event pass QR unlock. Pass bukan database record — ia computed service yang check approval, payment, event timing, dan check-in window. QR point ke staff verification page untuk check-in pada hari event."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Submit payment before approve | API should block — verify dalam controller |
| Duplicate payment submit | E2E Test 7E check |
| QR active outside window | Pass shows but scan fails — by design |
| Storage link missing | Payment proof images 404 kalau `php artisan storage:link` tak run |
| Large file upload | Max 5MB — user perlu compress image |
| Timezone edge cases | Check-in window guna MYT — **Needs verification** untuk server timezone config |

---

## Macam mana verify ia berfungsi

### Manual
1. Approve booking (lihat doc 03)
2. Vendor → dashboard → submit payment proof (guna screenshot/png)
3. Staff → verify paid
4. Vendor → refresh → receipt available + pass QR visible
5. Staff → buka verify URL → check-in

### E2E
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/vendor.payment-submit.spec.js
npm run test:e2e:headless -- --spec specs/vendor.receipt-pass-after-paid.spec.js
npm run test:e2e:headless -- --spec specs/vendor.payment-verification-pass-unlock.spec.js
```

### Storage setup
```bash
cd backend
php artisan storage:link
```

---

## Diagram end-to-end

```
Approved + Unpaid
       │
       ▼
[Vendor upload proof] ──► Pending Verification
       │
       ▼
[Staff Verify Paid] ──► Paid
       │
       ├──► PDF Receipt (GET /bookings/{id}/pdf)
       │
       └──► Event Pass QR unlock
                 │
                 ▼ (hari event, dalam window)
            [Staff scan QR]
                 │
                 ▼
            checked_in_at set
```
