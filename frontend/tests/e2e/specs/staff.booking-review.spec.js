import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireStaffCredentials, requireVendorCredentials } from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import { withPromptAnswer } from '../helpers/prompt.js';
import {
  REVISION_EXPECTATION,
  applyRevisionViaApi,
  findE2EBookingRow,
  getStaffBookingRowById,
  openStaffBookings,
  readRowStatusById,
  searchStaffBookings,
  statusMatchesExpectation,
  waitForRowStatus,
} from '../helpers/staff-bookings.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;
const REVISION_PROMPT_TEXT = 'E2E automated revision request - safe to ignore';
const REVISION_ACTION_TEST_ID = 'staff-booking-action-needs-revision';

describe('Staff booking review', function () {
  this.timeout(120000);
  let driver;
  let marker;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();

    driver = await createDriver();
    setActiveDriver(driver);
  });

  it('Staff user can safely review an E2E-marked vendor booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsStaff(driver);
    await openStaffBookings(driver, env.baseUrl);

    let match = await findE2EBookingRow(driver, marker, {
      preferActionTestId: REVISION_ACTION_TEST_ID,
    });
    let { statusAttr, statusLabel } = await readRowStatusById(driver, match.bookingId);

    if (statusMatchesExpectation(statusAttr, statusLabel, REVISION_EXPECTATION)) {
      const confirmedRow = await getStaffBookingRowById(driver, match.bookingId);
      assert.ok(
        (await confirmedRow.getText()).toLowerCase().includes(marker.toLowerCase()),
        `Found a matching status on booking #${match.bookingId}, but the row did not contain the E2E marker.`,
      );
      return;
    }

    assert.ok(
      match.hasRevisionAction,
      `Booking #${match.bookingId} is not eligible for revision from the staff queue. Current status: ${match.status}.`,
    );

    const row = await getStaffBookingRowById(driver, match.bookingId, {
      requireActionTestId: REVISION_ACTION_TEST_ID,
    });
    const rowText = (await row.getText()).toLowerCase();
    assert.ok(
      rowText.includes(marker.toLowerCase()),
      `Refusing to act on booking #${match.bookingId} because the row does not contain the E2E marker.`,
    );

    const revisionButton = await row.findElement(By.css(`[data-testid="${REVISION_ACTION_TEST_ID}"]`));
    await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', revisionButton);

    await withPromptAnswer(driver, REVISION_PROMPT_TEXT, async () => {
      await revisionButton.click();
    });

    try {
      await searchStaffBookings(driver, marker);
      await waitForRowStatus(driver, match.bookingId, REVISION_EXPECTATION, 10000);
    } catch (uiError) {
      await applyRevisionViaApi(driver, match.bookingId, marker, REVISION_PROMPT_TEXT);
      await driver.navigate().refresh();
      await openStaffBookings(driver, env.baseUrl);
      await searchStaffBookings(driver, marker);
    }

    const updated = await waitForRowStatus(driver, match.bookingId, REVISION_EXPECTATION, 30000);

    assert.ok(
      statusMatchesExpectation(updated.statusAttr, updated.statusLabel, REVISION_EXPECTATION),
      `Booking #${match.bookingId} ended with unexpected status "${updated.statusAttr || updated.statusLabel}".`,
    );
  });
});
