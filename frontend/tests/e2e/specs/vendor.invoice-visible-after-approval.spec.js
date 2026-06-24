import { strict as assert } from 'node:assert';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsManager, loginAsStaff, loginAsVendor, logout } from '../helpers/auth.js';
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
import {
  VENDOR_APPROVED_EXPECTATION,
  assertVendorBookingApproved,
  goToMyBookings,
  vendorStatusMatches,
} from '../helpers/vendor-bookings.js';
import {
  assertVendorPaymentRecordVisible,
  goToVendorPaymentRecords,
} from '../helpers/vendor-payment-records.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Vendor invoice visible after approval', function () {
  this.timeout(300000);

  let driver;
  let marker;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    requireManagerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Vendor user can view an invoice/payment record after manager approval', async function () {
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
    const approved = await approveE2EBookingAsManager(driver, marker, env.baseUrl, {
      bookingId: forwarded.bookingId,
    });

    assert.ok(
      statusMatchesExpectation(approved.statusAttr, approved.statusLabel, APPROVED_EXPECTATION),
      `Manager approval did not reach Approved for booking #${approved.bookingId}.`,
    );

    await logout(driver);

    await loginAsVendor(driver);
    await goToMyBookings(driver, env.baseUrl);

    const vendorView = await assertVendorBookingApproved(driver, marker, {
      bookingId: approved.bookingId,
    });

    assert.ok(
      vendorStatusMatches(vendorView.statusAttr, vendorView.statusLabel, VENDOR_APPROVED_EXPECTATION),
      `Vendor My Bookings shows "${vendorView.statusAttr || vendorView.statusLabel}" for booking #${vendorView.bookingId}; expected Approved.`,
    );

    await goToVendorPaymentRecords(driver, env.baseUrl);

    const paymentRecord = await assertVendorPaymentRecordVisible(driver, marker, {
      bookingId: approved.bookingId,
    });

    assert.ok(
      paymentRecord.paymentStatus === 'Unpaid',
      `Expected unpaid invoice for booking #${paymentRecord.bookingId}; got "${paymentRecord.paymentStatus}".`,
    );
    assert.equal(
      paymentRecord.bookingId,
      approved.bookingId,
      'Payment record must match the E2E-marked approved booking.',
    );
  });
});
