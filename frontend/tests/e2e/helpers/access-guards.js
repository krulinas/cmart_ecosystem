import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsOrganizer, logout, managementApiRequest } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import {
  assertRowContainsMarker,
  getOrganizerBookingRowById,
  openOrganizerBookings,
  searchOrganizerBookings,
} from './organizer-bookings.js';

const API_BASE = 'http://127.0.0.1:8000/api';

export const ORGANIZER_APPROVE_ACTION_TEST_ID = 'organizer-booking-action-approve';
export const ORGANIZER_REJECT_ACTION_TEST_ID = 'organizer-booking-action-reject';
export const ORGANIZER_DELETE_ACTION_TEST_ID = 'organizer-booking-action-delete';

/** @deprecated Use ORGANIZER_APPROVE_ACTION_TEST_ID */
export const STAFF_FORWARD_ACTION_TEST_ID = 'organizer-booking-action-approve';
/** @deprecated Use ORGANIZER_APPROVE_ACTION_TEST_ID */
export const STAFF_APPROVE_ACTION_TEST_ID = 'organizer-booking-action-approve';
/** @deprecated Use ORGANIZER_REJECT_ACTION_TEST_ID */
export const STAFF_REJECT_ACTION_TEST_ID = 'organizer-booking-action-reject';
/** @deprecated Use ORGANIZER_DELETE_ACTION_TEST_ID */
export const STAFF_DELETE_ACTION_TEST_ID = 'organizer-booking-action-delete';

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

export async function organizerApiRequest(driver, method, endpoint, { body } = {}) {
  return managementApiRequest(driver, method, endpoint, { body });
}

/** @deprecated Use organizerApiRequest */
export async function staffApiRequest(driver, method, endpoint, options = {}) {
  return organizerApiRequest(driver, method, endpoint, options);
}

/** @deprecated Use organizerApiRequest */
export async function managerApiRequest(driver, method, endpoint, options = {}) {
  return organizerApiRequest(driver, method, endpoint, options);
}

export async function assertOrganizerBookingActionsVisible(driver, marker, { bookingId } = {}) {
  const row = await getOrganizerBookingRowById(driver, bookingId, {
    requireActionTestId: ORGANIZER_APPROVE_ACTION_TEST_ID,
  });
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  for (const testId of [ORGANIZER_APPROVE_ACTION_TEST_ID, ORGANIZER_REJECT_ACTION_TEST_ID]) {
    const buttons = await row.findElements(By.css(`[data-testid="${testId}"]`));
    let visible = false;

    for (const button of buttons) {
      if (await button.isDisplayed()) {
        visible = true;
        break;
      }
    }

    assert.ok(
      visible,
      `Organizer ${testId} must be visible for Pending_Organizer booking #${bookingId}.`,
    );
  }

  return { bookingId, row };
}

/** @deprecated Use assertOrganizerBookingActionsVisible */
export async function assertStaffSafeBookingActionsVisible(driver, marker, options = {}) {
  return assertOrganizerBookingActionsVisible(driver, marker, options);
}

/** @deprecated Use assertCmartManagementCannotAccessBookings */
export async function assertManagerOnlyBookingActionsAbsent() {
  throw new Error('assertManagerOnlyBookingActionsAbsent is deprecated in Phase 1.3C.');
}

export async function assertCmartManagementCannotAccessBookings(driver, bookingId) {
  const deleteResult = await managementApiRequest(driver, 'DELETE', `/bookings/${bookingId}`);
  assert.equal(deleteResult.ok, false);
  assert.equal(
    deleteResult.status,
    403,
    `cmart_management DELETE /api/bookings/${bookingId} must return 403; got ${deleteResult.status}.`,
  );

  const fetchResult = await managementApiRequest(driver, 'GET', `/bookings/${bookingId}`);
  assert.equal(fetchResult.ok, false);
  assert.equal(
    fetchResult.status,
    403,
    `cmart_management GET /api/bookings/${bookingId} must return 403; got ${fetchResult.status}.`,
  );

  const listResult = await managementApiRequest(driver, 'GET', '/bookings');
  assert.equal(listResult.ok, false);
  assert.equal(
    listResult.status,
    403,
    `cmart_management GET /api/bookings must return 403; got ${listResult.status}.`,
  );

  return { bookingId, deleteStatus: deleteResult.status };
}

