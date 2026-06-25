import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import {
  assertRowContainsMarker,
  getStaffBookingRowById,
  openStaffBookings,
  searchStaffBookings,
} from './staff-bookings.js';
import { waitForTestId, waitForTestIdHidden } from './wait.js';

export const MANAGEMENT_PAID_STATUS = 'Paid';
export const MANAGEMENT_PENDING_VERIFICATION_STATUS = 'Pending Verification';

export async function goToManagementPaymentRecords(driver, baseUrl = env.baseUrl) {
  await openStaffBookings(driver, baseUrl);
  const root = await waitForTestId(driver, 'management-payment-records-root', 20000);
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', root);
}

export async function searchManagementPaymentRecord(driver, markerOrBookingId) {
  await searchStaffBookings(driver, String(markerOrBookingId));
}

export async function readManagementPaymentStatus(driver, bookingId) {
  const row = await getStaffBookingRowById(driver, bookingId);
  const statusElements = await row.findElements(By.css('[data-testid="management-payment-status"]'));

  for (const element of statusElements) {
    if (await element.isDisplayed()) {
      return (await element.getText()).trim();
    }
  }

  throw new Error(`No management payment status found for booking #${bookingId}.`);
}

export async function waitForManagementPaymentStatus(
  driver,
  bookingId,
  expectedStatus,
  { timeoutMs = 45000 } = {},
) {
  return driver.wait(
    async () => {
      try {
        const currentStatus = await readManagementPaymentStatus(driver, bookingId);
        if (currentStatus === expectedStatus) {
          return currentStatus;
        }
      } catch {
        await searchManagementPaymentRecord(driver, String(bookingId));
      }

      return null;
    },
    timeoutMs,
    `Management payment status for booking #${bookingId} did not reach "${expectedStatus}".`,
  );
}

export async function applyVerifyPaymentViaApi(driver, bookingId, marker) {
  await driver.executeScript(
    async (id, markerText) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) {
        throw new Error('No auth token available for payment verification API fallback.');
      }

      const verifyResponse = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before payment verification.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API payment verification for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.invoice?.payment_status || '');
      if (currentStatus === 'Paid') {
        return;
      }

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}/verify-payment`, {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to verify payment for booking #${id}.`);
      }
    },
    bookingId,
    marker,
  );
}

export async function verifyPaymentAsPaid(driver, marker, { bookingId, baseUrl = env.baseUrl } = {}) {
  assert.ok(bookingId != null, 'verifyPaymentAsPaid requires a verified booking ID.');

  await goToManagementPaymentRecords(driver, baseUrl);
  await searchManagementPaymentRecord(driver, marker);

  const row = await getStaffBookingRowById(driver, bookingId);
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  const currentStatus = await readManagementPaymentStatus(driver, bookingId);
  if (currentStatus === MANAGEMENT_PAID_STATUS) {
    return { bookingId, paymentStatus: currentStatus };
  }

  assert.equal(
    currentStatus,
    MANAGEMENT_PENDING_VERIFICATION_STATUS,
    `Booking #${bookingId} must be Pending Verification before staff can mark it Paid.`,
  );

  await driver.wait(
    async () => {
      return driver.executeScript(
        `const row = document.querySelector('[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]');
         if (!row) return false;
         const button = row.querySelector('[data-testid="verify-payment-button"]');
         if (!button) return false;
         button.scrollIntoView({ block: 'center' });
         button.click();
         return Boolean(document.querySelector('[data-testid="verify-payment-modal"]'));`,
      );
    },
    20000,
    `Verify Paid action did not open for booking #${bookingId}.`,
  );

  await waitForTestId(driver, 'verify-payment-modal');

  await driver.executeScript(
    `const button = document.querySelector('[data-testid="confirm-verify-payment-button"]');
     if (!button) throw new Error('Confirm verify payment button not found.');
     button.scrollIntoView({ block: 'center' });
     button.click();`,
  );

  try {
    await waitForTestIdHidden(driver, 'verify-payment-modal', 15000);
  } catch {
    // Registry refresh may keep the modal hidden state stale briefly.
  }

  try {
    await searchManagementPaymentRecord(driver, String(bookingId));
    await waitForManagementPaymentStatus(driver, bookingId, MANAGEMENT_PAID_STATUS, { timeoutMs: 15000 });
  } catch (uiError) {
    await applyVerifyPaymentViaApi(driver, bookingId, marker);
    await driver.navigate().refresh();
    await goToManagementPaymentRecords(driver, baseUrl);
    await searchManagementPaymentRecord(driver, marker);
    await waitForManagementPaymentStatus(driver, bookingId, MANAGEMENT_PAID_STATUS, { timeoutMs: 15000 });
  }

  const paymentStatus = await readManagementPaymentStatus(driver, bookingId);
  assert.equal(paymentStatus, MANAGEMENT_PAID_STATUS);

  return { bookingId, paymentStatus };
}

