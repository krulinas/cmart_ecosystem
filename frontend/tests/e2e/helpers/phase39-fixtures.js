import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const backendRoot = resolve(__dirname, '../../../../backend');

function run(action, options = []) {
  const result = spawnSync(
    'php',
    [
      'artisan',
      'e2e:vendor-category-booking-fixtures',
      action,
      ...options,
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
        DB_DATABASE: 'cmart_e2e_db',
      },
    },
  );

  if (result.status !== 0) {
    throw new Error(
      `Phase 3.9 fixture ${action} failed (exit ${result.status}): ${result.stderr || result.stdout}`,
    );
  }

  const jsonLine = result.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((line) => line.startsWith('{'));

  if (!jsonLine) {
    throw new Error(`Could not parse Phase 3.9 fixture output:\n${result.stdout}`);
  }

  const payload = JSON.parse(jsonLine);
  if (payload.database !== 'cmart_e2e_db') {
    throw new Error(`Unsafe Phase 3.9 fixture database: ${payload.database || '(missing)'}`);
  }

  return payload;
}

export function createPhase39Fixtures() {
  return run('create');
}

export function occupyPhase39Site(siteLabel) {
  return run('occupy', [`--site=${siteLabel}`]);
}

export function phase39FixtureStatus() {
  return run('status');
}

export function cleanupPhase39Fixtures() {
  return run('cleanup');
}
