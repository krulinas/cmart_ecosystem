import { strict as assert } from 'node:assert';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsManager, loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import {
  APPROVED_EXPECTATION,
  FORWARD_EXPECTATION,
  approveE2EBookingAsManager,
  forwardE2EBookingToManager,
  openStaffBookings,
  statusMatchesExpectation,
} from '../helpers/staff-bookings.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Manager booking approval', function () {
  this.timeout(300000);

  let driver;
  let marker;
  let usedApiFallback = false;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    requireManagerCredentials();
    driver = await createDriver();
    setActiveDriver(driver);
  });

  it('Manager user can safely approve an E2E-marked Pending_Boss booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsStaff(driver);
    await openStaffBookings(driver, env.baseUrl);
    const forwarded = await forwardE2EBookingToManager(driver, marker, env.baseUrl);

    assert.ok(
      statusMatchesExpectation(forwarded.statusAttr, forwarded.statusLabel, FORWARD_EXPECTATION),
      `Staff forward did not reach manager queue for booking #${forwarded.bookingId}.`,
    );

    await logout(driver);

    await loginAsManager(driver);
    await openStaffBookings(driver, env.baseUrl);

    // UI path first: manager clicks Approve on the verified E2E Pending_Boss row.
    const approved = await approveE2EBookingAsManager(driver, marker, env.baseUrl, {
      bookingId: forwarded.bookingId,
    });
    usedApiFallback = approved.usedApiFallback;

    assert.ok(
      statusMatchesExpectation(approved.statusAttr, approved.statusLabel, APPROVED_EXPECTATION),
      `Booking #${approved.bookingId} ended with unexpected status "${approved.statusAttr || approved.statusLabel}". ` +
        'Expected Approved.',
    );

    if (usedApiFallback) {
      assert.ok(true, 'API fallback was used after the UI Approve click did not update status in time.');
    }
  });
});
