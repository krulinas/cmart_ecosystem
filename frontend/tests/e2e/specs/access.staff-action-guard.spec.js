import { strict as assert } from 'node:assert';
import { env, requireStaffCredentials, requireVendorCredentials } from '../config/env.js';
import { e2eT7AStaffGuardMarker } from '../helpers/actions.js';
import {
  assertManagerOnlyBookingActionsAbsent,
  assertStaffCannotAccessManagerEndpoint,
  assertStaffCannotDeleteBooking,
  assertStaffSafeBookingActionsVisible,
} from '../helpers/access-guards.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import { findE2EBookingRow, openStaffBookings } from '../helpers/staff-bookings.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Staff vs manager action guard', function () {
  this.timeout(180000);

  let driver;
  let marker;
  let bookingId;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Staff can use staff-safe booking actions but not manager-only controls or APIs', async function () {
    marker = e2eT7AStaffGuardMarker();

    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    await loginAsStaff(driver);
    await openStaffBookings(driver, env.baseUrl);

    await waitForTestId(driver, 'staff-dashboard-root');
    await waitForTestId(driver, 'staff-bookings-root');

    const match = await findE2EBookingRow(driver, marker, {
      preferActionTestId: 'staff-booking-action-forward',
    });
    bookingId = match.bookingId;

    assert.ok(
      match.hasForwardAction,
      `Booking #${bookingId} must expose staff forward action while Pending_Staff.`,
    );
    assert.equal(
      match.hasApproveAction,
      false,
      `Staff view must not expose manager Approve action for booking #${bookingId}.`,
    );

    await assertStaffSafeBookingActionsVisible(driver, marker, { bookingId });
    await assertManagerOnlyBookingActionsAbsent(driver, marker, { bookingId });

    const deleteGuard = await assertStaffCannotDeleteBooking(driver, bookingId, marker);
    assert.equal(deleteGuard.deleteStatus, 403);

    const revenueGuard = await assertStaffCannotAccessManagerEndpoint(driver, '/boss/analytics/revenue', {
      label: 'boss revenue analytics',
    });
    assert.equal(revenueGuard.status, 403);

    const auditGuard = await assertStaffCannotAccessManagerEndpoint(driver, '/boss/audit-logs', {
      label: 'boss audit logs',
    });
    assert.equal(auditGuard.status, 403);

    await logout(driver);
  });
});
