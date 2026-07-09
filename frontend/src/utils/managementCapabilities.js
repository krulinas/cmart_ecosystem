import { ROLES, isCmartWorkerRole, isManagerOrAbove, normalizeRole } from './managementRoles';

export const CAPABILITIES = {
  CARBOOT_OPERATIONS: 'carboot_operations',
  CARBOOT_OPERATIONAL_ANALYTICS: 'carboot_operational_analytics',
  CMART_ACTIVITY_MANAGEMENT: 'cmart_activity_management',
  GENERATED_REPORTS: 'generated_reports',
  STAFF_QUEUE_ASSIST: 'staff_queue_assist',
};

/** Prepared for Phase 2 — not yet persisted in users.role. */
export const ROLE_ORGANIZER = 'organizer';

export const resolveCapabilitiesForRole = (role) => {
  if (!isCmartWorkerRole(role)) return [];

  const normalized = normalizeRole(role);
  const capabilities = [CAPABILITIES.STAFF_QUEUE_ASSIST];

  if ([ROLES.STAFF, ROLES.MANAGER, ROLES.SUPER_ADMIN].includes(normalized)) {
    capabilities.push(CAPABILITIES.CARBOOT_OPERATIONS, CAPABILITIES.CMART_ACTIVITY_MANAGEMENT);
  }

  if (canAccessCarbootOperationalAnalytics(role)) {
    capabilities.push(CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS);
  }

  if (canAccessGeneratedReports(role)) {
    capabilities.push(CAPABILITIES.GENERATED_REPORTS);
  }

  return [...new Set(capabilities)];
};

export const hasCapability = (role, capability, governanceCapabilities = null) => {
  if (Array.isArray(governanceCapabilities)) {
    return governanceCapabilities.includes(capability);
  }

  return resolveCapabilitiesForRole(role).includes(capability);
};

export const canAccessCarbootOperationalAnalytics = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLE_ORGANIZER) return true;
  return isManagerOrAbove(role);
};

export const canManageCmartActivities = (role) => isCmartWorkerRole(role);

export const canAccessGeneratedReports = (role) => {
  const normalized = normalizeRole(role);
  if (normalized === ROLE_ORGANIZER) return true;
  return isManagerOrAbove(role);
};

export const canAssistCarbootOperations = (role) => isCmartWorkerRole(role);

export const mapsToFutureOrganizer = (role) => {
  const normalized = normalizeRole(role);
  return [ROLES.MANAGER, ROLES.SUPER_ADMIN, ROLE_ORGANIZER].includes(normalized);
};
