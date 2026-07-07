/** Public routes available to all visitors */
export const PUBLIC_LINKS = [
  { label: 'Community', to: '/community', exact: true },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Events', to: '/calendar' },
  { label: 'Become a Vendor', to: '/community', hash: '#become-vendor', testId: 'nav-become-vendor' },
];

/** Authenticated community visitors (no vendor activity yet) */
export const COMMUNITY_VISITOR_LINKS = [
  { label: 'Community', to: '/community', exact: true },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Events', to: '/calendar' },
  { label: 'Become a Vendor', to: '/community', hash: '#become-vendor', testId: 'nav-become-vendor' },
];

/** Authenticated vendor navigation */
export const VENDOR_LINKS = [
  { label: 'Vendor Dashboard', to: '/dashboard', exact: true },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Community', to: '/community' },
  { label: 'Events', to: '/calendar' },
  { label: 'Business Profile', to: '/profile' },
];
