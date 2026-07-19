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
import {
  cleanupPhase45Fixtures,
  createPhase45Fixtures,
} from './helpers/phase45-fixtures.js';

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
  file.endsWith('vendor.category-site-selection.spec.js')
  || file.endsWith('phase3.closure.spec.js'),
);
const phase310Requested = specFiles.some((file) =>
  file.endsWith('public.event-layout.spec.js'),
);
const phase45Requested = specFiles.some((file) =>
  /phase45\.[^/\\]+\.spec\.js$/.test(file),
);

let cleanupPhase45 = false;
let cleanupPhase39 = false;
let cleanupPhase310 = false;

function injectPhase45Credentials(fixtures) {
  process.env.E2E_VENDOR_EMAIL = fixtures.vendor_email;
  process.env.E2E_VENDOR_PASSWORD = fixtures.vendor_password;
  process.env.E2E_VENDOR_B_EMAIL = fixtures.unrelated_vendor_email;
  process.env.E2E_VENDOR_B_PASSWORD = fixtures.unrelated_vendor_password;
  process.env.E2E_ORGANIZER_EMAIL = fixtures.organizer_email;
  process.env.E2E_ORGANIZER_PASSWORD = fixtures.organizer_password;
  process.env.E2E_CMART_MANAGEMENT_EMAIL = fixtures.cmart_management_email;
  process.env.E2E_CMART_MANAGEMENT_PASSWORD = fixtures.cmart_management_password;
  process.env.E2E_BOOKING_EVENT_NAME = fixtures.event_title;
  process.env.E2E_P45_EVENT_ID = String(fixtures.event_id);
  process.env.E2E_P45_EVENT_TITLE = fixtures.event_title;
  process.env.E2E_P45_SERVICE_FEE_AMOUNT = String(fixtures.service_fee_amount);
  process.env.E2E_P45_VENDOR_BOOKING_ID = String(fixtures.vendor_booking_id);
  process.env.E2E_P45_RESERVER_EMAIL = fixtures.reserver_email;
  process.env.E2E_P45_RESERVER_PASSWORD = fixtures.reserver_password;
  process.env.E2E_P45_COMPETITOR_EMAIL = fixtures.competitor_email;
  process.env.E2E_P45_COMPETITOR_PASSWORD = fixtures.competitor_password;
  process.env.E2E_P45_SUCCESS_ITEM_ID = String(fixtures.success_item_id);
  process.env.E2E_P45_CONFLICT_ITEM_ID = String(fixtures.conflict_item_id);
  process.env.E2E_P45_CANCEL_ITEM_ID = String(fixtures.cancel_item_id);
  process.env.E2E_P45_EXPIRY_ITEM_ID = String(fixtures.expiry_item_id);
  process.env.E2E_P45_COMPLETION_ITEM_ID = String(fixtures.completion_item_id);
  process.env.E2E_P45_ACCESS_ITEM_ID = String(fixtures.access_item_id);
  process.env.E2E_P45_OWNER_ONLY_ITEM_ID = String(fixtures.owner_only_item_id);
  process.env.E2E_P45_HELD_RESERVATION_REFERENCE = fixtures.held_reservation_reference;
  process.env.E2E_P45_DATABASE = fixtures.database;
}

function runCleanup() {
  if (cleanupPhase45) {
    try {
      cleanupPhase45Fixtures();
    } catch (error) {
      console.error('Phase 4.5 fixture cleanup failed:', error.message || error);
    }
    cleanupPhase45 = false;
  }
  if (cleanupPhase39) {
    try {
      cleanupPhase39Fixtures();
    } catch (error) {
      console.error('Phase 3.9 fixture cleanup failed:', error.message || error);
    }
    cleanupPhase39 = false;
  }
  if (cleanupPhase310) {
    try {
      cleanupPhase310Fixtures();
    } catch (error) {
      console.error('Phase 3.10 fixture cleanup failed:', error.message || error);
    }
    cleanupPhase310 = false;
  }
}

for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
  process.on(signal, () => {
    runCleanup();
    process.exit(130);
  });
}

process.on('exit', () => {
  runCleanup();
});

try {
  if (phase39Requested) {
    const fixtures = createPhase39Fixtures();
    cleanupPhase39 = true;
    process.env.E2E_VENDOR_EMAIL = fixtures.vendor_email;
    process.env.E2E_VENDOR_PASSWORD = fixtures.vendor_password;
    process.env.E2E_ORGANIZER_EMAIL = fixtures.organizer_email;
    process.env.E2E_ORGANIZER_PASSWORD = fixtures.organizer_password;
    process.env.E2E_CMART_MANAGEMENT_EMAIL = fixtures.cmart_management_email;
    process.env.E2E_CMART_MANAGEMENT_PASSWORD = fixtures.cmart_management_password;
    process.env.E2E_BOOKING_EVENT_NAME = fixtures.event_title;
    process.env.E2E_P39_EVENT_ID = String(fixtures.event_id);
    process.env.E2E_P39_FOOD_CATEGORY_ID = String(fixtures.food_category_id);
    process.env.E2E_P39_SITE_IDS = JSON.stringify(fixtures.site_ids);
  }

  if (phase310Requested) {
    const fixtures = createPhase310Fixtures();
    cleanupPhase310 = true;
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

  if (phase45Requested) {
    const fixtures = createPhase45Fixtures();
    cleanupPhase45 = true;
    injectPhase45Credentials(fixtures);
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

  runCleanup();
  process.exit(result.status ?? 1);
} catch (error) {
  console.error(error.message || error);
  runCleanup();
  process.exit(1);
}
