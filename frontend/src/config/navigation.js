/** Public routes available to all visitors */
export const PUBLIC_LINKS = [
  { label: 'Home', to: '/', exact: true },
  { label: 'Community', to: '/community' },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Events', to: '/#events', hash: '#events' },
  { label: 'Vendor Info', to: '/#vendor', hash: '#vendor' },
  { label: 'News & Updates', to: '/#news', hash: '#news' },
];

/** Authenticated community visitors (no vendor activity yet) */
export const COMMUNITY_VISITOR_LINKS = [
  { label: 'Explore CMart', to: '/community', exact: true },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Browse Events', to: '/calendar' },
  { label: 'Events', to: '/#events', hash: '#events' },
];

/** Authenticated vendor navigation */
export const VENDOR_LINKS = [
  { label: 'Vendor Dashboard', to: '/dashboard', exact: true },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Community', to: '/community' },
  { label: 'Calendar', to: '/calendar' },
  { label: 'Business Profile', to: '/profile' },
];
