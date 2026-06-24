import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from './actions.js';
import { loginAsManager, loginAsStaff, loginAsVendor, logout } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import {
  approveE2EBookingAsManager,
  forwardE2EBookingToManager,
  openStaffBookings,
} from './staff-bookings.js';

export async function runE2EApprovalPipeline(driver, marker = uniqueTestMarker(env.bookingDetails)) {
  requireVendorCredentials();
  requireStaffCredentials();
  requireManagerCredentials();

  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsStaff(driver);
  await openStaffBookings(driver, env.baseUrl);
  const forwarded = await forwardE2EBookingToManager(driver, marker, env.baseUrl);
  await logout(driver);

  await loginAsManager(driver);
  await openStaffBookings(driver, env.baseUrl);
  const approved = await approveE2EBookingAsManager(driver, marker, env.baseUrl, {
    bookingId: forwarded.bookingId,
  });
  await logout(driver);

  return {
    marker,
    bookingId: approved.bookingId,
    forwardedBookingId: forwarded.bookingId,
  };
}

export async function loginVendorForApprovedBooking(driver) {
  await loginAsVendor(driver);
}
