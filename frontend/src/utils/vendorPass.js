const MY_TZ = 'Asia/Kuala_Lumpur';

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

export const formatEventTimeLabel = () => 'All day event';

export const buildVerifyUrl = (bookingId, origin = typeof window !== 'undefined' ? window.location.origin : '') => {
  if (!bookingId) return '';
  return `${origin}/verify-booking/${bookingId}`;
};

export const buildStaffVerifyUrl = (bookingId, origin = typeof window !== 'undefined' ? window.location.origin : '') => {
  if (!bookingId) return '';
  return `${origin}/staff/verify-booking/${bookingId}`;
};

export const buildQrImageUrl = (bookingId, origin) => {
  const verifyUrl = buildVerifyUrl(bookingId, origin);
  if (!verifyUrl) return '';
  return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(verifyUrl)}`;
};

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
