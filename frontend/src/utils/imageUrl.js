import api from '../services/api';

/** Laravel backend origin — matches frontend/src/services/api.js baseURL. */
const BACKEND_ORIGIN = api.defaults.baseURL.replace(/\/api\/?$/, '');

/**
 * Resolve storage paths to absolute URLs on the Laravel server (127.0.0.1:8000).
 * Handles full URLs, wrong localhost host, /storage/ paths, and raw DB paths.
 */
export function resolveStorageUrl(value) {
  if (!value || typeof value !== 'string') {
    return null;
  }

  const trimmed = value.trim();
  if (!trimmed) {
    return null;
  }

  if (trimmed.startsWith('blob:')) {
    return trimmed;
  }

  if (/^https?:\/\//i.test(trimmed)) {
    if (/^https?:\/\/localhost(?::\d+)?/i.test(trimmed)) {
      const path = trimmed.replace(/^https?:\/\/[^/]+/, '') || '/';
      return `${BACKEND_ORIGIN}${path.startsWith('/') ? path : `/${path}`}`;
    }

    if (/^https?:\/\/127\.0\.0\.1(?::\d+)?/i.test(trimmed)) {
      const path = trimmed.replace(/^https?:\/\/[^/]+/, '') || '/';
      const origin = trimmed.match(/^https?:\/\/127\.0\.0\.1:\d+/i)?.[0] || BACKEND_ORIGIN;
      if (origin === BACKEND_ORIGIN || origin.replace(/:\d+$/, '') === 'http://127.0.0.1') {
        return `${BACKEND_ORIGIN}${path.startsWith('/') ? path : `/${path}`}`;
      }
      return trimmed;
    }

    return trimmed;
  }

  if (trimmed.startsWith('/storage/')) {
    return `${BACKEND_ORIGIN}${trimmed}`;
  }

  const normalized = trimmed.replace(/^\/+/, '');
  if (normalized.startsWith('storage/')) {
    return `${BACKEND_ORIGIN}/${normalized}`;
  }

  return `${BACKEND_ORIGIN}/storage/${normalized}`;
}

/** Resolve the best available image URL from an event API record. */
export function resolveEventImageUrl(event) {
  if (!event) {
    return null;
  }

  return resolveStorageUrl(event.poster_url || event.image_url || event.image_path);
}
