export const WORKSPACE_NAV_ITEMS = [
  { id: 'bookings', hash: 'bookings', label: 'Bookings', icon: 'B', bossOnly: false },
  { id: 'feedback', hash: 'feedback', label: 'Feedback', icon: 'F', bossOnly: false },
  { id: 'events', hash: 'events', label: 'Events', icon: 'E', bossOnly: false },
  { id: 'news', hash: 'news', label: 'News', icon: 'N', bossOnly: false },
  { id: 'tools', hash: 'tools', label: 'Tools', icon: 'T', bossOnly: false },
  { id: 'revenue', hash: 'revenue', label: 'Revenue', icon: 'R', bossOnly: true },
  { id: 'audit', hash: 'audit', label: 'Audit Log', icon: 'A', bossOnly: true },
];

export const BOSS_ONLY_HASHES = WORKSPACE_NAV_ITEMS.filter((i) => i.bossOnly).map((i) => i.hash);

export const ALL_WORKSPACE_HASHES = WORKSPACE_NAV_ITEMS.map((i) => i.hash);

export const SECTION_SUBTITLES = {
  bookings: 'Approve, reject, and monitor vendor slot bookings.',
  feedback: 'Moderate community reviews and hide inappropriate content.',
  events: 'Manage carboot dates shown on the calendar and portal.',
  news: 'Publish announcements on the community portal.',
  tools: 'Workspace utilities for CMart operations.',
  revenue: 'F&B and space quotas, earnings, and payment breakdown (Boss only).',
  audit: 'Review staff approval and rejection actions on vendor bookings.',
};
