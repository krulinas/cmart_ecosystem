import { env } from '../config/env.js';
import { loginAsOrganizer, logout } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import { openOrganizerBookings, rejectE2EBookingAsOrganizer } from './organizer-bookings.js';

export async function createRejectedE2EBooking(driver, marker, baseUrl = env.baseUrl) {
  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsOrganizer(driver);
  await openOrganizerBookings(driver, baseUrl);
  const rejected = await rejectE2EBookingAsOrganizer(driver, marker, baseUrl);
  await logout(driver);

  return { marker, bookingId: rejected.bookingId };
}
