import { ROLES, normalizeRole } from '../utils/managementRoles';

export const WORKSPACE_NAV_GROUPS = [
  {
    id: 'carboot_operations',
    label: 'Carboot Operations',
    items: ['bookings', 'feedback', 'events', 'layout', 'item-reservations'],
  },
  {
    id: 'cmart_activities',
    label: 'CMart Activities',
    items: ['news'],
  },
  {
    id: 'generated_reports',
    label: 'Generated Reports',
    items: ['reports'],
  },
  {
    id: 'carboot_analytics',
    label: 'Carboot Analytics',
    analyticsOnly: true,
    items: ['revenue', 'analytics', 'audit'],
  },
];

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
  organizer: {
    key: 'organizer',
    workspaceTitle: 'Carboot@CMart Organizer Control',
    workspaceSubtitle: 'Direct vendor booking review, payment verification, and Carboot operations.',
    roleBadge: 'Tier 2 · Carboot Organizer',
    tierLabel: 'Tier 2',
    registryLabel: 'Full-access registry',
    registryDescription: 'Complete Carboot booking registry with organizer-level actions.',
    ...CMART_BLUE_VISUAL,
  },
  cmart_management: {
    key: 'cmart_management',
    workspaceTitle: 'CMart Venue & Activities',
    workspaceSubtitle: 'Manage venue announcements and CMart side activities. Generated reports only.',
    roleBadge: 'Tier 2 · CMart Management',
    tierLabel: 'Tier 2',
    registryLabel: 'Activity workspace',
    registryDescription: 'Venue and promotional content management for non-carboot CMart activities.',
    ...CMART_BLUE_VISUAL,
  },
  super_admin: {
    key: 'super_admin',
    workspaceTitle: 'Carboot@CMart Organizer Control',
    workspaceSubtitle: 'Reserved HQ access — technical override for Carboot operations and analytics.',
    roleBadge: 'Tier 3 · Reserved HQ Access',
    tierLabel: 'Tier 3',
    registryLabel: 'Full-access registry',
    registryDescription: 'Complete branch booking registry with organizer-level actions.',
    ...CMART_BLUE_VISUAL,
  },
};

export const resolveWorkspaceThemeKey = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLES.SUPER_ADMIN) return ROLES.SUPER_ADMIN;
  if (normalized === ROLES.ORGANIZER) return ROLES.ORGANIZER;
  if (normalized === ROLES.CMART_MANAGEMENT) return ROLES.CMART_MANAGEMENT;
  return ROLES.ORGANIZER;
};

export const getWorkspaceTheme = (role) => {
  const key = resolveWorkspaceThemeKey(role);
  return WORKSPACE_THEMES[key] ?? WORKSPACE_THEMES.organizer;
};
