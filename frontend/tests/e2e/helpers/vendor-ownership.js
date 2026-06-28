import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { fillInputValue } from './booking.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { PAYMENT_PROOF_FIXTURE } from './vendor-payment-records.js';
import { goToMyBookings } from './vendor-bookings.js';

export const DENIED_OWNERSHIP_STATUSES = new Set([403, 404]);

export async function vendorApiRequest(driver, method, endpoint, { body, isFormData = false, formPayload } = {}) {
  return driver.executeScript(
    async (httpMethod, path, payload, apiBase, useForm, formDataPayload) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) {
        throw new Error('No auth token available for vendor API request.');
      }

      const options = {
        method: httpMethod,
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
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

export function assertOwnershipDenied(response, { endpoint, label }) {
  assert.ok(
    DENIED_OWNERSHIP_STATUSES.has(response.status),
    `${label} (${endpoint}) must deny wrong-owner access with 403 or 404; got HTTP ${response.status}. ` +
      `Body: ${String(response.body).slice(0, 240)}`,
  );
  assert.notEqual(
    response.status,
    200,
    `${label} (${endpoint}) must not return HTTP 200 for another vendor's booking.`,
  );
}

export function assertResponseDoesNotExposePrivateData(response, { marker, bookingId } = {}) {
  const body = String(response.body || '').toLowerCase();
  if (marker) {
    assert.ok(
      !body.includes(marker.toLowerCase()),
      `Response must not expose Vendor B marker "${marker}". Snippet: ${body.slice(0, 240)}`,
    );
  }
  if (bookingId != null && response.status === 200) {
    const idPatterns = [
      `"booking_id":${bookingId}`,
      `"booking_id":"${bookingId}"`,
      `"id":${bookingId}`,
      `"id":"${bookingId}"`,
    ];
    const exposesBookingId = idPatterns.some((pattern) => body.includes(pattern));
    assert.ok(
      !exposesBookingId,
      `Response must not expose Vendor B booking #${bookingId}. Snippet: ${body.slice(0, 240)}`,
    );
  }
}

export async function resolveVendorBookingIdByMarker(driver, marker) {
  const response = await vendorApiRequest(driver, 'GET', '/vendor/bookings');
  assert.equal(response.status, 200, `GET /vendor/bookings failed: ${response.body?.slice(0, 240)}`);

  const bookings = JSON.parse(response.body);
  assert.ok(Array.isArray(bookings), 'Vendor bookings list must be an array.');

  for (const booking of bookings) {
    const details = String(booking.product_details || booking.details || '').toLowerCase();
    if (details.includes(marker.toLowerCase())) {
      return Number(booking.id ?? booking.booking_id);
    }
  }

  throw new Error(`No vendor booking found with marker "${marker}" in GET /vendor/bookings.`);
}

export async function assertVendorMarkerNotInMyBookings(driver, marker) {
  await goToMyBookings(driver, env.baseUrl);
  await fillInputValue(driver, 'booking-search', marker);
  await driver.sleep(500);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      for (const row of rows) {
        if (!(await row.isDisplayed())) continue;
        const text = (await row.getText()).toLowerCase();
        if (text.includes(marker.toLowerCase())) {
          return false;
        }
      }
      return true;
    },
    8000,
    `Vendor B marker "${marker}" must not appear in Vendor A My Bookings.`,
  );
}

export async function assertVendorBookingRowAbsentById(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="booking-list-item"][data-booking-id="${bookingId}"]`),
  );

  for (const row of rows) {
    if (await row.isDisplayed()) {
      assert.fail(`Vendor A must not see booking #${bookingId} in My Bookings.`);
    }
  }
}

export async function assertVendorBookingsListExcludes(driver, { bookingId, marker }) {
  const response = await vendorApiRequest(driver, 'GET', '/vendor/bookings');
  assert.equal(response.status, 200, `GET /vendor/bookings failed: ${response.body?.slice(0, 240)}`);

  const bookings = JSON.parse(response.body);
  for (const booking of bookings) {
    const id = Number(booking.id ?? booking.booking_id);
    const details = String(booking.product_details || booking.details || '').toLowerCase();
    assert.notEqual(id, bookingId, `Vendor A list must not include Vendor B booking #${bookingId}.`);
    assert.ok(
      !details.includes(marker.toLowerCase()),
      `Vendor A list must not include Vendor B marker "${marker}".`,
    );
  }
}

export async function assertVendorHistoryReceiptsExclude(driver, bookingId, marker) {
  const response = await vendorApiRequest(driver, 'GET', '/vendor/history-receipts');
  assert.equal(response.status, 200, `GET /vendor/history-receipts failed: ${response.body?.slice(0, 240)}`);

  const payload = JSON.parse(response.body);
  const records = Array.isArray(payload.records) ? payload.records : [];

  for (const record of records) {
    const recordBookingId = Number(record.booking_id ?? record.bookingId);
    assert.notEqual(
      recordBookingId,
      bookingId,
      `Vendor A receipts must not include Vendor B booking #${bookingId}.`,
    );
  }

  assertResponseDoesNotExposePrivateData(response, { marker, bookingId });
}

export async function assertVendorEventPassesListExcludes(driver, bookingId, marker) {
  const response = await vendorApiRequest(driver, 'GET', '/vendor/event-passes');
  assert.equal(response.status, 200, `GET /vendor/event-passes failed: ${response.body?.slice(0, 240)}`);

  const payload = JSON.parse(response.body);
  const passes = [...(payload.upcoming || []), ...(payload.archived || [])];

  for (const pass of passes) {
    const passBookingId = Number(pass.booking_id ?? pass.bookingId);
    assert.notEqual(
      passBookingId,
      bookingId,
      `Vendor A event passes must not include Vendor B booking #${bookingId}.`,
    );
  }

  assertResponseDoesNotExposePrivateData(response, { marker, bookingId });
}

export async function attemptVendorPaymentSubmitForBookingId(driver, bookingId) {
  const fileBase64 = readFileSync(PAYMENT_PROOF_FIXTURE).toString('base64');

  return vendorApiRequest(driver, 'POST', `/vendor/bookings/${bookingId}/submit-payment`, {
    isFormData: true,
    formPayload: {
      base64: fileBase64,
      fieldName: 'payment_proof',
      fileName: 'payment-proof.png',
      mimeType: 'image/png',
    },
  });
}

export async function captureOwnershipFailureDiagnostics(driver, label, context = {}) {
  const meta = await captureFailureDiagnostics(driver, label);
  meta.ownershipContext = context;

  try {
    meta.currentUser = await driver.executeScript(() => {
      const raw = localStorage.getItem('carboot_cmart_user');
      if (!raw) return null;
      try {
        const user = JSON.parse(raw);
        return { email: user.email, role: user.role };
      } catch {
        return { raw: raw.slice(0, 120) };
      }
    });
  } catch (error) {
    meta.currentUserError = error.message;
  }

  return meta;
}
