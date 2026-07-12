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
  STAFF_QUEUE_ASSIST: 'staff_queue_assist',
};

export const resolveCapabilitiesForRole = (role) => {
  if (!isManagementUser(role)) return [];

  const capabilities = [];

  if (canAssistCarbootOperations(role)) {
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

/**
 * Canonical: organizer + super_admin. `staff` is TEMPORARY until the PR2
 * direct-Organizer booking cutover. Legacy manager/uum identities normalize
 * to organizer in managementRoles.js and are covered implicitly.
 * cmart_management must never appear in Carboot operations lists.
 */
export const canPerformCarbootOperations = (role) =>
  [ROLES.STAFF, ROLES.ORGANIZER, ROLES.SUPER_ADMIN].includes(normalizeRole(role));

export const canAccessCarbootOperationalAnalytics = (role) => isOrganizerEquivalent(role);

export const canManageCmartActivities = (role) =>
  [
    ROLES.STAFF,
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
