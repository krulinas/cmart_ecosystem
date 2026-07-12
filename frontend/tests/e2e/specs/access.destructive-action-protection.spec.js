import { strict as assert } from 'node:assert';
import {
  requireCmartManagementCredentials,
  requireOrganizerCredentials,
  requireVendorBCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import {
  e2eT7EDuplicateApproveMarker,
  e2eT7EDuplicatePaymentMarker,
  e2eT7EGuestDeleteMarker,
  e2eT7EStaffDeleteMarker,
  e2eT7ETerminalWithdrawMarker,
  e2eT7EVendorOtherMutationMarker,
} from '../helpers/actions.js';
import { createDriver } from '../helpers/driver.js';
import {
  assertCmartManagementDeleteGuard,
  assertDuplicateApproveDenied,
  assertDuplicatePaymentSubmitDenied,
  assertGuestDeleteGuard,
  assertTerminalWithdrawDenied,
  assertWrongVendorMutationGuard,
  captureDestructiveFailureDiagnostics,
  prepareApprovedBooking,
  preparePaidVendorBooking,
  prepareRejectedBooking,
  prepareWithdrawnBooking,
} from '../helpers/destructive-guards.js';
import { setActiveDriver } from '../setup.js';

describe('Destructive action protection', function () {
  this.timeout(360000);

  let driver;

  before(async function () {
    requireVendorCredentials();
    requireCmartManagementCredentials();
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('7E-A - cmart_management cannot access or delete bookings via API', async function () {
    const marker = e2eT7EStaffDeleteMarker();

    try {
      const result = await assertCmartManagementDeleteGuard(driver, marker);
      assert.equal(result.deleteStatus, 403);
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-cmart-mgmt-delete-failed', {
        marker,
        flow: '7E-A',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });

  it('7E-B - Guest cannot delete bookings and resource remains intact', async function () {
    const marker = e2eT7EGuestDeleteMarker();

    try {
      const result = await assertGuestDeleteGuard(driver, marker);
      assert.ok([401, 403, 404].includes(result.deleteStatus));
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-guest-delete-failed', {
        marker,
        flow: '7E-B',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });

  it('7E-C - Vendor A cannot mutate Vendor B booking via withdraw, payment, or update APIs', async function () {
    const vendorBMarker = e2eT7EVendorOtherMutationMarker();
    const vendorBCredentials = requireVendorBCredentials();

    try {
      const result = await assertWrongVendorMutationGuard(driver, vendorBMarker, vendorBCredentials);
      assert.ok(result.observed.length >= 3);

      for (const attempt of result.observed) {
        assert.ok(
          [403, 404].includes(attempt.status),
          `${attempt.label} must return 403 or 404; got ${attempt.status}.`,
        );
      }
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-wrong-vendor-mutation-failed', {
        vendorBMarker,
        flow: '7E-C',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });

  it('7E-D - Vendor cannot withdraw terminal or locked bookings', async function () {
    const terminalCases = [
      { label: 'Approved', prepare: prepareApprovedBooking, expectedStatus: 'Approved' },
      { label: 'Rejected', prepare: prepareRejectedBooking, expectedStatus: 'Rejected' },
      { label: 'Withdrawn', prepare: prepareWithdrawnBooking, expectedStatus: 'Withdrawn' },
      { label: 'Paid', prepare: preparePaidVendorBooking, expectedStatus: 'Approved' },
    ];

    try {
      for (const terminalCase of terminalCases) {
        const marker = e2eT7ETerminalWithdrawMarker(terminalCase.label);
        const result = await assertTerminalWithdrawDenied(driver, marker, terminalCase.prepare);

        assert.equal(result.approvalStatus, terminalCase.expectedStatus);
        assert.equal(result.withdrawStatus, 422);
      }
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-terminal-withdraw-failed', {
        flow: '7E-D',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });

  it('7E-E - Vendor cannot submit duplicate payment proof after Paid', async function () {
    const marker = e2eT7EDuplicatePaymentMarker();

    try {
      const result = await assertDuplicatePaymentSubmitDenied(driver, marker);
      assert.equal(result.paymentStatus, 'Paid');
      assert.equal(result.submitStatus, 422);
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-duplicate-payment-failed', {
        marker,
        flow: '7E-E',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });

  it('7E-F - Organizer cannot duplicate-approve an already Approved booking', async function () {
    const marker = e2eT7EDuplicateApproveMarker();

    try {
      const result = await assertDuplicateApproveDenied(driver, marker);
      assert.equal(result.approveStatus, 422);
    } catch (error) {
      const diagnostics = await captureDestructiveFailureDiagnostics(driver, 't7e-duplicate-approve-failed', {
        marker,
        flow: '7E-F',
      });
      error.message = `${error.message} Diagnostics: ${diagnostics.json}.`;
      throw error;
    }
  });
});
