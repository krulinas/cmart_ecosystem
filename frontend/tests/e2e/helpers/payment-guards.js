import { env } from '../config/env.js';
import { loginAsStaff, logout } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import { openStaffBookings, rejectE2EBookingAsStaff } from './staff-bookings.js';

export async function createRejectedE2EBooking(driver, marker, baseUrl = env.baseUrl) {
  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsStaff(driver);
  await openStaffBookings(driver, baseUrl);
  const rejected = await rejectE2EBookingAsStaff(driver, marker, baseUrl);
  await logout(driver);

  return { marker, bookingId: rejected.bookingId };
}
