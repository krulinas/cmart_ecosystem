/**
 * Build the vendor booking route, optionally scoped to a specific event.
 * Preserves event_id through login redirects for unauthenticated users.
 */
export function vendorBookingLink(eventId, auth) {
  const bookingPath = eventId ? `/vendor-booking?event_id=${eventId}` : '/vendor-booking';

  if (auth?.isAuthenticated) {
    return bookingPath;
  }

  return `/login?redirect=${encodeURIComponent(bookingPath)}`;
}
