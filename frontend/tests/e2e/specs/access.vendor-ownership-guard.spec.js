import { strict as assert } from 'node:assert';
import { env, requireVendorBCredentials, requireVendorCredentials } from '../config/env.js';
import { e2eT7CVendorBOwnershipMarker } from '../helpers/actions.js';
import { loginAsCommunityVendor, loginAsVendor, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import {
  assertOwnershipDenied,
  assertResponseDoesNotExposePrivateData,
  assertVendorBookingRowAbsentById,
  assertVendorBookingsListExcludes,
  assertVendorEventPassesListExcludes,
  assertVendorHistoryReceiptsExclude,
  assertVendorMarkerNotInMyBookings,
  attemptVendorPaymentSubmitForBookingId,
  captureOwnershipFailureDiagnostics,
  resolveVendorBookingIdByMarker,
  vendorApiRequest,
} from '../helpers/vendor-ownership.js';
import { setActiveDriver } from '../setup.js';

describe('Vendor data ownership guard', function () {
  this.timeout(300000);

  let driver;
  let vendorBMarker;
  let vendorBBookingId;

  before(async function () {
    requireVendorCredentials();
    requireVendorBCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Vendor A cannot access Vendor B booking, receipt, pass, or payment resources', async function () {
    const vendorBCredentials = requireVendorBCredentials();
    vendorBMarker = e2eT7CVendorBOwnershipMarker();

    try {
      await ensureE2EBookingExists(driver, vendorBMarker, {
        allowReuse: false,
        vendorCredentials: vendorBCredentials,
      });

      await loginAsCommunityVendor(driver, vendorBCredentials, { roleLabel: 'Vendor B' });
      vendorBBookingId = await resolveVendorBookingIdByMarker(driver, vendorBMarker);
      assert.ok(vendorBBookingId > 0, 'Vendor B booking ID must be resolved from API.');
      await logout(driver);

      await loginAsVendor(driver);

      await assertVendorMarkerNotInMyBookings(driver, vendorBMarker);
      await assertVendorBookingRowAbsentById(driver, vendorBBookingId);

      const directAccessChecks = [
        ['GET', `/vendor/bookings/${vendorBBookingId}`, 'Vendor booking detail'],
        ['GET', `/vendor/event-passes/${vendorBBookingId}`, 'Vendor event pass detail'],
        ['GET', `/bookings/${vendorBBookingId}/pdf`, 'Booking PDF document'],
        ['GET', `/bookings/${vendorBBookingId}`, 'Staff booking detail'],
      ];

      for (const [method, endpoint, label] of directAccessChecks) {
        const response = await vendorApiRequest(driver, method, endpoint);
        assertOwnershipDenied(response, { endpoint, label });
        assertResponseDoesNotExposePrivateData(response, {
          marker: vendorBMarker,
          bookingId: vendorBBookingId,
        });
      }

      await assertVendorBookingsListExcludes(driver, {
        bookingId: vendorBBookingId,
        marker: vendorBMarker,
      });
      await assertVendorHistoryReceiptsExclude(driver, vendorBBookingId, vendorBMarker);
      await assertVendorEventPassesListExcludes(driver, vendorBBookingId, vendorBMarker);

      const paymentResponse = await attemptVendorPaymentSubmitForBookingId(driver, vendorBBookingId);
      assertOwnershipDenied(paymentResponse, {
        endpoint: `/vendor/bookings/${vendorBBookingId}/submit-payment`,
        label: 'Vendor payment proof submission',
      });
      assertResponseDoesNotExposePrivateData(paymentResponse, {
        marker: vendorBMarker,
        bookingId: vendorBBookingId,
      });

      await logout(driver);
      await loginAsCommunityVendor(driver, vendorBCredentials, { roleLabel: 'Vendor B' });
      const stillOwnedId = await resolveVendorBookingIdByMarker(driver, vendorBMarker);
      assert.equal(
        stillOwnedId,
        vendorBBookingId,
        'Vendor B booking must still exist after Vendor A denied access attempts.',
      );
    } catch (error) {
      const diagnostics = await captureOwnershipFailureDiagnostics(driver, 'vendor-ownership-failed', {
        vendorBMarker,
        vendorBBookingId,
        vendorAEmail: env.vendorEmail,
        vendorBEmail: env.vendorBEmail,
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });
});
