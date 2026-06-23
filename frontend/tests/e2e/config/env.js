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
