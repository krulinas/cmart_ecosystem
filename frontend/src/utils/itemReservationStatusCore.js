/**
 * Pure reservation/charge status helpers (no Vue i18n import).
 * Display wrappers in itemReservationDisplay.js supply `t`.
 */

export const RESERVATION_STATUS_KEYS = {
  pending_charge: 'status.pending_charge',
  confirmed: 'status.confirmed',
  cancelled: 'status.cancelled',
  expired: 'status.expired',
  completed: 'status.completed',
};

export const CHARGE_STATUS_KEYS = {
  required: 'status.required',
  confirmed: 'status.charge_confirmed',
  waived: 'status.waived',
  not_required: 'status.not_required',
  cancelled: 'status.charge_cancelled',
};

export const RESERVATION_STATUS_VALUES = Object.keys(RESERVATION_STATUS_KEYS);
export const CHARGE_STATUS_VALUES = Object.keys(CHARGE_STATUS_KEYS);

export function normalizeStatusKey(status) {
  return String(status ?? '')
    .trim()
    .replace(/^status\./i, '')
    .toLowerCase();
}

export function humanizeStatusKey(status) {
  const raw = normalizeStatusKey(status).replace(/_/g, ' ').trim();
  if (!raw) return '';
  return raw.replace(/\b\w/g, (c) => c.toUpperCase());
}

function translateStatus(map, status, t, unknownKey = 'common.unknown') {
  const normalized = normalizeStatusKey(status);
  const lookup = normalized === 'cancelled' ? 'cancelled' : normalized;
  const key = map[lookup] || map[normalized];
  if (!key) {
    return humanizeStatusKey(status) || t(unknownKey);
  }
  const translated = t(key);
  if (translated === key || String(translated).startsWith('status.')) {
    return humanizeStatusKey(status) || t(unknownKey);
  }
  return translated;
}

export function reservationStatusLabel(status, t) {
  if (status == null || status === '') return t('common.unknown');
  return translateStatus(RESERVATION_STATUS_KEYS, status, t);
}

export function chargeStatusLabel(status, t) {
  if (status == null || status === '') return t('common.unknown');
  return translateStatus(CHARGE_STATUS_KEYS, status, t);
}

export function reservationStatusOptions(t) {
  return RESERVATION_STATUS_VALUES.map((value) => ({
    value,
    label: reservationStatusLabel(value, t),
  }));
}

export function chargeStatusOptions(t) {
  return CHARGE_STATUS_VALUES.map((value) => ({
    value,
    label: chargeStatusLabel(value, t),
  }));
}

export function canCompleteReservation(reservation) {
  return normalizeStatusKey(reservation?.reservation_status) === 'confirmed';
}

export function canOrganizerConfirmCharge(reservation) {
  return (
    normalizeStatusKey(reservation?.reservation_status) === 'pending_charge'
    && normalizeStatusKey(reservation?.charge_status) === 'required'
  );
}

/**
 * Compact lifecycle model matching ItemReservationLifecycleService.
 *
 * @param {object} reservation
 * @param {Function} t
 * @param {{ audits?: Array|null }} [options]
 */
export function buildReservationLifecycle(reservation, t, options = {}) {
  const status = normalizeStatusKey(reservation?.reservation_status);
  const charge = normalizeStatusKey(reservation?.charge_status);
  const audits = options.audits;
  const zeroFee = Number(reservation?.service_fee_amount) === 0
    || charge === 'not_required';

  const isTerminal = status === 'cancelled' || status === 'expired';
  const isCompleted = status === 'completed';
  const isConfirmed = status === 'confirmed' || isCompleted;
  const needsChargeDecision = !zeroFee && charge === 'required' && status === 'pending_charge';
  const chargeResolved = ['confirmed', 'waived', 'not_required'].includes(charge)
    || (isTerminal && charge === 'cancelled');

  const stages = [
    {
      id: 'received',
      label: t('reservation.lifecycle.received'),
      state: 'complete',
    },
    {
      id: 'charge_decision',
      label: zeroFee
        ? t('reservation.lifecycle.noChargeRequired')
        : charge === 'waived'
          ? t('reservation.lifecycle.chargeWaived')
          : charge === 'confirmed'
            ? t('reservation.lifecycle.chargeConfirmed')
            : t('reservation.lifecycle.chargeDecision'),
      state: needsChargeDecision
        ? 'current'
        : (chargeResolved || isConfirmed || isTerminal || zeroFee ? 'complete' : 'upcoming'),
    },
    {
      id: 'confirmed',
      label: t('reservation.lifecycle.confirmed'),
      state: 'upcoming',
    },
    {
      id: 'completed',
      label: t('reservation.lifecycle.completed'),
      state: 'upcoming',
    },
  ];

  if (status === 'pending_charge') {
    stages[2].state = 'upcoming';
    stages[3].state = 'upcoming';
  } else if (status === 'confirmed') {
    stages[2].state = 'current';
    stages[3].state = 'upcoming';
  } else if (isCompleted) {
    stages[2].state = 'complete';
    stages[3].state = 'complete';
  } else if (isTerminal) {
    const confirmedReached = resolveConfirmedReached(audits, charge);
    if (confirmedReached === true) {
      stages[2].state = 'complete';
    } else if (confirmedReached === false) {
      stages[2].state = 'skipped';
    } else {
      // Current status alone cannot prove Confirmed happened — stay neutral.
      stages[2].state = 'unknown';
      stages[2].a11yLabel = t('reservation.lifecycle.confirmedIndeterminate');
    }
    stages[3].state = 'skipped';
  }

  let terminal = null;
  if (status === 'cancelled') {
    terminal = { id: 'cancelled', label: t('reservation.lifecycle.cancelled'), state: 'terminal' };
  } else if (status === 'expired') {
    terminal = { id: 'expired', label: t('reservation.lifecycle.expired'), state: 'terminal' };
  }

  let nextAction = null;
  if (canOrganizerConfirmCharge(reservation)) {
    nextAction = t('reservation.lifecycle.nextConfirmOrWaive');
  } else if (canCompleteReservation(reservation)) {
    nextAction = t('reservation.lifecycle.nextMarkCollected');
  }

  const summaryParts = [
    reservationStatusLabel(status, t),
    chargeStatusLabel(charge, t),
  ];
  if (nextAction) summaryParts.push(nextAction);

  return {
    stages,
    terminal,
    nextAction,
    summary: summaryParts.filter(Boolean).join(' · '),
    chargeLabel: chargeStatusLabel(charge, t),
    reservationLabel: reservationStatusLabel(status, t),
  };
}

/**
 * Determine whether the Confirmed stage was reached for a terminal reservation.
 * @returns {true|false|null} null = not determinable
 */
export function resolveConfirmedReached(audits, chargeStatus) {
  const charge = normalizeStatusKey(chargeStatus);

  if (Array.isArray(audits) && audits.length > 0) {
    return audits.some((entry) => {
      const action = String(entry?.action || '');
      if ([
        'reservation_confirmed',
        'charge_confirmation_recorded',
        'charge_waived',
        'reservation_completed',
      ].includes(action)) {
        return true;
      }
      const to = normalizeStatusKey(entry?.to_reservation_status);
      return to === 'confirmed' || to === 'completed';
    });
  }

  // After termination, preserved charge statuses imply Confirmed was reached;
  // charge cancelled typically means terminated from pending_charge.
  if (['confirmed', 'waived', 'not_required'].includes(charge)) return true;
  if (charge === 'cancelled') return false;
  return null;
}
