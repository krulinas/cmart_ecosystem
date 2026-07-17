import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { reassignmentErrorMessage } from '../../src/services/organizerSiteReassignmentMessages.js';

const organizerDetails = readFileSync(
  new URL('../../src/components/organizer/OrganizerWithdrawalReconciliationModal.vue', import.meta.url),
  'utf8',
);
const reassignmentModal = readFileSync(
  new URL('../../src/components/organizer/OrganizerSiteReassignmentModal.vue', import.meta.url),
  'utf8',
);

describe('Organizer site reassignment', () => {
  it('maps important backend error codes to BM messages', () => {
    assert.match(reassignmentErrorMessage('BOOKING_PAYMENT_LOCKED'), /bayaran/i);
    assert.match(reassignmentErrorMessage('CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED'), /sahkan/i);
    assert.match(reassignmentErrorMessage('ASSIGNMENT_CHANGED'), /muat semula/i);
    assert.match(reassignmentErrorMessage('UNKNOWN_CODE'), /Susunan tapak tidak dapat dikemas kini/i);
  });

  it('renders placement summary and reassignment action in organizer booking details', () => {
    for (const testId of [
      'organizer-category-placement',
      'organizer-compatibility-status',
      'organizer-open-site-reassignment',
      'organizer-active-override',
      'organizer-reassignment-blockers',
    ]) {
      assert.match(organizerDetails, new RegExp(`data-testid="${testId}"`));
    }
    assert.match(organizerDetails, /Susun Semula Tapak/);
    assert.match(organizerDetails, /Kategori Tempahan/);
    assert.match(organizerDetails, /Status Keserasian/);
  });

  it('renders reassignment modal controls for options, mismatch reveal, and override', () => {
    for (const testId of [
      'organizer-site-reassignment-modal',
      'reassignment-options-loading',
      'reveal-mismatched-rows',
      'reassignment-row-badge',
      'reassignment-site-option',
      'reassignment-override-warning',
      'reassignment-override-acknowledgement',
      'reassignment-override-reason',
      'reassignment-confirm',
      'reassignment-api-error',
    ]) {
      assert.match(reassignmentModal, new RegExp(`data-testid="${testId}"`));
    }
    assert.match(reassignmentModal, /Tunjukkan baris kategori lain/);
    assert.match(reassignmentModal, /Sepadan dengan kategori tempahan/);
    assert.match(reassignmentModal, /Kategori berbeza — memerlukan pengecualian/);
    assert.match(reassignmentModal, /assignment_fingerprint/);
    assert.match(reassignmentModal, /acknowledge_category_override/);
  });
});
