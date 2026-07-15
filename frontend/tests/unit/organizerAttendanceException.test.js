import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
  attendanceExceptionValidation,
  attendancePolicyForBooking,
  attendanceReleaseCount,
  attendanceRetainedDayIds,
} from '../../src/utils/bookingDisplay.js';

const policy = {
  has_exception: false,
  retained_days: [
    { id: 21, operational_date: '2026-08-01', has_started: true },
    { id: 22, operational_date: '2026-08-02', has_started: false },
    { id: 23, operational_date: '2026-08-03', has_started: false },
  ],
  released_days: [{ id: 20, operational_date: '2026-07-31' }],
  requires_no_refund_acknowledgement: true,
  no_refund_warning: 'Pengecualian hari tidak mengubah jumlah bayaran.',
};

const organizerModal = readFileSync(
  new URL('../../src/components/organizer/OrganizerAttendanceExceptionModal.vue', import.meta.url),
  'utf8',
);
const organizerDetails = readFileSync(
  new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
  'utf8',
);
const vendorDetails = readFileSync(
  new URL('../../src/components/VendorBookingDetailsModal.vue', import.meta.url),
  'utf8',
);

describe('Organizer attendance exception', () => {
  it('reads the full-event attendance policy', () => {
    assert.equal(attendancePolicyForBooking({ attendance_policy: policy }), policy);
    assert.equal(attendancePolicyForBooking({}), null);
  });

  it('starts with every currently retained day selected', () => {
    assert.deepEqual(attendanceRetainedDayIds(policy), [21, 22, 23]);
  });

  it('counts only newly deselected retained days', () => {
    assert.equal(attendanceReleaseCount(policy, [21, 22]), 1);
    assert.equal(attendanceReleaseCount(policy, [21]), 2);
  });

  it('requires one retained day and a meaningful reason', () => {
    assert.match(attendanceExceptionValidation(policy, [], 'Long enough reason', true), /at least one/i);
    assert.match(attendanceExceptionValidation(policy, [21], 'short', true), /at least 10/i);
  });

  it('requires at least one actual reduction', () => {
    assert.match(attendanceExceptionValidation(policy, [21, 22, 23], 'Long enough reason', true), /Deselect/i);
  });

  it('requires acknowledgement for paid or submitted payment', () => {
    assert.match(attendanceExceptionValidation(policy, [21, 22], 'Long enough reason', false), /acknowledgement/i);
    assert.equal(attendanceExceptionValidation(policy, [21, 22], 'Long enough reason', true), '');
  });

  it('does not require acknowledgement for unpaid bookings', () => {
    const unpaid = { ...policy, requires_no_refund_acknowledgement: false };
    assert.equal(attendanceExceptionValidation(unpaid, [21, 22], 'Long enough reason', false), '');
  });

  it('renders stable day, reason, warning, error, and confirmation selectors', () => {
    for (const testId of [
      'attendance-event-day-list',
      'attendance-event-day-option',
      'attendance-exception-reason',
      'attendance-no-refund-warning',
      'attendance-no-refund-acknowledgement',
      'attendance-validation-error',
      'attendance-api-error',
      'attendance-exception-confirm',
    ]) {
      assert.match(organizerModal, new RegExp(`data-testid="${testId}"`));
    }
  });

  it('locks released and started retained days while allowing future changes', () => {
    assert.match(organizerModal, /Already released · cannot be re-added/);
    assert.match(organizerModal, /Started or completed · must remain retained/);
    assert.match(organizerModal, /Future day · may be released/);
    assert.match(organizerModal, /:disabled="isDayDisabled\(day\) \|\| submitting"/);
  });

  it('blocks double submit and disables confirmation while submitting', () => {
    assert.match(organizerModal, /if \(!canSubmit\.value\) return/);
    assert.match(organizerModal, /!submitting\.value && !validationError\.value/);
    assert.match(organizerModal, /:disabled="!canSubmit"/);
  });

  it('Organizer details expose the safe policy and audit action surface', () => {
    assert.match(organizerDetails, /organizer-attendance-policy/);
    assert.match(organizerDetails, /organizer-apply-attendance-exception/);
    assert.match(organizerDetails, /organizer-booking-audit-timeline/);
    assert.match(organizerDetails, /Invoice amount unchanged · No refund/);
  });

  it('Vendor details are read-only and contain no day or site mutation controls', () => {
    assert.match(vendorDetails, /This booking covers all active event days/);
    assert.match(vendorDetails, /Attendance exception approved by Organizer/);
    assert.match(vendorDetails, /vendor-retained-event-days/);
    assert.match(vendorDetails, /vendor-released-event-days/);
    assert.match(vendorDetails, /No refund applies to released EventDays/);
    assert.doesNotMatch(vendorDetails, /Apply Attendance Exception/);
    assert.doesNotMatch(vendorDetails, /attendance-event-day-option/);
    assert.doesNotMatch(vendorDetails, /Change Site|Swap Site|Restore Day/i);
  });
});
