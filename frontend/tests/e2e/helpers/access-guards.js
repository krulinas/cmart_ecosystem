import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import {
  assertRowContainsMarker,
  getStaffBookingRowById,
  searchStaffBookings,
} from './staff-bookings.js';

const API_BASE = 'http://127.0.0.1:8000/api';

export const STAFF_FORWARD_ACTION_TEST_ID = 'staff-booking-action-forward';
export const STAFF_APPROVE_ACTION_TEST_ID = 'staff-booking-action-approve';
export const STAFF_DELETE_ACTION_TEST_ID = 'staff-booking-action-delete';

export async function assertTestIdAbsent(driver, testId, { root, timeoutMs = 5000 } = {}) {
  await driver.wait(
    async () => {
      const elements = root
        ? await root.findElements(By.css(`[data-testid="${testId}"]`))
        : await driver.findElements(By.css(`[data-testid="${testId}"]`));

      for (const element of elements) {
        try {
          if (await element.isDisplayed()) {
            return false;
          }
        } catch (error) {
          if (error.name !== 'StaleElementReferenceError') {
            throw error;
          }
        }
      }

      return true;
    },
    timeoutMs,
    `[data-testid="${testId}"] must not be visible.`,
  );
}

export async function staffApiRequest(driver, method, endpoint, { body } = {}) {
  return driver.executeScript(
    async (httpMethod, path, payload) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) {
        throw new Error('No auth token available for staff API request.');
      }

      const options = {
        method: httpMethod,
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      };

      if (payload != null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
      }

      const response = await fetch(`http://127.0.0.1:8000/api${path}`, options);

      return {
        ok: response.ok,
        status: response.status,
        body: await response.text(),
      };
    },
    method,
    endpoint,
    body ?? null,
  );
}

export async function assertStaffSafeBookingActionsVisible(driver, marker, { bookingId } = {}) {
  const row = await getStaffBookingRowById(driver, bookingId, {
    requireActionTestId: STAFF_FORWARD_ACTION_TEST_ID,
  });
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  const forwardButtons = await row.findElements(By.css(`[data-testid="${STAFF_FORWARD_ACTION_TEST_ID}"]`));
  let forwardVisible = false;

  for (const button of forwardButtons) {
    if (await button.isDisplayed()) {
      forwardVisible = true;
      break;
    }
  }

  assert.ok(
    forwardVisible,
    `Staff forward action must be visible for Pending_Staff booking #${bookingId}.`,
  );

  const revisionButtons = await row.findElements(By.css('[data-testid="staff-booking-action-needs-revision"]'));
  let revisionVisible = false;

  for (const button of revisionButtons) {
    if (await button.isDisplayed()) {
      revisionVisible = true;
      break;
    }
  }

  assert.ok(
    revisionVisible,
    `Staff revision action must be visible for Pending_Staff booking #${bookingId}.`,
  );

  return { bookingId, row };
}

export async function assertManagerOnlyBookingActionsAbsent(driver, marker, { bookingId } = {}) {
  await assertTestIdAbsent(driver, STAFF_DELETE_ACTION_TEST_ID);

  const row = await getStaffBookingRowById(driver, bookingId);
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  await assertTestIdAbsent(driver, STAFF_APPROVE_ACTION_TEST_ID, { root: row });
  await assertTestIdAbsent(driver, STAFF_DELETE_ACTION_TEST_ID, { root: row });

  const rowDeleteButtons = await row.findElements(
    By.xpath(".//button[contains(normalize-space(.), 'Delete')]"),
  );

  for (const button of rowDeleteButtons) {
    assert.equal(
      await button.isDisplayed(),
      false,
      `Delete booking control must not be visible to staff for booking #${bookingId}.`,
    );
  }
}

export async function assertStaffCannotDeleteBooking(driver, bookingId, marker) {
  const deleteResult = await staffApiRequest(driver, 'DELETE', `/bookings/${bookingId}`);

  assert.equal(
    deleteResult.ok,
    false,
    `Staff DELETE /api/bookings/${bookingId} must be denied. Response: ${deleteResult.body?.slice(0, 240)}`,
  );
  assert.equal(
    deleteResult.status,
    403,
    `Expected HTTP 403 for staff DELETE /api/bookings/${bookingId}, got ${deleteResult.status}.`,
  );

  const fetchResult = await staffApiRequest(driver, 'GET', `/bookings/${bookingId}`);
  assert.equal(
    fetchResult.ok,
    true,
    `Booking #${bookingId} must still exist after denied staff DELETE. Response: ${fetchResult.body?.slice(0, 240)}`,
  );

  const booking = JSON.parse(fetchResult.body);
  const bookingRecord = booking.booking ?? booking;
  const details = String(bookingRecord.product_details || '').toLowerCase();

  assert.ok(
    details.includes(String(marker).toLowerCase()),
    `Booking #${bookingId} must still contain the E2E marker after denied staff DELETE.`,
  );

  await searchStaffBookings(driver, marker);
  const row = await getStaffBookingRowById(driver, bookingId);
  assertRowContainsMarker((await row.getText()).toLowerCase(), marker, bookingId);

  return { bookingId, deleteStatus: deleteResult.status };
}

export async function assertStaffCannotAccessManagerEndpoint(driver, endpoint, { label } = {}) {
  const result = await staffApiRequest(driver, 'GET', endpoint);

  assert.equal(
    result.ok,
    false,
    `Staff GET ${API_BASE}${endpoint} must be denied${label ? ` (${label})` : ''}. Response: ${result.body?.slice(0, 240)}`,
  );
  assert.equal(
    result.status,
    403,
    `Expected HTTP 403 for staff GET ${endpoint}${label ? ` (${label})` : ''}, got ${result.status}.`,
  );

  return { endpoint, status: result.status };
}
