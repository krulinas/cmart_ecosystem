import { PENDING_STATUSES } from './bookingDisplay';

const ONBOARDING_STATES = {
  WELCOME: 'welcome',
  PENDING: 'pending',
  NEEDS_REVISION: 'needs_revision',
  REJECTED: 'rejected',
  ACTIVE: 'active',
};

/**
 * Derive the vendor workspace onboarding banner state from booking records.
 * Prefers the most recent actionable booking when multiple exist.
 */
export function resolveVendorOnboardingState(bookings = []) {
  if (!Array.isArray(bookings) || bookings.length === 0) {
    return ONBOARDING_STATES.WELCOME;
  }

  const sorted = [...bookings].sort((a, b) => (b.id ?? 0) - (a.id ?? 0));
  const latest = sorted[0];
  const status = latest?.approval_status;

  if (status === 'Needs_Revision') {
    return ONBOARDING_STATES.NEEDS_REVISION;
  }

  if (status === 'Rejected' || status === 'Cancelled') {
    return ONBOARDING_STATES.REJECTED;
  }

  if (PENDING_STATUSES.includes(status)) {
    return ONBOARDING_STATES.PENDING;
  }

  if (status === 'Approved') {
    return ONBOARDING_STATES.ACTIVE;
  }

  return ONBOARDING_STATES.WELCOME;
}

export const VENDOR_ONBOARDING_COPY = {
  welcome: {
    title: 'Welcome to your vendor workspace',
    message:
      'Set up your profile, prepare your listings, and submit a booth booking. CMart management will review your booking before event participation is confirmed.',
    tone: 'brand',
  },
  pending: {
    title: 'Your vendor booking is under review',
    message:
      'CMart staff will review your submission. You can continue exploring CMart and preparing your vendor profile while waiting.',
    tone: 'info',
  },
  needs_revision: {
    title: 'Action needed',
    message:
      'Your booking needs revision. Please review the staff note and update your submission.',
    tone: 'warning',
  },
  rejected: {
    title: 'Booking not approved',
    message:
      'This booking was not approved. You may review the details and submit a new booking for another event if available.',
    tone: 'neutral',
  },
  active: {
    title: 'Your vendor workspace is active',
    message:
      'Your booking has been approved. Manage your listings, receipts, and event passes from this dashboard.',
    tone: 'success',
  },
};

export { ONBOARDING_STATES };
