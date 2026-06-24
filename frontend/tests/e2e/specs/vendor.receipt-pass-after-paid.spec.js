import { strict as assert } from 'node:assert';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { loginVendorForApprovedBooking, runE2EApprovalPipeline } from '../helpers/approval-pipeline.js';
import { createDriver } from '../helpers/driver.js';
import { verifyPaymentAsPaid } from '../helpers/payment-verification.js';
import {
  VENDOR_PENDING_VERIFICATION_STATUS,
  assertVendorPaymentSubmitted,
  assertVendorReceiptOrPassVisible,
  goToVendorPaymentRecords,
  openVendorPaymentAction,
  submitVendorPayment,
  uploadVendorPaymentProof,
  waitForVendorPaidStatus,
} from '../helpers/vendor-payment-records.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Vendor receipt and pass after paid verification', function () {
  this.timeout(360000);

  let driver;
  let marker;
  let bookingId;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    requireManagerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Vendor sees Paid receipt and unlocked event pass after staff verifies payment', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const approved = await runE2EApprovalPipeline(driver, marker);
    marker = approved.marker;
    bookingId = approved.bookingId;

    await loginVendorForApprovedBooking(driver);
    await goToVendorPaymentRecords(driver, env.baseUrl);

    await openVendorPaymentAction(driver, bookingId);
    await uploadVendorPaymentProof(driver);
    await submitVendorPayment(driver, { bookingId });

    const pending = await assertVendorPaymentSubmitted(driver, marker, {
      bookingId,
      expectedStatus: VENDOR_PENDING_VERIFICATION_STATUS,
    });

    assert.equal(
      pending.paymentStatus,
      VENDOR_PENDING_VERIFICATION_STATUS,
      `Payment record for booking #${bookingId} should remain Pending Verification until CMart verifies it.`,
    );

    await logout(driver);

    await loginAsStaff(driver);
    const verified = await verifyPaymentAsPaid(driver, marker, { bookingId, baseUrl: env.baseUrl });

    assert.equal(
      verified.paymentStatus,
      'Paid',
      `Management registry should show Paid for booking #${bookingId} after verification.`,
    );

    await logout(driver);

    await loginVendorForApprovedBooking(driver);

    const paid = await waitForVendorPaidStatus(driver, bookingId, {
      baseUrl: env.baseUrl,
    });

    assert.equal(paid.paymentStatus, 'Paid');

    const receiptAndPass = await assertVendorReceiptOrPassVisible(driver, marker, {
      bookingId,
      baseUrl: env.baseUrl,
    });

    assert.equal(receiptAndPass.bookingId, bookingId);
    assert.ok(receiptAndPass.eventLabel);
    assert.ok(receiptAndPass.boothLabel);
  });
});
