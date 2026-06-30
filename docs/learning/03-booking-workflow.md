# 03 — Booking Workflow

> **Bahasa:** Bahasa Melayu / Manglish | **Fokus:** Dari vendor submit sampai approve/reject/withdraw

---

## Apa modul ini buat (What this module does)

Modul booking mengurus **permohonan tapak jualan** vendor di carboot event. Setiap booking:

- Link ke **user** (vendor), **space** (saiz tapak), **carboot_event** (tarikh event)
- Ada **approval_status** — two-tier pipeline (staff → manager)
- Auto-create **invoice** (RM 20 × tapak quantity) bila booking dibuat
- Ada audit trail dalam `booking_audit_logs`

---

## Fail penting (Important files)

### Backend

| Fail | Fungsi |
|------|--------|
| `backend/app/Models/Booking.php` | Model booking |
| `backend/app/Models/Invoice.php` | Invoice 1:1 dengan booking |
| `backend/app/Models/BookingAuditLog.php` | Log perubahan status |
| `backend/app/Models/UserBookingPreference.php` | Simpan preferensi form vendor |
| `backend/app/Http/Controllers/Api/BookingController.php` | CRUD + approval + withdraw + payment |
| `backend/app/Http/Controllers/Api/UserBookingPreferenceController.php` | Preferensi booking |
| `backend/app/Services/BookingAuditLogger.php` | Tulis audit log |
| `backend/app/Services/VendorBookingPresenter.php` | Format response vendor (booth number, etc.) |
| `backend/database/migrations/2026_05_09_052330_create_bookings_table.php` | Schema asal |
| `backend/database/migrations/2026_06_14_000003_add_cancelled_status_and_vendor_requests_to_bookings.php` | Cancelled + vendor requests |
| `backend/database/migrations/2026_06_21_000003_add_withdrawal_fields_to_bookings_table.php` | Withdrawn status |

### Frontend

| Fail | Fungsi |
|------|--------|
| `frontend/src/views/auth/Registration.vue` | Form submit booking baru |
| `frontend/src/views/dashboards/VendorDashboard.vue` | Senarai booking vendor |
| `frontend/src/components/VendorBookingDetailsModal.vue` | Detail, edit, resubmit, withdraw |
| `frontend/src/components/WithdrawBookingModal.vue` | Confirm withdraw |
| `frontend/src/views/dashboards/staff/StaffBookingsPanel.vue` | Staff/manager registry |
| `frontend/src/utils/bookingDisplay.js` | Label status, pipeline steps |
| `frontend/src/composables/useManagementAccess.js` | Staff vs manager API endpoints |

---

## Status values (`approval_status`)

| Status | Maksud |
|--------|--------|
| `Pending_Staff` | Baru submit, tunggu staff |
| `Pending_Boss` | Staff dah forward, tunggu manager |
| `Needs_Revision` | Staff/manager minta vendor betulkan |
| `Approved` | Lulus — vendor boleh bayar |
| `Rejected` | Ditolak — terminal |
| `Cancelled` | Dibatalkan (legacy cancel flow) — terminal |
| `Withdrawn` | Vendor tarik balik — terminal |

**Terminal statuses** (`Rejected`, `Cancelled`, `Withdrawn`) — tak boleh masuk pipeline semula (API return 422).

---

## Workflow step-by-step

### Fasa 1: Vendor create booking

1. Vendor login → pergi `/vendor-booking`
2. Isi form: event, kategori produk, butiran, bilangan tapak
3. Submit → `POST /api/bookings`
4. System create:
   - `Booking` dengan `approval_status = Pending_Staff`
   - `Invoice` dengan `payment_status = Unpaid`, `amount = tapak × RM 20`
5. Audit log direkod

### Fasa 2: Staff review (Tier 1)

Staff buka `/admin#bookings` → queue `Pending_Staff`

| Action | API | Status baru |
|--------|-----|-------------|
| Forward ke manager | `PATCH /api/bookings/{id}` | `Pending_Boss` |
| Minta revision | `PATCH /api/bookings/{id}` | `Needs_Revision` |
| Reject | `PATCH /api/bookings/{id}` | `Rejected` |

Staff guna endpoint `GET /api/staff/bookings` (registry view).

### Fasa 3: Manager review (Tier 2)

Manager lihat queue `Pending_Boss`

| Action | API | Status baru |
|--------|-----|-------------|
| Approve | `PATCH /api/bookings/{id}` | `Approved` |
| Minta revision | `PATCH /api/bookings/{id}` | `Needs_Revision` |
| Reject | `PATCH /api/bookings/{id}` | `Rejected` |

Manager guna endpoint `GET /api/bookings` (full list).

