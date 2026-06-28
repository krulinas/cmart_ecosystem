import { strict as assert } from 'node:assert';
import { By, until } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { fillInputValue } from './booking.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { waitForTestId } from './wait.js';

export const FORWARD_EXPECTATION = {
  attrs: new Set(['Pending_Boss']),
  labels: new Set(['Awaiting Manager', 'Pending', 'Pending Manager', 'Pending Boss']),
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

const FORWARD_ACTION_TEST_ID = 'staff-booking-action-forward';
const APPROVE_ACTION_TEST_ID = 'staff-booking-action-approve';
const REJECT_ACTION_TEST_ID = 'staff-booking-action-reject';

export { APPROVE_ACTION_TEST_ID, REJECT_ACTION_TEST_ID };

export async function waitForStaffBookingRows(driver, timeoutMs = 20000) {
  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="staff-booking-row"]'));
      return rows.length > 0;
    },
    timeoutMs,
    'Staff bookings list did not load any rows.',
  );
}

export async function searchStaffBookings(driver, searchText) {
  await fillInputValue(driver, 'staff-bookings-search', searchText);

  // Registry search is debounced (~300ms) before the API refresh runs.
  await driver.sleep(500);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="staff-booking-row"]'));
      for (const row of rows) {
        const text = (await row.getText()).toLowerCase();
        if (text.includes(searchText.toLowerCase())) {
          return true;
        }
      }
      return false;
    },
    20000,
    `No staff booking row matched search text "${searchText}".`,
  );
}

export async function findE2EBookingRow(driver, marker, { preferActionTestId, bookingId } = {}) {
  await searchStaffBookings(driver, marker);

  const rows = await driver.findElements(By.css('[data-testid="staff-booking-row"]'));
  const matches = [];

  for (const row of rows) {
    try {
      const text = (await row.getText()).toLowerCase();
      if (!text.includes(marker.toLowerCase())) continue;

      const rowBookingId = Number(await row.getAttribute('data-booking-id'));
      if (bookingId != null && rowBookingId !== bookingId) continue;
      const section = await row.getAttribute('data-booking-section');
      const hasRevisionAction = (await row.findElements(By.css('[data-testid="staff-booking-action-needs-revision"]'))).length > 0;
      const hasApproveAction = (await row.findElements(By.css('[data-testid="staff-booking-action-approve"]'))).length > 0;
      const hasForwardAction = (await row.findElements(By.css('[data-testid="staff-booking-action-forward"]'))).length > 0;

      matches.push({
        bookingId: rowBookingId,
        section,
        hasRevisionAction,
        hasApproveAction,
        hasForwardAction,
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
      if (preferActionTestId === FORWARD_ACTION_TEST_ID) {
        score += match.hasForwardAction ? 4 : 0;
      } else if (preferActionTestId === 'staff-booking-action-needs-revision') {
        score += match.hasRevisionAction ? 4 : 0;
      } else if (preferActionTestId === APPROVE_ACTION_TEST_ID) {
        score += match.hasApproveAction ? 4 : 0;
      } else {
        score +=
          (match.hasRevisionAction ? 4 : 0) +
          (match.hasApproveAction ? 2 : 0) +
          (match.hasForwardAction ? 1 : 0);
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

export async function getStaffBookingRowById(driver, bookingId, { requireActionTestId } = {}) {
  const rows = await driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]`),
  );

  for (const row of rows) {
    if (!requireActionTestId) return row;

    const actions = await row.findElements(By.css(`[data-testid="${requireActionTestId}"]`));
    if (actions.length) return row;
  }

  if (requireActionTestId) {
    throw new Error(
      `No actionable staff queue row found for booking #${bookingId} ` +
        `(expected action: ${requireActionTestId}).`,
    );
  }

  if (!rows.length) {
    throw new Error(`No staff booking row found for booking #${bookingId}.`);
  }

  return rows[0];
}

async function readRowStatusFromElement(row) {
  const statusAttr = await row.getAttribute('data-booking-status');
  const statusLabel = (await row.findElement(By.css('[data-testid="staff-booking-status"]')).getText()).trim();
  const section = await row.getAttribute('data-booking-section');
  return { statusAttr, statusLabel, section };
}

export async function readAllRowStatusesById(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]`),
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
    throw new Error(`No staff booking row found for booking #${bookingId}.`);
  }

  const attrPriority = ['Approved', 'Rejected', 'Pending_Boss', 'Needs_Revision', 'Pending_Staff'];
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

export async function clickStaffQueueAction(driver, bookingId, actionTestId, marker) {
  const row = await getStaffBookingRowById(driver, bookingId, { requireActionTestId: actionTestId });
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

async function waitForApiBookingStatus(driver, bookingId, marker, expectation, timeoutMs = 20000) {
  return driver.wait(
    async () => {
      const status = await readE2EBookingStatusViaApi(driver, bookingId, marker);
      if (status && expectation.attrs.has(status)) {
        return status;
      }
      return null;
    },
    timeoutMs,
    `Booking #${bookingId} did not reach API status ${[...expectation.attrs].join('|')}.`,
  );
}

async function reloadStaffBookingsPanel(driver, baseUrl, marker) {
  await driver.get(`${baseUrl}/admin#bookings`);
  await waitForTestId(driver, 'staff-dashboard-root');
  await waitForTestId(driver, 'staff-bookings-root');
  await waitForStaffBookingRows(driver);
  await searchStaffBookings(driver, marker);
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

export async function readE2EBookingStatusViaApi(driver, bookingId, marker) {
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

      const booking = await response.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) return null;
      return String(bookingRecord.approval_status || '');
    },
    bookingId,
    marker,
    apiBase,
  );
}

export async function applyForwardViaApi(driver, bookingId, marker) {
  await driver.executeScript(
    async (id, markerText) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });

      if (!verifyResponse.ok) {
        throw new Error(`Unable to verify E2E booking #${id} before API forward.`);
      }

      const booking = await verifyResponse.json();
      const bookingRecord = booking.booking ?? booking;
      const details = String(bookingRecord.product_details || '').toLowerCase();

      if (!details.includes(String(markerText).toLowerCase())) {
        throw new Error(`Refusing API forward for booking #${id} because it is not E2E-marked.`);
      }

      const currentStatus = String(bookingRecord.approval_status || '');
      if (currentStatus === 'Pending_Boss') {
        return;
      }

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ approval_status: 'Pending_Boss' }),
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(body || `Failed to forward booking #${id} to Pending_Boss.`);
      }
    },
    bookingId,
    marker,
  );
}

