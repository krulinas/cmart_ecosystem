import {
  env,
  requireOrganizerCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from './actions.js';
import { loginAsOrganizer, loginAsVendor, logout } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import {
  approveOrganizerBooking,
  openOrganizerBookings,
} from './organizer-bookings.js';

export async function runE2EApprovalPipeline(driver, marker = uniqueTestMarker(env.bookingDetails)) {
  requireVendorCredentials();
  requireOrganizerCredentials();

  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsOrganizer(driver);
  await openOrganizerBookings(driver, env.baseUrl);
  const approved = await approveOrganizerBooking(driver, marker, env.baseUrl);
  await logout(driver);

  return {
    marker,
    bookingId: approved.bookingId,
  };
}

export async function loginVendorForApprovedBooking(driver) {
  await loginAsVendor(driver);
}
