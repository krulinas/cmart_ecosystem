# 07 — API Route Map

> **Bahasa:** Bahasa Melayu / Manglish | **Base URL:** `http://127.0.0.1:8000/api` (local)

Semua route dalam `backend/routes/api.php`, loaded oleh `RouteServiceProvider` dengan prefix `/api`.

**Auth header untuk protected routes:**
```
Authorization: Bearer <sanctum_token>
```

---

## Apa modul ini buat (What this module does)

Dokumen ini ialah **peta semua REST API endpoint** yang Vue frontend consume. Setiap route ada middleware guard yang tentukan siapa boleh call.

---

## Fail penting (Important files)

| Fail | Fungsi |
|------|--------|
| `backend/routes/api.php` | Semua API routes |
| `backend/routes/web.php` | Web routes (bukan SPA API utama) |
| `backend/app/Providers/RouteServiceProvider.php` | Load api.php dengan prefix `api` |
| `backend/app/Http/Kernel.php` | Middleware aliases |

### Web routes tambahan (bukan api.php)

| Method | Path | Nota |
|--------|------|------|
| GET | `/` | Welcome page |
| GET | `/admin/analytics` | Analytics page |
| GET | `/api/proxy/analytics/*` | Analytics proxy — **tiada role middleware** |

---

## Authentication & Session

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| POST | `/auth/register` | Public | `AuthController@register` |
| POST | `/auth/login` | Public | `AuthController@login` |
| GET | `/auth/google` | Public | Google OAuth redirect |
| GET | `/auth/google/callback` | Public | Google OAuth callback |
| GET | `/auth/me` | Sanctum | Current user |
| POST | `/auth/logout` | Sanctum | Logout |

---

## Spaces (Tapak sewa)

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| GET | `/spaces` | Public | List spaces |
| GET | `/spaces/{space}` | Public | Show space |
| POST | `/spaces` | Sanctum + `boss` | Create |
| PUT/PATCH | `/spaces/{space}` | Sanctum + `boss` | Update |
| DELETE | `/spaces/{space}` | Sanctum + `boss` | Delete |

---

## Community Feedback

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| GET | `/feedbacks` | Public | Public feedback list |
| POST | `/feedback/{id}/helpful` | Public | Mark helpful |
| POST | `/feedback/submit` | Sanctum + throttle 10/min | Submit feedback |
| GET | `/staff/feedbacks` | CMart worker | Staff feedback list |
| GET | `/feedbacks/{feedback}` | CMart worker | Show |
| PUT/PATCH | `/feedbacks/{feedback}` | CMart worker | Update |
| DELETE | `/feedbacks/{feedback}` | CMart worker | Delete |

---

## Carboot Events

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| GET | `/events` | Public | Public event list |
| GET | `/events/{carboot_event}` | Public | Public event detail |
| POST | `/events/{carboot_event}/register` | Sanctum | Community RSVP |
| GET | `/carboot-events` | CMart worker | Staff event list |
| POST | `/carboot-events` | CMart worker | Create event |
| GET | `/carboot-events/{id}` | CMart worker | Show |
| PUT/PATCH | `/carboot-events/{id}` | CMart worker | Update |
| DELETE | `/carboot-events/{id}` | CMart worker | Delete |

---

## News

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| GET | `/news` | Public | Public news list |
| GET | `/news-posts` | CMart worker | Staff news list |
| POST | `/news-posts` | CMart worker | Create |
| GET | `/news-posts/{id}` | CMart worker | Show |
| PUT/PATCH | `/news-posts/{id}` | CMart worker | Update |
| DELETE | `/news-posts/{id}` | CMart worker | Delete |

---

## Marketplace (Reuse Items — Public)

| Method | Path | Auth | Controller |
|--------|------|------|------------|
| GET | `/marketplace/items` | Public | Browse items |
| GET | `/marketplace/items/{vendor_item}` | Public | Item detail |

---

## Vendor Profile & Items (`role:community`)

| Method | Path | Controller |
|--------|------|------------|
| GET | `/vendor/analytics/me` | Analytics summary |
| GET | `/vendor/analytics/report` | Analytics report |
| GET | `/vendor/history-receipts` | Payment history |
| GET/PATCH | `/vendor/profile` | Personal profile |
| GET/PUT | `/vendor/business-profile` | Business profile |
| POST | `/vendor/business-profile/logo` | Upload logo |
| GET | `/vendor/event-passes` | Pass list |
| GET | `/vendor/event-passes/{booking}` | Pass detail |
| GET/POST | `/vendor/items` | List/create items |
| GET/PUT/DELETE | `/vendor/items/{vendor_item}` | Item CRUD |

---

## Vendor Bookings & Payments (`vendor.approved`)

| Method | Path | Controller |
|--------|------|------------|
| POST | `/bookings` | Create booking |
| GET | `/vendor/bookings` | My bookings |
| GET | `/vendor/bookings/{booking}` | Booking detail |
| PATCH | `/vendor/bookings/{booking}` | Vendor edit |
| PATCH | `/vendor/bookings/{booking}/resubmit` | Resubmit after revision |
| POST | `/vendor/bookings/{booking}/cancel` | Cancel (legacy) |
| PATCH | `/bookings/{booking}/withdraw` | Withdraw |
| POST | `/vendor/bookings/{booking}/request-change` | Post-approval change request |
| POST | `/vendor/bookings/{booking}/request-cancellation` | Post-approval cancel request |
| POST | `/vendor/bookings/{booking}/submit-payment` | Upload payment proof |