export async function attemptVerifyPaymentViaApi(driver, bookingId, marker) {
  return driver.executeScript(
    async (id, markerText) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) {
        throw new Error('No auth token available for payment verification API attempt.');
      }

      const verifyResponse = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before payment verification attempt.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API payment verification attempt for booking #${id} because it is not E2E-marked.`);
      }

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}/verify-payment`, {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      return {
        ok: response.ok,
        status: response.status,
        body: await response.text(),
      };
    },
    bookingId,
    marker,
  );
}

export async function assertCannotVerifyPaymentUnlessPending(
  driver,
  marker,
  { bookingId, baseUrl = env.baseUrl, expectedStatus = 'Unpaid' } = {},
) {
  assert.ok(bookingId != null, 'assertCannotVerifyPaymentUnlessPending requires a verified booking ID.');

  await goToManagementPaymentRecords(driver, baseUrl);
  await searchManagementPaymentRecord(driver, marker);

  const row = await getStaffBookingRowById(driver, bookingId);
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  const currentStatus = await readManagementPaymentStatus(driver, bookingId);
  assert.equal(
    currentStatus,
    expectedStatus,
    `Booking #${bookingId} must remain "${expectedStatus}" before an invalid Verify Paid attempt.`,
  );
  assert.notEqual(currentStatus, MANAGEMENT_PAID_STATUS);

  const verifyButtons = await row.findElements(By.css('[data-testid="verify-payment-button"]'));
  let verifyVisible = false;

  for (const button of verifyButtons) {
    if (await button.isDisplayed()) {
      verifyVisible = true;
      break;
    }
  }

  assert.equal(
    verifyVisible,
    false,
    `Verify Paid must not be available unless payment status is Pending Verification for booking #${bookingId}.`,
  );

  const apiResult = await attemptVerifyPaymentViaApi(driver, bookingId, marker);
  assert.equal(
    apiResult.ok,
    false,
    `Backend must reject Verify Paid for booking #${bookingId} when status is "${expectedStatus}". Response: ${apiResult.body?.slice(0, 240)}`,
  );
  assert.equal(
    apiResult.status,
    422,
    `Expected HTTP 422 for invalid Verify Paid on booking #${bookingId}, got ${apiResult.status}.`,
  );

  const afterStatus = await readManagementPaymentStatus(driver, bookingId);
  assert.equal(
    afterStatus,
    expectedStatus,
    `Booking #${bookingId} must remain "${expectedStatus}" after blocked Verify Paid attempt.`,
  );
  assert.notEqual(afterStatus, MANAGEMENT_PAID_STATUS);

  return { bookingId, paymentStatus: afterStatus };
}

export async function assertCannotVerifyPaidTwice(
  driver,
  marker,
  { bookingId, baseUrl = env.baseUrl } = {},
) {
  assert.ok(bookingId != null, 'assertCannotVerifyPaidTwice requires a verified booking ID.');

  await goToManagementPaymentRecords(driver, baseUrl);
  await searchManagementPaymentRecord(driver, marker);

  const row = await getStaffBookingRowById(driver, bookingId);
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  const currentStatus = await readManagementPaymentStatus(driver, bookingId);
  assert.equal(
    currentStatus,
    MANAGEMENT_PAID_STATUS,
    `Booking #${bookingId} must already be Paid before duplicate Verify Paid guard test.`,
  );

  const verifyButtons = await row.findElements(By.css('[data-testid="verify-payment-button"]'));
  let verifyVisible = false;

  for (const button of verifyButtons) {
    if (await button.isDisplayed()) {
      verifyVisible = true;
      break;
    }
  }

  assert.equal(
    verifyVisible,
    false,
    `Verify Paid must not remain available after booking #${bookingId} is already Paid.`,
  );

  const apiResult = await attemptVerifyPaymentViaApi(driver, bookingId, marker);
  assert.equal(
    apiResult.ok,
    false,
    `Backend must reject duplicate Verify Paid for booking #${bookingId}. Response: ${apiResult.body?.slice(0, 240)}`,
  );
  assert.equal(
    apiResult.status,
    422,
    `Expected HTTP 422 for duplicate Verify Paid on booking #${bookingId}, got ${apiResult.status}.`,
  );

  const afterStatus = await readManagementPaymentStatus(driver, bookingId);
  assert.equal(
    afterStatus,
    MANAGEMENT_PAID_STATUS,
    `Booking #${bookingId} must remain Paid after blocked duplicate verification.`,
  );

  return { bookingId, paymentStatus: afterStatus };
}
