import { resolveNewsBannerUrl, normalizeNews } from './imageUrl';

const MY_TZ = 'Asia/Kuala_Lumpur';

export const formatNewsDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

export const formatNewsDateTime = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString('en-GB', {
    timeZone: MY_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

export const newsStatusClass = (isPublished) =>
  isPublished
    ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
    : 'bg-gray-100 text-gray-700 border border-gray-200';

export const mapApiNewsToCard = (post) => {
  const normalized = normalizeNews(post);
  return {
    id: normalized.id,
    title: normalized.title,
    excerpt: normalized.excerpt || '',
    body: normalized.body || '',
    category: normalized.category || 'Announcement',
    publishedAt: normalized.published_at,
    published_at: normalized.published_at,
    image_url: normalized.external_image_url || normalized.image_url || '',
    image_path: normalized.image_path || null,
    publishedDateLabel: formatNewsDateTime(normalized.published_at),
    publishedDateShort: formatNewsDate(normalized.published_at),
    bannerUrl: resolveNewsBannerUrl(normalized),
    images: normalized.images || [],
    isPublished: Boolean(normalized.is_published),
    is_published: Boolean(normalized.is_published),
    statusLabel: normalized.is_published ? 'Published' : 'Draft',
    statusClass: newsStatusClass(normalized.is_published),
  };
};
