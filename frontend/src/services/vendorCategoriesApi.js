export async function fetchVendorCategories(api) {
  const { data } = await api.get('/vendor-categories');
  return Array.isArray(data?.categories) ? data.categories : [];
}

export function categoryConflictMessage(errorCode, fallback) {
  const messages = {
    CATEGORY_REQUIRED: 'Please select a selling category first.',
    SITE_CATEGORY_INCOMPATIBLE: 'The selected sites do not match your selling category.',
    MIXED_CATEGORY_SITE_SELECTION: 'All selected sites must belong to the same category.',
    LAYOUT_CHANGED: 'The event layout has changed. Please review and select sites again.',
    EVENT_LAYOUT_NOT_READY: 'The event layout is not ready for booking yet.',
    CATEGORY_INACTIVE: 'This category is no longer active.',
    CATEGORY_ARCHIVED: 'This category is no longer available.',
    SITE_MISSING_LAYOUT_ROW: 'This site is not configured correctly and cannot be booked.',
    SITE_ROW_INACTIVE: 'This site row is no longer active.',
    site_day_occupied: 'One or more sites are no longer available.',
    UNKNOWN_LEGACY_CATEGORY: 'This category is no longer active.',
    CATEGORY_NOT_FOUND: 'This category is no longer active.',
  };

  return messages[errorCode] || fallback || 'Unable to submit booking.';
}
