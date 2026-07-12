import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';
import { getWorkspaceTheme } from '../config/managementWorkspaceTheme';
import {
  ROLES,
  isOrganizerEquivalent,
  workflowRoleKey,
} from '../utils/managementRoles';
import {
  canManageCmartActivities,
  hasCapability,
  mapsToFutureOrganizer,
  CAPABILITIES,
} from '../utils/managementCapabilities';

/**
 * Centralized role-aware access helpers for the Carboot@CMart management workspace.
 */
export function useManagementAccess() {
  const auth = useAuthStore();
  const bossPreview = useBossPreviewStore();

  const effectiveRole = computed(() => bossPreview.effectiveRole);
  const isStaffView = computed(() => effectiveRole.value === ROLES.STAFF);
  const isManagerView = computed(() => workflowRoleKey(effectiveRole.value) === ROLES.MANAGER);
  const isStaffPortalAssist = computed(() => isOrganizerEquivalent(auth.role) && bossPreview.viewAsStaff);
  const isSuperAdminView = computed(() => auth.isSuperAdmin && !bossPreview.viewAsStaff);
  const isFutureOrganizerView = computed(() => mapsToFutureOrganizer(auth.role));

  const governanceCapabilities = computed(
    () => auth.user?.governance_capabilities ?? null,
  );

  const canAccessCarbootAnalytics = computed(() => {
    if (isStaffView.value) return false;
    return hasCapability(
      auth.role,
      CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS,
      governanceCapabilities.value,
    );
  });

  const canManageActivities = computed(() =>
    hasCapability(auth.role, CAPABILITIES.CMART_ACTIVITY_MANAGEMENT, governanceCapabilities.value),
  );

  const canDeleteBookings = computed(() => isManagerView.value);
  const canDeleteFeedback = computed(() => isManagerView.value);
  const canPublishOfficialReply = computed(() => isManagerView.value);
  const canFinalApproveBookings = computed(() => isManagerView.value);
  const canSeeManagerSections = computed(() => canAccessCarbootAnalytics.value);
  const shouldLoadManagerPanels = computed(() => canAccessCarbootAnalytics.value);

  const bookingsListEndpoint = computed(() =>
    isStaffView.value ? '/staff/bookings' : '/bookings',
  );

  const staffQueueStatus = 'Pending_Staff';
  const managerQueueStatus = 'Pending_Boss';

  const queueStatusForView = computed(() =>
    isManagerView.value ? managerQueueStatus : staffQueueStatus,
  );

  const workspaceTheme = computed(() =>
    getWorkspaceTheme(auth.role, { previewAsStaff: bossPreview.viewAsStaff }),
  );

  return {
    effectiveRole,
    isStaffView,
    isStaffPortalAssist,
    isManagerView,
    isSuperAdminView,
    isFutureOrganizerView,
    canAccessCarbootAnalytics,
    canManageCmartActivities: canManageActivities,
    canDeleteBookings,
    canDeleteFeedback,
    canPublishOfficialReply,
    canFinalApproveBookings,
    canSeeManagerSections,
    shouldLoadManagerPanels,
    bookingsListEndpoint,
    queueStatusForView,
    staffQueueStatus,
    managerQueueStatus,
    workspaceTheme,
    governanceCapabilities,
  };
}
