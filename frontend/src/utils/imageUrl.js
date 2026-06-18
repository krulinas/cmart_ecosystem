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

  const gallery = resolveEventGallery(event);
  if (gallery.length) {
    const primary = gallery.find((image) => image.is_primary) || gallery[0];
    return primary?.image_url || null;
  }

  return resolveStorageUrl(event.poster_url || event.image_url || event.image_path);
}

/** Normalize gallery images from an event API record. */
export function resolveEventGallery(event) {
  if (!event) {
    return [];
  }

  if (Array.isArray(event.images) && event.images.length) {
    return event.images
      .map((image) => ({
        ...image,
        image_url: resolveStorageUrl(image.image_url || image.image_path),
      }))
      .filter((image) => image.image_url)
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  const fallback = resolveStorageUrl(event.poster_url || event.image_url || event.image_path);
  if (!fallback) {
    return [];
  }

  return [{
    id: null,
    image_path: event.image_path || null,
    image_url: fallback,
    sort_order: 0,
    is_primary: true,
  }];
}

export function normalizeEvent(event) {
  if (!event) return event;

  const images = resolveEventGallery(event);

  return {
    ...event,
    images,
    poster_url: images.find((image) => image.is_primary)?.image_url
      || images[0]?.image_url
      || resolveStorageUrl(event.poster_url || event.image_url || event.image_path),
    image_url: images.find((image) => image.is_primary)?.image_url
      || images[0]?.image_url
      || resolveStorageUrl(event.poster_url || event.image_url || event.image_path),
  };
}

/** Resolve the best available banner URL from a news API record. */
export function resolveNewsBannerUrl(post) {
  if (!post) {
    return null;
  }

  const gallery = resolveNewsGallery(post);
  if (gallery.length) {
    const primary = gallery.find((image) => image.is_primary) || gallery[0];
    return primary?.image_url || null;
  }

  return resolveStorageUrl(post.banner_url || post.image_url || post.image_path);
}

/** Normalize gallery images from a news API record. */
export function resolveNewsGallery(post) {
  if (!post) {
    return [];
  }

  if (Array.isArray(post.images) && post.images.length) {
    return post.images
      .map((image) => ({
        ...image,
        image_url: image.image_path
          ? resolveStorageUrl(image.image_url || image.image_path)
          : resolveStorageUrl(image.image_url),
      }))
      .filter((image) => image.image_url)
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  const storageFallback = resolveStorageUrl(post.banner_url || post.image_path);
  if (storageFallback) {
    return [{
      id: null,
      image_path: post.image_path || null,
      image_url: storageFallback,
      sort_order: 0,
      is_primary: true,
    }];
  }

  if (post.image_url && /^https?:\/\//i.test(post.image_url)) {
    return [{
      id: null,
      image_path: null,
      image_url: post.image_url,
      sort_order: 0,
      is_primary: true,
    }];
  }

  return [];
}

export function normalizeNews(post) {
  if (!post) return post;

  const images = resolveNewsGallery(post);
  const bannerUrl = images.find((image) => image.is_primary)?.image_url
    || images[0]?.image_url
    || resolveNewsBannerUrl(post);

  return {
    ...post,
    images,
    external_image_url: post.external_image_url ?? post.image_url ?? '',
    banner_url: bannerUrl,
    bannerUrl,
  };
}

/** Resolve a browser-ready URL for a reuse / vendor item record. */
export function resolveReuseItemImageUrl(item) {
  if (!item) {
    return null;
  }

  const gallery = resolveReuseItemGallery(item);
  if (gallery.length) {
    const primary = gallery.find((image) => image.is_primary) || gallery[0];
    return primary?.image_url || null;
  }

  return resolveStorageUrl(item.image_url || item.image_path);
}

/** Normalize gallery images from a reuse item API record. */
export function resolveReuseItemGallery(item) {
  if (!item) {
    return [];
  }

  if (Array.isArray(item.images) && item.images.length) {
    return item.images
      .map((image) => ({
        ...image,
        image_url: resolveStorageUrl(image.image_url || image.image_path),
      }))
      .filter((image) => image.image_url)
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  const fallback = resolveStorageUrl(item.image_url || item.image_path);
  if (!fallback) {
    return [];
  }

  return [{
    id: null,
    image_path: item.image_path || null,
    image_url: fallback,
    sort_order: 0,
    is_primary: true,
  }];
}

/** Normalize vendor item payloads after API fetch. */
export function normalizeReuseItem(item) {
  if (!item) return item;

  const images = resolveReuseItemGallery(item);

  return {
    ...item,
    images,
    image_url: images.find((image) => image.is_primary)?.image_url
      || images[0]?.image_url
      || resolveStorageUrl(item.image_url || item.image_path),
  };
}
