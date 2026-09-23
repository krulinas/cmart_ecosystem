import { PENDING_STATUSES } from './bookingDisplay';
import { tt } from '../i18n';

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

/** Map onboarding state ids to camelCase catalog key prefixes. */
const ONBOARDING_COPY_PREFIX = {
  welcome: 'welcome',
  pending: 'pending',
  needs_revision: 'needsRevision',
  rejected: 'rejected',
  active: 'active',
};

/** Localized onboarding copy for the current UI locale. */
export function vendorOnboardingCopy(state, t = tt) {
  const key = state || ONBOARDING_STATES.WELCOME;
  const prefix = ONBOARDING_COPY_PREFIX[key] || 'welcome';
  return {
    title: t(`vendor.onboarding.${prefix}Title`),
    message: t(`vendor.onboarding.${prefix}Message`),
    tone: VENDOR_ONBOARDING_TONES[key] || 'brand',
  };
}

const VENDOR_ONBOARDING_TONES = {
  welcome: 'brand',
  pending: 'info',
  needs_revision: 'warning',
  rejected: 'neutral',
  active: 'success',
};

/** @deprecated Prefer vendorOnboardingCopy(state). English snapshot for tests/legacy. */
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
      'The Carboot Organizer will review your submission. Payment will be available after approval.',
    tone: 'info',
  },
  needs_revision: {
    title: 'Action needed',
    message:
      'Your booking needs revision. Please review the organizer note and update your submission.',
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
      'Your booking has been approved. Use Manage for listings and passes, and Account for receipts and insights.',
    tone: 'success',
  },
};

export { ONBOARDING_STATES };
