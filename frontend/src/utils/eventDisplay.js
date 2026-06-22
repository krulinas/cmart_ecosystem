import { resolveEventImageUrl, normalizeEvent } from './imageUrl';

export const DEFAULT_EVENT_LOCATION = 'CMart Kompleks Changlun, Changlun';

export const EVENT_TZ = 'Asia/Kuala_Lumpur';

export const parseEventInstant = (value) => {
  if (!value) return null;

  if (typeof value === 'string') {
    const trimmed = value.trim();
    // Naive wall-clock strings from MySQL/API (no timezone): treat as Malaysia local.
    const naive = trimmed.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/);
    if (naive && !/[Zz]|[+-]\d{2}:\d{2}$/.test(trimmed)) {
      const isoLocal = `${naive[1]}T${naive[2].length === 5 ? `${naive[2]}:00` : naive[2]}+08:00`;
      const zoned = new Date(isoLocal);
      if (!Number.isNaN(zoned.getTime())) return zoned;
    }
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

export const getEventZonedParts = (value) => {
  const parsed = parseEventInstant(value);
  if (!parsed) return null;

  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: EVENT_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });

  const parts = Object.fromEntries(
    formatter.formatToParts(parsed).map((part) => [part.type, part.value]),
  );

  return {
    year: parts.year,
    month: parts.month,
    day: parts.day,
    hour: parts.hour,
    minute: parts.minute,
  };
};

/** Format API datetime for `<input type="datetime-local">` in Malaysia time. */
export const toDatetimeLocalValue = (value) => {
  const parts = getEventZonedParts(value);
  if (!parts) return '';
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

/** Convert datetime-local value to Laravel-friendly wall-clock string (no UTC shift). */
export const fromDatetimeLocalValue = (value) => {
  if (!value) return '';
  const [datePart, timePart = '00:00'] = value.split('T');
  const [hours, minutes = '00'] = timePart.split(':');
  return `${datePart} ${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}:00`;
};

export const formatEventDateTime = (value) => {
  const parsed = parseEventInstant(value);
  if (!parsed) return '';

  return parsed.toLocaleString('en-GB', {
    timeZone: EVENT_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

export const getEventCalendarParts = (value) => {
  const parsed = parseEventInstant(value);
  if (!parsed) return { day: '', month: '' };

  const parts = getEventZonedParts(value);
  const month = parsed.toLocaleString('en-GB', {
    timeZone: EVENT_TZ,
    month: 'short',
  });

  return {
    day: String(Number(parts.day)),
    month,
  };
};

export const statusClassForEvent = (status) => {
  if (status === 'Available') return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
  if (status === 'Almost Full') return 'bg-amber-100 text-amber-800 border border-amber-200';
  return 'bg-gray-100 text-gray-700 border border-gray-200';
};

export const formatEventTime = (startsAt, endsAt) => {
  const opts = {
    timeZone: EVENT_TZ,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  };
  const start = parseEventInstant(startsAt);
  const end = parseEventInstant(endsAt);
  if (!start || !end) return '';

  return `${start.toLocaleTimeString('en-GB', opts)} – ${end.toLocaleTimeString('en-GB', opts)}`;
};

export const formatEventDate = (startsAt) => {
  const parsed = parseEventInstant(startsAt);
  if (!parsed) return '';

  return parsed.toLocaleDateString('en-GB', {
    timeZone: EVENT_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

export const mapApiEventToCard = (ev, location = DEFAULT_EVENT_LOCATION) => {
  const normalized = normalizeEvent(ev);
  const { day, month } = getEventCalendarParts(normalized.starts_at);

  return {
    id: normalized.id,
    day,
    month,
    title: normalized.title,
    time: formatEventTime(normalized.starts_at, normalized.ends_at),
    dateLabel: formatEventDate(normalized.starts_at),
    location,
    description: normalized.description || 'Join us for a weekend of bargains, local vendors, and community fun.',
    status: normalized.status,
    statusClass: statusClassForEvent(normalized.status),
    posterUrl: resolveEventImageUrl(normalized),
    images: normalized.images || [],
    startsAt: normalized.starts_at,
    endsAt: normalized.ends_at,
  };
};
