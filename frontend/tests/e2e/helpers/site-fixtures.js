import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const backendRoot = resolve(__dirname, '../../../../backend');

/**
 * Phase 2A.8.1 — drive the test-only `e2e:site-fixtures` artisan command.
 *
 * Fixtures are created/cleaned entirely through the backend command so the
 * browser flow never depends on permanent local demo layout data, and every
 * temporary row is removed afterwards regardless of test outcome.
 */
function runArtisan(action, { json = false } = {}) {
  const args = ['artisan', 'e2e:site-fixtures', action];
  if (json) args.push('--json');

  const result = spawnSync('php', args, {
    cwd: backendRoot,
    encoding: 'utf-8',
    shell: process.platform === 'win32',
  });

  if (result.status !== 0) {
    throw new Error(
      `e2e:site-fixtures ${action} failed (exit ${result.status}): ${result.stderr || result.stdout}`,
    );
  }

  return result.stdout;
}

export function createSiteFixtures() {
  return parseFixtureJson(runArtisan('create', { json: true }));
}

export function createPaidWithdrawalFixture() {
  return parseFixtureJson(runArtisan('create-paid-booking', { json: true }));
}

export function createPaymentSubmittedWithdrawalFixture() {
  return parseFixtureJson(runArtisan('create-payment-submitted-booking', { json: true }));
}

export function createPaidThreeDayAttendanceFixture() {
  return parseFixtureJson(runArtisan('create-paid-three-day-booking', { json: true }));
}

export function createReleasedDayRecoveryFixture() {
  return parseFixtureJson(runArtisan('create-released-day-recovery', { json: true }));
}

export function recoveryAddCompetingAllocation(siteLabel) {
  const args = ['artisan', 'e2e:site-fixtures', 'recovery-add-competing-allocation', `--site=${siteLabel}`, '--json'];
  const result = spawnSync('php', args, {
    cwd: backendRoot,
    encoding: 'utf-8',
    shell: process.platform === 'win32',
  });
  if (result.status !== 0) {
    throw new Error(
      `e2e:site-fixtures recovery-add-competing-allocation failed (exit ${result.status}): ${result.stderr || result.stdout}`,
    );
  }
  return parseFixtureJson(result.stdout);
}

export function recoveryFixtureStatus() {
  return parseFixtureJson(runArtisan('recovery-status', { json: true }));
}

export function attendanceFixtureStatus() {
  return parseFixtureJson(runArtisan('attendance-status', { json: true }));
}

function parseFixtureJson(stdout) {
  const jsonLine = stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .find((line) => line.startsWith('{'));

  if (!jsonLine) {
    throw new Error(`Could not parse e2e:site-fixtures output:\n${stdout}`);
  }

  return JSON.parse(jsonLine);
}

export function cleanupSiteFixtures() {
  try {
    runArtisan('cleanup');
  } catch (error) {
    // Cleanup must be best-effort so a failing assertion never leaks fixtures
    // and never masks the original test failure.
    console.error(`[site-fixtures] cleanup warning: ${error.message}`);
  }
}
