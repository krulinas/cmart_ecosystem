import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { getWorkspaceTheme } from '../config/managementWorkspaceTheme';
import { isOrganizerEquivalent } from '../utils/managementRoles';
import {
  hasCapability,
  mapsToFutureOrganizer,
  CAPABILITIES,
} from '../utils/managementCapabilities';

/** Canonical Organizer queue status (Phase 1.3C PR3). */
export const ORGANIZER_QUEUE_STATUS = 'Pending_Organizer';

/**
 * Role-aware access helpers for the Carboot@CMart management workspace.
 */
export function useManagementAccess() {
  const auth = useAuthStore();

  const isOrganizerView = computed(() => isOrganizerEquivalent(auth.role));
  const isSuperAdminView = computed(() => auth.isSuperAdmin);
  const isFutureOrganizerView = computed(() => mapsToFutureOrganizer(auth.role));

  const governanceCapabilities = computed(
    () => auth.user?.governance_capabilities ?? null,
  );

  const canAccessCarbootAnalytics = computed(() =>
    hasCapability(
      auth.role,
      CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
      governanceCapabilities.value,
    ),
  );

  const canPerformCarbootOperations = computed(() =>
    hasCapability(auth.role, CAPABILITIES.CARBOOT_OPERATIONS, governanceCapabilities.value),
  );

  const canManageActivities = computed(() =>
    hasCapability(auth.role, CAPABILITIES.CMART_ACTIVITY_MANAGEMENT, governanceCapabilities.value),
  );

  const canDeleteBookings = computed(() => isOrganizerView.value);
  const canDeleteFeedback = computed(() => isOrganizerView.value);
  const canPublishOfficialReply = computed(() => isOrganizerView.value);
  const canApproveBookings = computed(() => isOrganizerView.value);
  const canVerifyPayments = computed(() => isOrganizerView.value);
  const canSeeOrganizerAnalytics = computed(() => canAccessCarbootAnalytics.value);
  const shouldLoadOrganizerAnalyticsPanels = computed(() => canAccessCarbootAnalytics.value);

  const bookingsListEndpoint = '/bookings';
  const operationsSummaryEndpoint = '/organizer/operations-summary';
  const feedbackListEndpoint = '/organizer/feedbacks';

  const queueStatusForView = computed(() => ORGANIZER_QUEUE_STATUS);

  const workspaceTheme = computed(() => getWorkspaceTheme(auth.role));

  return {
    isOrganizerView,
    isSuperAdminView,
    isFutureOrganizerView,
    canAccessCarbootAnalytics,
    canPerformCarbootOperations,
    canManageCmartActivities: canManageActivities,
    canDeleteBookings,
    canDeleteFeedback,
    canPublishOfficialReply,
    canApproveBookings,
    canVerifyPayments,
    canSeeOrganizerAnalytics,
    shouldLoadOrganizerAnalyticsPanels,
    /** @deprecated Use canSeeOrganizerAnalytics */
    canSeeManagerSections: canSeeOrganizerAnalytics,
    /** @deprecated Use shouldLoadOrganizerAnalyticsPanels */
    shouldLoadManagerPanels: shouldLoadOrganizerAnalyticsPanels,
    /** @deprecated Use canApproveBookings */
    canFinalApproveBookings: canApproveBookings,
    bookingsListEndpoint,
    operationsSummaryEndpoint,
    feedbackListEndpoint,
    queueStatusForView,
    workspaceTheme,
    governanceCapabilities,
  };
}
