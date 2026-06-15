import { ROLES } from '../utils/managementRoles';

export const WORKSPACE_NAV_GROUPS = [
  {
    id: 'operations',
    label: 'Operations',
    items: ['bookings', 'feedback', 'events', 'news'],
  },
  {
    id: 'insights',
    label: 'Insights',
    managerOnly: true,
    items: ['revenue', 'analytics', 'audit'],
  },
  {
    id: 'system',
    label: 'System',
    items: ['tools'],
  },
];

/** Shared CMart blue visual language for all management tiers. */
const CMART_BLUE_VISUAL = {
  accentName: 'cyan',
  logoBg: 'bg-gradient-to-br from-cyan-500 to-sky-600',
  sidebarAccent: 'border-cyan-500',
  sidebarHeaderBg: 'bg-gradient-to-br from-cyan-600 via-sky-600 to-cyan-700',
  heroGradient: 'bg-gradient-to-r from-cyan-50 via-white to-sky-50',
  heroBorder: 'border-cyan-200/80',
  heroTitle: 'text-cyan-950',
  heroSubtitle: 'text-cyan-800/70',
  badgeBg: 'bg-cyan-100 text-cyan-900 ring-cyan-200',
  tierBadgeBg: 'bg-cyan-500/15 text-cyan-800 ring-cyan-300/50',
  branchBadgeBg: 'bg-white/80 text-cyan-900 ring-cyan-200',
  navActive: 'bg-cyan-50 text-cyan-800 font-semibold ring-1 ring-cyan-200/80',
  navHover: 'hover:bg-cyan-50/60 hover:text-cyan-900',
  kpiAccent: 'from-cyan-500 to-sky-500',
  kpiText: 'text-cyan-700',
  kpiRing: 'ring-cyan-100',
  queueHeader: 'bg-gradient-to-r from-cyan-600 to-sky-600',
};

export const WORKSPACE_THEMES = {
  staff: {
    key: 'staff',
    workspaceTitle: 'CMart Operations Desk',
    workspaceSubtitle: 'Tier 1 staff review for vendor booking requests.',
    roleBadge: 'Tier 1 · Operations Staff',
    tierLabel: 'Tier 1',
    registryLabel: 'Read-only registry',
    registryDescription: 'View booking history across the branch. Editing and deletion require manager access.',
    ...CMART_BLUE_VISUAL,
  },
  manager: {
    key: 'manager',
    workspaceTitle: 'CMart Branch Control',
    workspaceSubtitle: 'Final approval workspace for vendor slot bookings.',
    roleBadge: 'Tier 2 · Branch Manager',
    tierLabel: 'Tier 2',
    registryLabel: 'Full-access registry',
    registryDescription: 'Complete branch booking registry with manager-level actions.',
    ...CMART_BLUE_VISUAL,
  },
  super_admin: {
    key: 'super_admin',
    workspaceTitle: 'CMart HQ Command Centre',
    workspaceSubtitle: 'System-wide management and oversight.',
    roleBadge: 'Tier 3 · HQ Administrator',
    tierLabel: 'Tier 3',
    registryLabel: 'System-wide registry',
    registryDescription: 'HQ-level visibility across all branch bookings and administrative actions.',
    ...CMART_BLUE_VISUAL,
  },
};

export const resolveWorkspaceThemeKey = (role, { previewAsStaff = false } = {}) => {
  if (previewAsStaff) return ROLES.STAFF;
  if (role === ROLES.LEGACY_STAFF) return ROLES.STAFF;
  if (role === ROLES.LEGACY_MANAGER || role === ROLES.LEGACY_BOSS) return ROLES.MANAGER;
  if (role === ROLES.SUPER_ADMIN) return ROLES.SUPER_ADMIN;
  if (role === ROLES.MANAGER) return ROLES.MANAGER;
  return ROLES.STAFF;
};

export const getWorkspaceTheme = (role, options = {}) => {
  const key = resolveWorkspaceThemeKey(role, options);
  return WORKSPACE_THEMES[key] ?? WORKSPACE_THEMES.staff;
};
