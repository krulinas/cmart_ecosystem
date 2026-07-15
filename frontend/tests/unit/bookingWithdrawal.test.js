import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import {
  NO_REFUND_WITHDRAWAL_WARNING_MS,
  UNPAID_WITHDRAWAL_WARNING,
  canVendorWithdraw,
  requiresNoRefundAcknowledgement,
  withdrawnNoRefundNotice,
  withdrawalWarningMessage,
} from '../../src/utils/bookingDisplay.js';

describe('booking withdrawal policy helpers', () => {
  const unpaidBooking = {
    approval_status: 'Pending_Organizer',
    can_withdraw: true,
    withdrawal_policy: {
      can_withdraw: true,
      payment_state: 'unpaid',
      refund_allowed: false,
      requires_no_refund_acknowledgement: false,
      warning_message: UNPAID_WITHDRAWAL_WARNING,
    },
    invoice: { payment_status: 'Unpaid' },
  };

  const paidBooking = {
    approval_status: 'Approved',
    can_withdraw: true,
    withdrawal_policy: {
      can_withdraw: true,
      payment_state: 'paid',
      refund_allowed: false,
      requires_no_refund_acknowledgement: true,
      warning_message: NO_REFUND_WITHDRAWAL_WARNING_MS,
    },
    invoice: { payment_status: 'Paid' },
    site_selection: { allocation_status: 'released' },
  };

  it('shows mandatory Malay warning for paid bookings', () => {
    assert.equal(withdrawalWarningMessage(paidBooking), NO_REFUND_WITHDRAWAL_WARNING_MS);
    assert.match(withdrawalWarningMessage(paidBooking), /bayaran tidak akan dipulangkan/i);
  });

  it('shows unpaid withdrawal warning without refund language', () => {
    assert.equal(withdrawalWarningMessage(unpaidBooking), UNPAID_WITHDRAWAL_WARNING);
    assert.doesNotMatch(withdrawalWarningMessage(unpaidBooking), /refund/i);
  });

  it('requires acknowledgement for paid or payment-submitted states', () => {
    assert.equal(requiresNoRefundAcknowledgement(paidBooking), true);
    assert.equal(requiresNoRefundAcknowledgement(unpaidBooking), false);
  });

  it('uses backend can_withdraw policy when present', () => {
    assert.equal(canVendorWithdraw(paidBooking), true);
    assert.equal(
      canVendorWithdraw({ ...paidBooking, withdrawal_policy: { can_withdraw: false } }),
      false,
    );
  });

  it('shows no-refund outcome for withdrawn paid bookings', () => {
    const notice = withdrawnNoRefundNotice({
      approval_status: 'Withdrawn',
      withdrawal_policy: { payment_state: 'paid' },
      invoice: { payment_status: 'Paid' },
      site_selection: { allocation_status: 'released' },
    });
    assert.match(notice, /No refund was issued/i);
    assert.match(notice, /released/i);
  });

  it('does not show no-refund notice for unpaid withdrawn bookings', () => {
    assert.equal(
      withdrawnNoRefundNotice({
        approval_status: 'Withdrawn',
        withdrawal_policy: { payment_state: 'unpaid' },
        invoice: { payment_status: 'Unpaid' },
      }),
      null,
    );
  });

  it('hides withdrawal action for terminal bookings', () => {
    assert.equal(
      canVendorWithdraw({
        approval_status: 'Withdrawn',
        withdrawal_policy: { can_withdraw: false },
      }),
      false,
    );
  });
});