/** @deprecated Use assertCmartManagementCannotAccessBookings */
export async function assertStaffCannotDeleteBooking(driver, bookingId, _marker) {
  return assertCmartManagementCannotAccessBookings(driver, bookingId);
}

export async function assertCmartManagementCannotAccessAnalyticsEndpoint(driver, endpoint, { label } = {}) {
  const result = await managementApiRequest(driver, 'GET', endpoint);

  assert.equal(
    result.ok,
    false,
    `cmart_management GET ${API_BASE}${endpoint} must be denied${label ? ` (${label})` : ''}. Response: ${result.body?.slice(0, 240)}`,
  );
  assert.equal(
    result.status,
    403,
    `Expected HTTP 403 for cmart_management GET ${endpoint}${label ? ` (${label})` : ''}, got ${result.status}.`,
  );

  return { endpoint, status: result.status };
}

/** @deprecated Use assertCmartManagementCannotAccessAnalyticsEndpoint */
export async function assertStaffCannotAccessManagerEndpoint(driver, endpoint, options = {}) {
  return assertCmartManagementCannotAccessAnalyticsEndpoint(driver, endpoint, options);
}

export async function assertOrganizerCanAccessEndpoint(driver, endpoint, { label } = {}) {
  const result = await organizerApiRequest(driver, 'GET', endpoint);

  assert.notEqual(
    result.status,
    401,
    `Organizer GET ${API_BASE}${endpoint} must not be unauthorized${label ? ` (${label})` : ''}.`,
  );
  assert.notEqual(
    result.status,
    403,
    `Organizer GET ${API_BASE}${endpoint} must not be forbidden${label ? ` (${label})` : ''}. Response: ${result.body?.slice(0, 240)}`,
  );
  assert.ok(
    result.ok,
    `Organizer GET ${API_BASE}${endpoint} should succeed${label ? ` (${label})` : ''}. Status: ${result.status}. Response: ${result.body?.slice(0, 240)}`,
  );

  return { endpoint, status: result.status };
}

/** @deprecated Use assertOrganizerCanAccessEndpoint */
export async function assertManagerCanAccessEndpoint(driver, endpoint, options = {}) {
  return assertOrganizerCanAccessEndpoint(driver, endpoint, options);
}

export async function prepareBookingForOrganizerReview(driver, marker, baseUrl = env.baseUrl) {
  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsOrganizer(driver);
  await openOrganizerBookings(driver, baseUrl);
  await searchOrganizerBookings(driver, marker);

  return { marker };
}

/** @deprecated Use prepareBookingForOrganizerReview */
export async function prepareBookingForManagerReview(driver, marker, baseUrl = env.baseUrl) {
  return prepareBookingForOrganizerReview(driver, marker, baseUrl);
}

/** @deprecated Use assertOrganizerBookingActionsVisible */
export async function assertManagerBookingActionsVisible(driver, marker, options = {}) {
  return assertOrganizerBookingActionsVisible(driver, marker, options);
}

export async function assertOrganizerDeleteControlVisibleInRegistry(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="organizer-booking-row"][data-booking-id="${bookingId}"]`),
  );

  let deleteVisible = false;

  for (const row of rows) {
    const deleteButtons = await row.findElements(By.css(`[data-testid="${ORGANIZER_DELETE_ACTION_TEST_ID}"]`));

    for (const button of deleteButtons) {
      if (await button.isDisplayed()) {
        deleteVisible = true;
        break;
      }
    }

    if (deleteVisible) break;
  }

  assert.ok(
    deleteVisible,
    `Organizer delete control should be visible in registry for booking #${bookingId}.`,
  );

  return { bookingId, deleteVisible };
}

/** @deprecated Use assertOrganizerDeleteControlVisibleInRegistry */
export async function assertManagerDeleteControlVisibleInRegistry(driver, bookingId) {
  return assertOrganizerDeleteControlVisibleInRegistry(driver, bookingId);
}
