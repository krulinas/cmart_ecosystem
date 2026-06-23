import { config } from 'dotenv';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath = resolve(__dirname, '../.env.e2e');

if (existsSync(envPath)) {
  config({ path: envPath });
}

const readBool = (value, defaultValue = false) => {
  if (value === undefined || value === '') return defaultValue;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
};

export const env = {
  baseUrl: process.env.E2E_BASE_URL || 'http://localhost:5173',
  headless: readBool(process.env.E2E_HEADLESS, false),
  vendorEmail: process.env.E2E_VENDOR_EMAIL || '',
  vendorPassword: process.env.E2E_VENDOR_PASSWORD || '',
  staffEmail: process.env.E2E_STAFF_EMAIL || '',
  staffPassword: process.env.E2E_STAFF_PASSWORD || '',
  bookingEventName: process.env.E2E_BOOKING_EVENT_NAME || '',
  bookingBusinessName: process.env.E2E_BOOKING_BUSINESS_NAME || 'E2E Test Vendor',
  bookingCategory: process.env.E2E_BOOKING_CATEGORY || 'Food & Beverages',
  bookingDetails: process.env.E2E_BOOKING_DETAILS || 'Automated Selenium booking test',
  staffBookingAction: (process.env.E2E_STAFF_BOOKING_ACTION || 'needs_revision').toLowerCase(),
};

export function requireVendorCredentials() {
  const missing = [];

  if (!env.vendorEmail) missing.push('E2E_VENDOR_EMAIL');
  if (!env.vendorPassword) missing.push('E2E_VENDOR_PASSWORD');

  if (missing.length) {
    throw new Error(
      `Missing required E2E credentials: ${missing.join(', ')}.\n` +
        `Copy tests/e2e/.env.e2e.example to tests/e2e/.env.e2e and fill in your local test user values.`,
    );
  }

  return {
    email: env.vendorEmail,
    password: env.vendorPassword,
  };
}

export function requireStaffCredentials() {
  const missing = [];

  if (!env.staffEmail) missing.push('E2E_STAFF_EMAIL');
  if (!env.staffPassword) missing.push('E2E_STAFF_PASSWORD');

  if (missing.length) {
    throw new Error(
      `Missing required E2E staff credentials: ${missing.join(', ')}.\n` +
        `Copy tests/e2e/.env.e2e.example to tests/e2e/.env.e2e and fill in your local staff test user values.`,
    );
  }

  return {
    email: env.staffEmail,
    password: env.staffPassword,
  };
}

export function resolveStaffBookingAction() {
  const action = env.staffBookingAction.replace(/-/g, '_');

  if (['needs_revision', 'approve'].includes(action)) {
    return action;
  }

  throw new Error(
    `Unsupported E2E_STAFF_BOOKING_ACTION="${env.staffBookingAction}". Use "needs_revision" or "approve".`,
  );
}
