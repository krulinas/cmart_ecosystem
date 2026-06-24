import { strict as assert } from 'node:assert';
import { env, requireVendorCredentials } from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import {
  VENDOR_WITHDRAWN_EXPECTATION,
  assertVendorBookingWithdrawn,
  goToMyBookings,
  vendorStatusMatches,
  withdrawVendorBooking,
} from '../helpers/vendor-bookings.js';
import { setActiveDriver } from '../setup.js';

const WITHDRAWAL_REASON = 'E2E automated withdrawal test';
const E2E_MARKER_BASE = env.bookingDetails;

describe('Vendor booking withdraw', function () {
  this.timeout(240000);

  let driver;
  let marker;

  before(async function () {
    requireVendorCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Vendor user can withdraw an eligible E2E-marked booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;

    await goToMyBookings(driver, env.baseUrl);

    const withdrawn = await withdrawVendorBooking(driver, marker, WITHDRAWAL_REASON);

    assert.ok(
      vendorStatusMatches(withdrawn.statusAttr, withdrawn.statusLabel, VENDOR_WITHDRAWN_EXPECTATION),
      `Withdraw did not reach Withdrawn for booking #${withdrawn.bookingId}.`,
    );

    const vendorView = await assertVendorBookingWithdrawn(driver, marker, {
      bookingId: withdrawn.bookingId,
    });

    assert.ok(
      vendorView.rowText.includes(marker.toLowerCase()),
      `Vendor My Bookings row for booking #${vendorView.bookingId} does not contain the E2E marker.`,
    );
  });
});
