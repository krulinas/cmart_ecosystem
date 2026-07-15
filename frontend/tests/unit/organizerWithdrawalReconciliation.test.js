import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
  bookingMatchesNoRefundFilter,
  organizerPaymentStateLabel,
  organizerReconciliationForBooking,
  safeAuditActionLabel,
} from '../../src/utils/bookingDisplay.js';

const paid = {
  approval_status: 'Withdrawn',
  withdrawal_reconciliation: {
    payment_state: 'paid',
    invoice_amount: '60.00',
    payment_proof_present: true,
    no_refund_applied: true,
    allocation_status: 'released',
    released_site_labels: ['A01', 'A02'],
  },
};

const submitted = {
  approval_status: 'Withdrawn',
  withdrawal_reconciliation: {
    payment_state: 'payment_submitted',
    invoice_amount: '60.00',
    payment_proof_present: true,
    no_refund_applied: true,
    allocation_status: 'released',
  },
};

const unpaid = {
  approval_status: 'Withdrawn',
  withdrawal_reconciliation: {
    payment_state: 'unpaid',
    invoice_amount: '60.00',
    payment_proof_present: false,
    no_refund_applied: false,
    allocation_status: 'released',
  },
};

describe('Organizer withdrawal reconciliation helpers', () => {
  it('labels paid, payment-submitted, and unpaid states distinctly', () => {
    assert.equal(organizerPaymentStateLabel('paid'), 'Paid');
    assert.equal(organizerPaymentStateLabel('payment_submitted'), 'Payment Submitted');
    assert.equal(organizerPaymentStateLabel('unpaid'), 'Unpaid');
  });

  it('preserves formatted Invoice amount and proof-presence indicator', () => {
    const reconciliation = organizerReconciliationForBooking(paid);
    assert.equal(reconciliation.invoice_amount, '60.00');
    assert.equal(reconciliation.payment_proof_present, true);
  });

  it('exposes released site labels and allocation status', () => {
    const reconciliation = organizerReconciliationForBooking(paid);
    assert.deepEqual(reconciliation.released_site_labels, ['A01', 'A02']);
    assert.equal(reconciliation.allocation_status, 'released');
  });

  it('matches no-refund applied filter', () => {
    assert.equal(bookingMatchesNoRefundFilter(paid, 'yes'), true);
    assert.equal(bookingMatchesNoRefundFilter(submitted, 'yes'), true);
    assert.equal(bookingMatchesNoRefundFilter(unpaid, 'yes'), false);
  });

  it('matches no-refund not-applicable filter', () => {
    assert.equal(bookingMatchesNoRefundFilter(unpaid, 'no'), true);
    assert.equal(bookingMatchesNoRefundFilter(paid, 'no'), false);
  });

  it('uses a safe unknown audit action fallback', () => {
    assert.equal(safeAuditActionLabel({ action: 'internal_unknown' }), 'Booking activity recorded');
    assert.equal(safeAuditActionLabel({ label: 'Vendor withdrew booking' }), 'Vendor withdrew booking');
  });

  it('returns null when Organizer reconciliation is absent', () => {
    assert.equal(organizerReconciliationForBooking({ approval_status: 'Withdrawn' }), null);
  });

  it('modal has stable reconciliation and audit test IDs', () => {
    const source = readFileSync(
      new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
      'utf8',
    );
    assert.match(source, /data-testid="organizer-withdrawal-reconciliation"/);
    assert.match(source, /data-testid="organizer-booking-audit-timeline"/);
    assert.match(source, /data-testid="organizer-booking-audit-item"/);
    assert.match(source, /data-testid="organizer-released-sites"/);
  });

  it('modal includes loading and empty audit states', () => {
    const source = readFileSync(
      new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
      'utf8',
    );
    assert.match(source, /organizer-booking-details-loading/);
    assert.match(source, /No booking audit events are available/);
  });

  it('modal renders audit content as Vue text, not raw HTML', () => {
    const source = readFileSync(
      new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
      'utf8',
    );
    assert.doesNotMatch(source, /v-html/);
    assert.match(source, /\{\{ item\.summary/);
  });

  it('modal adds no refund, reversal, restoration, or reassignment controls', () => {
    const source = readFileSync(
      new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
      'utf8',
    );
    const buttonText = [...source.matchAll(/<button[\s\S]*?<\/button>/gi)]
      .map((match) => match[0])
      .join(' ');
    assert.doesNotMatch(buttonText, /Refund|Reverse payment|Restore booking|Reassign site/i);
  });

  it('Vendor component does not render Organizer-only reconciliation fields', () => {
    const source = readFileSync(
      new URL('../../src/components/VendorBookingDetailsModal.vue', import.meta.url),
      'utf8',
    );
    assert.doesNotMatch(source, /withdrawal_reconciliation/);
    assert.doesNotMatch(source, /audit_timeline/);
  });
});
