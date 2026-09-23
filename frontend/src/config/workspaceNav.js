import { CAPABILITIES } from '../utils/managementCapabilities';

export const WORKSPACE_NAV_ITEMS = [
  {
    id: 'bookings',
    hash: 'bookings',
    labelKey: 'management.bookings',
    shortIcon: 'Bk',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'feedback',
    hash: 'feedback',
    labelKey: 'management.feedback',
    shortIcon: 'Fb',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'events',
    hash: 'events',
    labelKey: 'management.carbootEvents',
    shortIcon: 'Ev',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'layout',
    hash: 'layout',
    labelKey: 'management.layoutManagement',
    shortIcon: 'Ly',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'item-reservations',
    hash: 'item-reservations',
    labelKey: 'management.itemReservations',
    shortIcon: 'Ir',
    group: 'carboot_operations',
    domain: 'carboot',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'news',
    hash: 'news',
    labelKey: 'management.venueNews',
    shortIcon: 'Nw',
    group: 'cmart_activities',
    domain: 'cmart_activity',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CMART_ACTIVITY_MANAGEMENT,
  },
  {
    id: 'event-analytics',
    hash: 'event-analytics',
    labelKey: 'management.analyticsHub',
    shortIcon: 'Ah',
    group: 'carboot_analytics',
    domain: 'carboot_analytics',
    analyticsOnly: true,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
  {
    id: 'audit',
    hash: 'audit',
    labelKey: 'management.bookingAuditLog',
    shortIcon: 'Au',
    group: 'administration',
    domain: 'administration',
    analyticsOnly: false,
    superAdminOnly: true,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'report-centre',
    hash: 'report-centre',
    labelKey: 'management.reportCentre',
    shortIcon: 'RC',
    group: 'report_centre',
    domain: 'report_centre',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.CARBOOT_OPERATIONS,
  },
  {
    id: 'reports',
    hash: 'reports',
    labelKey: 'management.reports',
    shortIcon: 'Rp',
    group: 'generated_reports',
    domain: 'generated_reports',
    analyticsOnly: false,
    requiredCapability: CAPABILITIES.GENERATED_REPORTS,
    // CMart-facing Report Centre only — Organizer uses Report Centre instead.
    hideWhenCapability: CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
  },
];

/** Legacy hashes that redirect into the Analytics Hub (not shown in sidebar). */
export const LEGACY_ANALYTICS_HASH_REDIRECTS = {
  revenue: { section: 'event-analytics', tab: 'overview' },
  analytics: { section: 'event-analytics', tab: 'vendor-insights' },
};

export const ANALYTICS_HUB_TAB_STORAGE_KEY = 'cmart.eventAnalytics.activeTab';

export const CARBOOT_ANALYTICS_HASHES = WORKSPACE_NAV_ITEMS.filter(
  (item) => item.domain === 'carboot_analytics',
).map((item) => item.hash);

/** @deprecated Use CARBOOT_ANALYTICS_HASHES */
export const MANAGER_ONLY_HASHES = CARBOOT_ANALYTICS_HASHES;

/** @deprecated Use CARBOOT_ANALYTICS_HASHES */
export const BOSS_ONLY_HASHES = CARBOOT_ANALYTICS_HASHES;

export const ALL_WORKSPACE_HASHES = WORKSPACE_NAV_ITEMS.map((item) => item.hash);

export const SECTION_SUBTITLE_KEYS = {
  bookings: 'management.sectionBookings',
  feedback: 'management.sectionFeedback',
  events: 'management.sectionEvents',
  layout: 'management.sectionLayout',
  'item-reservations': 'management.sectionItemReservations',
  news: 'management.sectionNews',
  'event-analytics': 'management.sectionEventAnalytics',
  audit: 'management.sectionAudit',
  'report-centre': 'management.sectionReportCentre',
  reports: 'management.sectionReports',
};

/** @deprecated Prefer SECTION_SUBTITLE_KEYS + vue-i18n */
export const SECTION_SUBTITLES = {
  bookings: 'Review vendor slot requests, approve or request revision, and verify payments.',
  feedback: 'Moderate community reviews and manage visibility on the public portal.',
  events: 'Schedule and maintain carboot event dates for the community calendar.',
  layout: 'Manage category rows and physical sites for each Carboot event.',
  'item-reservations': 'Reconcile item reservation holds and record manual off-platform service fees.',
  news: 'Publish CMart venue announcements, promotions, and operational updates.',
  'event-analytics': 'Event-scoped overview, vendor survey insights, operations, and text analytics.',
  audit: 'System booking approval history for reserved HQ access.',
  'report-centre': 'Manage CMart report requests, generate drafts, and publish Post-Event Summaries.',
  reports: 'Request event reports from the Organizer and view published Post-Event Summaries.',
};
