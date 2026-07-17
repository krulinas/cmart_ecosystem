import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import {
  cleanupPhase39Fixtures,
  createPhase39Fixtures,
} from './helpers/phase39-fixtures.js';
import {
  cleanupPhase310Fixtures,
  createPhase310Fixtures,
} from './helpers/phase310-fixtures.js';

const require = createRequire(import.meta.url);
const __dirname = dirname(fileURLToPath(import.meta.url));
const frontendRoot = resolve(__dirname, '../..');
const mochaBin = require.resolve('mocha/bin/mocha.js');
const rawArgs = process.argv.slice(2);
const modeFlags = new Set(['--headless', '--headed']);
const passthroughArgs = [];
const specFiles = [];

for (const arg of rawArgs) {
  if (modeFlags.has(arg)) continue;
  if (arg.endsWith('.spec.js')) {
    specFiles.push(arg.startsWith('tests/') ? arg : `tests/e2e/specs/${arg}`);
    continue;
  }
  passthroughArgs.push(arg);
}

if (rawArgs.includes('--headless')) {
  process.env.E2E_HEADLESS = 'true';
} else if (rawArgs.includes('--headed')) {
  process.env.E2E_HEADLESS = 'false';
}

const phase39Requested = specFiles.some((file) =>
  file.endsWith('vendor.category-site-selection.spec.js'),
);
const phase310Requested = specFiles.some((file) =>
  file.endsWith('public.event-layout.spec.js'),
);

if (phase39Requested) {
  const fixtures = createPhase39Fixtures();
  process.env.E2E_VENDOR_EMAIL = fixtures.vendor_email;
  process.env.E2E_VENDOR_PASSWORD = fixtures.vendor_password;
  process.env.E2E_ORGANIZER_EMAIL = fixtures.organizer_email;
  process.env.E2E_ORGANIZER_PASSWORD = fixtures.organizer_password;
  process.env.E2E_CMART_MANAGEMENT_EMAIL = fixtures.cmart_management_email;
  process.env.E2E_CMART_MANAGEMENT_PASSWORD = fixtures.cmart_management_password;
  process.env.E2E_BOOKING_EVENT_NAME = fixtures.event_title;
}

if (phase310Requested) {
  const fixtures = createPhase310Fixtures();
  process.env.E2E_PUBLIC_LAYOUT_EVENT_ID = String(fixtures.published_event_id);
  process.env.E2E_PUBLIC_LAYOUT_EVENT_TITLE = fixtures.published_event_title;
  process.env.E2E_UNPUBLISHED_LAYOUT_EVENT_ID = String(fixtures.unpublished_event_id);
  process.env.E2E_UNPUBLISHED_LAYOUT_EVENT_TITLE = fixtures.unpublished_event_title;
  process.env.E2E_ENDED_LAYOUT_EVENT_ID = String(fixtures.ended_event_id);
  process.env.E2E_CLOSED_LAYOUT_EVENT_ID = String(fixtures.closed_event_id);
  process.env.E2E_PUBLIC_LAYOUT_FOOD_CATEGORY_ID = String(fixtures.food_category_id);
  process.env.E2E_PUBLIC_LAYOUT_PRIVATE_ROW = fixtures.private_row_label;
  process.env.E2E_PUBLIC_LAYOUT_UNRESOLVED_SITE = fixtures.unresolved_site_label;
  process.env.E2E_PUBLIC_LAYOUT_PRIVATE_VENDOR_NAME = fixtures.private_vendor_name;
  process.env.E2E_PUBLIC_LAYOUT_PRIVATE_VENDOR_EMAIL = fixtures.private_vendor_email;
  process.env.E2E_PUBLIC_LAYOUT_PRIVATE_OVERRIDE = fixtures.private_override_reason;
  process.env.E2E_VENDOR_EMAIL = fixtures.private_vendor_email;
  process.env.E2E_VENDOR_PASSWORD = fixtures.private_vendor_password;
}

const { requiresBookingData } = await import('./helpers/preflight.js');
process.env.E2E_REQUIRES_BOOKING_DATA = requiresBookingData(specFiles) ? 'true' : 'false';

const mochaArgs = [
  ...(specFiles.length ? specFiles : ['tests/e2e/specs/**/*.spec.js']),
  '--timeout',
  '120000',
  '--file',
  'tests/e2e/setup.js',
  ...passthroughArgs,
];

const result = spawnSync(process.execPath, [mochaBin, ...mochaArgs], {
  cwd: frontendRoot,
  stdio: 'inherit',
  env: process.env,
});

if (phase39Requested) {
  cleanupPhase39Fixtures();
}
if (phase310Requested) {
  cleanupPhase310Fixtures();
}

process.exit(result.status ?? 1);
