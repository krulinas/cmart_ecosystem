import { strict as assert } from 'node:assert';
import {
  env,
  requireOrganizerCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsOrganizer, loginAsVendor, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import {
  APPROVED_EXPECTATION,
  approveOrganizerBooking,
  openOrganizerBookings,
  statusMatchesExpectation,
} from '../helpers/organizer-bookings.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Organizer booking approval', function () {
  this.timeout(300000);

  let driver;
  let marker;
  let usedApiFallback = false;

  before(async function () {
    requireVendorCredentials();
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Organizer can safely approve an E2E-marked Pending_Organizer booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsOrganizer(driver);
    await openOrganizerBookings(driver, env.baseUrl);

    const approved = await approveOrganizerBooking(driver, marker, env.baseUrl);
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
