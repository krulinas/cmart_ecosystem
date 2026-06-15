export const WORKSPACE_NAV_ITEMS = [
  { id: 'bookings', hash: 'bookings', label: 'Bookings', shortIcon: 'Bk', group: 'operations', managerOnly: false },
  { id: 'feedback', hash: 'feedback', label: 'Feedback', shortIcon: 'Fb', group: 'operations', managerOnly: false },
  { id: 'events', hash: 'events', label: 'Events', shortIcon: 'Ev', group: 'operations', managerOnly: false },
  { id: 'news', hash: 'news', label: 'News', shortIcon: 'Nw', group: 'operations', managerOnly: false },
  { id: 'revenue', hash: 'revenue', label: 'Revenue', shortIcon: 'Rv', group: 'insights', managerOnly: true },
  { id: 'analytics', hash: 'analytics', label: 'Word Cloud', shortIcon: 'Wc', group: 'insights', managerOnly: true },
  { id: 'audit', hash: 'audit', label: 'Audit Log', shortIcon: 'Au', group: 'insights', managerOnly: true },
  { id: 'tools', hash: 'tools', label: 'Tools', shortIcon: 'Tl', group: 'system', managerOnly: false },
];

export const MANAGER_ONLY_HASHES = WORKSPACE_NAV_ITEMS.filter((i) => i.managerOnly).map((i) => i.hash);

/** @deprecated Use MANAGER_ONLY_HASHES */
export const BOSS_ONLY_HASHES = MANAGER_ONLY_HASHES;

export const ALL_WORKSPACE_HASHES = WORKSPACE_NAV_ITEMS.map((i) => i.hash);

export const SECTION_SUBTITLES = {
  bookings: 'Review vendor slot requests, escalate decisions, and monitor the booking registry.',
  feedback: 'Moderate community reviews and manage visibility on the public portal.',
  events: 'Schedule and maintain carboot event dates for the community calendar.',
  news: 'Publish announcements and operational updates on the community portal.',
  tools: 'Workspace utilities and operational shortcuts for CMart staff.',
  revenue: 'Branch revenue analytics, space quotas, and payment breakdown.',
  analytics: 'Text analytics from community feedback and vendor product listings.',
  audit: 'Trace staff and manager approval actions across the booking pipeline.',
};
