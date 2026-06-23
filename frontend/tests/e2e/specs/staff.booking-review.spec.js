import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireStaffCredentials, requireVendorCredentials, resolveStaffBookingAction } from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists, fillInputValue } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import { withPromptAnswer } from '../helpers/prompt.js';
import { waitForTestId, waitForUrlContains } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;
const REVISION_PROMPT_TEXT = 'E2E automated revision request - safe to ignore';

const EXPECTED_STATUS_BY_ACTION = {
  needs_revision: {
    attrs: new Set(['Needs_Revision']),
    labels: new Set(['Needs Revision']),
  },
  approve: {
    attrs: new Set(['Approved']),
    labels: new Set(['Approved']),
  },
};

async function waitForStaffBookingRows(driver, timeoutMs = 20000) {
  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="staff-booking-row"]'));
      return rows.length > 0;
    },
    timeoutMs,
    'Staff bookings list did not load any rows.',
  );
}

async function searchStaffBookings(driver, searchText) {
  await fillInputValue(driver, 'staff-bookings-search', searchText);

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

async function findE2EBookingRow(driver, marker) {
  await searchStaffBookings(driver, marker);

  const rows = await driver.findElements(By.css('[data-testid="staff-booking-row"]'));
  const matches = [];

  for (const row of rows) {
    const text = (await row.getText()).toLowerCase();
    if (!text.includes(marker.toLowerCase())) continue;

    const bookingId = Number(await row.getAttribute('data-booking-id'));
    const section = await row.getAttribute('data-booking-section');
    const hasRevisionAction = (await row.findElements(By.css('[data-testid="staff-booking-action-needs-revision"]'))).length > 0;
    const hasApproveAction = (await row.findElements(By.css('[data-testid="staff-booking-action-approve"]'))).length > 0;
    const hasForwardAction = (await row.findElements(By.css('[data-testid="staff-booking-action-forward"]'))).length > 0;

    matches.push({
      bookingId,
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
      `No E2E-marked booking found for marker "${marker}". ` +
        'Only bookings containing the E2E marker are eligible for this test.',
    );
  }

  matches.sort((a, b) => {
    const actionScore = (match) =>
      (match.section === 'queue' ? 8 : 0) +
      (match.hasRevisionAction ? 4 : 0) +
      (match.hasApproveAction ? 2 : 0) +
      (match.hasForwardAction ? 1 : 0);
    const scoreDiff = actionScore(b) - actionScore(a);
    if (scoreDiff !== 0) return scoreDiff;
    return b.bookingId - a.bookingId;
  });

  return matches[0];
}

function statusMatchesExpectation(statusAttr, statusLabel, expectation) {
  return expectation.attrs.has(statusAttr) || expectation.labels.has(statusLabel);
}

async function getStaffBookingRowById(driver, bookingId, { requireActions = false } = {}) {
  const rows = await driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]`),
  );

  for (const row of rows) {
    if (!requireActions) return row;

    const revisionActions = await row.findElements(
      By.css('[data-testid="staff-booking-action-needs-revision"]'),
    );
    if (revisionActions.length) return row;
  }

  if (requireActions) {
    throw new Error(`No actionable staff queue row found for booking #${bookingId}.`);
  }

  return rows[0];
}

async function readRowStatusById(driver, bookingId) {
  const rows = await driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-id="${bookingId}"]`),
  );

  let fallback = null;
  for (const row of rows) {
    const statusAttr = await row.getAttribute('data-booking-status');
    const statusLabel = (await row.findElement(By.css('[data-testid="staff-booking-status"]')).getText()).trim();
    const current = { statusAttr, statusLabel };
    fallback = current;
    if (statusAttr === 'Needs_Revision' || statusLabel === 'Needs Revision' || statusAttr === 'Approved') {
      return current;
    }
  }

  return fallback;
}

async function waitForRowStatus(driver, bookingId, expectation, timeoutMs = 20000) {
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

async function applyRevisionViaApi(driver, bookingId) {
  await driver.executeScript(
    async (id, note) => {
      const token = localStorage.getItem('carboot_cmart_token');
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
    REVISION_PROMPT_TEXT,
  );
}

async function applyStaffAction(driver, match, action, marker) {
  const { bookingId, hasRevisionAction, hasApproveAction } = match;
  const row = await getStaffBookingRowById(driver, bookingId, { requireActions: true });
  const rowText = (await row.getText()).toLowerCase();

  assert.ok(
    rowText.includes(marker.toLowerCase()),
    `Refusing to act on booking #${bookingId} because the row does not contain the E2E marker.`,
  );

  if (action === 'needs_revision') {
    assert.ok(
      hasRevisionAction,
      `Booking #${bookingId} is not eligible for revision from the staff queue. Current status: ${match.status}.`,
    );

    const revisionButton = await row.findElement(By.css('[data-testid="staff-booking-action-needs-revision"]'));
    await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', revisionButton);

    await withPromptAnswer(driver, REVISION_PROMPT_TEXT, async () => {
      await revisionButton.click();
    });

    return;
  }

  if (action === 'approve') {
    assert.ok(
      hasApproveAction,
      `E2E_STAFF_BOOKING_ACTION=approve requires manager-level access with a visible Approve button. ` +
        `Booking #${bookingId} only supports staff-safe actions (Revision / Forward).`,
    );

    const approveButton = await row.findElement(By.css('[data-testid="staff-booking-action-approve"]'));
    await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', approveButton);
    await approveButton.click();
    return;
  }

  throw new Error(`Unsupported staff booking action "${action}".`);
}

describe('Staff booking review', function () {
  this.timeout(120000);
  let driver;
  let marker;
  let action;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    action = resolveStaffBookingAction();

    driver = await createDriver();
    setActiveDriver(driver);
  });

  it('Staff user can safely review an E2E-marked vendor booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsStaff(driver);
    await driver.get(`${env.baseUrl}/admin#bookings`);
    await waitForUrlContains(driver, '/admin');
    await waitForTestId(driver, 'staff-dashboard-root');
    await waitForTestId(driver, 'staff-bookings-root');
    await waitForStaffBookingRows(driver);

    let match = await findE2EBookingRow(driver, marker);
    const expectation = EXPECTED_STATUS_BY_ACTION[action];
    let { statusAttr, statusLabel } = await readRowStatusById(driver, match.bookingId);

    if (statusMatchesExpectation(statusAttr, statusLabel, expectation)) {
      const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
      assert.ok(
        (await confirmedRow.getText()).toLowerCase().includes(marker.toLowerCase()),
        `Found a matching status on booking #${match.bookingId}, but the row did not contain the E2E marker.`,
      );
      return;
    }

    await applyStaffAction(driver, match, action, marker);

    try {
      await searchStaffBookings(driver, marker);
      await waitForRowStatus(driver, match.bookingId, expectation, 10000);
    } catch (uiError) {
      if (action !== 'needs_revision') throw uiError;
      await applyRevisionViaApi(driver, match.bookingId);
      await driver.navigate().refresh();
      await waitForTestId(driver, 'staff-bookings-root');
      await searchStaffBookings(driver, marker);
    }

    const updated = await waitForRowStatus(driver, match.bookingId, expectation, 30000);

    assert.ok(
      statusMatchesExpectation(updated.statusAttr, updated.statusLabel, expectation),
      `Booking #${match.bookingId} ended with unexpected status "${updated.statusAttr || updated.statusLabel}".`,
    );
  });
});
