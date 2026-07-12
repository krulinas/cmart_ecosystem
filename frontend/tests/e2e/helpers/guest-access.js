import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { e2eT7DGuestProtectionMarker } from './actions.js';
import { assertTestIdAbsent } from './access-guards.js';
import { loginAsVendor } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { clearBrowserSession } from './session.js';
import { resolveVendorBookingIdByMarker, vendorApiRequest } from './vendor-ownership.js';
import { PAYMENT_PROOF_FIXTURE } from './vendor-payment-records.js';

export const DENIED_GUEST_STATUSES = new Set([401, 403, 404]);

export const PROTECTED_FRONTEND_ROUTES = [
  {
    path: '/dashboard',
    label: 'Vendor dashboard',
    forbiddenTestIds: ['vendor-dashboard-root'],
  },
  {
    path: '/profile',
    label: 'Vendor profile',
    forbiddenTestIds: ['vendor-dashboard-root'],
  },
  {
    path: '/vendor-booking',
    label: 'Vendor booking form',
    forbiddenTestIds: ['vendor-dashboard-root', 'booking-form'],
  },
  {
    path: '/admin',
    label: 'Admin workspace',
    forbiddenTestIds: ['management-dashboard-root', 'organizer-bookings-root'],
  },
  {
    path: '/admin#bookings',
    label: 'Admin bookings panel',
    forbiddenTestIds: ['management-dashboard-root', 'organizer-bookings-root'],
  },
];

export async function ensureGuestSession(driver) {
  await clearBrowserSession(driver);

  const tokenPresent = await driver.executeScript(
    () => Boolean(localStorage.getItem('carboot_cmart_token')),
  );
  assert.equal(tokenPresent, false, 'Guest session must not retain carboot_cmart_token in localStorage.');
}

export async function assertGuestSessionClean(driver) {
  const storage = await driver.executeScript(() => ({
    localStorageKeys: Object.keys(localStorage),
    sessionStorageKeys: Object.keys(sessionStorage),
    tokenPresent: Boolean(localStorage.getItem('carboot_cmart_token')),
  }));

  assert.equal(storage.tokenPresent, false, 'Guest must not have an auth token in localStorage.');
  return storage;
}

export async function assertGuestRedirectedFromProtectedRoute(driver, path, { label, forbiddenTestIds = [] } = {}) {
  await driver.get(`${env.baseUrl}${path}`);

  await driver.wait(
    async () => {
      const url = await driver.getCurrentUrl();
      if (url.includes('/login')) {
        return true;
      }

      const loginSelectors = [
        '[data-testid="login-email"]',
        '[data-testid="management-login-email"]',
        '[data-testid="auth-continue-email"]',
      ];

      for (const selector of loginSelectors) {
        const inputs = await driver.findElements(By.css(selector));
        for (const input of inputs) {
          if (await input.isDisplayed()) {
            return true;
          }
        }
      }

      return false;
    },
    15000,
    `Guest must be redirected to login or see login form when opening ${label} (${path}).`,
  );

  const currentUrl = await driver.getCurrentUrl();
  const loginSelectors = [
    '[data-testid="login-email"]',
    '[data-testid="management-login-email"]',
    '[data-testid="auth-continue-email"]',
  ];
  let loginFormShown = false;

  for (const selector of loginSelectors) {
    const loginVisible = await driver.findElements(By.css(selector));
    for (const input of loginVisible) {
      if (await input.isDisplayed()) {
        loginFormShown = true;
        break;
      }
    }
    if (loginFormShown) break;
  }

  assert.ok(
    currentUrl.includes('/login') || loginFormShown,
    `Guest opening ${label} must land on login. URL: ${currentUrl}`,
  );

  for (const testId of forbiddenTestIds) {
    await assertTestIdAbsent(driver, testId);
  }
}

