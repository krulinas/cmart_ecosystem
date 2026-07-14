import { strict as assert } from 'node:assert';
import { By, until } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { fillInputValue } from './booking.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { withPromptAnswer } from './prompt.js';
import { waitForTestId } from './wait.js';

export const PENDING_ORGANIZER_EXPECTATION = {
  attrs: new Set(['Pending_Organizer']),
  labels: new Set(['Pending Organizer Review', 'Organizer Review', 'Pending']),
};

export const REVISION_EXPECTATION = {
  attrs: new Set(['Needs_Revision']),
  labels: new Set(['Needs Revision']),
};

export const APPROVED_EXPECTATION = {
  attrs: new Set(['Approved']),
  labels: new Set(['Approved']),
};

export const REJECTED_EXPECTATION = {
  attrs: new Set(['Rejected']),
  labels: new Set(['Rejected']),
};

const APPROVE_ACTION_TEST_ID = 'organizer-booking-action-approve';
const REJECT_ACTION_TEST_ID = 'organizer-booking-action-reject';
const REVISION_ACTION_TEST_ID = 'organizer-booking-action-needs-revision';

export { APPROVE_ACTION_TEST_ID, REJECT_ACTION_TEST_ID, REVISION_ACTION_TEST_ID };

export async function waitForOrganizerBookingRows(driver, timeoutMs = 20000) {
  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="organizer-booking-row"]'));
      return rows.length > 0;
    },
    timeoutMs,
    'Organizer bookings list did not load any rows.',
  );
}

export async function searchOrganizerBookings(driver, searchText) {
  await fillInputValue(driver, 'organizer-bookings-search', searchText);

  // Registry search is debounced (~300ms) before the API refresh runs.
  await driver.sleep(500);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="organizer-booking-row"]'));
      for (const row of rows) {
        const text = (await row.getText()).toLowerCase();
        if (text.includes(searchText.toLowerCase())) {
          return true;
        }
      }
      return false;
    },
    20000,
    `No organizer booking row matched search text "${searchText}".`,
  );
}

export async function findE2EBookingRow(driver, marker, { preferActionTestId, bookingId } = {}) {
  await searchOrganizerBookings(driver, marker);

  const rows = await driver.findElements(By.css('[data-testid="organizer-booking-row"]'));
  const matches = [];

  for (const row of rows) {
    try {
      const text = (await row.getText()).toLowerCase();
      if (!text.includes(marker.toLowerCase())) continue;

      const rowBookingId = Number(await row.getAttribute('data-booking-id'));
      if (bookingId != null && rowBookingId !== bookingId) continue;
      const section = await row.getAttribute('data-booking-section');
      const hasRevisionAction =
        (await row.findElements(By.css(`[data-testid="${REVISION_ACTION_TEST_ID}"]`))).length > 0;
      const hasApproveAction =
        (await row.findElements(By.css(`[data-testid="${APPROVE_ACTION_TEST_ID}"]`))).length > 0;
      const hasRejectAction =
        (await row.findElements(By.css(`[data-testid="${REJECT_ACTION_TEST_ID}"]`))).length > 0;

      matches.push({
        bookingId: rowBookingId,
        section,
        hasRevisionAction,
        hasApproveAction,
        hasRejectAction,
        status: await row.getAttribute('data-booking-status'),
        text,
      });
    } catch (error) {
      if (error.name === 'StaleElementReferenceError') continue;
      throw error;
    }
  }

  if (!matches.length) {
    throw new Error(
      `No E2E-marked booking found for marker "${marker}"` +
        (bookingId != null ? ` (booking #${bookingId})` : '') +
        '. Only bookings containing the E2E marker are eligible for this test.',
    );
  }

  matches.sort((a, b) => {
    const actionScore = (match) => {
      let score = match.section === 'queue' ? 8 : 0;
      if (preferActionTestId === APPROVE_ACTION_TEST_ID) {
        score += match.hasApproveAction ? 4 : 0;
      } else if (preferActionTestId === REVISION_ACTION_TEST_ID) {
        score += match.hasRevisionAction ? 4 : 0;
      } else if (preferActionTestId === REJECT_ACTION_TEST_ID) {
        score += match.hasRejectAction ? 4 : 0;
      } else {
        score +=
          (match.hasRevisionAction ? 4 : 0) +
          (match.hasApproveAction ? 2 : 0) +
          (match.hasRejectAction ? 1 : 0);
      }
      return score;
    };

    const scoreDiff = actionScore(b) - actionScore(a);
    if (scoreDiff !== 0) return scoreDiff;
    return b.bookingId - a.bookingId;
  });

  return matches[0];
}

