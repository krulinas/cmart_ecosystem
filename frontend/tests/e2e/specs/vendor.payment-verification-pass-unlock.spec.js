import { strict as assert } from 'node:assert';
import {
  env,
  requireOrganizerCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import {
  e2eT6APayPassMarker,
  e2eT6BRejectedMarker,
  e2eT6BWithdrawnMarker,
  e2eT6CNotPendingMarker,
  e2eT6DDoubleVerifyMarker,
} from '../helpers/actions.js';
import { loginAsOrganizer, loginAsVendor, logout } from '../helpers/auth.js';
import { runE2EApprovalPipeline } from '../helpers/approval-pipeline.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import { createRejectedE2EBooking } from '../helpers/payment-guards.js';
import {
  MANAGEMENT_PAID_STATUS,
  assertCannotVerifyPaidTwice,
  assertCannotVerifyPaymentUnlessPending,
  verifyPaymentAsPaid,
} from '../helpers/payment-verification.js';
import {
  VENDOR_APPROVED_EXPECTATION,
  assertVendorBookingApproved,
  createWithdrawnE2EBooking,
  goToMyBookings,
  vendorStatusMatches,
} from '../helpers/vendor-bookings.js';
import {
  VENDOR_PENDING_VERIFICATION_STATUS,
  assertVendorPaymentActionUnavailable,
  assertVendorPaymentSubmitted,
  assertVendorReceiptAndPassLocked,
  assertVendorReceiptOrPassVisible,
  goToVendorPaymentRecords,
  openVendorPaymentAction,
  submitVendorPayment,
  uploadVendorPaymentProof,
} from '../helpers/vendor-payment-records.js';
import { setActiveDriver } from '../setup.js';

describe('Vendor payment verification and pass unlock', function () {
  this.timeout(600000);

  let driver;

  before(async function () {
    requireVendorCredentials();
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('6A - Vendor cannot unlock receipt or pass until organizer verifies payment as Paid', async function () {
    let marker = e2eT6APayPassMarker();
    let bookingId;

    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    marker = ensured.marker;
    await logout(driver);

    const approved = await runE2EApprovalPipeline(driver, marker);
    marker = approved.marker;
    bookingId = approved.bookingId;

    await loginAsVendor(driver);
    await goToMyBookings(driver, env.baseUrl);

    const approvedView = await assertVendorBookingApproved(driver, marker, { bookingId });
    assert.ok(
      vendorStatusMatches(approvedView.statusAttr, approvedView.statusLabel, VENDOR_APPROVED_EXPECTATION),
      `Booking #${bookingId} must remain Approved before payment verification.`,
    );

    const unpaidLocked = await assertVendorReceiptAndPassLocked(driver, marker, {
      bookingId,
      expectedPaymentStatus: 'Unpaid',
      expectViewInvoice: true,
    });

    assert.equal(unpaidLocked.paymentStatus, 'Unpaid');
    assert.notEqual(unpaidLocked.paymentStatus, 'Paid');

    await openVendorPaymentAction(driver, bookingId);
    await uploadVendorPaymentProof(driver);
    await submitVendorPayment(driver, { bookingId });

    const pendingSubmitted = await assertVendorPaymentSubmitted(driver, marker, {
      bookingId,
      expectedStatus: VENDOR_PENDING_VERIFICATION_STATUS,
    });

    assert.equal(pendingSubmitted.paymentStatus, VENDOR_PENDING_VERIFICATION_STATUS);

    const pendingLocked = await assertVendorReceiptAndPassLocked(driver, marker, {
      bookingId,
      expectedPaymentStatus: VENDOR_PENDING_VERIFICATION_STATUS,
      expectViewInvoice: true,
    });

    assert.equal(pendingLocked.paymentStatus, VENDOR_PENDING_VERIFICATION_STATUS);
    assert.notEqual(pendingLocked.paymentStatus, 'Paid');

    await logout(driver);

    await loginAsOrganizer(driver);
    const verified = await verifyPaymentAsPaid(driver, marker, { bookingId, baseUrl: env.baseUrl });

    assert.equal(
      verified.paymentStatus,
      MANAGEMENT_PAID_STATUS,
      `Management registry should show Paid for booking #${bookingId} after Verify Paid.`,
    );
    await logout(driver);

    await loginAsVendor(driver);
    await goToVendorPaymentRecords(driver, env.baseUrl);

    const unlocked = await assertVendorReceiptOrPassVisible(driver, marker, {
      bookingId,
      baseUrl: env.baseUrl,
    });

    assert.equal(unlocked.bookingId, bookingId);
    assert.equal(unlocked.paymentStatus, 'Paid');
    assert.ok(unlocked.eventLabel);
    assert.ok(unlocked.boothLabel);
    assert.ok(unlocked.bookingReference.includes(String(bookingId)));
  });

  it('6B - Vendor cannot submit payment for Withdrawn or Rejected bookings', async function () {
    await logout(driver);

    const withdrawnMarker = e2eT6BWithdrawnMarker();
    const withdrawn = await createWithdrawnE2EBooking(driver, withdrawnMarker);

    const withdrawnGuard = await assertVendorPaymentActionUnavailable(driver, withdrawn.marker, {
      bookingId: withdrawn.bookingId,
      baseUrl: env.baseUrl,
      expectedPaymentStatus: 'Unpaid',
    });

    assert.equal(withdrawnGuard.paymentStatus, 'Unpaid');
    assert.notEqual(withdrawnGuard.paymentStatus, VENDOR_PENDING_VERIFICATION_STATUS);
    await logout(driver);

    const rejectedMarker = e2eT6BRejectedMarker();
    const rejected = await createRejectedE2EBooking(driver, rejectedMarker);

    await loginAsVendor(driver);
    const rejectedGuard = await assertVendorPaymentActionUnavailable(driver, rejected.marker, {
      bookingId: rejected.bookingId,
      baseUrl: env.baseUrl,
      expectedPaymentStatus: 'Unpaid',
    });

    assert.equal(rejectedGuard.paymentStatus, 'Unpaid');
    assert.notEqual(rejectedGuard.paymentStatus, VENDOR_PENDING_VERIFICATION_STATUS);
    await logout(driver);
  });

  it('6C - Organizer cannot verify payment unless status is Pending Verification', async function () {
    await logout(driver);

    let marker = e2eT6CNotPendingMarker();
    let bookingId;

    const approved = await runE2EApprovalPipeline(driver, marker);
    marker = approved.marker;
    bookingId = approved.bookingId;

    await loginAsOrganizer(driver);
    const guard = await assertCannotVerifyPaymentUnlessPending(driver, marker, {
      bookingId,
      baseUrl: env.baseUrl,
      expectedStatus: 'Unpaid',
    });

    assert.equal(guard.paymentStatus, 'Unpaid');
    assert.notEqual(guard.paymentStatus, MANAGEMENT_PAID_STATUS);
    await logout(driver);
  });

  it('6D - Paid booking cannot be verified twice', async function () {
    await logout(driver);

    let marker = e2eT6DDoubleVerifyMarker();
    let bookingId;

    const approved = await runE2EApprovalPipeline(driver, marker);
    marker = approved.marker;
    bookingId = approved.bookingId;

    await loginAsVendor(driver);
    await goToVendorPaymentRecords(driver, env.baseUrl);
    await openVendorPaymentAction(driver, bookingId);
    await uploadVendorPaymentProof(driver);
    await submitVendorPayment(driver, { bookingId });
    await logout(driver);

    await loginAsOrganizer(driver);
    const verified = await verifyPaymentAsPaid(driver, marker, { bookingId, baseUrl: env.baseUrl });
    assert.equal(verified.paymentStatus, MANAGEMENT_PAID_STATUS);

    const duplicateGuard = await assertCannotVerifyPaidTwice(driver, marker, {
      bookingId,
      baseUrl: env.baseUrl,
    });

    assert.equal(duplicateGuard.paymentStatus, MANAGEMENT_PAID_STATUS);
    await logout(driver);
  });
});
