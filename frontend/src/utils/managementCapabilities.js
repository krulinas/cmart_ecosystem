import {
  ROLES,
  isManagementUser,
  isOrganizerEquivalent,
  normalizeRole,
} from './managementRoles';

export const CAPABILITIES = {
  CARBOOT_OPERATIONS: 'carboot_operations',
  CARBOOT_OPERATIONAL_ANALYTICS: 'carboot_operational_analytics',
  CMART_ACTIVITY_MANAGEMENT: 'cmart_activity_management',
  GENERATED_REPORTS: 'generated_reports',
  ORGANIZER_QUEUE: 'organizer_queue',
  /** @deprecated PR3 — use ORGANIZER_QUEUE */
  STAFF_QUEUE_ASSIST: 'staff_queue_assist',
};

export const resolveCapabilitiesForRole = (role) => {
  if (!isManagementUser(role)) return [];

  const capabilities = [];

  if (canPerformCarbootOperations(role)) {
    capabilities.push(CAPABILITIES.ORGANIZER_QUEUE);
    capabilities.push(CAPABILITIES.STAFF_QUEUE_ASSIST);
  }
  if (canPerformCarbootOperations(role)) {
    capabilities.push(CAPABILITIES.CARBOOT_OPERATIONS);
  }
  if (canManageCmartActivities(role)) {
    capabilities.push(CAPABILITIES.CMART_ACTIVITY_MANAGEMENT);
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

/** Carboot ops: organizer + super_admin only (PR2). */
export const canPerformCarbootOperations = (role) => isOrganizerEquivalent(role);

export const canAccessCarbootOperationalAnalytics = (role) => isOrganizerEquivalent(role);

export const canManageCmartActivities = (role) =>
  [
    ROLES.ORGANIZER,
    ROLES.CMART_MANAGEMENT,
    ROLES.SUPER_ADMIN,
  ].includes(normalizeRole(role));

export const canAccessGeneratedReports = (role) =>
  [ROLES.ORGANIZER, ROLES.CMART_MANAGEMENT, ROLES.SUPER_ADMIN].includes(
    normalizeRole(role),
  );

export const canAssistCarbootOperations = (role) => canPerformCarbootOperations(role);

export const mapsToFutureOrganizer = (role) => isOrganizerEquivalent(role);
