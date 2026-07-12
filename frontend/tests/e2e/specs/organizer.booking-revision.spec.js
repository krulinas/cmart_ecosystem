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
import { resolveVendorBookingIdByMarker } from '../helpers/vendor-ownership.js';
import {
  PENDING_ORGANIZER_EXPECTATION,
  REVISION_EXPECTATION,
  openOrganizerBookings,
  requestOrganizerRevision,
  resubmitVendorBookingAfterRevision,
  statusMatchesExpectation,
} from '../helpers/organizer-bookings.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Organizer booking revision', function () {
  this.timeout(300000);

  let driver;
  let marker;
  let bookingId;

  before(async function () {
    requireVendorCredentials();
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Organizer requests revision and vendor resubmits to Pending_Organizer', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    bookingId = await resolveVendorBookingIdByMarker(driver, marker);
    await logout(driver);

    await loginAsOrganizer(driver);
    await openOrganizerBookings(driver, env.baseUrl);
    const revised = await requestOrganizerRevision(driver, marker, env.baseUrl, { bookingId });

    assert.ok(
      statusMatchesExpectation(revised.statusAttr, revised.statusLabel, REVISION_EXPECTATION),
      `Booking #${revised.bookingId} did not reach Needs_Revision.`,
    );
    await logout(driver);

    await loginAsVendor(driver);
    const resubmitted = await resubmitVendorBookingAfterRevision(driver, marker, { bookingId });

    assert.ok(
      statusMatchesExpectation(resubmitted.statusAttr, resubmitted.statusLabel, PENDING_ORGANIZER_EXPECTATION),
      `Booking #${resubmitted.bookingId} did not return to Pending_Organizer after vendor resubmit.`,
    );
  });
});
