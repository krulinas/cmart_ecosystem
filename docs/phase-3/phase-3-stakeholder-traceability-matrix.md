# Phase 3 Stakeholder Traceability Matrix

| Feedback item | Status | Phase 3 evidence | Remaining work |
|---|---|---|---|
| Paid withdrawal with no refund | Implemented | withdrawal policy, audit/reconciliation UI, backend and E2E tests | none in Phase 3 |
| Item reservation and extra charge | Deferred | explicitly excluded from Phase 3 | Phase 4 |
| Category-based layout | Implemented | canonical categories, rows, sites, readiness, Organizer UI, public layout | controlled database rollout |
| Important Bahasa Melayu flows | Implemented | Organizer layout/reassignment/public navigation messages and tests | continue localization review in later phases |
| Reuse/recycle messaging | Partially implemented | existing marketplace/reuse item language | sustainability/ESG content strategy deferred |
| Vendor information requirements | Partially implemented | profile category, business details, canonical booking snapshot | review additional compliance fields separately |
| Full-event booking and exceptions | Implemented | allocations across active EventDays and governed attendance exceptions | no per-day self-service booking |
| Walk-in vendor | Deferred | out of Phase 3 scope | future approved phase |
| Organizer governance | Implemented | Organizer-only layout, publication, reassignment, override reason/audit | retain in future work |
| CMart Management restrictions | Implemented | authorization middleware, focused tests, Phase 3.11 E2E | retain in future work |
| Generated reports | Deferred | not part of category-layout closure | future reporting phase |
| User-friendly UX | Implemented for Phase 3 scope | category-first booking, TGV-style sites, Organizer controls, public navigation, accessibility checks | iterative usability work remains |
| Public visitor map | Implemented | publication gate and allowlisted projection | controlled rollout |
| Booking/site privacy | Implemented | no occupancy, PII, payment, booking, or override authority in public projection | retain as release gate |
| Mismatch governance | Implemented | acknowledgement, meaningful reason, active/superseded override history | retain append-only audit |
| Item/category canonical FK | Partially implemented | schema/backfill exists | preference and item runtime writers remain debt |

Statuses are deliberately conservative. Deferred and partially implemented requests are not counted as Phase 3 completion.