---

## Booking Preferences (`auth:sanctum`)

| Method | Path | Controller |
|--------|------|------------|
| GET | `/booking-preferences/me` | Get saved prefs |
| PUT | `/booking-preferences/me` | Update prefs |
| DELETE | `/booking-preferences/me` | Clear prefs |

---

## Staff / Management (`role: staff, manager, super_admin, + legacy`)

| Method | Path | Controller |
|--------|------|------------|
| GET | `/staff/bookings` | Staff registry |
| GET | `/staff/bookings/{booking}/verify` | Pass verification data |
| POST | `/staff/bookings/{booking}/check-in` | Check-in vendor |
| GET | `/bookings` | Full booking list |
| GET | `/bookings/{booking}` | Booking detail |
| PUT/PATCH | `/bookings/{booking}` | Approval transitions |
| PATCH | `/bookings/{booking}/verify-payment` | Verify payment |
| GET | `/bookings/{booking}/pdf` | PDF receipt/invoice |
| GET | `/invoices` | Invoice list |
| GET | `/invoices/{invoice}` | Invoice detail |
| PUT/PATCH | `/invoices/{invoice}` | Update invoice |

---

## Boss / Manager-only (`boss` middleware)

| Method | Path | Controller |
|--------|------|------------|
| DELETE | `/bookings/{booking}` | Hard delete booking |
| POST | `/profitability` | Profitability check |
| GET | `/boss/analytics/revenue` | Revenue analytics |
| GET | `/boss/analytics/wordcloud/{source}` | Word cloud |
| GET | `/boss/audit-logs` | Audit logs |
| POST/PUT/PATCH/DELETE | `/spaces` | Space management |

**`boss` middleware** = `manager`, `super_admin`, legacy `cmart_admin`, `boss`

---

## Access control / permission notes

### Middleware shorthand

| Shorthand | Maksud |
|-----------|--------|
| Public | Tiada token |
| Sanctum | Mana-mana user login |
| `role:community` | Vendor role |
| `vendor.approved` | community + vendor_status approved |
| CMart worker | staff, manager, super_admin (+ legacy aliases) |
| `boss` | manager atau super_admin sahaja |

### Tiada API untuk role `uum`
Frontend ada `/uum` page tapi **Needs verification** — tiada dedicated routes dalam `api.php`.

---

## Apa nak cakap kalau lecturer tanya

> "API kami centralized dalam satu file `api.php` dengan route groups ikut middleware. Public routes untuk marketplace, events, news. Authenticated routes guna Sanctum bearer token. Vendor booking routes ada extra `vendor.approved` gate. Management routes grouped under CMart worker role, dengan subset `boss` untuk manager-only actions macam delete booking dan analytics. RESTful pattern dengan resource controllers untuk invoices, feedbacks, events, news."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Missing token | 401 Unauthenticated |
| Wrong role | 403 Forbidden |
| CORS issues | Frontend dev server mesti configure proxy — check `vite.config` |
| Rate limit feedback | `throttle:10,1` on submit |
| IDOR | Controllers check `user_id` ownership untuk vendor routes |

---

## Macam mana verify ia berfungsi

```bash
# Public — patut 200
curl http://127.0.0.1:8000/api/events

# Protected — patut 401 tanpa token
curl http://127.0.0.1:8000/api/bookings

# Login dulu
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"vendor@cmart.com","password":"password"}'

# Guna token
curl -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/vendor/bookings
```

E2E preflight juga verify API reachability: `frontend/tests/e2e/helpers/preflight.js`

---

## Controller index

| Controller | Path |
|------------|------|
| AuthController | `backend/app/Http/Controllers/Api/AuthController.php` |
| BookingController | `backend/app/Http/Controllers/Api/BookingController.php` |
| InvoiceController | `backend/app/Http/Controllers/Api/InvoiceController.php` |
| FeedbackController | `backend/app/Http/Controllers/Api/FeedbackController.php` |
| CarbootEventController | `backend/app/Http/Controllers/Api/CarbootEventController.php` |
| EventRegistrationController | `backend/app/Http/Controllers/Api/EventRegistrationController.php` |
| NewsPostController | `backend/app/Http/Controllers/Api/NewsPostController.php` |
| SpaceController | `backend/app/Http/Controllers/Api/SpaceController.php` |
| VendorProfileController | `backend/app/Http/Controllers/Api/VendorProfileController.php` |
| VendorBusinessProfileController | `backend/app/Http/Controllers/Api/VendorBusinessProfileController.php` |
| VendorItemController | `backend/app/Http/Controllers/Api/VendorItemController.php` |
| VendorEventPassController | `backend/app/Http/Controllers/Api/VendorEventPassController.php` |
| VendorHistoryController | `backend/app/Http/Controllers/Api/VendorHistoryController.php` |
| VendorAnalyticsController | `backend/app/Http/Controllers/Api/VendorAnalyticsController.php` |
| BookingPassVerificationController | `backend/app/Http/Controllers/Api/BookingPassVerificationController.php` |
| BossAnalyticsController | `backend/app/Http/Controllers/Api/BossAnalyticsController.php` |
| AuditLogController | `backend/app/Http/Controllers/Api/AuditLogController.php` |
| MarketplaceController | `backend/app/Http/Controllers/Api/MarketplaceController.php` |
| UserBookingPreferenceController | `backend/app/Http/Controllers/Api/UserBookingPreferenceController.php` |
