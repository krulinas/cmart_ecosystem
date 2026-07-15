import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
  buildRecoveryQueryParams,
  formatOperationalDate,
  recoveryBlockerSummary,
  recoveryPaymentLabel,
  recoveryStateBadgeClass,
  recoveryStateLabel,
  recoveryStateOptions,
  releasedSiteLabels,
  releaseReasonLabel,
} from '../../src/utils/bookingDisplay.js';

const panel = readFileSync(
  new URL('../../src/components/organizer/OrganizerReleasedDayRecoveryPanel.vue', import.meta.url),
  'utf8',
);
const modal = readFileSync(
  new URL('../../src/components/organizer/OrganizerReleasedDayRecoveryModal.vue', import.meta.url),
  'utf8',
);
const vendorDetails = readFileSync(
  new URL('../../src/components/VendorBookingDetailsModal.vue', import.meta.url),
  'utf8',
);

const row = {
  id: 'booking-41-day-9',
  source_booking: { id: 41, reference: 'BKG-0041', status: 'Approved' },
  event: { id: 7, title: 'Carboot Weekend' },
  event_day: { id: 9, operational_date: '2026-08-09' },
  released_sites: [
    { id: 101, label: 'A01', recovery_state: 'recoverable', blocker: null },
    { id: 102, label: 'A02', recovery_state: 'fully_blocked', blocker: 'Occupied by another active booking' },
  ],
  source_payment_state: 'paid',
  recovery_state: 'partially_blocked',
  standard_full_event_available: false,
  release: { reason: 'organizer_day_exception', released_at: '2026-08-01T10:00:00+08:00', released_by: { name: 'Organizer' } },
  attendance_exception_reason: 'Emergency family commitment on the final event day.',
};

describe('Organizer released-day recovery', () => {
  it('renders queue structure and stable test ids', () => {
    assert.match(panel, /data-testid="organizer-released-day-recovery-panel"/);
    assert.match(panel, /data-testid="recovery-queue-row"/);
    assert.match(panel, /data-testid="recovery-state-chip"/);
    assert.match(panel, /data-testid="recovery-empty-state"/);
    assert.match(panel, /data-testid="recovery-loading"/);
    assert.match(panel, /data-testid="recovery-pagination"/);
    assert.match(panel, /data-testid="recovery-view-detail"/);
    assert.doesNotMatch(panel, /Assign Vendor/);
    assert.doesNotMatch(panel, /Create Invoice/);
    assert.doesNotMatch(panel, /Refund/);
  });

  it('formats recovery display helpers', () => {
    assert.equal(releasedSiteLabels(row), 'A01, A02');
    assert.equal(recoveryPaymentLabel('paid'), 'Paid');
    assert.equal(recoveryStateLabel('recoverable'), 'Recoverable');
    assert.equal(recoveryStateLabel('partially_blocked'), 'Partially Blocked');
    assert.equal(recoveryStateLabel('fully_blocked'), 'Fully Blocked');
    assert.equal(recoveryStateLabel('expired'), 'Expired');
    assert.equal(recoveryStateLabel('operationally_unavailable'), 'Operationally Unavailable');
    assert.match(recoveryBlockerSummary(row), /Occupied/);
    assert.match(formatOperationalDate('2026-08-09'), /2026/);
    assert.equal(releaseReasonLabel('organizer_day_exception'), 'Organizer attendance exception');
    assert.match(recoveryStateBadgeClass('recoverable'), /emerald/);
  });

  it('builds API filter params', () => {
    assert.deepEqual(buildRecoveryQueryParams(), { page: 1, per_page: 15 });
    assert.deepEqual(
      buildRecoveryQueryParams({ search: 'BKG', recoveryState: 'recoverable', paymentState: 'paid', page: 2 }),
      { page: 2, per_page: 15, search: 'BKG', recovery_state: 'recoverable', payment_state: 'paid' },
    );
    assert.equal(recoveryStateOptions.length, 5);
  });

  it('opens read-only detail modal without forbidden controls', () => {
    assert.match(modal, /Released-Day Recovery Detail/);
    assert.match(modal, /data-testid="recovery-detail-modal"/);
    assert.match(modal, /data-testid="recovery-exception-reason"/);
    assert.match(modal, /data-testid="recovery-standard-availability"/);
    assert.match(modal, /data-testid="recovery-recoverable-copy"/);
    assert.doesNotMatch(modal, /Assign Vendor/);
    assert.doesNotMatch(modal, /Create Invoice/);
    assert.doesNotMatch(modal, /Refund/);
    assert.doesNotMatch(modal, /active_lock/);
    assert.doesNotMatch(modal, /payment_proof_path/);
  });

  it('keeps vendor components free of recovery queue data', () => {
    assert.doesNotMatch(vendorDetails, /recovery_state/);
    assert.doesNotMatch(vendorDetails, /released-day-recovery/);
    assert.doesNotMatch(vendorDetails, /recovery-queue/);
  });
});
