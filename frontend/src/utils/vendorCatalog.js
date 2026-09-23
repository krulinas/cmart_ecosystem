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

export const ITEM_VISIBILITY_OPTIONS = [
  { value: 'active', labelKey: 'items.form.visibilityVisible' },
  { value: 'inactive', labelKey: 'items.form.visibilityHidden' },
];

export const ITEM_DISPLAY_STATUS_KEYS = {
  available: 'items.displayStatus.available',
  reserved: 'items.displayStatus.reserved',
  sold: 'items.displayStatus.sold',
  hidden: 'items.displayStatus.hidden',
};

const ITEM_DISPLAY_STATUS_BADGE_CLASSES = {
  available: 'bg-emerald-100 text-emerald-800',
  reserved: 'bg-amber-100 text-amber-800',
  sold: 'bg-sky-100 text-sky-800',
  hidden: 'bg-ink-100 text-ink-700',
};

/** Falls back to the legacy active/inactive status when display_status is absent. */
export const itemDisplayStatus = (item) => {
  if (item?.display_status) return item.display_status;
  if (item?.has_sale) return 'sold';
  if (item?.has_active_reservation) return 'reserved';
  return item?.status === 'active' ? 'available' : 'hidden';
};

export const itemDisplayStatusLabel = (item, t = tt) =>
  t(ITEM_DISPLAY_STATUS_KEYS[itemDisplayStatus(item)] || ITEM_DISPLAY_STATUS_KEYS.hidden);

export const itemDisplayStatusBadgeClass = (item) =>
  ITEM_DISPLAY_STATUS_BADGE_CLASSES[itemDisplayStatus(item)]
  || ITEM_DISPLAY_STATUS_BADGE_CLASSES.hidden;

export const itemVisibility = (item) => {
  if (item?.visibility) return item.visibility;
  return item?.status === 'active' ? 'visible' : 'hidden';
};

export const itemVisibilityLabel = (item, t = tt) =>
  itemVisibility(item) === 'visible'
    ? t('items.visibilityVisible')
    : t('items.visibilityHidden');

export const MARKETPLACE_SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
];

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