export function statusMatchesExpectation(statusAttr, statusLabel, expectation) {
  return expectation.attrs.has(statusAttr) || expectation.labels.has(statusLabel);
}

export async function getOrganizerBookingRowById(driver, bookingId, { requireActionTestId } = {}) {
  const rows = await driver.findElements(
    By.css(`[data-testid="organizer-booking-row"][data-booking-id="${bookingId}"]`),
  );

  for (const row of rows) {
    if (!requireActionTestId) return row;

    const actions = await row.findElements(By.css(`[data-testid="${requireActionTestId}"]`));
    if (actions.length) return row;
  }

  if (requireActionTestId) {
    throw new Error(
      `No actionable organizer queue row found for booking #${bookingId} ` +
        `(expected action: ${requireActionTestId}).`,
    );
  }

  if (!rows.length) {
    throw new Error(`No organizer booking row found for booking #${bookingId}.`);
  }

  return rows[0];
}

async function readRowStatusFromElement(row) {
  const statusAttr = await row.getAttribute('data-booking-status');
  const statusLabel = (await row.findElement(By.css('[data-testid="organizer-booking-status"]')).getText()).trim();
  const section = await row.getAttribute('data-booking-section');
  return { statusAttr, statusLabel, section };
}

export async function readAllRowStatusesById(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="organizer-booking-row"][data-booking-id="${bookingId}"]`),
  );

  const statuses = [];
  for (const row of rows) {
    statuses.push(await readRowStatusFromElement(row));
  }
  return statuses;
}

export async function readRowStatusById(driver, bookingId) {
  const statuses = await readAllRowStatusesById(driver, bookingId);
  if (!statuses.length) {
    throw new Error(`No organizer booking row found for booking #${bookingId}.`);
  }

  const attrPriority = ['Approved', 'Rejected', 'Needs_Revision', 'Pending_Organizer'];
  for (const attr of attrPriority) {
    const match = statuses.find((entry) => entry.statusAttr === attr);
    if (match) return match;
  }

  return statuses[0];
}

export async function waitForRowStatus(driver, bookingId, expectation, timeoutMs = 20000) {
  return driver.wait(
    async () => {
      const { statusAttr, statusLabel } = await readRowStatusById(driver, bookingId);
      if (statusMatchesExpectation(statusAttr, statusLabel, expectation)) {
        return { statusAttr, statusLabel };
      }
      return null;
    },
    timeoutMs,
    `Booking #${bookingId} did not reach an expected status.`,
  );
}

export function assertRowContainsMarker(rowText, marker, bookingId) {
  assert.ok(
    rowText.toLowerCase().includes(marker.toLowerCase()),
    `Refusing to act on booking #${bookingId} because the row does not contain the E2E marker.`,
  );
}

export async function clickOrganizerQueueAction(driver, bookingId, actionTestId, marker) {
  const row = await getOrganizerBookingRowById(driver, bookingId, { requireActionTestId: actionTestId });
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, bookingId);

  const actionButton = await row.findElement(By.css(`[data-testid="${actionTestId}"]`));
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', actionButton);
  await driver.wait(until.elementIsVisible(actionButton), 10000, `${actionTestId} is not visible.`);
  await driver.wait(until.elementIsEnabled(actionButton), 10000, `${actionTestId} is not enabled.`);
  await driver.executeScript('arguments[0].click();', actionButton);
}

async function waitForBookingActionToast(driver, bookingId, statusLabel, timeoutMs = 20000) {
  const needle = statusLabel.toLowerCase();
  return driver.wait(
    async () => {
      const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
      for (const toast of toasts) {
        const text = (await toast.getText()).toLowerCase();
        if (text.includes(`booking #${bookingId}`) && text.includes(needle)) {
          return true;
        }
      }
      return null;
    },
    timeoutMs,
    `Toast for booking #${bookingId} (${statusLabel}) did not appear.`,
  );
}

