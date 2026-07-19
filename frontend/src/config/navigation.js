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
  { label: 'My Reservations', to: '/community', hash: '#my-item-reservations', testId: 'nav-my-reservations' },
  { label: 'Carboot Preview', to: '/marketplace', exact: true },
  { label: 'Events', to: '/calendar' },
  { label: 'Become a Vendor', to: '/community', hash: '#become-vendor', testId: 'nav-become-vendor' },
];

/** Vendor workspace — primary top-level link */
export const VENDOR_DASHBOARD_LINK = {
  label: 'Vendor Dashboard',
  to: '/dashboard',
  exact: true,
  testId: 'nav-vendor-dashboard',
};

/** Vendor — public exploration grouped under one menu */
export const VENDOR_EXPLORE_MENU = {
  id: 'explore',
  label: 'Explore CMart',
  testId: 'nav-explore-cmart',
  items: [
    { label: 'Carboot Preview', to: '/marketplace', exact: true, testId: 'nav-carboot-preview' },
    { label: 'Community', to: '/community', exact: true, testId: 'nav-community' },
    { label: 'Events', to: '/calendar', testId: 'nav-events' },
  ],
};

/** Vendor — account/setup grouped under one menu */
export const VENDOR_ACCOUNT_MENU = {
  id: 'account',
  label: 'Account',
  testId: 'nav-account-menu',
  items: [
    { label: 'Business Profile', to: '/profile', testId: 'nav-business-profile' },
  ],
};

/**
 * @deprecated Flat list kept for backwards compatibility in tests/helpers.
 * Prefer VENDOR_DASHBOARD_LINK + VENDOR_EXPLORE_MENU + VENDOR_ACCOUNT_MENU.
 */
export const VENDOR_LINKS = [
  VENDOR_DASHBOARD_LINK,
  ...VENDOR_EXPLORE_MENU.items,
  ...VENDOR_ACCOUNT_MENU.items,
];

/** In-dashboard section quick links (anchor scroll targets on /dashboard) */
export const VENDOR_DASHBOARD_SECTION_LINKS = [
  { label: 'My Bookings', targetId: 'vendor-my-bookings', testId: 'dash-nav-bookings' },
  { label: 'Item Preparation', targetId: 'vendor-reuse-listings', testId: 'dash-nav-item-prep' },
  { label: 'Item Reservations', targetId: 'vendor-item-reservations', testId: 'dash-nav-item-reservations' },
  { label: 'My Reservations', targetId: 'my-item-reservations', testId: 'dash-nav-my-reservations' },
  { label: 'Business Profile', targetId: 'vendor-business-profile', testId: 'dash-nav-profile' },
  { label: 'Event Passes', targetId: 'vendor-event-passes', testId: 'dash-nav-passes' },
  { label: 'Receipts', targetId: 'vendor-history-receipts', testId: 'dash-nav-receipts' },
];
