import { ROLES, normalizeRole } from '../utils/managementRoles';

export const WORKSPACE_NAV_GROUPS = [
  {
    id: 'carboot_operations',
    label: 'Event Operations',
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
    label: 'Event Analytics',
    analyticsOnly: true,
    items: ['revenue', 'analytics', 'audit'],
  },
];

/** SOC UUM organizer — university blue with restrained gold accents */
const UUM_ORGANIZER_VISUAL = {
  accentName: 'uum',
  logoBg: 'bg-gradient-to-br from-blue-700 to-indigo-900',
  brandSubtitleClass: 'text-amber-200/90',
  sidebarAccent: 'border-amber-400',
  sidebarHeaderBg: 'bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-950',
  heroGradient: 'bg-gradient-to-r from-blue-50 via-white to-amber-50/50',
  heroBorder: 'border-blue-200/80',
  heroTitle: 'text-blue-950',
  heroSubtitle: 'text-blue-900/70',
  badgeBg: 'bg-blue-100 text-blue-900 ring-blue-200',
  tierBadgeBg: 'bg-amber-100 text-amber-900 ring-amber-200/80',
  branchBadgeBg: 'bg-white/90 text-blue-900 ring-blue-200',
  navActive: 'bg-blue-50 text-blue-900 font-semibold ring-1 ring-blue-200/80',
  navHover: 'hover:bg-blue-50/70 hover:text-blue-950',
  navIconActive: 'bg-white shadow-sm text-blue-800',
  navIconIdle: 'bg-ink-100 text-ink-500',
  kpiAccent: 'from-blue-700 to-indigo-700',
  kpiText: 'text-blue-800',
  kpiRing: 'ring-blue-100',
  queueHeader: 'bg-gradient-to-r from-blue-800 to-indigo-800',
  tabActive: 'bg-blue-700 text-white shadow-sm',
  tabIdle: 'bg-white text-ink-600 ring-1 ring-ink-200',
  accentSoft: 'bg-blue-50 text-blue-900 ring-blue-200',
  loaderPulse: 'from-blue-100 to-indigo-100',
  sectionEyebrow: 'text-blue-800',
  accentSurface: 'border-blue-200 bg-blue-50/40',
};

/** CMart Management — cyan venue portal, visually distinct from organizer */
const CMART_MANAGEMENT_VISUAL = {
  accentName: 'cyan',
  logoBg: 'bg-gradient-to-br from-cyan-500 to-sky-600',
  brandSubtitleClass: 'text-cyan-100/90',
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
  navIconActive: 'bg-white/80 shadow-sm text-cyan-700',
  navIconIdle: 'bg-ink-100 text-ink-500',
  kpiAccent: 'from-cyan-500 to-sky-500',
  kpiText: 'text-cyan-700',
  kpiRing: 'ring-cyan-100',
  queueHeader: 'bg-gradient-to-r from-cyan-600 to-sky-600',
  tabActive: 'bg-cyan-600 text-white shadow-sm',
  tabIdle: 'bg-white text-ink-600 ring-1 ring-ink-200',
  accentSoft: 'bg-cyan-50 text-cyan-800 ring-cyan-200',
  loaderPulse: 'from-cyan-100 to-sky-100',
  sectionEyebrow: 'text-cyan-700',
  accentSurface: 'border-cyan-200 bg-cyan-50/40',
};

export const WORKSPACE_THEMES = {
  organizer: {
    key: 'organizer',
    brandTitle: 'SOC UUM',
    brandSubtitle: 'Carboot Event Operations',
    logoMark: 'SU',
    workspaceTitle: 'Carboot Event Operations',
    workspaceSubtitle:
      'Review vendor bookings, verify payments, and run Carboot events hosted at CMart Changlun.',
    roleBadge: 'SOC UUM Organizer',
    venueLabel: 'CMart Changlun',
    hideTierBadge: true,
    registryLabel: 'Event booking registry',
    registryDescription: 'Complete Carboot booking registry with organizer-level actions.',
    ...UUM_ORGANIZER_VISUAL,
  },
  cmart_management: {
    key: 'cmart_management',
    brandTitle: 'CMart',
    brandSubtitle: 'Venue & Activities',
    logoMark: 'CM',
    workspaceTitle: 'CMart Venue & Activities',
    workspaceSubtitle: 'Manage venue announcements and CMart side activities. Generated reports only.',
    roleBadge: 'CMart Management',
    venueLabel: 'CMart Changlun',
    hideTierBadge: true,
    registryLabel: 'Activity workspace',
    registryDescription: 'Venue and promotional content management for non-carboot CMart activities.',
    ...CMART_MANAGEMENT_VISUAL,
  },
  super_admin: {
    key: 'super_admin',
    brandTitle: 'SOC UUM',
    brandSubtitle: 'Reserved HQ Access',
    logoMark: 'HQ',
    workspaceTitle: 'Carboot Event Operations',
    workspaceSubtitle:
      'Reserved HQ access — technical override for Carboot operations and analytics at CMart Changlun.',
    roleBadge: 'Reserved HQ Access',
    venueLabel: 'CMart Changlun',
    hideTierBadge: true,
    registryLabel: 'Full-access registry',
    registryDescription: 'Complete booking registry with organizer-level actions.',
    ...UUM_ORGANIZER_VISUAL,
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
