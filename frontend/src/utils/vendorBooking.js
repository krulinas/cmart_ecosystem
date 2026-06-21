/**
 * Build the vendor booking route, optionally scoped to a specific event.
 * Preserves event_id through login redirects for unauthenticated vendors.
 */
export function vendorBookingLink(eventId, auth) {
  const bookingPath = eventId ? `/vendor-booking?event_id=${eventId}` : '/vendor-booking';

  if (auth?.isApprovedVendor) {
    return bookingPath;
  }

  return `/login?redirect=${encodeURIComponent(bookingPath)}`;
}