async function waitForApiBookingStatus(
  driver,
  bookingId,
  marker,
  expectation,
  timeoutMs = 20000,
  { readStatus = readOrganizerBookingStatusViaApi } = {},
) {
  return driver.wait(
    async () => {
      const status = await readStatus(driver, bookingId, marker);
      if (status && expectation.attrs.has(status)) {
        return status;
      }
      return null;
    },
    timeoutMs,
    `Booking #${bookingId} did not reach API status ${[...expectation.attrs].join('|')}.`,
  );
}

/** Poll booking status via API until expectation is met (organizer endpoint by default). */
export async function waitForBookingStatus(
  driver,
  bookingId,
  marker,
  expectation,
  timeoutMs = 20000,
  options = {},
) {
  return waitForApiBookingStatus(driver, bookingId, marker, expectation, timeoutMs, options);
}

async function reloadOrganizerBookingsPanel(driver, baseUrl, marker) {
  await driver.get(`${baseUrl}/admin#bookings`);
  await waitForTestId(driver, 'management-dashboard-root');
  await waitForTestId(driver, 'organizer-bookings-root');
  await waitForOrganizerBookingRows(driver);
  await searchOrganizerBookings(driver, marker);
}

async function captureApproveFailureDiagnostics(driver, bookingId, marker, phase) {
  const meta = await captureFailureDiagnostics(driver, `approve-${phase}-${bookingId}`);

  try {
    meta.bookingId = bookingId;
    meta.marker = marker;
    meta.rowStatuses = await readAllRowStatusesById(driver, bookingId);
    meta.apiStatus = await readE2EBookingStatusViaApi(driver, bookingId, marker);

    const approveButtons = await driver.findElements(
      By.css(`[data-testid="${APPROVE_ACTION_TEST_ID}"][data-booking-id="${bookingId}"]`),
    );
    meta.approveButtons = [];

    for (const button of approveButtons) {
      meta.approveButtons.push({
        displayed: await button.isDisplayed(),
        enabled: await button.isEnabled(),
      });
    }
  } catch (error) {
    meta.approveContextError = error.message;
  }

  return meta;
}

export async function readOrganizerBookingStatusViaApi(driver, bookingId, marker) {
  const apiBase = env.apiBaseUrl;
  return driver.executeScript(
    async (id, markerText, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) return null;

      const response = await fetch(`${base}/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) return null;

      const payload = await response.json();
      const bookingRecord = payload.booking ?? payload;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) return null;
      return String(bookingRecord.approval_status || '');
    },
    bookingId,
    marker,
    apiBase,
  );
}

export async function readVendorBookingStatusViaApi(driver, bookingId, marker) {
  const apiBase = env.apiBaseUrl;
  return driver.executeScript(
    async (id, markerText, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      if (!token) return null;

      const response = await fetch(`${base}/vendor/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) return null;

      const bookingRecord = await response.json();
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) return null;
      return String(bookingRecord.approval_status || '');
    },
    bookingId,
    marker,
    apiBase,
  );
}

/** @deprecated Use readOrganizerBookingStatusViaApi or readVendorBookingStatusViaApi */
export async function readE2EBookingStatusViaApi(driver, bookingId, marker) {
  return readOrganizerBookingStatusViaApi(driver, bookingId, marker);
}

export async function applyApproveViaApi(driver, bookingId, marker) {
  const apiBase = env.apiBaseUrl;
  await driver.executeScript(
    async (id, markerText, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`${base}/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before API approve.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API approve for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.approval_status || '');
      if (currentStatus === 'Approved') {
        return;
      }

      if (currentStatus !== 'Pending_Organizer') {
        throw new Error(
          `Refusing API approve for booking #${id}: expected Pending_Organizer, got "${currentStatus}".`,
        );
      }

      const response = await fetch(`${base}/bookings/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ approval_status: 'Approved' }),
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to approve booking #${id}.`);
      }
    },
    bookingId,
    marker,
    apiBase,
  );
}

