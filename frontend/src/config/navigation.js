/** Public routes available to all visitors */
export const PUBLIC_LINKS = [
  { label: 'Community', labelKey: 'navigation.community', to: '/community', exact: true },
  { label: 'Carboot Preview', labelKey: 'navigation.carbootPreview', to: '/marketplace', exact: true },
  { label: 'Events', labelKey: 'navigation.events', to: '/calendar' },
  { label: 'Become a Vendor', labelKey: 'navigation.becomeVendor', to: '/community', hash: '#become-vendor', testId: 'nav-become-vendor' },
];

/** Authenticated community visitors — always-visible top-level links */
export const COMMUNITY_PRIMARY_LINKS = [
  { label: 'Community', labelKey: 'navigation.community', to: '/community', exact: true, testId: 'nav-community' },
  { label: 'My Reservations', labelKey: 'navigation.myReservations', to: '/community', hash: '#my-item-reservations', testId: 'nav-my-reservations' },
];

/** Community visitor — public exploration grouped under one menu */
export const COMMUNITY_EXPLORE_MENU = {
  id: 'explore',
  label: 'Explore CMart',
  labelKey: 'navigation.exploreCmart',
  testId: 'nav-explore-cmart',
  items: [
    { label: 'Carboot Preview', labelKey: 'navigation.carbootPreview', to: '/marketplace', exact: true, testId: 'nav-carboot-preview' },
    { label: 'Events', labelKey: 'navigation.events', to: '/calendar', testId: 'nav-events' },
  ],
};

/** Single vendor-onboarding CTA for community members who are not yet eligible vendors */
export const COMMUNITY_BECOME_VENDOR_CTA = {
  label: 'Become a Vendor',
  labelKey: 'navigation.becomeVendor',
  to: '/community',
  hash: '#become-vendor',
  testId: 'nav-become-vendor',
};

/** Community visitor — account menu (identity + logout; no vendor profile route) */
export const COMMUNITY_ACCOUNT_MENU = {
  id: 'account',
  label: 'Account',
  labelKey: 'navigation.account',
  testId: 'nav-account-menu',
  items: [],
};

/**
 * @deprecated Flat list kept for backwards compatibility in tests/helpers.
 * Prefer COMMUNITY_PRIMARY_LINKS + COMMUNITY_EXPLORE_MENU + COMMUNITY_BECOME_VENDOR_CTA.
 */
export const COMMUNITY_VISITOR_LINKS = [
  ...COMMUNITY_PRIMARY_LINKS,
  ...COMMUNITY_EXPLORE_MENU.items,
  COMMUNITY_BECOME_VENDOR_CTA,
];

/** Vendor workspace — primary top-level link */
export const VENDOR_DASHBOARD_LINK = {
  label: 'Vendor Dashboard',
  labelKey: 'navigation.vendorDashboard',
  to: '/dashboard',
  exact: true,
  testId: 'nav-vendor-dashboard',
};

/** Vendor — booth operations grouped under Manage */
export const VENDOR_MANAGE_MENU = {
  id: 'manage',
  label: 'Manage',
  labelKey: 'navigation.manage',
  testId: 'nav-manage-menu',
  items: [
    { label: 'My Bookings', labelKey: 'navigation.myBookings', to: '/vendor/manage/bookings', testId: 'nav-manage-bookings' },
    { label: 'Event Passes', labelKey: 'navigation.eventPasses', to: '/vendor/manage/event-passes', testId: 'nav-manage-event-passes' },
    { label: 'My Items', labelKey: 'navigation.myItems', to: '/vendor/manage/items', testId: 'nav-manage-items' },
    {
      label: 'Customer Reservations',
      labelKey: 'navigation.customerReservations',
      to: '/vendor/manage/customer-reservations',
      testId: 'nav-manage-customer-reservations',
    },
  ],
};

/** Vendor — public exploration grouped under one menu */
export const VENDOR_EXPLORE_MENU = {
  id: 'explore',
  label: 'Explore CMart',
  labelKey: 'navigation.exploreCmart',
  testId: 'nav-explore-cmart',
  items: [
    { label: 'Carboot Preview', labelKey: 'navigation.carbootPreview', to: '/marketplace', exact: true, testId: 'nav-carboot-preview' },
    { label: 'Community', labelKey: 'navigation.community', to: '/community', exact: true, testId: 'nav-community' },
    { label: 'Events', labelKey: 'navigation.events', to: '/calendar', testId: 'nav-events' },
    { label: 'My Reservations', labelKey: 'navigation.myReservations', to: '/my-reservations', testId: 'nav-my-reservations' },
  ],
};

/** Vendor — account/setup grouped under one menu */
export const VENDOR_ACCOUNT_MENU = {
  id: 'account',
  label: 'Account',
  labelKey: 'navigation.account',
  testId: 'nav-account-menu',
  items: [
    { label: 'Business Profile', labelKey: 'navigation.businessProfile', to: '/profile', testId: 'nav-business-profile' },
    { label: 'Payment History', labelKey: 'navigation.paymentHistory', to: '/vendor/payment-history', testId: 'nav-payment-history' },
    { label: 'Insights', labelKey: 'navigation.insights', to: '/vendor/insights', testId: 'nav-insights' },
  ],
};

/**
 * @deprecated Flat list kept for backwards compatibility in tests/helpers.
 * Prefer VENDOR_DASHBOARD_LINK + VENDOR_MANAGE_MENU + VENDOR_EXPLORE_MENU + VENDOR_ACCOUNT_MENU.
 */
export const VENDOR_LINKS = [
  VENDOR_DASHBOARD_LINK,
  ...VENDOR_MANAGE_MENU.items,
  ...VENDOR_EXPLORE_MENU.items,
  ...VENDOR_ACCOUNT_MENU.items,
];

/**
 * Legacy in-dashboard section hashes → discrete routes (Option C IA).
 * Kept for bookmarks and older links; prefer the path values directly in new code.
 */
export const VENDOR_DASHBOARD_LEGACY_HASH_REDIRECTS = {
  'vendor-my-bookings': '/vendor/manage/bookings',
  'vendor-event-passes': '/vendor/manage/event-passes',
  'vendor-reuse-listings': '/vendor/manage/items',
  'vendor-item-reservations': '/vendor/manage/customer-reservations',
  'my-item-reservations': '/my-reservations',
  'vendor-business-profile': '/profile',
  'vendor-analytics': '/vendor/insights',
  'vendor-history-receipts': '/vendor/payment-history',
};

/**
 * @deprecated Removed with chip-nav IA. Prefer VENDOR_MANAGE_MENU / discrete routes.
 * Retained empty so accidental imports do not crash; do not reintroduce chip navigation.
 */
export const VENDOR_DASHBOARD_SECTION_LINKS = [];
