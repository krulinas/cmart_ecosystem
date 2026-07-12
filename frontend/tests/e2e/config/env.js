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

const firstNonEmpty = (...values) => values.find((value) => value !== undefined && value !== '');

export const env = {
  baseUrl: process.env.E2E_BASE_URL || 'http://localhost:5175',
  apiBaseUrl: process.env.E2E_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headless: readBool(process.env.E2E_HEADLESS, false),
  vendorEmail: process.env.E2E_VENDOR_EMAIL || '',
  vendorPassword: process.env.E2E_VENDOR_PASSWORD || '',
  vendorBEmail: process.env.E2E_VENDOR_B_EMAIL || '',
  vendorBPassword: process.env.E2E_VENDOR_B_PASSWORD || '',
  organizerEmail: firstNonEmpty(process.env.E2E_ORGANIZER_EMAIL, process.env.E2E_MANAGER_EMAIL) || '',
  organizerPassword: firstNonEmpty(process.env.E2E_ORGANIZER_PASSWORD, process.env.E2E_MANAGER_PASSWORD) || '',
  cmartManagementEmail: firstNonEmpty(
    process.env.E2E_CMART_MANAGEMENT_EMAIL,
    process.env.E2E_STAFF_EMAIL,
  ) || '',
  cmartManagementPassword: firstNonEmpty(
    process.env.E2E_CMART_MANAGEMENT_PASSWORD,
    process.env.E2E_STAFF_PASSWORD,
  ) || '',
  /** @deprecated Use organizerEmail — kept for backward-compatible scripts */
  staffEmail: firstNonEmpty(process.env.E2E_STAFF_EMAIL, process.env.E2E_CMART_MANAGEMENT_EMAIL) || '',
  /** @deprecated Use organizerPassword */
  staffPassword: firstNonEmpty(process.env.E2E_STAFF_PASSWORD, process.env.E2E_CMART_MANAGEMENT_PASSWORD) || '',
  /** @deprecated Use cmartManagementEmail */
  managerEmail: firstNonEmpty(process.env.E2E_MANAGER_EMAIL, process.env.E2E_ORGANIZER_EMAIL) || '',
  /** @deprecated Use cmartManagementPassword */
  managerPassword: firstNonEmpty(process.env.E2E_MANAGER_PASSWORD, process.env.E2E_ORGANIZER_PASSWORD) || '',
  bookingEventName: process.env.E2E_BOOKING_EVENT_NAME || '',
  bookingBusinessName: process.env.E2E_BOOKING_BUSINESS_NAME || 'E2E Test Vendor',
  bookingCategory: process.env.E2E_BOOKING_CATEGORY || 'Food & Beverages',
  bookingDetails: process.env.E2E_BOOKING_DETAILS || 'Automated Selenium booking test',
  organizerBookingAction: (process.env.E2E_ORGANIZER_BOOKING_ACTION || process.env.E2E_STAFF_BOOKING_ACTION || 'needs_revision').toLowerCase(),
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

export function requireVendorBCredentials() {
  const missing = [];

  if (!env.vendorBEmail) missing.push('E2E_VENDOR_B_EMAIL');
  if (!env.vendorBPassword) missing.push('E2E_VENDOR_B_PASSWORD');

  if (missing.length) {
    throw new Error(
      `Missing required E2E vendor B credentials: ${missing.join(', ')}.\n` +
        `Copy tests/e2e/.env.e2e.example to tests/e2e/.env.e2e and set vendor B values (seed: vendor_b@cmart.com).`,
    );
  }

  return {
    email: env.vendorBEmail,
    password: env.vendorBPassword,
  };
}

export function requireOrganizerCredentials() {
  const missing = [];

  if (!env.organizerEmail) missing.push('E2E_ORGANIZER_EMAIL');
  if (!env.organizerPassword) missing.push('E2E_ORGANIZER_PASSWORD');

  if (missing.length) {
    throw new Error(
      `Missing required E2E organizer credentials: ${missing.join(', ')}.\n` +
        `Copy tests/e2e/.env.e2e.example to tests/e2e/.env.e2e and fill in organizer test user values (seed: organizer@cmart.com or admin@cmart.com).`,
    );
  }

  return {
    email: env.organizerEmail,
    password: env.organizerPassword,
  };
}

export function requireCmartManagementCredentials() {
  const missing = [];

  if (!env.cmartManagementEmail) missing.push('E2E_CMART_MANAGEMENT_EMAIL');
  if (!env.cmartManagementPassword) missing.push('E2E_CMART_MANAGEMENT_PASSWORD');

  if (missing.length) {
    throw new Error(
      `Missing required E2E cmart_management credentials: ${missing.join(', ')}.\n` +
        `Copy tests/e2e/.env.e2e.example to tests/e2e/.env.e2e and fill in cmart_management test user values (seed: staff@cmart.com or venue@cmart.com).`,
    );
  }

  return {
    email: env.cmartManagementEmail,
    password: env.cmartManagementPassword,
  };
}

/** @deprecated Use requireCmartManagementCredentials */
export function requireStaffCredentials() {
  return requireCmartManagementCredentials();
}

/** @deprecated Use requireOrganizerCredentials */
export function requireManagerCredentials() {
  return requireOrganizerCredentials();
}

export function resolveOrganizerBookingAction() {
  const action = env.organizerBookingAction.replace(/-/g, '_');

  if (['needs_revision', 'approve', 'reject'].includes(action)) {
    return action;
  }

  throw new Error(
    `Unsupported E2E_ORGANIZER_BOOKING_ACTION="${env.organizerBookingAction}". ` +
      'Use "needs_revision", "approve", or "reject".',
  );
}

/** @deprecated Use resolveOrganizerBookingAction */
export function resolveStaffBookingAction() {
  return resolveOrganizerBookingAction();
}
