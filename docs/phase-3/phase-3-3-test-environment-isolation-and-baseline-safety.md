# Phase 3.3 — Test Environment Isolation and Baseline Safety

| Field | Value |
|-------|-------|
| **Status** | Implemented |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.2 ADR (`docs/phase-3/phase-3-2-event-layout-architecture-decision.md`) |
| **Blocks** | Phase 3.4 schema migrations |

---

## 1. Problem statement

PHPUnit set `APP_ENV=testing` in `phpunit.xml` but inherited `DB_DATABASE` from the local `.env` file (`cmart_db`). Database-aware feature tests therefore wrote to the same persistent MySQL database used for local development. That is unacceptable before migration-heavy Phase 3 work.

---

## 2. Confirmed original risk

| Setting | Before Phase 3.3 |
|---------|------------------|
| `APP_ENV` during PHPUnit | `testing` (from `phpunit.xml`) |
| `DB_CONNECTION` during PHPUnit | `mysql` (from `.env`) |
| `DB_DATABASE` during PHPUnit | `cmart_db` (from `.env`) |
| Config cache | Not present (`bootstrap/cache/config.php` absent) |
| Protection | Manual fixture cleanup only (`CleansUpTestFixtures`) |

Configuration alone was insufficient because `.env` could still override the intended test database unless `phpunit.xml` explicitly set `DB_DATABASE` and a runtime guard rejected unsafe names.

---

## 3. Final test database contract

| Setting | Value |
|---------|-------|
| `APP_ENV` | `testing` |
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` | `cmart_test` (approved disposable database) |
| Development database (blocked) | `cmart_db` |
| Approved driver | `mysql` only for integration tests |
| Guard class | `App\Support\TestingDatabaseGuard` |
| Guard config | `config/testing.php` |

---

## 4. Local setup

1. Copy the example test environment file:

   ```bash
   cd backend
   cp .env.testing.example .env.testing
   ```

2. Edit `.env.testing` (ignored by Git):
   - Set `DB_USERNAME` and `DB_PASSWORD` to match your local MySQL user.
   - Keep `DB_DATABASE=cmart_test`.
   - Copy `APP_KEY` from `.env` if tests require encryption.

3. Create the disposable database (once):

   ```sql
   CREATE DATABASE IF NOT EXISTS cmart_test
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci;
   ```

4. Migrate the test database:

   ```bash
   php artisan migrate --env=testing --force
   ```

   Or:

   ```bash
   composer test:setup
   ```

5. Run tests:

   ```bash
   php artisan test
   ```

---

## 5. Test database creation

Only create `cmart_test`. Never recreate or wipe `cmart_db` as part of test setup.

If your MySQL user lacks `CREATE DATABASE` permission, create `cmart_test` manually using the SQL above, then run migrations with `--env=testing`.

---

## 6. Environment configuration

| Source | Purpose |
|--------|---------|
| `phpunit.xml` | Forces `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=cmart_test` |
| `.env.testing` | Local credentials and host (Git-ignored) |
| `.env.testing.example` | Tracked placeholders for onboarding |
| `.env` | Development only; must not be used as the PHPUnit database |
| `config/testing.php` | Approved/blocked database names |

Laravel loads `.env`, then `.env.testing` when `APP_ENV=testing`. PHPUnit `<env>` entries take precedence over file values for those keys.

---

## 7. Runtime guard behaviour

`TestingDatabaseGuard::assertSafeFromApplication()` runs in `Tests\CreatesApplication::createApplication()` immediately after kernel bootstrap and **before** any test `setUp()`, `RefreshDatabase`, factories, or `CleansUpTestFixtures` teardown can execute.

The guard verifies:

- `APP_ENV === testing`
- Connection driver is approved (`mysql`)
- Database name is non-empty
- Database name is not `cmart_db`
- Database name is not in the blocked list
- Database name exactly matches the approved disposable name (`cmart_test` by default)

On failure it throws `App\Exceptions\UnsafeTestDatabaseException` with setup instructions and **no credentials**.

Pure validation is covered by `tests/Unit/TestingDatabaseGuardTest.php` without connecting to unsafe databases.

Boot-path coverage: `tests/Feature/TestingDatabaseGuardBootTest.php`.

---

## 8. Safe migration command

Allowed only after verifying the resolved database is `cmart_test`:

```bash
php artisan migrate --env=testing --force
```

Destructive reset is allowed **only** against the verified test database:

```bash
php artisan migrate:fresh --env=testing --force
```

Never run `migrate`, `migrate:fresh`, or `db:wipe` without `--env=testing` during test setup.

---

## 9. Safe test command

```bash
php artisan test
```

Equivalent convenience script:

```bash
composer test:safe
```

Focused guard tests:

```bash
php artisan test --filter=TestingDatabaseGuard
```

---

## 10. CI environment contract

No CI workflow exists in this repository yet. When CI is added:

- Provision an isolated MySQL database named `cmart_test` (or set `TESTING_APPROVED_DATABASE` to another non-blocked name).
- Set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` via CI secrets.
- Set `DB_DATABASE=cmart_test` in the CI environment or rely on `phpunit.xml`.
- Run `php artisan migrate --env=testing --force` before `php artisan test`.
- Do not point CI at `cmart_db`.

---

## 11. Development baseline verification

Before and after the full PHPUnit suite, compare read-only counts on `cmart_db`:

```bash
php scripts/count_baseline.php
```

Expected result: **zero difference** for all operational tables.

`scripts/count_baseline.php` uses the default development environment (`.env`), not the test environment.

---

## 12. Troubleshooting

| Symptom | Action |
|---------|--------|
| `UnsafeTestDatabaseException: cmart_db` | Ensure `phpunit.xml` sets `DB_DATABASE=cmart_test`; remove stale `bootstrap/cache/config.php` if present |
| `SQLSTATE[HY000] [1049] Unknown database 'cmart_test'` | Create `cmart_test` using the SQL above |
| `Access denied` on test DB | Fix credentials in `.env.testing` |
| Guard passes but migrations fail | Run `php artisan migrate --env=testing --force` |
| Tests pass but dev counts changed | **Stop** — report failure; do not run more tests against dev DB |

---

## 13. Commands that must never target development data

Do not run against `cmart_db` or default/local environment during test work:

```bash
php artisan test            # without phpunit.xml + guard fixes
php artisan migrate
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

Read-only inspection of `cmart_db` is permitted.

---

## 14. Rollback considerations

To revert Phase 3.3 isolation:

1. Remove `TestingDatabaseGuard` invocation from `CreatesApplication`.
2. Revert `phpunit.xml` database entries.
3. Remove `config/testing.php` and guard tests.

**Do not roll back** merely to make tests pass — fix test database setup instead.

---

## 15. Phase 3.4 entry criteria

Phase 3.4 may begin only when all are true:

| Requirement | Evidence |
|-------------|----------|
| Dedicated test DB configured | `phpunit.xml` + `.env.testing.example` |
| Runtime guard active | `TestingDatabaseGuard` in `CreatesApplication` |
| Unsafe DB rejected | `TestingDatabaseGuardTest` |
| Full suite uses test DB | `php artisan test` with guard boot test |
| Development counts unchanged | `count_baseline.php` before/after |
| No secrets tracked | `.env.testing` ignored |
| Setup documented | This file |
| Mandatory Phase 3.2 ADR corrections applied | Separate pre-3.4 documentation task |

---

## References

- `backend/app/Support/TestingDatabaseGuard.php`
- `backend/config/testing.php`
- `backend/phpunit.xml`
- `docs/phase-2/phase-2a7-1-test-isolation-and-local-data-cleanup.md`
