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
  if (status === 'Closed') return 'bg-gray-100 text-gray-600 border border-gray-200';
  return 'bg-sky-100 text-sky-800 border border-sky-200';
};

export const statusDotClassForEvent = (status) => {
  if (status === 'Available') return 'bg-emerald-500';
  if (status === 'Almost Full') return 'bg-amber-500';
  if (status === 'Closed') return 'bg-gray-400';
  return 'bg-sky-500';
};

export const isEventBookable = (status) => status === 'Available' || status === 'Almost Full';

export const formatEventShortDate = (startsAt) => {
  const parsed = parseEventInstant(startsAt);
  if (!parsed) return '';

  return parsed.toLocaleDateString('en-GB', {
    timeZone: EVENT_TZ,
    day: 'numeric',
    month: 'short',
  });
};

export const formatEventChipTime = (startsAt) => {
  const parsed = parseEventInstant(startsAt);
  if (!parsed) return '';

  return parsed.toLocaleTimeString('en-GB', {
    timeZone: EVENT_TZ,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

export const eventDateKey = (value) => {
  const parts = getEventZonedParts(value);
  if (!parts) return '';
  return `${parts.year}-${parts.month}-${parts.day}`;
};

const startOfEventDay = (value) => {
  const parts = getEventZonedParts(value);
  if (!parts) return null;
  return new Date(`${parts.year}-${parts.month}-${parts.day}T00:00:00+08:00`);
};

/** Simple urgency label for event cards and modals (Malaysia timezone). */
export const getEventUrgencyLabel = (startsAt, endsAt) => {
  const start = parseEventInstant(startsAt);
  const end = parseEventInstant(endsAt);
  if (!start) return '';

  const now = new Date();
  if (end && end < now) return 'Event ended';
  if (start <= now && end && end >= now) return 'Happening now';
  if (start <= now && !end) return 'Happening now';

  const todayStart = startOfEventDay(now.toISOString());
  const eventStart = startOfEventDay(startsAt);
  if (!todayStart || !eventStart) return '';

  const diffDays = Math.round((eventStart - todayStart) / (24 * 60 * 60 * 1000));
  if (diffDays === 0) return 'Happening today';
  if (diffDays === 1) return 'Tomorrow';
  if (diffDays > 1) return `Starts in ${diffDays} days`;
  return '';
};

export const normalizeEventStatus = (status) => {
  const value = String(status || '').trim();
  if (!value) return 'Unknown';
  return value;
};

export const filterEventsByChip = (events, filter, monthKey = null) => {
  const list = Array.isArray(events) ? events : [];
  const now = new Date();

  if (filter === 'available') {
    return list.filter((ev) => ev.status === 'Available');
  }
  if (filter === 'closed') {
    return list.filter((ev) => ev.status === 'Closed' || ev.status === 'Almost Full');
  }
  if (filter === 'upcoming') {
    return list.filter((ev) => {
      const end = parseEventInstant(ev.ends_at ?? ev.endsAt);
      return !end || end >= now;
    });
  }
  if (filter === 'this-month' && monthKey) {
    return list.filter((ev) => {
      const key = eventDateKey(ev.starts_at ?? ev.startsAt);
      return key.startsWith(monthKey);
    });
  }
  return list;
};

/** Build a downloadable .ics file for an event card object. */
export const buildEventIcsContent = (event) => {
  if (!event) return '';

  const startsAt = event.startsAt ?? event.starts_at;
  const endsAt = event.endsAt ?? event.ends_at;
  const startParts = getEventZonedParts(startsAt);
  const endParts = getEventZonedParts(endsAt);
  if (!startParts) return '';

  const toIcsLocal = (parts) => `${parts.year}${parts.month}${parts.day}T${parts.hour}${parts.minute}00`;
  const uid = `carboot-event-${event.id}@cmart`;
  const summary = (event.title || 'Carboot Event').replace(/[,;\\]/g, '');
  const description = (event.description || '').replace(/\n/g, '\\n').replace(/[,;\\]/g, '');
  const location = (event.location || DEFAULT_EVENT_LOCATION).replace(/[,;\\]/g, '');

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Carboot@CMart//EN',
    'CALSCALE:GREGORIAN',
    'BEGIN:VEVENT',
    `UID:${uid}`,
    `DTSTART;TZID=Asia/Kuala_Lumpur:${toIcsLocal(startParts)}`,
  ];

  if (endParts) {
    lines.push(`DTEND;TZID=Asia/Kuala_Lumpur:${toIcsLocal(endParts)}`);
  }

  lines.push(
    `SUMMARY:${summary}`,
    `DESCRIPTION:${description}`,
    `LOCATION:${location}`,
    'END:VEVENT',
    'END:VCALENDAR',
  );

  return lines.join('\r\n');
};

export const downloadEventIcs = (event) => {
  const content = buildEventIcsContent(event);
  if (!content) return;

  const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `carboot-event-${event.id || 'event'}.ics`;
  link.click();
  URL.revokeObjectURL(url);
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

/** Numeric date for clarity, e.g. `27/06/2026` (Malaysia timezone). */
export const formatEventNumericDate = (value) => {
  const parts = getEventZonedParts(value);
  if (!parts) return '';
  return `${parts.day}/${parts.month}/${parts.year}`;
};

/** Numeric month/year, e.g. `06/2026`. */
export const formatMonthYearNumeric = (value) => {
  const parts = getEventZonedParts(value);
  if (!parts) return '';
  return `${parts.month}/${parts.year}`;
};

/**
 * Calendar month heading, e.g. `June 2026 · 06/2026`.
 * @param {Date|string} anchorDate — FullCalendar view anchor or any date in that month
 */
export const formatCalendarMonthTitle = (anchorDate) => {
  if (!anchorDate) return '';

  const instant = anchorDate instanceof Date
    ? anchorDate.toISOString()
    : anchorDate;

  const parts = getEventZonedParts(instant);
  if (!parts) return '';

  const parsed = parseEventInstant(instant);
  const monthName = parsed.toLocaleDateString('en-GB', {
    timeZone: EVENT_TZ,
    month: 'long',
  });

  return `${monthName} ${parts.year} · ${parts.month}/${parts.year}`;
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
    dateNumeric: formatEventNumericDate(normalized.starts_at),
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
