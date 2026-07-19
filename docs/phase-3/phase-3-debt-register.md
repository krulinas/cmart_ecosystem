# Phase 3 Debt Register

| Debt | Severity | Owner/phase | Reason | Risk | Entry condition | Exit condition |
|---|---|---|---|---|---|---|
| `cmart_db` still has 14 pending Phase 3 migrations | High operational | controlled rollout | automatic development mutation prohibited | feature/schema mismatch until rollout | approved backup window | runbook migration and zero-violation postflight |
| Preference writes do not make `vendor_category_id` authoritative | Medium | post-rollout hardening | legacy saved-preference API remains string-based | nullable canonical gap | Phase 3 schema deployed | canonical selector/API, compatibility tests, clean backfill |
| Vendor-item canonical category writes | Resolved | Phase 4.1 | marketplace item API previously remained string-based | item/category mismatch was possible | Phase 3 schema deployed | Phase 4.1 now resolves canonical `vendor_category_id`, derives the legacy label, and rejects unknown/inactive/non-public categories |
| Booking `product_category` compatibility mirror | Medium | later retirement | analytics, pass, search, and clients still read it | divergence if future writers bypass resolver | reader inventory complete | zero legacy readers and safe rollback migration |
| Profile `business_category` compatibility mirror | Medium | later retirement | profile/auth/analytics clients expose it | compatibility break if removed | API version plan | canonical-only clients and compatibility window closed |
| Event-site `row_label` compatibility mirror | Medium | later layout migration | legacy site generation, display, and validation use it | historical sites cannot be interpreted if removed | all sites canonical and readers migrated | zero readers/writers plus rollback-safe migration |
| Nullable row/category FKs | Medium | post-rollout data governance | historical unresolved rows/sites are valid migration outcomes | premature `NOT NULL` would invalidate history | real-data preflight complete | unknowns zero or explicitly archived/classified |
| Phase 3.4A rollback may refuse | Low/expected | release operations | append-only history cannot fit legacy uniqueness | operator must restore or forward-fix | colliding observations exist | backup restore or continued forward schema |
| Frontend bundle exceeds 500 kB advisory threshold | Low | frontend performance | current monolithic chunk | slower initial load | performance budget approved | route/code splitting and measured improvement |
| PHPUnit XML deprecated schema warning | Low | test infrastructure | legacy PHPUnit configuration | future PHPUnit incompatibility | framework/tooling upgrade window | migrate configuration with suite passing |
| E2E `shell: true` deprecation warning | Low | test infrastructure | Windows Artisan fixture spawning | future Node behavior/security warning | cross-platform process refactor | direct executable invocation without shell warning |
| MySQL runtime matrix not exercised in Phase 3.11 | Low | release engineering | available runtime was MariaDB 10.4.32 | vendor-specific behavior could differ | MySQL deployment target selected | fresh/upgrade/trigger suite passes on target MySQL |
| Item reservation and extra charge | In progress | Phase 4 | Phase 4.3 manual charge lifecycle (Organizer queue, confirmation, waiver, cancellation, expiry) is complete; reservation-facing UI and completion transition remain deferred | no risk to Phase 3 closure | Phase 4.3 complete | Phase 4.4 reservation user interfaces and later completion lifecycle |
| Walk-in vendor | Planned | future phase | out of Phase 3 | none to Phase 3 closure | stakeholder prioritization | separately designed and tested |
| Sustainability/ESG messaging and analytics | Planned | future phase | out of Phase 3 | none to Phase 3 closure | metrics/content definition | approved implementation and evidence |
| Generated reports and analytics | Planned | future phase | out of Phase 3 | none to Phase 3 closure | reporting requirements | approved implementation and authorization review |

No listed debt weakens the tested Phase 3 privacy, audit-history, authorization, or isolated-migration gates. The pending `cmart_db` rollout remains an operational prerequisite for using Phase 3 in that database.
