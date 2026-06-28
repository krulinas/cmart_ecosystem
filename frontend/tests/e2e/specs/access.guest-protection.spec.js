import { strict as assert } from 'node:assert';
import { requireVendorCredentials } from '../config/env.js';
import {
  assertBookingStillExistsForVendor,
  assertGuestApiDenied,
  assertGuestRedirectedFromProtectedRoute,
  assertGuestResponseDoesNotExposeProtectedData,
  assertGuestSessionClean,
  attemptGuestPaymentSubmit,
  captureGuestFailureDiagnostics,
  ensureGuestSession,
  guestApiRequest,
  prepareGuestTestBookingId,
  PROTECTED_FRONTEND_ROUTES,
} from '../helpers/guest-access.js';
import { createDriver } from '../helpers/driver.js';
import { setActiveDriver } from '../setup.js';

describe('Guest / unauthenticated protection', function () {
  this.timeout(300000);

  let driver;
  let guestTestBookingId;
  let guestTestMarker;

  before(async function () {
    requireVendorCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);

    const prepared = await prepareGuestTestBookingId(driver);
    guestTestBookingId = prepared.bookingId;
    guestTestMarker = prepared.marker;
  });

  beforeEach(async function () {
    await ensureGuestSession(driver);
    await assertGuestSessionClean(driver);
  });

  it('Guest cannot access protected dashboards, booking resources, payment/receipt/pass endpoints, or management APIs', async function () {
    try {
      for (const route of PROTECTED_FRONTEND_ROUTES) {
        await assertGuestRedirectedFromProtectedRoute(driver, route.path, {
          label: route.label,
          forbiddenTestIds: route.forbiddenTestIds,
        });
      }

      if (guestTestBookingId) {
        await assertGuestRedirectedFromProtectedRoute(
          driver,
          `/staff/verify-booking/${guestTestBookingId}`,
          {
            label: 'Staff verify booking page',
            forbiddenTestIds: ['staff-dashboard-root', 'staff-bookings-root'],
          },
        );
      }

      const bookingListEndpoints = [
        ['GET', '/vendor/bookings', 'Vendor bookings list'],
        ['GET', '/vendor/history-receipts', 'Vendor history receipts'],
        ['GET', '/vendor/event-passes', 'Vendor event passes'],
        ['GET', '/staff/bookings', 'Staff bookings registry'],
        ['GET', '/bookings', 'Staff bookings index'],
        ['GET', '/invoices', 'Invoices list'],
        ['GET', '/vendor/analytics/me', 'Vendor analytics'],
      ];

      for (const [method, endpoint, label] of bookingListEndpoints) {
        const response = await guestApiRequest(driver, method, endpoint);
        assertGuestApiDenied(response, { endpoint, label });
        assertGuestResponseDoesNotExposeProtectedData(response, { label });
      }

      const resourceEndpoints = [
        ['GET', `/vendor/bookings/${guestTestBookingId}`, 'Vendor booking detail'],
        ['GET', `/vendor/event-passes/${guestTestBookingId}`, 'Vendor event pass detail'],
        ['GET', `/bookings/${guestTestBookingId}`, 'Staff booking detail'],
        ['GET', `/bookings/${guestTestBookingId}/pdf`, 'Booking PDF receipt'],
      ];

      for (const [method, endpoint, label] of resourceEndpoints) {
        const response = await guestApiRequest(driver, method, endpoint);
        assertGuestApiDenied(response, { endpoint, label });
        assertGuestResponseDoesNotExposeProtectedData(response, { label });
      }

      const paymentResponse = await attemptGuestPaymentSubmit(driver, guestTestBookingId);
      assertGuestApiDenied(paymentResponse, {
        endpoint: `/vendor/bookings/${guestTestBookingId}/submit-payment`,
        label: 'Guest payment proof submission',
      });
      assertGuestResponseDoesNotExposeProtectedData(paymentResponse, {
        label: 'Guest payment proof submission',
      });

      const managementEndpoints = [
        ['GET', '/boss/analytics/revenue', 'Boss revenue analytics'],
        ['GET', '/boss/audit-logs', 'Boss audit logs'],
      ];

      for (const [method, endpoint, label] of managementEndpoints) {
        const response = await guestApiRequest(driver, method, endpoint);
        assertGuestApiDenied(response, { endpoint, label });
        assertGuestResponseDoesNotExposeProtectedData(response, { label });
      }

      const deleteResponse = await guestApiRequest(driver, 'DELETE', `/bookings/${guestTestBookingId}`);
      assertGuestApiDenied(deleteResponse, {
        endpoint: `/bookings/${guestTestBookingId}`,
        label: 'Guest booking delete',
      });

      await assertBookingStillExistsForVendor(driver, guestTestBookingId, guestTestMarker);
    } catch (error) {
      const diagnostics = await captureGuestFailureDiagnostics(driver, 'guest-protection-failed', {
        guestTestBookingId,
        guestTestMarker,
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });
});