export async function applyRevisionViaApi(driver, bookingId, marker, revisionComment) {
  const apiBase = env.apiBaseUrl;
  await driver.executeScript(
    async (id, markerText, note, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`${base}/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before API revision.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API revision for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.approval_status || '');
      if (currentStatus === 'Needs_Revision') {
        return;
      }

      const response = await fetch(`${base}/bookings/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          approval_status: 'Needs_Revision',
          revision_comment: note,
        }),
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to update booking #${id} to Needs_Revision.`);
      }
    },
    bookingId,
    marker,
    revisionComment,
    apiBase,
  );
}

export async function applyResubmitViaApi(driver, bookingId, marker) {
  const apiBase = env.apiBaseUrl;
  await driver.executeScript(
    async (id, markerText, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`${base}/vendor/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before API resubmit.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API resubmit for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.approval_status || '');
      if (currentStatus === 'Pending_Organizer') {
        return;
      }

      const response = await fetch(`${base}/vendor/bookings/${id}/resubmit`, {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to resubmit booking #${id}.`);
      }
    },
    bookingId,
    marker,
    apiBase,
  );
}

export async function openOrganizerBookings(driver, baseUrl) {
  await driver.get(`${baseUrl}/admin#bookings`);
  await waitForTestId(driver, 'management-dashboard-root');
  await waitForTestId(driver, 'organizer-bookings-root');
  await waitForOrganizerBookingRows(driver);
}

export async function approveOrganizerBooking(driver, marker, baseUrl, { bookingId: expectedBookingId } = {}) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: APPROVE_ACTION_TEST_ID,
    bookingId: expectedBookingId,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, APPROVED_EXPECTATION)) {
    const confirmedRow = await getOrganizerBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial, usedApiFallback: false };
  }

  if (!statusMatchesExpectation(initial.statusAttr, initial.statusLabel, PENDING_ORGANIZER_EXPECTATION)) {
    throw new Error(
      `Booking #${match.bookingId} is not organizer-pending. ` +
        `Current status: ${initial.statusAttr || initial.statusLabel}. ` +
        'Create a fresh Pending_Organizer E2E booking first.',
    );
  }

  assert.ok(
    match.hasApproveAction,
    `Booking #${match.bookingId} has no Approve button in the organizer queue. ` +
      `Current status: ${match.status}. Ensure organizer credentials are used.`,
  );

  const confirmedRow = await getOrganizerBookingRowById(driver, match.bookingId, {
    requireActionTestId: APPROVE_ACTION_TEST_ID,
  });
  assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);

  let usedApiFallback = false;
  await clickOrganizerQueueAction(driver, match.bookingId, APPROVE_ACTION_TEST_ID, marker);

  try {
    await waitForBookingActionToast(driver, match.bookingId, 'Approved', 20000);
  } catch {
    // Toast may clear quickly; API poll below is authoritative.
  }

  let apiApproved = false;
  try {
    await waitForApiBookingStatus(driver, match.bookingId, marker, APPROVED_EXPECTATION, 25000);
    apiApproved = true;
  } catch {
    apiApproved = false;
  }

  if (!apiApproved) {
    usedApiFallback = true;
    await applyApproveViaApi(driver, match.bookingId, marker);
    await waitForApiBookingStatus(driver, match.bookingId, marker, APPROVED_EXPECTATION, 15000);
  }

  await reloadOrganizerBookingsPanel(driver, baseUrl, marker);

  try {
    const updated = await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 20000);
    return { bookingId: match.bookingId, ...updated, usedApiFallback };
  } catch (finalError) {
    const apiStatus = await readE2EBookingStatusViaApi(driver, match.bookingId, marker);
    if (apiStatus !== 'Approved') {
      const diagnostics = await captureApproveFailureDiagnostics(driver, match.bookingId, marker, 'failed');
      const current = await readRowStatusById(driver, match.bookingId);
      throw new Error(
        `Organizer approve failed for booking #${match.bookingId}. ` +
          `UI status: ${current.statusAttr || current.statusLabel}. API status: ${apiStatus || 'unknown'}. ` +
          `Diagnostics: ${diagnostics.json}.`,
        { cause: finalError },
      );
    }

    await reloadOrganizerBookingsPanel(driver, baseUrl, marker);
    const updated = await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 15000);
    return { bookingId: match.bookingId, ...updated, usedApiFallback };
  }
}

