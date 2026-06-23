import { strict as assert } from 'node:assert';
import { env, requireStaffCredentials, requireVendorCredentials } from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import {
  FORWARD_EXPECTATION,
  forwardE2EBookingToManager,
  openStaffBookings,
  statusMatchesExpectation,
} from '../helpers/staff-bookings.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Staff booking forward', function () {
  this.timeout(120000);

  let driver;
  let marker;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    driver = await createDriver();
    setActiveDriver(driver);
  });

  it('Staff user can safely forward an E2E-marked vendor booking to manager queue', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsStaff(driver);
    await openStaffBookings(driver, env.baseUrl);

    const updated = await forwardE2EBookingToManager(driver, marker, env.baseUrl);

    assert.ok(
      statusMatchesExpectation(updated.statusAttr, updated.statusLabel, FORWARD_EXPECTATION),
      `Booking #${updated.bookingId} ended with unexpected status "${updated.statusAttr || updated.statusLabel}". ` +
        `Expected manager queue status (Pending_Boss / Awaiting Manager).`,
    );
  });
});
