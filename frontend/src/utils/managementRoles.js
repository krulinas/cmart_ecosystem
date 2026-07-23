/**
 * Phase 1.3C PR3 — canonical role identity (final).
 */
export const ROLES = {
  ORGANIZER: 'organizer',
  CMART_MANAGEMENT: 'cmart_management',
  SUPER_ADMIN: 'super_admin',
};

export const MANAGEMENT_WORKSPACE_ROLES = [
  ROLES.ORGANIZER,
  ROLES.CMART_MANAGEMENT,
  ROLES.SUPER_ADMIN,
];

/** Normalize legacy API/session strings at read time only. */
export const normalizeRole = (role) => {
  if (role === 'staff' || role === 'cmart_staff') return ROLES.CMART_MANAGEMENT;
  if (['manager', 'uum', 'cmart_admin', 'boss'].includes(role)) return ROLES.ORGANIZER;
  return role;
};

export const isOrganizerRole = (role) => normalizeRole(role) === ROLES.ORGANIZER;

export const isCmartManagementRole = (role) => normalizeRole(role) === ROLES.CMART_MANAGEMENT;

export const isSuperAdminRole = (role) => normalizeRole(role) === ROLES.SUPER_ADMIN;

export const isManagementUser = (role) =>
  MANAGEMENT_WORKSPACE_ROLES.includes(normalizeRole(role));

export const isOrganizerEquivalent = (role) =>
  [ROLES.ORGANIZER, ROLES.SUPER_ADMIN].includes(normalizeRole(role));

export const matchesRole = (userRole, requiredRole) => {
  if (!userRole) return false;
  if (userRole === requiredRole) return true;

  const normalizedUser = normalizeRole(userRole);
  const normalizedRequired = normalizeRole(requiredRole);

  if (normalizedUser === normalizedRequired) return true;

  if (
    normalizedRequired === ROLES.ORGANIZER
    && [ROLES.ORGANIZER, ROLES.SUPER_ADMIN].includes(normalizedUser)
  ) {
    return true;
  }

  return false;
};

export const hasAnyManagementRole = (userRole, roles = []) =>
  roles.some((requiredRole) => matchesRole(userRole, requiredRole));

export const roleDisplayLabel = (role, managementProfile = null) => {
  const normalized = normalizeRole(role);

  // Canonical CMart shell identity — never surface Organizer residue.
  if (normalized === ROLES.CMART_MANAGEMENT) {
    return 'CMart Management';
  }

  if (managementProfile?.position_title) {
    return managementProfile.position_title;
  }

  if (normalized === ROLES.ORGANIZER) return 'SOC UUM Organizer';
  if (normalized === ROLES.SUPER_ADMIN) return 'Reserved HQ Access';
  return role || 'User';
};

export const managementWorkspaceLabel = (role, managementProfile = null) => {
  const normalized = normalizeRole(role);
  const venue = managementProfile?.branch_name;
  const venueLabel =
    venue && venue !== 'CMart Main Branch' ? venue : 'CMart Changlun';

  if (normalized === ROLES.SUPER_ADMIN) return `SOC UUM · Reserved HQ · ${venueLabel}`;
  if (normalized === ROLES.ORGANIZER) return `SOC UUM · Carboot Event Operations · ${venueLabel}`;
  if (normalized === ROLES.CMART_MANAGEMENT) return `CMart · Venue & Activities · ${venueLabel}`;
  return `SOC UUM · ${venueLabel}`;
};

/** Internal tier helper — not shown in organizer UI (hideTierBadge). */
export const managementTierLabel = (managementProfile = null, role = null) => {
  if (managementProfile?.tier) return `Tier ${managementProfile.tier}`;
  const normalized = normalizeRole(role);
  if ([ROLES.ORGANIZER, ROLES.CMART_MANAGEMENT].includes(normalized)) return 'Tier 2';
  if (normalized === ROLES.SUPER_ADMIN) return 'Tier 3';
  return null;
};

export const defaultManagementHashForRole = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLES.CMART_MANAGEMENT) return 'news';
  if (normalized === ROLES.ORGANIZER || normalized === ROLES.SUPER_ADMIN) return 'bookings';
  return 'news';
};

/** Map legacy audit log status labels for display only. */
export const legacyBookingStatusLabel = (status) => {
  if (status === 'Pending_Staff' || status === 'Pending_Boss') {
    return 'Pending Organizer Review (legacy)';
  }
  return status;
};