export async function guestApiRequest(driver, method, endpoint, { body, isFormData = false, formPayload } = {}) {
  await driver.get(`${env.baseUrl}/login`);

  return driver.executeScript(
    async (httpMethod, path, payload, apiBase, useForm, formDataPayload) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (token) {
        throw new Error('Guest API request must not run with auth token present.');
      }

      const options = {
        method: httpMethod,
        headers: {
          Accept: 'application/json',
        },
      };

      if (useForm && formDataPayload) {
        const bytes = Uint8Array.from(atob(formDataPayload.base64), (char) => char.charCodeAt(0));
        const blob = new Blob([bytes], { type: formDataPayload.mimeType || 'image/png' });
        const formData = new FormData();
        formData.append(formDataPayload.fieldName, blob, formDataPayload.fileName);
        options.body = formData;
      } else if (payload != null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
      }

      const response = await fetch(`${apiBase}${path}`, options);

      return {
        ok: response.ok,
        status: response.status,
        body: await response.text(),
      };
    },
    method,
    endpoint,
    body ?? null,
    env.apiBaseUrl,
    isFormData,
    formPayload ?? null,
  );
}

export function assertGuestApiDenied(response, { endpoint, label }) {
  assert.ok(
    DENIED_GUEST_STATUSES.has(response.status),
    `${label} (${endpoint}) must deny unauthenticated access with 401, 403, or 404; got HTTP ${response.status}. ` +
      `Body: ${String(response.body).slice(0, 240)}`,
  );
  assert.notEqual(
    response.status,
    200,
    `${label} (${endpoint}) must not return HTTP 200 without authentication.`,
  );
}

export function assertGuestResponseDoesNotExposeProtectedData(response, { label }) {
  const body = String(response.body || '').toLowerCase();

  assert.ok(
    !body.includes('"booking_id"') || response.status !== 200,
    `${label} must not return booking records to guests.`,
  );
  assert.ok(
    !body.includes('application/pdf') || response.status !== 200,
    `${label} must not return PDF content to guests.`,
  );
}

export async function attemptGuestPaymentSubmit(driver, bookingId) {
  const fileBase64 = readFileSync(PAYMENT_PROOF_FIXTURE).toString('base64');

  return guestApiRequest(driver, 'POST', `/vendor/bookings/${bookingId}/submit-payment`, {
    isFormData: true,
    formPayload: {
      base64: fileBase64,
      fieldName: 'payment_proof',
      fileName: 'payment-proof.png',
      mimeType: 'image/png',
    },
  });
}

export async function prepareGuestTestBookingId(driver) {
  const marker = e2eT7DGuestProtectionMarker();
  await ensureE2EBookingExists(driver, marker, { allowReuse: true });
  const bookingId = await resolveVendorBookingIdByMarker(driver, marker);
  assert.ok(bookingId > 0, 'Guest protection setup must resolve a vendor booking ID.');

  await ensureGuestSession(driver);
  return { bookingId, marker };
}

export async function assertBookingStillExistsForVendor(driver, bookingId, marker) {
  await loginAsVendor(driver);
  const response = await vendorApiRequest(driver, 'GET', `/vendor/bookings/${bookingId}`);

  assert.equal(
    response.status,
    200,
    `Booking #${bookingId} must still exist after guest denied attempts. Response: ${response.body?.slice(0, 240)}`,
  );

  const body = String(response.body || '').toLowerCase();
  assert.ok(
    body.includes(String(marker).toLowerCase()) || body.includes(String(bookingId)),
    `Vendor must still own booking #${bookingId} after guest denied attempts.`,
  );
}

export async function captureGuestFailureDiagnostics(driver, label, context = {}) {
  const meta = await captureFailureDiagnostics(driver, label);
  meta.guestContext = context;

  try {
    const loginInputs = await driver.findElements(By.css('[data-testid="login-email"]'));
    meta.loginFormVisible = false;
    for (const input of loginInputs) {
      if (await input.isDisplayed()) {
        meta.loginFormVisible = true;
        break;
      }
    }
  } catch (error) {
    meta.loginFormVisibleError = error.message;
  }

  return meta;
}
