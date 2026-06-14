import { resolveEventImageUrl } from './imageUrl';

export const DEFAULT_EVENT_LOCATION = 'CMart Kompleks Changlun, Changlun';

const MY_TZ = 'Asia/Kuala_Lumpur';

export const statusClassForEvent = (status) => {
  if (status === 'Available') return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
  if (status === 'Almost Full') return 'bg-amber-100 text-amber-800 border border-amber-200';
  return 'bg-gray-100 text-gray-700 border border-gray-200';
};

export const formatEventTime = (startsAt, endsAt) => {
  const opts = {
    timeZone: MY_TZ,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  };
  const start = new Date(startsAt);
  const end = new Date(endsAt);
  return `${start.toLocaleTimeString('en-GB', opts)} – ${end.toLocaleTimeString('en-GB', opts)}`;
};

export const formatEventDate = (startsAt) => {
  if (!startsAt) return '';
  return new Date(startsAt).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

export const mapApiEventToCard = (ev, location = DEFAULT_EVENT_LOCATION) => {
  const start = new Date(ev.starts_at);
  return {
    id: ev.id,
    day: String(start.getDate()),
    month: start.toLocaleString('en-GB', { month: 'short', timeZone: MY_TZ }),
    title: ev.title,
    time: formatEventTime(ev.starts_at, ev.ends_at),
    dateLabel: formatEventDate(ev.starts_at),
    location,
    description: ev.description || 'Join us for a weekend of bargains, local vendors, and community fun.',
    status: ev.status,
    statusClass: statusClassForEvent(ev.status),
    posterUrl: resolveEventImageUrl(ev),
    startsAt: ev.starts_at,
    endsAt: ev.ends_at,
  };
};