export async function requestOrganizerRevision(
  driver,
  marker,
  baseUrl,
  { bookingId: expectedBookingId, revisionComment = 'E2E automated revision request - safe to ignore' } = {},
) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: REVISION_ACTION_TEST_ID,
    bookingId: expectedBookingId,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, REVISION_EXPECTATION)) {
    const confirmedRow = await getOrganizerBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial };
  }

  assert.ok(
    match.hasRevisionAction,
    `Booking #${match.bookingId} is not eligible for revision from the organizer queue. Current status: ${match.status}.`,
  );

  const row = await getOrganizerBookingRowById(driver, match.bookingId, {
    requireActionTestId: REVISION_ACTION_TEST_ID,
  });
  const rowText = (await row.getText()).toLowerCase();
  assertRowContainsMarker(rowText, marker, match.bookingId);

  const revisionButton = await row.findElement(By.css(`[data-testid="${REVISION_ACTION_TEST_ID}"]`));
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', revisionButton);

  await withPromptAnswer(driver, revisionComment, async () => {
    await revisionButton.click();
  });

  try {
    await searchOrganizerBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, REVISION_EXPECTATION, 10000);
  } catch (uiError) {
    await applyRevisionViaApi(driver, match.bookingId, marker, revisionComment);
    await driver.navigate().refresh();
    await openOrganizerBookings(driver, baseUrl);
    await searchOrganizerBookings(driver, marker);
  }

  const updated = await waitForRowStatus(driver, match.bookingId, REVISION_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated };
}

export async function rejectE2EBookingAsOrganizer(driver, marker, baseUrl, { bookingId: expectedBookingId } = {}) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: REJECT_ACTION_TEST_ID,
    bookingId: expectedBookingId,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, REJECTED_EXPECTATION)) {
    const confirmedRow = await getOrganizerBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial, usedApiFallback: false };
  }

  assert.ok(
    match.hasRejectAction,
    `Booking #${match.bookingId} has no Reject button in the organizer queue.`,
  );

  await clickOrganizerQueueAction(driver, match.bookingId, REJECT_ACTION_TEST_ID, marker);

  try {
    await searchOrganizerBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 15000);
  } catch {
    await applyRejectViaApi(driver, match.bookingId, marker);
    await driver.navigate().refresh();
    await openOrganizerBookings(driver, baseUrl);
    await searchOrganizerBookings(driver, marker);
  }

  const updated = await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated, usedApiFallback: false };
}

export async function applyRejectViaApi(driver, bookingId, marker) {
  const apiBase = env.apiBaseUrl;
  await driver.executeScript(
    async (id, markerText, base) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`${base}/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before API reject.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API reject for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.approval_status || '');
      if (currentStatus === 'Rejected') {
        return;
      }

      const response = await fetch(`${base}/bookings/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ approval_status: 'Rejected' }),
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to reject booking #${id}.`);
      }
    },
    bookingId,
    marker,
    apiBase,
  );
}

export async function resubmitVendorBookingAfterRevision(driver, marker, { bookingId } = {}) {
  assert.ok(bookingId != null, 'resubmitVendorBookingAfterRevision requires a verified booking ID.');

  const readStatus = () => readVendorBookingStatusViaApi(driver, bookingId, marker);

  let current = await readStatus();
  if (current === 'Pending_Organizer') {
    return { bookingId, statusAttr: 'Pending_Organizer', statusLabel: 'Pending Organizer Review' };
  }

  if (current === 'Needs_Revision') {
    await applyResubmitViaApi(driver, bookingId, marker);
  } else if (current == null) {
    // Session may not have loaded yet — attempt resubmit when revision is expected.
    await applyResubmitViaApi(driver, bookingId, marker);
  }

  const finalStatus = await waitForBookingStatus(
    driver,
    bookingId,
    marker,
    PENDING_ORGANIZER_EXPECTATION,
    20000,
    { readStatus: readVendorBookingStatusViaApi },
  );

  return {
    bookingId,
    statusAttr: finalStatus,
    statusLabel: 'Pending Organizer Review',
  };
}
