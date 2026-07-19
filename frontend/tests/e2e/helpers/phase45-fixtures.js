import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const backendRoot = resolve(__dirname, '../../../../backend');
const APPROVED_DATABASE = 'cmart_e2e_db';

function run(action) {
  const result = spawnSync(
    'php',
    [
      'artisan',
      'e2e:item-reservation-fixtures',
      action,
      '--json',
      '--env=e2e',
    ],
    {
      cwd: backendRoot,
      encoding: 'utf-8',
      shell: process.platform === 'win32',
      env: {
        ...process.env,
        APP_ENV: 'e2e',
        DB_DATABASE: APPROVED_DATABASE,
      },
    },
  );

  if (result.status !== 0) {
    throw new Error(
      `Phase 4.5 fixture ${action} failed (exit ${result.status}): ${result.stderr || result.stdout}`,
    );
  }

  const jsonLine = result.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((line) => line.startsWith('{'));

  if (!jsonLine) {
    throw new Error(`Could not parse Phase 4.5 fixture output:\n${result.stdout}`);
  }

  const payload = JSON.parse(jsonLine);
  if (payload.database !== APPROVED_DATABASE) {
    throw new Error(`Unsafe Phase 4.5 fixture database: ${payload.database || '(missing)'}`);
  }

  return payload;
}

export function createPhase45Fixtures() {
  return run('create');
}

export function phase45FixtureStatus() {
  return run('status');
}

export function cleanupPhase45Fixtures() {
  return run('cleanup');
}

export function assertPhase45ResidueClean(status = phase45FixtureStatus()) {
  const counters = [
    'users',
    'events',
    'items',
    'reservations',
    'audits',
    'orphan_audits',
    'active_locks',
    'bookings',
    'spaces',
    'fixture_images',
  ];

  for (const key of counters) {
    if (Number(status[key] || 0) !== 0) {
      throw new Error(`Phase 4.5 residue not clean: ${key}=${status[key]}`);
    }
  }

  return status;
}
