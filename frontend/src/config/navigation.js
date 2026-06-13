/** Public routes available to all visitors */
export const PUBLIC_LINKS = [
  { label: 'Home', to: '/', exact: true },
  { label: 'Events', to: '/#events', hash: '#events' },
  { label: 'Vendor Info', to: '/#vendor', hash: '#vendor' },
  { label: 'News & Updates', to: '/#news', hash: '#news' },
];

/** Authenticated vendor navigation */
export const VENDOR_LINKS = [
  { label: 'Dashboard', to: '/dashboard', exact: true },
  { label: 'Community', to: '/community' },
  { label: 'Calendar', to: '/calendar' },
  { label: 'Profile', to: '/profile' },
];
