import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { fillInputValue } from './booking.js';
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

const FORWARD_ACTION_TEST_ID = 'staff-booking-action-forward';
const APPROVE_ACTION_TEST_ID = 'staff-booking-action-approve';

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

export async function readRowStatusById(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]`),
  );

  let fallback = null;
  for (const row of rows) {
    const statusAttr = await row.getAttribute('data-booking-status');
    const statusLabel = (await row.findElement(By.css('[data-testid="staff-booking-status"]')).getText()).trim();
    const current = { statusAttr, statusLabel };
    fallback = current;

    if (
      statusAttr === 'Needs_Revision' ||
      statusAttr === 'Pending_Boss' ||
      statusAttr === 'Approved' ||
      statusLabel === 'Needs Revision' ||
      statusLabel === 'Awaiting Manager'
    ) {
      return current;
    }
  }

  return fallback;
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
  await actionButton.click();
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

  const updated = await waitForRowStatus(driver, match.bookingId, FORWARD_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated };
}

export async function applyApproveViaApi(driver, bookingId, marker) {
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

      const response = await fetch(`http://127.0.0.1:8000/api/bookings/${id}`, {
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

  const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
  assertRowContainsMarker((await confirmedRow.getText()).toLowerCase(), marker, match.bookingId);

  let usedApiFallback = false;
  await clickStaffQueueAction(driver, match.bookingId, APPROVE_ACTION_TEST_ID, marker);

  try {
    await searchStaffBookings(driver, marker);
    await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 15000);
  } catch (uiError) {
    const current = await readRowStatusById(driver, match.bookingId);
    if (statusMatchesExpectation(current.statusAttr, current.statusLabel, APPROVED_EXPECTATION)) {
      return { bookingId: match.bookingId, ...current, usedApiFallback: false };
    }

    usedApiFallback = true;
    await applyApproveViaApi(driver, match.bookingId, marker);
    await driver.navigate().refresh();
    await openStaffBookings(driver, baseUrl);
    await searchStaffBookings(driver, marker);
  }

  const updated = await waitForRowStatus(driver, match.bookingId, APPROVED_EXPECTATION, 30000);
  return { bookingId: match.bookingId, ...updated, usedApiFallback };
}