export async function applyRevisionViaApi(driver, bookingId, marker, revisionComment) {
  await driver.executeScript(
    async (id, markerText, note) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
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

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
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
  );
}

export async function openStaffBookings(driver, baseUrl) {
  await driver.get(`${baseUrl}/admin#bookings`);
  await waitForTestId(driver, 'staff-dashboard-root');
  await waitForTestId(driver, 'staff-bookings-root');
  await waitForStaffBookingRows(driver);
}

export async function forwardE2EBookingToManager(driver, marker, baseUrl) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: FORWARD_ACTION_TEST_ID,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, FORWARD_EXPECTATION)) {
    const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial };
  }

  assert.ok(
    match.hasForwardAction,
    `Booking #${match.bookingId} is not forwardable from the staff queue. ` +
      `Current status: ${match.status}. Create a fresh Pending_Staff E2E booking first.`,
  );

  await clickStaffQueueAction(driver, match.bookingId, FORWARD_ACTION_TEST_ID, marker);

  try {
    await searchStaffBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, FORWARD_EXPECTATION, 10000);
  } catch (uiError) {
    await applyForwardViaApi(driver, match.bookingId, marker);
    await driver.navigate().refresh();
    await openStaffBookings(driver, baseUrl);
    await searchStaffBookings(driver, marker);
  }

  try {
    const updated = await waitForRowStatus(driver, match.bookingId, FORWARD_EXPECTATION, 15000);
    return { bookingId: match.bookingId, ...updated };
  } catch (uiError) {
    const apiStatus = await readE2EBookingStatusViaApi(driver, match.bookingId, marker);
    if (apiStatus === 'Pending_Boss') {
      return {
        bookingId: match.bookingId,
        statusAttr: 'Pending_Boss',
        statusLabel: 'Awaiting Manager',
      };
    }
    throw uiError;
  }
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

      if (currentStatus !== 'Pending_Boss') {
        throw new Error(
          `Refusing API approve for booking #${id}: expected Pending_Boss, got "${currentStatus}".`,
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

export async function approveE2EBookingAsManager(driver, marker, baseUrl, { bookingId: expectedBookingId } = {}) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: APPROVE_ACTION_TEST_ID,
    bookingId: expectedBookingId,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, APPROVED_EXPECTATION)) {
    const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial, usedApiFallback: false };
  }

  if (!statusMatchesExpectation(initial.statusAttr, initial.statusLabel, FORWARD_EXPECTATION)) {
    throw new Error(
      `Booking #${match.bookingId} is not manager-pending. ` +
        `Current status: ${initial.statusAttr || initial.statusLabel}. ` +
        'Forward the E2E booking to the manager queue before approving.',
    );
  }

  assert.ok(
    match.hasApproveAction,
    `Booking #${match.bookingId} has no Approve button in the manager queue. ` +
      `Current status: ${match.status}. Ensure manager credentials are used.`,
  );

  const confirmedRow = await getStaffBookingRowById(driver, match.bookingId, {
    requireActionTestId: APPROVE_ACTION_TEST_ID,
  });
  assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);

  let usedApiFallback = false;
  await clickStaffQueueAction(driver, match.bookingId, APPROVE_ACTION_TEST_ID, marker);

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

  await reloadStaffBookingsPanel(driver, baseUrl, marker);

  try {
    const updated = await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 20000);
    return { bookingId: match.bookingId, ...updated, usedApiFallback };
  } catch (finalError) {
    const apiStatus = await readE2EBookingStatusViaApi(driver, match.bookingId, marker);
    if (apiStatus !== 'Approved') {
      const diagnostics = await captureApproveFailureDiagnostics(driver, match.bookingId, marker, 'failed');
      const current = await readRowStatusById(driver, match.bookingId);
      throw new Error(
        `Manager approve failed for booking #${match.bookingId}. ` +
          `UI status: ${current.statusAttr || current.statusLabel}. API status: ${apiStatus || 'unknown'}. ` +
          `Diagnostics: ${diagnostics.json}.`,
        { cause: finalError },
      );
    }

    await reloadStaffBookingsPanel(driver, baseUrl, marker);
    const updated = await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 15000);
    return { bookingId: match.bookingId, ...updated, usedApiFallback };
  }
}

