import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
  VENDOR_PAYMENT_APPROVAL_REQUIRED_MESSAGE,
  canVendorPayBooking,
  canVendorProceedToDemoPayment,
  resolveVendorFocusPrimaryAction,
  resolveVendorPaymentUi,
  vendorBookingStatusHint,
  vendorPaymentBlockedMessage,
} from '../../src/utils/bookingDisplay.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');

const booking = (approval, payment, extras = {}) => ({
  id: extras.id ?? 41,
  approval_status: approval,
  invoice: {
    amount: extras.amount ?? 20,
    payment_status: payment,
  },
  ...extras,
});

describe('vendor payment eligibility', () => {
  it('does not treat unpaid pending bookings as payable', () => {
    const pending = booking('Pending_Organizer', 'Unpaid');
    assert.equal(canVendorPayBooking(pending), false);
    assert.equal(canVendorProceedToDemoPayment(pending), false);
    assert.equal(vendorPaymentBlockedMessage(pending), VENDOR_PAYMENT_APPROVAL_REQUIRED_MESSAGE);
  });

  it('unlocks payment only for approved unpaid bookings', () => {
    const approved = booking('Approved', 'Unpaid');
    assert.equal(canVendorPayBooking(approved), true);
    assert.equal(canVendorProceedToDemoPayment(approved), true);
    assert.equal(vendorPaymentBlockedMessage(approved), null);
  });

  it('blocks payment after proof is submitted or paid', () => {
    const submitted = booking('Approved', 'Pending Verification');
    const paid = booking('Approved', 'Paid');
    assert.equal(canVendorPayBooking(submitted), false);
    assert.equal(canVendorPayBooking(paid), false);
    assert.match(vendorPaymentBlockedMessage(submitted), /verification/i);
    assert.match(vendorPaymentBlockedMessage(paid), /already been paid/i);
  });

  it('locks Pay Now before organizer approval', () => {
    const pending = booking('Pending_Organizer', 'Unpaid');
    const payment = resolveVendorPaymentUi(pending);
    const action = resolveVendorFocusPrimaryAction(pending, payment);

    assert.equal(payment.canPay, false);
    assert.equal(payment.value, 'Locked until approval');
    assert.match(payment.hint, /due after approval/);
    assert.match(payment.lockCopy, /organizer approves/);
    assert.equal(vendorBookingStatusHint(pending), 'Waiting for organizer approval');
    assert.equal(action.label, 'Pay Now');
    assert.equal(action.disabled, true);
    assert.equal(action.locked, true);
    assert.match(action.lockHint, /organizer approves/);
  });

  it('enables Pay Now for approved unpaid bookings', () => {
    const approved = booking('Approved', 'Unpaid');
    const payment = resolveVendorPaymentUi(approved);
    const action = resolveVendorFocusPrimaryAction(approved, payment);

    assert.equal(payment.canPay, true);
    assert.equal(payment.value, 'Unpaid');
    assert.match(payment.hint, /RM20.00 due/);
    assert.equal(vendorBookingStatusHint(approved), 'Ready for payment');
    assert.equal(action.label, 'Pay Now');
    assert.equal(Boolean(action.disabled), false);
    assert.equal(action.emphasis, 'payment');
  });

  it('does not present Pay Now while payment is awaiting verification', () => {
    const submitted = booking('Approved', 'Pending Verification');
    const payment = resolveVendorPaymentUi(submitted, { invoice_available: true });
    const action = resolveVendorFocusPrimaryAction(submitted, payment);

    assert.equal(payment.canPay, false);
    assert.equal(payment.value, 'Payment submitted');
    assert.equal(payment.hint, 'Waiting for organizer verification.');
    assert.notEqual(action?.label, 'Pay Now');
  });

  it('replaces Pay Now with the event pass action once paid', () => {
    const paid = booking('Approved', 'Paid');
    const payment = resolveVendorPaymentUi(paid);
    const action = resolveVendorFocusPrimaryAction(paid, payment);

    assert.equal(payment.canPay, false);
    assert.equal(payment.value, 'Paid');
    assert.equal(action.label, 'View Event Pass');
    assert.equal(action.type, 'view-pass');
  });

  it('keeps revision as the next action instead of payment', () => {
    const revision = booking('Needs_Revision', 'Unpaid');
    const payment = resolveVendorPaymentUi(revision);
    const action = resolveVendorFocusPrimaryAction(revision, payment);

    assert.equal(canVendorPayBooking(revision), false);
    assert.equal(action.label, 'Review Booking');
  });

  it('gates history-receipt rows with the same helper', () => {
    assert.equal(
      canVendorPayBooking({
        booking_status: 'Pending_Organizer',
        payment_status: 'Unpaid',
        invoice_available: true,
      }),
      false,
    );
    assert.equal(
      canVendorPayBooking({
        booking_status: 'Approved',
        payment_status: 'Unpaid',
        invoice_available: true,
      }),
      true,
    );
  });
});

describe('vendor payment CTA wiring', () => {
  const focusSource = readFileSync(
    join(root, 'src/components/vendor/VendorDashboardFocus.vue'),
    'utf8',
  );
  const dashboardSource = readFileSync(
    join(root, 'src/views/dashboards/VendorDashboard.vue'),
    'utf8',
  );
  const detailsSource = readFileSync(
    join(root, 'src/components/VendorBookingDetailsModal.vue'),
    'utf8',
  );
  const modalSource = readFileSync(
    join(root, 'src/components/VendorPaymentModal.vue'),
    'utf8',
  );
  const historySource = readFileSync(
    join(root, 'src/components/VendorHistoryReceipts.vue'),
    'utf8',
  );
  const checkoutSource = readFileSync(
    join(root, 'src/views/vendor/VendorCheckoutPage.vue'),
    'utf8',
  );
  const onboardingSource = readFileSync(
    join(root, 'src/utils/vendorOnboarding.js'),
    'utf8',
  );

  it('disables the dashboard Pay Now CTA before approval', () => {
    assert.equal(focusSource.includes(':disabled="Boolean(primaryAction.disabled)"'), true);
    assert.equal(focusSource.includes('if (!action || action.disabled) return;'), true);
    assert.equal(focusSource.includes("statusLower === 'unpaid'"), false);
    assert.equal(focusSource.includes('vendor-focus-pay-lock-hint'), true);
    assert.equal(dashboardSource.includes('canVendorPayBooking'), true);
    assert.equal(dashboardSource.includes('action.disabled'), true);
  });

  it('guards payment modal open and checkout navigation', () => {
    assert.equal(dashboardSource.includes('vendorPaymentBlockedMessage'), true);
    assert.equal(modalSource.includes('closeIfIneligible'), true);
    assert.equal(modalSource.includes('VENDOR_PAYMENT_APPROVAL_REQUIRED_MESSAGE') || modalSource.includes('vendorPaymentBlockedMessage'), true);
    assert.equal(detailsSource.includes('canVendorPayBooking(booking)'), true);
    assert.equal(detailsSource.includes('vendor-booking-payment-locked'), true);
    assert.equal(detailsSource.includes('goToCheckout'), true);
    assert.equal(historySource.includes('canVendorPayBooking(row)'), true);
    assert.equal(checkoutSource.includes('canVendorPayBooking'), true);
  });

  it('clarifies the pending onboarding banner without asking the vendor to pay', () => {
    assert.equal(onboardingSource.includes("title: 'Your vendor booking is under review'"), true);
    assert.equal(onboardingSource.includes('Payment will be available after approval.'), true);
    assert.equal(/pay now/i.test(onboardingSource), false);
  });
});
