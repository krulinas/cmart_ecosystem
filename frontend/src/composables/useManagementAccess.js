import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';
import { getWorkspaceTheme } from '../config/managementWorkspaceTheme';
import {
  ROLES,
  isManagerOrAbove,
  workflowRoleKey,
} from '../utils/managementRoles';
/**
 * Centralized role-aware access helpers for the CMart management workspace.
 */
export function useManagementAccess() {
  const auth = useAuthStore();
  const bossPreview = useBossPreviewStore();

  const effectiveRole = computed(() => bossPreview.effectiveRole);
  const isStaffView = computed(() => effectiveRole.value === ROLES.STAFF);
  const isManagerView = computed(() => workflowRoleKey(effectiveRole.value) === ROLES.MANAGER);
  // Identity-only flag for Tier 3 reserved-access notice; operational UI follows manager view.
  const isSuperAdminView = computed(() => auth.isSuperAdmin && !bossPreview.viewAsStaff);

  const canDeleteBookings = computed(() => isManagerOrAbove(auth.role));
  const canDeleteFeedback = computed(() => isManagerOrAbove(auth.role));
  const canPublishOfficialReply = computed(() => isManagerOrAbove(auth.role));
  const canFinalApproveBookings = computed(() => isManagerView.value);
  const canSeeManagerSections = computed(() => isManagerView.value);
  const shouldLoadManagerPanels = computed(() => isManagerView.value);

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
    isManagerView,
    isSuperAdminView,
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
  };
}
