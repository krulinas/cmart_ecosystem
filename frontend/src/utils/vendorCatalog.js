import { tt } from '../i18n';

export const ITEM_CONDITIONS = ['New', 'Like New', 'Good', 'Fair', 'For Parts'];

export const ITEM_CONDITION_LABEL_KEYS = {
  New: 'items.conditions.new',
  'Like New': 'items.conditions.likeNew',
  Good: 'items.conditions.good',
  Fair: 'items.conditions.fair',
  'For Parts': 'items.conditions.forParts',
};

export const ITEM_PRICING_TYPES = [
  { value: 'fixed', labelKey: 'items.pricing.fixed' },
  { value: 'free', labelKey: 'items.pricing.free' },
  { value: 'donation', labelKey: 'items.pricing.donation' },
];

export const ITEM_STATUS_TABS = [
  { id: 'all', labelKey: 'items.tabAll' },
  { id: 'active', labelKey: 'items.tabActive' },
  { id: 'inactive', labelKey: 'items.tabInactive' },
];

export const MARKETPLACE_SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
];

export const marketplaceVisibilityLabel = (status, t = tt) =>
  status === 'active'
    ? t('items.visibilityActive')
    : t('items.visibilityInactive');

export const formatItemPrice = (item, t = tt) => {
  if (!item) return t('common.none');
  if (item.pricing_type === 'free') return t('items.pricing.free');
  if (item.pricing_type === 'donation') return t('items.pricing.donation');
  return `RM ${Number(item.price ?? 0).toFixed(2)}`;
};

export const formatListedDate = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};
