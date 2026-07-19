/**
 * Phase 4.4 — centralized reservation status / charge / action helpers.
 * Keep exact backend values in API payloads; use these labels in templates.
 */

export const RESERVATION_STATUS_LABELS = {
  pending_charge: 'Pending Charge',
  confirmed: 'Confirmed',
  cancelled: 'Cancelled',
  expired: 'Expired',
  completed: 'Completed',
};

export const CHARGE_STATUS_LABELS = {
  required: 'Charge Required',
  confirmed: 'Charge Confirmed',
  waived: 'Waived',
  not_required: 'No Charge Required',
  cancelled: 'Charge Cancelled',
};

export const AUDIT_ACTION_LABELS = {
  reservation_created: 'Reservation created',
  charge_confirmation_recorded: 'Manual charge confirmation recorded',
  charge_waived: 'Service fee waived',
  reservation_confirmed: 'Reservation confirmed',
  reservation_cancelled: 'Reservation cancelled',
  reservation_expired: 'Reservation manually expired',
  reservation_completed: 'Item collected / completed',
};

export function reservationStatusLabel(status) {
  return RESERVATION_STATUS_LABELS[status] || status || 'Unknown';
}

export function chargeStatusLabel(status) {
  return CHARGE_STATUS_LABELS[status] || status || 'Unknown';
}

export function auditActionLabel(action) {
  return AUDIT_ACTION_LABELS[action] || action || 'Activity';
}

export function formatReservationFee(amount, currency = 'MYR') {
  if (amount == null || amount === '') return '—';
  const value = Number(amount);
  if (Number.isNaN(value)) return '—';
  const prefix = currency === 'MYR' ? 'RM' : `${currency} `;
  return `${prefix} ${value.toFixed(2)}`;
}

export function isZeroFee(amount) {
  return Number(amount) === 0;
}

export function feeExplanation(amount) {
  if (isZeroFee(amount)) {
    return 'No reservation service fee is required for this item.';
  }
  return 'The system records the reservation and required service fee. Payment is handled manually outside the platform and confirmed by the Organizer.';
}

export function requiresNoRefundAcknowledgement(reservation) {
  return reservation?.charge_status === 'confirmed';
}

export function canCommunityCancel(reservation) {
  return reservation?.reservation_status === 'pending_charge';
}

export function canVendorCancel(reservation) {
  return ['pending_charge', 'confirmed'].includes(reservation?.reservation_status);
}

export function canCompleteReservation(reservation) {
  return reservation?.reservation_status === 'confirmed';
}

export function canOrganizerConfirmCharge(reservation) {
  return (
    reservation?.reservation_status === 'pending_charge'
    && reservation?.charge_status === 'required'
  );
}

export function canOrganizerWaiveCharge(reservation) {
  return canOrganizerConfirmCharge(reservation);
}

export function canOrganizerCancelOrExpire(reservation) {
  return ['pending_charge', 'confirmed'].includes(reservation?.reservation_status);
}

export function canShowReserveCta({
  item,
  isAuthenticated = false,
  isCommunityMember = false,
  isCmartWorker = false,
} = {}) {
  if (!item?.is_reservable) return false;
  if (item?.is_own_item) return false;
  if (isCmartWorker) return false;
  if (isAuthenticated && !isCommunityMember) return false;
  return true;
}

export function reserveCtaMode({
  item,
  isAuthenticated = false,
  isCommunityMember = false,
  isCmartWorker = false,
} = {}) {
  if (!canShowReserveCta({ item, isAuthenticated, isCommunityMember, isCmartWorker })) {
    return 'hidden';
  }
  if (!isAuthenticated) return 'login';
  return 'reserve';
}

export function myReservationsPath(auth) {
  if (auth?.isVendorUser) return '/dashboard#my-item-reservations';
  return '/community#my-item-reservations';
}

export function reservationErrorMessage(error, fallback = 'Unable to update this reservation.') {
  const data = error?.response?.data;
  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message;
  }
  return fallback;
}

export function reservationConflictCode(error) {
  return error?.response?.data?.error || null;
}

export function reservationStatusBadgeClass(status) {
  switch (status) {
    case 'pending_charge':
      return 'bg-amber-100 text-amber-800 ring-amber-200';
    case 'confirmed':
      return 'bg-emerald-100 text-emerald-800 ring-emerald-200';
    case 'completed':
      return 'bg-sky-100 text-sky-800 ring-sky-200';
    case 'cancelled':
      return 'bg-rose-100 text-rose-800 ring-rose-200';
    case 'expired':
      return 'bg-ink-100 text-ink-700 ring-ink-200';
    default:
      return 'bg-ink-100 text-ink-700 ring-ink-200';
  }
}

export function chargeStatusBadgeClass(status) {
  switch (status) {
    case 'required':
      return 'bg-amber-50 text-amber-800 ring-amber-200';
    case 'confirmed':
      return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    case 'waived':
      return 'bg-violet-50 text-violet-800 ring-violet-200';
    case 'not_required':
      return 'bg-ink-50 text-ink-700 ring-ink-200';
    case 'cancelled':
      return 'bg-rose-50 text-rose-800 ring-rose-200';
    default:
      return 'bg-ink-50 text-ink-700 ring-ink-200';
  }
}

export function formatReservationTimestamp(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('en-MY', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
