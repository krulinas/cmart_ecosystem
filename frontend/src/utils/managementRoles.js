/**
 * Phase 1.3C PR1 — canonical role identity.
 *
 * Canonical roles: organizer, cmart_management, super_admin (+ community).
 * Legacy identities (manager, uum, cmart_admin, boss) normalize to organizer.
 * `staff` is a TEMPORARY transitional role until the PR2 direct-Organizer
 * booking workflow cutover; PR3 removes the remaining staff-facing UI.
 */
export const ROLES = {
  ORGANIZER: 'organizer',
  CMART_MANAGEMENT: 'cmart_management',
  SUPER_ADMIN: 'super_admin',
  // TEMPORARY (PR2 removes): staff-stage actor for the legacy booking pipeline.
  STAFF: 'staff',
  // LEGACY (normalize to organizer):
  MANAGER: 'manager',
  LEGACY_UUM: 'uum',
  LEGACY_STAFF: 'cmart_staff',
  LEGACY_MANAGER: 'cmart_admin',
  LEGACY_BOSS: 'boss',
};

export const MANAGEMENT_WORKSPACE_ROLES = [
  ROLES.STAFF,
  ROLES.ORGANIZER,
  ROLES.CMART_MANAGEMENT,
  ROLES.SUPER_ADMIN,
  ROLES.MANAGER,
  ROLES.LEGACY_STAFF,
  ROLES.LEGACY_MANAGER,
  ROLES.LEGACY_BOSS,
];

export const normalizeRole = (role) => {
  if (role === ROLES.LEGACY_STAFF) return ROLES.STAFF;
  if (
    role === ROLES.MANAGER
    || role === ROLES.LEGACY_UUM
    || role === ROLES.LEGACY_MANAGER
    || role === ROLES.LEGACY_BOSS
  ) {
    return ROLES.ORGANIZER;
  }
  return role;
};

export const isStaffRole = (role) => normalizeRole(role) === ROLES.STAFF;

/** @deprecated Legacy manager identities normalize to organizer; always false for real roles. */
export const isManagerRole = (role) => normalizeRole(role) === ROLES.MANAGER;

export const isOrganizerRole = (role) => normalizeRole(role) === ROLES.ORGANIZER;

export const isCmartManagementRole = (role) => normalizeRole(role) === ROLES.CMART_MANAGEMENT;

export const isSuperAdminRole = (role) => normalizeRole(role) === ROLES.SUPER_ADMIN;

export const isManagementUser = (role) =>
  [ROLES.STAFF, ROLES.ORGANIZER, ROLES.CMART_MANAGEMENT, ROLES.SUPER_ADMIN].includes(
    normalizeRole(role),
  );

/** @deprecated Use isManagementUser() */
export const isCmartWorkerRole = isManagementUser;

export const isOrganizerEquivalent = (role) =>
  [ROLES.ORGANIZER, ROLES.SUPER_ADMIN].includes(normalizeRole(role));

/** @deprecated Use isOrganizerEquivalent() */
export const isManagerOrAbove = isOrganizerEquivalent;

export const matchesRole = (userRole, requiredRole) => {
  if (!userRole) return false;
  if (userRole === requiredRole) return true;

  const normalizedUser = normalizeRole(userRole);
  const normalizedRequired = normalizeRole(requiredRole);

  if (normalizedUser === normalizedRequired) return true;

  // Organizer-authority bridge: organizer-required routes also accept super_admin.
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

/**
 * NOTE (PR2): the booking state machine still uses the legacy 'manager' key
 * for the final-approval stage; organizer and super_admin map onto it until
 * PR2 replaces the two-stage pipeline with direct Organizer review.
 */
export const workflowRoleKey = (role) => {
  const normalized = normalizeRole(role);
  if ([ROLES.ORGANIZER, ROLES.SUPER_ADMIN].includes(normalized)) {
    return ROLES.MANAGER;
  }
  return normalized;
};

export const roleDisplayLabel = (role, managementProfile = null) => {
  if (managementProfile?.position_title) {
    return managementProfile.position_title;
  }

  const normalized = normalizeRole(role);
  if (normalized === ROLES.STAFF) return 'Carboot Operations Staff';
  if (normalized === ROLES.ORGANIZER) return 'Carboot Organizer';
  if (normalized === ROLES.CMART_MANAGEMENT) return 'CMart Management';
  if (normalized === ROLES.SUPER_ADMIN) return 'Reserved HQ Access';
  return role || 'User';
};

export const managementWorkspaceLabel = (role, managementProfile = null) => {
  if (managementProfile?.branch_name) {
    return `Carboot@CMart · ${managementProfile.branch_name}`;
  }

  const normalized = normalizeRole(role);
  if (normalized === ROLES.SUPER_ADMIN) return 'Carboot@CMart · Reserved HQ';
  if (normalized === ROLES.ORGANIZER) return 'Carboot@CMart · Organizer';
  if (normalized === ROLES.CMART_MANAGEMENT) return 'Carboot@CMart · Venue & Activities';
  if (managementProfile?.tier) return `Carboot@CMart · Tier ${managementProfile.tier}`;
  return 'Carboot@CMart · Tier 1';
};

export const managementTierLabel = (managementProfile = null, role = null) => {
  if (managementProfile?.tier) return `Tier ${managementProfile.tier}`;
  const normalized = normalizeRole(role);
  if ([ROLES.ORGANIZER, ROLES.CMART_MANAGEMENT].includes(normalized)) return 'Tier 2';
  if (normalized === ROLES.SUPER_ADMIN) return 'Tier 3';
  if (normalized === ROLES.STAFF) return 'Tier 1';
  return null;
};

export const defaultManagementHashForRole = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLES.CMART_MANAGEMENT) return 'news';
  return 'bookings';
};
