# 06 — Management Dashboard Workflow

> **Bahasa:** Bahasa Melayu / Manglish | **Fokus:** Portal staff/manager di `/admin`

---

## Apa modul ini buat (What this module does)

**Management Dashboard** ialah back-office CMart untuk staff dan manager. Satu route `/admin` dengan **hash-based sections** — bukan nested Vue routes.

Staff dan manager share shell yang sama (`AdminDashboard.vue` + `WorkspaceShell.vue`), tapi:
- **Staff** — tier 1 operations (booking queue, feedback, events, news)
- **Manager** — tier 2 + insights (revenue, word cloud, audit log)
- **Super Admin** — same as manager untuk workflow, label "HQ Admin" dalam UI

Route verify booking (standalone): `/staff/verify-booking/:bookingId`

---

## Fail penting (Important files)

### Shell & orchestration

| Fail | Fungsi |
|------|--------|
| `frontend/src/views/dashboards/AdminDashboard.vue` | Section switcher, lazy load panels |
| `frontend/src/layouts/WorkspaceShell.vue` | Sidebar, mobile nav, workspace chrome |
| `frontend/src/config/workspaceNav.js` | Nav items & manager-only hashes |
| `frontend/src/composables/useWorkspaceNav.js` | Build `/admin#<hash>` links |
| `frontend/src/composables/useManagementAccess.js` | Role-aware permissions |
| `frontend/src/composables/useSectionCache.js` | Per-section load cache |
| `frontend/src/stores/bossPreview.js` | Manager "view as staff" toggle |
| `frontend/src/config/managementWorkspaceTheme.js` | Tier themes |

### Staff panels (`/admin#...`)

| Hash | Panel file | Fungsi |
|------|------------|--------|
| `#bookings` | `staff/StaffBookingsPanel.vue` | Booking registry, approve, verify payment |
| `#feedback` | `staff/StaffFeedbackPanel.vue` | Moderate community feedback |
| `#events` | `staff/StaffEventsPanel.vue` | CRUD carboot events |
| `#news` | `staff/StaffNewsPanel.vue` | CRUD news posts |
| `#tools` | `staff/StaffToolsPanel.vue` | Workspace utilities |

### Manager-only panels

| Hash | Panel file | Fungsi |
|------|------------|--------|
| `#revenue` | `boss/BossRevenuePanel.vue` | Revenue charts (Chart.js) |
| `#analytics` | `boss/BossWordCloudPanel.vue` | Text analytics word cloud |
| `#audit` | `boss/BossAuditLogsPanel.vue` | Booking audit trail |

### Shared management components

| Component | Fungsi |
|-----------|--------|
| `management/ManagementSectionLoader.vue` | Loading state |
| `management/ManagementEmptyState.vue` | Empty data UI |
| `management/ManagementKpiCard.vue` | KPI cards |
| `management/ManagementStatusChip.vue` | Status badges |

### Staff verify page

| Fail | Fungsi |
|------|--------|
| `frontend/src/views/staff/StaffVerifyBooking.vue` | QR scan result + check-in |

---

## Workflow step-by-step

### 1. Login & land di admin
```
Staff/Manager login → auth.homeForUser() → /admin
Default section: #bookings (kalau hash invalid atau blocked)
WorkspaceShell render sidebar dari workspaceNav.js
```

### 2. Bookings panel (core workflow)
```
StaffBookingsPanel load:
  - Staff: GET /api/staff/bookings (registry, read-focused)
  - Manager: GET /api/bookings (full list)

Actions:
  - Forward / Revise / Reject (staff tier)
  - Approve / Revise / Reject (manager tier)
  - View payment proof
  - Verify Paid → PATCH /api/bookings/{id}/verify-payment
  - Delete booking (manager only)
```

### 3. Feedback panel
```
GET /api/staff/feedbacks
Moderate: hide/show, update, delete feedback
Public feedback visible di /community
```

### 4. Events panel
```
CRUD /api/carboot-events
Upload images via MultiImageUploadField
Events appear di public /calendar dan /api/events
```

### 5. News panel
```
CRUD /api/news-posts
Publish announcements → public /api/news
```

### 6. Manager insights (manager+ only)
```
#revenue → GET /api/boss/analytics/revenue
#analytics → GET /api/boss/analytics/wordcloud/{source}
#audit → GET /api/boss/audit-logs
```

