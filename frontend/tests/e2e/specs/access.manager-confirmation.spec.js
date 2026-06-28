import { strict as assert } from 'node:assert';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { e2eT7BManagerApproveMarker, e2eT7BManagerRejectMarker } from '../helpers/actions.js';
import {
  assertManagerBookingActionsVisible,
  assertManagerCanAccessEndpoint,
  assertManagerDeleteControlVisibleInRegistry,
  prepareBookingForManagerReview,
} from '../helpers/access-guards.js';
import { loginAsManager, logout } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import {
  APPROVED_EXPECTATION,
  REJECTED_EXPECTATION,
  approveE2EBookingAsManager,
  findE2EBookingRow,
  openStaffBookings,
  rejectE2EBookingAsManager,
  searchStaffBookings,
  statusMatchesExpectation,
  waitForRowStatus,
} from '../helpers/staff-bookings.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Manager access confirmation', function () {
  this.timeout(300000);

  let driver;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    requireManagerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Manager can approve a forwarded Pending_Boss booking', async function () {
    const marker = e2eT7BManagerApproveMarker();
    const prepared = await prepareBookingForManagerReview(driver, marker);

    await loginAsManager(driver);
    await openStaffBookings(driver, env.baseUrl);
    await waitForTestId(driver, 'staff-dashboard-root');
    await waitForTestId(driver, 'staff-bookings-root');

    const match = await findE2EBookingRow(driver, prepared.marker, {
      preferActionTestId: 'staff-booking-action-approve',
      bookingId: prepared.bookingId,
    });

    assert.ok(match.hasApproveAction, `Booking #${prepared.bookingId} must expose Approve in manager queue.`);
    await assertManagerBookingActionsVisible(driver, prepared.marker, { bookingId: prepared.bookingId });
    await searchStaffBookings(driver, prepared.marker);
    await assertManagerDeleteControlVisibleInRegistry(driver, prepared.bookingId);

    const approved = await approveE2EBookingAsManager(driver, prepared.marker, env.baseUrl, {
      bookingId: prepared.bookingId,
    });

    assert.ok(
      statusMatchesExpectation(approved.statusAttr, approved.statusLabel, APPROVED_EXPECTATION),
      `Booking #${approved.bookingId} should reach Approved after manager approval.`,
    );

    await searchStaffBookings(driver, prepared.marker);
    const confirmed = await waitForRowStatus(driver, prepared.bookingId, APPROVED_EXPECTATION, 15000);
    assert.ok(
      statusMatchesExpectation(confirmed.statusAttr, confirmed.statusLabel, APPROVED_EXPECTATION),
      `Approved booking #${prepared.bookingId} must remain searchable in manager registry.`,
    );

    await logout(driver);
  });

  it('Manager can reject a forwarded Pending_Boss booking', async function () {
    const marker = e2eT7BManagerRejectMarker();
    const prepared = await prepareBookingForManagerReview(driver, marker);

    await loginAsManager(driver);
    await openStaffBookings(driver, env.baseUrl);

    const match = await findE2EBookingRow(driver, prepared.marker, {
      preferActionTestId: 'staff-booking-action-approve',
      bookingId: prepared.bookingId,
    });

    assert.ok(match.hasApproveAction, `Booking #${prepared.bookingId} must expose manager queue actions.`);
    await assertManagerBookingActionsVisible(driver, prepared.marker, { bookingId: prepared.bookingId });
    await searchStaffBookings(driver, prepared.marker);
    await assertManagerDeleteControlVisibleInRegistry(driver, prepared.bookingId);

    const rejected = await rejectE2EBookingAsManager(driver, prepared.marker, env.baseUrl, {
      bookingId: prepared.bookingId,
    });

    assert.ok(
      statusMatchesExpectation(rejected.statusAttr, rejected.statusLabel, REJECTED_EXPECTATION),
      `Booking #${rejected.bookingId} should reach Rejected after manager rejection.`,
    );

    await searchStaffBookings(driver, prepared.marker);
    const confirmed = await waitForRowStatus(driver, prepared.bookingId, REJECTED_EXPECTATION, 15000);
    assert.ok(
      statusMatchesExpectation(confirmed.statusAttr, confirmed.statusLabel, REJECTED_EXPECTATION),
      `Rejected booking #${prepared.bookingId} must remain searchable in manager registry.`,
    );

    await logout(driver);
  });

  it('Manager can access representative manager-only API endpoints', async function () {
    await loginAsManager(driver);
    await openStaffBookings(driver, env.baseUrl);
    await waitForTestId(driver, 'staff-dashboard-root');

    const revenue = await assertManagerCanAccessEndpoint(driver, '/boss/analytics/revenue', {
      label: 'boss revenue analytics',
    });
    assert.equal(revenue.status, 200);

    const auditLogs = await assertManagerCanAccessEndpoint(driver, '/boss/audit-logs', {
      label: 'boss audit logs',
    });
    assert.equal(auditLogs.status, 200);

    await logout(driver);
  });
});
