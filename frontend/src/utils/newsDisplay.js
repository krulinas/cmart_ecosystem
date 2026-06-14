import { resolveStorageUrl } from './imageUrl';

const MY_TZ = 'Asia/Kuala_Lumpur';

export const resolveNewsBannerUrl = (post) =>
  resolveStorageUrl(post?.banner_url || post?.image_url || post?.image_path);

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

export const mapApiNewsToCard = (post) => ({
  id: post.id,
  title: post.title,
  excerpt: post.excerpt || '',
  body: post.body || '',
  category: post.category || 'Announcement',
  publishedAt: post.published_at,
  published_at: post.published_at,
  image_url: post.image_url || '',
  image_path: post.image_path || null,
  publishedDateLabel: formatNewsDateTime(post.published_at),
  publishedDateShort: formatNewsDate(post.published_at),
  bannerUrl: resolveNewsBannerUrl(post),
  isPublished: Boolean(post.is_published),
  is_published: Boolean(post.is_published),
  statusLabel: post.is_published ? 'Published' : 'Draft',
  statusClass: newsStatusClass(post.is_published),
});
