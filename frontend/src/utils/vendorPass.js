const MY_TZ = 'Asia/Kuala_Lumpur';

export const PASS_STATUS_LABELS = {
  pending_approval: 'Pending Approval',
  approved: 'Approved',
  checkin_open: 'Check-in Open',
  checked_in: 'Checked In',
  event_active: 'Event Active',
  completed: 'Completed',
  expired: 'Expired',
  cancelled: 'Cancelled',
};

export const passStatusBadgeClass = (status) => {
  const map = {
    pending_approval: 'ml-badge bg-brand-100 text-brand-800',
    approved: 'ml-badge bg-emerald-100 text-emerald-800',
    checkin_open: 'ml-badge bg-cyan-100 text-cyan-800',
    checked_in: 'ml-badge bg-indigo-100 text-indigo-800',
    event_active: 'ml-badge bg-purple-100 text-purple-800',
    completed: 'ml-badge bg-ink-100 text-ink-700',
    expired: 'ml-badge bg-rose-100 text-rose-800',
    cancelled: 'ml-badge bg-rose-100 text-rose-800',
  };

  return map[status] || 'ml-badge bg-ink-100 text-ink-700';
};

export const isValidBookingDate = (dateStr) => {
  if (!dateStr) return false;
  if (String(dateStr).startsWith('0000')) return false;
  const date = new Date(dateStr);
  return !Number.isNaN(date.getTime()) && date.getFullYear() > 1970;
};

export const formatBookingDateLong = (dateStr) => {
  if (!isValidBookingDate(dateStr)) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

export const formatBookingDateShort = (dateStr) => {
  if (!isValidBookingDate(dateStr)) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

export const formatEventTimeLabel = (pass) => {
  if (pass?.event_time_label) return pass.event_time_label;
  return 'All day event';
};

export const buildVerifyUrl = (bookingId, origin = typeof window !== 'undefined' ? window.location.origin : '') => {
  if (!bookingId) return '';
  return `${origin}/verify-booking/${bookingId}`;
};

export const buildStaffVerifyUrl = (bookingId, origin = typeof window !== 'undefined' ? window.location.origin : '') => {
  if (!bookingId) return '';
  return `${origin}/organizer/verify-booking/${bookingId}`;
};

export const buildQrImageUrl = (bookingId, origin) => {
  const verifyUrl = buildStaffVerifyUrl(bookingId, origin);
  if (!verifyUrl) return '';
  return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(verifyUrl)}`;
};

/** @deprecated Use pass.pass_status from API */
export const passStateForBooking = (booking) => {
  if (!booking) return 'locked';
  if (booking.approval_status === 'Approved') return 'active';
  if (['Rejected', 'Cancelled'].includes(booking.approval_status)) return 'invalid';
  return 'locked';
};

export const productCategoryLabel = (booking) => booking?.product_category || 'Others';

export const productDetailsLabel = (booking) => {
  const category = productCategoryLabel(booking);
  const details = booking?.product_details?.trim();
  return details ? `${category} · ${details}` : category;
};

export const passFromApi = (pass) => pass;

export const isPassQrScannable = (pass) => Boolean(pass?.show_qr && pass?.qr_active);

export const passQrDisabledMessage = (pass) => {
  if (!pass) return 'No pass selected.';
  if (!pass.show_qr) return pass.pending_message || 'QR will be available after approval.';
  if (pass.qr_expired || pass.pass_status === 'expired') return 'This QR code has expired.';
  if (pass.pass_status === 'completed') return 'This event pass is completed. QR is no longer active.';
  if (pass.pass_status === 'cancelled') return 'This pass has been cancelled.';
  if (!pass.qr_active) return 'QR check-in opens 3 hours before the event starts.';
  return null;
};
