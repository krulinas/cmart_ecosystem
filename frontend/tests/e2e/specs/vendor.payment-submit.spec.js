import { strict as assert } from 'node:assert';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginVendorForApprovedBooking, runE2EApprovalPipeline } from '../helpers/approval-pipeline.js';
import { createDriver } from '../helpers/driver.js';
import {
  VENDOR_PENDING_VERIFICATION_STATUS,
  assertVendorPaymentRecordVisible,
  assertVendorPaymentSubmitted,
  goToVendorPaymentRecords,
  openVendorPaymentAction,
  submitVendorPayment,
  uploadVendorPaymentProof,
} from '../helpers/vendor-payment-records.js';
import { setActiveDriver } from '../setup.js';

const E2E_MARKER_BASE = env.bookingDetails;

describe('Vendor payment submission', function () {
  this.timeout(300000);

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

  it('Vendor user can submit payment proof for an approved E2E-marked booking', async function () {
    marker = uniqueTestMarker(E2E_MARKER_BASE);
    const approved = await runE2EApprovalPipeline(driver, marker);
    marker = approved.marker;
    bookingId = approved.bookingId;

    await loginVendorForApprovedBooking(driver);
    await goToVendorPaymentRecords(driver, env.baseUrl);

    const unpaidRecord = await assertVendorPaymentRecordVisible(driver, marker, {
      bookingId,
    });

    assert.equal(
      unpaidRecord.paymentStatus,
      'Unpaid',
      `Expected an unpaid invoice before submission for booking #${bookingId}.`,
    );

    await openVendorPaymentAction(driver, bookingId);
    await uploadVendorPaymentProof(driver);
    await submitVendorPayment(driver, { bookingId });

    const submitted = await assertVendorPaymentSubmitted(driver, marker, {
      bookingId,
      expectedStatus: VENDOR_PENDING_VERIFICATION_STATUS,
    });

    assert.equal(
      submitted.paymentStatus,
      VENDOR_PENDING_VERIFICATION_STATUS,
      `Payment record for booking #${bookingId} should show Pending Verification after proof upload.`,
    );
    assert.equal(
      submitted.bookingId,
      bookingId,
      'Submitted payment record must remain linked to the same E2E-marked booking.',
    );
  });
});
