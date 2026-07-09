export const ROLES = {
  STAFF: 'staff',
  MANAGER: 'manager',
  SUPER_ADMIN: 'super_admin',
  ORGANIZER: 'organizer',
  LEGACY_STAFF: 'cmart_staff',
  LEGACY_MANAGER: 'cmart_admin',
  LEGACY_BOSS: 'boss',
};

export const normalizeRole = (role) => {
  if (role === ROLES.LEGACY_STAFF) return ROLES.STAFF;
  if (role === ROLES.LEGACY_MANAGER || role === ROLES.LEGACY_BOSS) return ROLES.MANAGER;
  return role;
};

export const isStaffRole = (role) => normalizeRole(role) === ROLES.STAFF;

export const isManagerRole = (role) => normalizeRole(role) === ROLES.MANAGER;

export const isSuperAdminRole = (role) => normalizeRole(role) === ROLES.SUPER_ADMIN;

export const isCmartWorkerRole = (role) =>
  [ROLES.STAFF, ROLES.MANAGER, ROLES.SUPER_ADMIN].includes(normalizeRole(role));

export const isManagerOrAbove = (role) =>
  [ROLES.MANAGER, ROLES.SUPER_ADMIN].includes(normalizeRole(role));

export const matchesRole = (userRole, requiredRole) => {
  if (!userRole) return false;
  if (userRole === requiredRole) return true;

  const normalizedUser = normalizeRole(userRole);
  const normalizedRequired = normalizeRole(requiredRole);

  if (normalizedUser === normalizedRequired) return true;

  if (normalizedRequired === ROLES.MANAGER && normalizedUser === ROLES.SUPER_ADMIN) {
    return true;
  }

  return false;
};

export const hasAnyManagementRole = (userRole, roles = []) =>
  roles.some((requiredRole) => matchesRole(userRole, requiredRole));

export const workflowRoleKey = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLES.SUPER_ADMIN) return ROLES.MANAGER;
  return normalized;
};

export const roleDisplayLabel = (role, managementProfile = null) => {
  if (managementProfile?.position_title) {
    return managementProfile.position_title;
  }

  const normalized = normalizeRole(role);
  if (normalized === ROLES.STAFF) return 'Carboot Operations Staff';
  if (normalized === ROLES.MANAGER) return 'Carboot Organizer';
  if (normalized === ROLES.ORGANIZER) return 'Carboot Organizer';
  if (normalized === ROLES.SUPER_ADMIN) return 'Reserved HQ Access';
  return role || 'User';
};

export const managementWorkspaceLabel = (role, managementProfile = null) => {
  if (managementProfile?.branch_name) {
    return `CMart · ${managementProfile.branch_name}`;
  }

  const normalized = normalizeRole(role);
  if (normalized === ROLES.SUPER_ADMIN) return 'Carboot@CMart · Reserved HQ';
  if (normalized === ROLES.MANAGER || normalized === ROLES.ORGANIZER) return 'Carboot@CMart · Organizer';
  if (managementProfile?.tier) return `Carboot@CMart · Tier ${managementProfile.tier}`;
  return 'Carboot@CMart · Tier 1';
};

export const managementTierLabel = (managementProfile = null, role = null) => {
  if (managementProfile?.tier) return `Tier ${managementProfile.tier}`;
  const normalized = normalizeRole(role);
  if (normalized === ROLES.MANAGER || normalized === ROLES.ORGANIZER) return 'Tier 2';
  if (normalized === ROLES.SUPER_ADMIN) return 'Tier 3';
  if (normalized === ROLES.STAFF) return 'Tier 1';
  return null;
};