### 7. Tools panel
```
Operational shortcuts — Tier 1 staff see `StaffOperationalSnapshot`; managers/HQ still see `ImpactDashboard` on the tools panel.
```

### 8. Boss preview mode
```
Manager toggle "View as Staff" (bossPreview store)
  - Hides manager-only nav
  - Simulates tier 1 experience
Router still blocks #revenue etc. untuk actual staff users
```

### 9. QR verify (hari event)
```
Staff scan vendor QR → /staff/verify-booking/:id
GET /api/staff/bookings/{id}/verify
POST /api/staff/bookings/{id}/check-in
```

---

## Access control / permission notes

### Section access matrix

| Section | Staff | Manager | Super Admin |
|---------|-------|---------|-------------|
| #bookings | ✓ | ✓ | ✓ |
| #feedback | ✓ | ✓ | ✓ |
| #events | ✓ | ✓ | ✓ |
| #news | ✓ | ✓ | ✓ |
| #tools | ✓ | ✓ | ✓ |
| #revenue | ✗ | ✓ | ✓ |
| #analytics | ✗ | ✓ | ✓ |
| #audit | ✗ | ✓ | ✓ |

### API differences staff vs manager

| Action | Staff endpoint | Manager endpoint |
|--------|----------------|------------------|
| List bookings | `/staff/bookings` | `/bookings` |
| Update approval | `/bookings/{id}` (limited transitions) | `/bookings/{id}` (can approve) |
| Delete booking | ✗ | `DELETE /bookings/{id}` |
| Boss analytics | ✗ | `/boss/analytics/*` |
| Manage spaces | ✗ | `POST/PUT/DELETE /spaces` |

Frontend guard: `MANAGER_ONLY_HASHES` dalam router `beforeEach`

---

## Apa nak cakap kalau lecturer tanya

> "Management portal guna single route dengan hash navigation — macam SPA tab system. Ini elak complex nested routing tapi masih bagi deep-linkable sections. Staff handle tier 1 booking queue dan content moderation. Manager ada additional insights panels untuk revenue, text analytics, dan audit logs. Frontend ada dual guard: router hash check dan composable useManagementAccess untuk hide actions staff tak boleh buat, walaupun API juga enforce yang sama."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Staff cuba approve final | UI button should hide; API returns error |
| Hash bookmark `#revenue` as staff | Router redirect to `#bookings` |
| Section cache stale | useSectionCache mungkin show old data — refresh behavior **Needs verification** |
| Manager preview confusion | Preview mode UI berbeza tapi API calls still as manager |
| Event image upload | MultiImageUploadField dependency on storage |
| Full suite timeout | E2E manager approve boleh timeout 300s kalau server lambat |

---

## Macam mana verify ia berfungsi

### Manual
1. Login `staff@cmart.com` → `/admin` → verify 5 sections visible (no revenue)
2. Login `admin@cmart.com` → verify 8 sections visible
3. Staff forward booking → manager approve
4. Staff verify payment
5. Create event di #events → check `/calendar`

### E2E
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/auth.staff-login.spec.js
npm run test:e2e:headless -- --spec specs/auth.manager-login.spec.js
npm run test:e2e:headless -- --spec specs/staff.booking-review.spec.js
npm run test:e2e:headless -- --spec specs/access.staff-action-guard.spec.js
npm run test:e2e:headless -- --spec specs/access.manager-confirmation.spec.js
```

---

## Diagram management workspace

```
/admin (AdminDashboard + WorkspaceShell)
│
├── #bookings ─── StaffBookingsPanel
│                  ├── Staff queue (Pending_Staff)
│                  ├── Manager queue (Pending_Boss)
│                  └── Verify Payment
│
├── #feedback ─── StaffFeedbackPanel
├── #events ───── StaffEventsPanel
├── #news ─────── StaffNewsPanel
├── #tools ────── StaffToolsPanel
│
├── #revenue ──── BossRevenuePanel      [manager+]
├── #analytics ── BossWordCloudPanel    [manager+]
└── #audit ────── BossAuditLogsPanel    [manager+]

Standalone: /staff/verify-booking/:id → StaffVerifyBooking
```