export async function applyRejectViaApi(driver, bookingId, marker) {
  await driver.executeScript(
    async (id, markerText) => {
      const token = localStorage.getItem('carboot_cmart_token');
      const verifyResponse = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
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

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
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
  );
}

export async function rejectE2EBookingAsStaff(driver, marker, baseUrl) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: FORWARD_ACTION_TEST_ID,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, REJECTED_EXPECTATION)) {
    const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial };
  }

  await driver.wait(
    async () => {
      return driver.executeScript(
        (id) => {
          const rows = document.querySelectorAll(`[data-testid="staff-booking-row"][data-booking-id="${id}"]`);
          for (const row of rows) {
            const buttons = row.querySelectorAll('button');
            for (const button of buttons) {
              if (button.textContent.trim() === 'Reject') {
                button.scrollIntoView({ block: 'center' });
                button.click();
                return true;
              }
            }
          }
          return false;
        },
        match.bookingId,
      );
    },
    20000,
    `Reject action did not trigger for booking #${match.bookingId}.`,
  );

  try {
    await searchStaffBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 10000);
  } catch {
    await applyRejectViaApi(driver, match.bookingId, marker);
    await driver.navigate().refresh();
    await openStaffBookings(driver, baseUrl);
    await searchStaffBookings(driver, marker);
  }

  const updated = await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated };
}

export async function rejectE2EBookingAsManager(driver, marker, baseUrl, { bookingId: expectedBookingId } = {}) {
  const match = await findE2EBookingRow(driver, marker, {
    preferActionTestId: APPROVE_ACTION_TEST_ID,
    bookingId: expectedBookingId,
  });

  const initial = await readRowStatusById(driver, match.bookingId);
  if (statusMatchesExpectation(initial.statusAttr, initial.statusLabel, REJECTED_EXPECTATION)) {
    const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
    assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);
    return { bookingId: match.bookingId, ...initial, usedApiFallback: false };
  }

  if (!statusMatchesExpectation(initial.statusAttr, initial.statusLabel, FORWARD_EXPECTATION)) {
    throw new Error(
      `Booking #${match.bookingId} is not manager-pending. ` +
        `Current status: ${initial.statusAttr || initial.statusLabel}. ` +
        'Forward the E2E booking to the manager queue before rejecting.',
    );
  }

  assert.ok(
    match.hasApproveAction,
    `Booking #${match.bookingId} has no manager queue actions. Ensure manager credentials are used.`,
  );

  const confirmedRow = await getStaffBookingRowById(driver, match.bookingId, {
    requireActionTestId: APPROVE_ACTION_TEST_ID,
  });
  assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);

  let usedApiFallback = false;
  await clickStaffQueueAction(driver, match.bookingId, REJECT_ACTION_TEST_ID, marker);

  try {
    await searchStaffBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 15000);
  } catch (uiError) {
    const current = await readRowStatusById(driver, match.bookingId);
    if (statusMatchesExpectation(current.statusAttr, current.statusLabel, REJECTED_EXPECTATION)) {
      return { bookingId: match.bookingId, ...current, usedApiFallback: false };
    }

    usedApiFallback = true;
    await applyRejectViaApi(driver, match.bookingId, marker);
    await driver.navigate().refresh();
    await openStaffBookings(driver, baseUrl);
    await searchStaffBookings(driver, marker);
  }

  const updated = await waitForRowStatus(driver, match.bookingId, REJECTED_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated, usedApiFallback };
}
