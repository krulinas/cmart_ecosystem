export function reassignmentErrorMessage(errorCode, fallback) {
  const messages = {
    BOOKING_NOT_REASSIGNABLE: 'This booking cannot be reassigned in its current state.',
    BOOKING_PAYMENT_LOCKED: 'Sites cannot be changed after payment is submitted or verified.',
    BOOKING_ALLOCATION_CONFIRMED: 'Sites cannot be changed because the booking allocation is confirmed.',
    EVENT_DAY_ALREADY_STARTED: 'Sites cannot be changed after an event day has started.',
    SITE_COUNT_CHANGE_NOT_SUPPORTED: 'The new site count must match the original booking.',
    SITE_PRICE_CHANGE_NOT_SUPPORTED: 'The new site type or price must match the original booking.',
    TARGET_SITE_UNAVAILABLE: 'One or more selected sites are no longer available.',
    TARGET_SITE_SELECTION_INVALID: 'The site selection does not meet layout rules.',
    TARGET_SITE_MIXED_ROWS: 'All sites must be selected from the same row.',
    TARGET_SITE_MIXED_CATEGORIES: 'All sites must use the same row category.',
    CATEGORY_OVERRIDE_REQUIRED: 'This selection requires a category exception.',
    CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED: 'Confirm that you understand this category exception.',
    CATEGORY_OVERRIDE_REASON_REQUIRED: 'Provide a reason for the category exception.',
    CATEGORY_OVERRIDE_REASON_TOO_SHORT: 'The exception reason is too short.',
    ASSIGNMENT_CHANGED: 'Site assignment has changed. Refresh and review your selection.',
    EVENT_LAYOUT_NOT_READY: 'The event layout is not ready yet.',
  };

  return messages[errorCode] || fallback || 'Site assignment could not be updated. Refresh and try again.';
}
