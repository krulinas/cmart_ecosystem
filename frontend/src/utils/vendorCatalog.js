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

export const formatItemPrice = (item) => {
  if (!item) return '—';
  if (item.pricing_type === 'free') return 'Free';
  if (item.pricing_type === 'donation') return 'Donation';
  return `RM ${Number(item.price ?? 0).toFixed(2)}`;
};