**State transitions** ditakrif dalam `BookingController::STATE_TRANSITIONS` — ikut `workflowRoleKey()`:
- Staff role key → `staff`
- Manager / super_admin role key → `manager`

### Fasa 4: Revision loop

Bila status `Needs_Revision`:
1. Vendor edit → `PATCH /api/vendor/bookings/{id}` (hanya bila `Pending_Staff` atau `Needs_Revision`)
2. Vendor resubmit → `PATCH /api/vendor/bookings/{id}/resubmit` → balik `Pending_Staff`

### Fasa 5: Post-approval requests (optional)

Bila status `Approved`, vendor boleh:
- `POST /api/vendor/bookings/{id}/request-change` — minta ubah butiran
- `POST /api/vendor/bookings/{id}/request-cancellation` — minta batal

Ini set `vendor_request_type` dan `vendor_request_note` — staff review manual dalam panel.

### Fasa 6: Withdraw / Cancel

**Withdraw** (recommended flow):
- `PATCH /api/bookings/{id}/withdraw`
- Hanya bila: `Pending_Staff`, `Pending_Boss`, atau `Needs_Revision`
- **Tidak boleh** kalau payment dah `Paid`
- Set `withdrawn_at`, `withdrawal_reason`, `withdrawn_by`

**Legacy cancel**:
- `POST /api/vendor/bookings/{id}/cancel` → `Cancelled` (pending statuses sahaja)

### Fasa 7: Manager delete (hard delete)

- `DELETE /api/bookings/{id}` — middleware `boss` sahaja
- Buang record dari database

### Booth number (computed)

Bila `Approved`, system generate booth label: `{A|B|C}-{booking_id padded}`
- Logic dalam `VendorBookingPresenter::boothNumber()`
- **Needs verification** — exact mapping A/B/C ikut space size

---

## Access control / permission notes

| Action | Siapa |
|--------|-------|
| Create booking | `vendor.approved` (community + approved) |
| View own bookings | Vendor owner |
| Edit/resubmit own | Vendor owner, status allow |
| Withdraw own | Vendor owner, status allow, not paid |
| Staff review tier 1 | staff, manager, super_admin (+ legacy) |
| Manager approve tier 2 | manager, super_admin |
| Delete booking | manager, super_admin (`boss` middleware) |
| View PDF | Owner atau CMart worker |

---

## Apa nak cakap kalau lecturer tanya

> "Booking workflow kami guna finite state machine dengan two-tier approval. Vendor submit masuk `Pending_Staff`. Staff boleh forward ke `Pending_Boss`, minta revision, atau reject. Manager buat final decision — approve, revise, atau reject. Terminal states tak boleh re-enter pipeline. Setiap transition direkod dalam `booking_audit_logs` untuk audit trail. Invoice auto-create bila booking dibuat supaya payment flow boleh start selepas approval."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Staff cuba approve final | API block — hanya manager boleh set `Approved` |
| Edit selepas forward | Vendor edit limited kepada `Pending_Staff` / `Needs_Revision` |
| Withdraw bila dah bayar | Blocked — elak refund complexity |
| Duplicate booking | E2E Test 7E check destructive guards |
| Stale event dates | Booking form takde event available kalau DB events expired |
| `super_admin` confusion | Workflow treat sebagai manager, bukan staff |

---

## Macam mana verify ia berfungsi

### Manual flow
1. Login vendor → `/vendor-booking` → submit
2. Login staff → `/admin#bookings` → forward booking
3. Login manager → approve
4. Login vendor → `/dashboard` → status `Approved`

### E2E (full pipeline)
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/vendor.booking.spec.js
npm run test:e2e:headless -- --spec specs/staff.booking-forward.spec.js
npm run test:e2e:headless -- --spec specs/manager.booking-approval.spec.js
npm run test:e2e:headless -- --spec specs/vendor.booking-approved.spec.js
```

### API check
```bash
# Vendor create (perlu token + approved vendor)
curl -X POST -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"carboot_event_id":1,"tapak_quantity":1,...}' \
  http://127.0.0.1:8000/api/bookings
```

---

## Diagram pipeline

```
[Vendor Submit]
      │
      ▼
 Pending_Staff ──staff forward──► Pending_Boss ──manager approve──► Approved
      │                │                    │
      │                │                    ├──► Needs_Revision ──resubmit──┐
      │                │                    │                               │
      ├──► Needs_Revision                   ├──► Rejected (terminal)       │
      ├──► Rejected                         │                               │
      └──► Withdrawn                        └──► (vendor payment next)     │
                                                                            │
      ◄─────────────────────────────────────────────────────────────────────┘
```

Lihat `04-payment-receipt-pass-workflow.md` untuk langkah selepas `Approved`.
