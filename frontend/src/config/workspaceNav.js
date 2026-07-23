import { CAPABILITIES } from '../utils/managementCapabilities';

export const WORKSPACE_NAV_ITEMS = [
  {
    id: 'bookings',
    hash: 'bookings',
    label: 'Bookings',
    shortIcon: 'Bk',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'feedback',
    hash: 'feedback',
    label: 'Feedback',
    shortIcon: 'Fb',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'events',
    hash: 'events',
    label: 'Carboot Events',
    shortIcon: 'Ev',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'layout',
    hash: 'layout',
    label: 'Layout Management',
    shortIcon: 'Ly',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'item-reservations',
    hash: 'item-reservations',
    label: 'Item Reservations',
    shortIcon: 'Ir',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'news',
    hash: 'news',
    label: 'Venue News',
    shortIcon: 'Nw',
    group: 'cmart_activities',
    domain: 'cmart_activity',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CMART_ACTIVITY_MANAGEMENT,
  },
  {
    id: 'revenue',
    hash: 'revenue',
    label: 'Revenue',
    shortIcon: 'Rv',
    group: 'carboot_analytics',
    domain: 'carboot_analytics',
    analyticsOnly: true,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
  {
    id: 'analytics',
    hash: 'analytics',
    label: 'Word Cloud',
    shortIcon: 'Wc',
    group: 'carboot_analytics',
    domain: 'carboot_analytics',
    analyticsOnly: true,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
  {
    id: 'audit',
    hash: 'audit',
    label: 'Audit Log',
    shortIcon: 'Au',
    group: 'carboot_analytics',
    domain: 'carboot_analytics',
    analyticsOnly: true,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
  {
    id: 'report-centre',
    hash: 'report-centre',
    label: 'Report Centre',
    shortIcon: 'RC',
    group: 'report_centre',
    domain: 'report_centre',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'reports',
    hash: 'reports',
    label: 'Reports',
    shortIcon: 'Rp',
    group: 'generated_reports',
    domain: 'generated_reports',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.GENERATED_REPORTS,
    // CMart-facing Report Centre only — Organizer uses Report Centre instead.
    hideWhenCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
];

export const CARBOOT_ANALYTICS_HASHES = WORKSPACE_NAV_ITEMS.filter(
  (item) => item.domain === 'carboot_analytics',
).map((item) => item.hash);

/** @deprecated Use CARBOOT_ANALYTICS_HASHES */
export const MANAGER_ONLY_HASHES = CARBOOT_ANALYTICS_HASHES;

/** @deprecated Use CARBOOT_ANALYTICS_HASHES */
export const BOSS_ONLY_HASHES = CARBOOT_ANALYTICS_HASHES;

export const ALL_WORKSPACE_HASHES = WORKSPACE_NAV_ITEMS.map((item) => item.hash);

export const SECTION_SUBTITLES = {
  bookings: 'Review vendor slot requests, approve or request revision, and verify payments.',
  feedback: 'Moderate community reviews and manage visibility on the public portal.',
  events: 'Schedule and maintain carboot event dates for the community calendar.',
  layout: 'Manage category rows and physical sites for each Carboot event.',
  'item-reservations': 'Reconcile item reservation holds and record manual off-platform service fees.',
  news: 'Publish CMart venue announcements, promotions, and operational updates.',
  revenue: 'Carboot operational revenue: expected vs collected payments and profit simulator.',
  analytics: 'Carboot text analytics from community feedback and vendor product listings.',
  audit: 'Trace organizer approval actions across the booking pipeline.',
  'report-centre': 'Manage CMart report requests, generate drafts, and publish Post-Event Summaries.',
  reports: 'Request event reports from the Organizer and view published Post-Event Summaries.',
};
