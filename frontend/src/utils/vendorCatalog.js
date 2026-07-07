export const ITEM_CONDITIONS = ['New', 'Like New', 'Good', 'Fair', 'For Parts'];

export const ITEM_PRICING_TYPES = [
  { value: 'fixed', label: 'Fixed price' },
  { value: 'free', label: 'Free' },
  { value: 'donation', label: 'Donation' },
];

export const ITEM_STATUS_TABS = [
  { id: 'all', label: 'All' },
  { id: 'active', label: 'Active' },
  { id: 'inactive', label: 'Inactive' },
];

export const MARKETPLACE_SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
];

export const marketplaceVisibilityLabel = (status) =>
  status === 'active'
    ? 'Private prep · ready for your records'
    : 'Private prep · inactive draft';

export const formatItemPrice = (item) => {
  if (!item) return '—';
  if (item.pricing_type === 'free') return 'Free';
  if (item.pricing_type === 'donation') return 'Donation';
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
