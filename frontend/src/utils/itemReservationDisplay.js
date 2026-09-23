import { tt } from '../i18n';
import { formatLocaleDateTime } from './localeFormat';
import {
  CHARGE_STATUS_KEYS,
  CHARGE_STATUS_VALUES,
  RESERVATION_STATUS_KEYS,
  RESERVATION_STATUS_VALUES,
  buildReservationLifecycle as buildLifecycleCore,
  canCompleteReservation as canCompleteCore,
  canOrganizerConfirmCharge as canConfirmCore,
  chargeStatusLabel as chargeLabelCore,
  chargeStatusOptions as chargeOptionsCore,
  humanizeStatusKey,
  normalizeStatusKey,
  reservationStatusLabel as reservationLabelCore,
  reservationStatusOptions as reservationOptionsCore,
} from './itemReservationStatusCore';

/**
 * Phase 4.4 / Part 04 — centralized reservation status / charge / action helpers.
 * Keep exact backend values in API payloads; translate only at the display boundary.
 */

export {
  CHARGE_STATUS_KEYS,
  CHARGE_STATUS_VALUES,
  RESERVATION_STATUS_KEYS,
  RESERVATION_STATUS_VALUES,
  humanizeStatusKey,
  normalizeStatusKey,
};

export const AUDIT_ACTION_KEYS = {
  reservation_created: 'reservation.auditCreated',
  charge_confirmation_recorded: 'reservation.auditChargeConfirmed',
  charge_waived: 'reservation.auditChargeWaived',
  reservation_confirmed: 'reservation.auditConfirmed',
  reservation_cancelled: 'reservation.auditCancelled',
  reservation_expired: 'reservation.auditExpired',
  reservation_completed: 'reservation.auditCompleted',
};

/** @deprecated Prefer reservationStatusOptions(t); kept for callers reading maps. */
export const RESERVATION_STATUS_LABELS = RESERVATION_STATUS_KEYS;
export const CHARGE_STATUS_LABELS = CHARGE_STATUS_KEYS;
export const AUDIT_ACTION_LABELS = AUDIT_ACTION_KEYS;

export function reservationStatusLabel(status, t = tt) {
  return reservationLabelCore(status, t);
}

export function chargeStatusLabel(status, t = tt) {
  return chargeLabelCore(status, t);
}

export function auditActionLabel(action, t = tt) {
  const key = AUDIT_ACTION_KEYS[action];
  if (!key) return action || t('reservation.activity');
  const translated = t(key);
  return translated === key ? humanizeStatusKey(action) : translated;
}

export function reservationStatusOptions(t = tt) {
  return reservationOptionsCore(t);
}

export function chargeStatusOptions(t = tt) {
  return chargeOptionsCore(t);
}

export function buildReservationLifecycle(reservation, t = tt, options = {}) {
  return buildLifecycleCore(reservation, t, options);
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
    return tt('reservation.confirm.feeZero');
  }
  return tt('reservation.confirm.feeExplain');
}

export function requiresNoRefundAcknowledgement(reservation) {
  return normalizeStatusKey(reservation?.charge_status) === 'confirmed';
}

export function canCommunityCancel(reservation) {
  return normalizeStatusKey(reservation?.reservation_status) === 'pending_charge';
}

export function canVendorCancel(reservation) {
  return ['pending_charge', 'confirmed'].includes(normalizeStatusKey(reservation?.reservation_status));
}

export function canCompleteReservation(reservation) {
  return canCompleteCore(reservation);
}

export function canOrganizerConfirmCharge(reservation) {
  return canConfirmCore(reservation);
}

export function canOrganizerWaiveCharge(reservation) {
  return canOrganizerConfirmCharge(reservation);
}

export function canOrganizerCancelOrExpire(reservation) {
  return ['pending_charge', 'confirmed'].includes(normalizeStatusKey(reservation?.reservation_status));
}

export function canShowReserveCta(args = {}) {
  const mode = reserveCtaMode(args);
  return mode === 'reserve' || mode === 'login';
}

export function reservationAvailabilityCode(item) {
  return item?.reservation_availability?.code
    || (item?.has_active_reservation
      ? 'already_reserved'
      : item?.is_own_item
        ? 'own_item'
        : item?.is_reservable
          ? 'available'
          : 'not_available');
}

export function reserveCtaMode({
  item,
  isAuthenticated = false,
  isCommunityMember = false,
  isCmartWorker = false,
} = {}) {
  if (!item) return 'hidden';

  const code = reservationAvailabilityCode(item);

  if (code === 'own_item' || item?.is_own_item) return 'own_item';
  if (code === 'already_reserved' || item?.has_active_reservation) return 'already_reserved';
  if (code === 'event_reservations_not_configured') return 'not_configured';
  if (code === 'no_eligible_upcoming_event') return 'not_available';
  if (isCmartWorker || (isAuthenticated && !isCommunityMember)) return 'ineligible_role';

  const backendAvailable = item?.reservation_availability?.available === true || item?.is_reservable;
  if (!backendAvailable || code === 'not_available') return 'not_available';

  if (!isAuthenticated) return 'login';
  if (!isCommunityMember) return 'ineligible_role';
  return 'reserve';
}

export function myReservationsPath(auth) {
  if (auth?.isVendorUser) return '/my-reservations';
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
  switch (normalizeStatusKey(status)) {
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
  switch (normalizeStatusKey(status)) {
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
  return formatLocaleDateTime(date, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
