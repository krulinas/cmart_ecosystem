import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from './auth';
import { isOrganizerEquivalent, normalizeRole, ROLES, workflowRoleKey } from '../utils/managementRoles';

export const useBossPreviewStore = defineStore('bossPreview', () => {
  const viewAsStaff = ref(false);

  const effectiveRole = computed(() => {
    const auth = useAuthStore();
    if (isOrganizerEquivalent(auth.role) && viewAsStaff.value) {
      return ROLES.STAFF;
    }
    return normalizeRole(auth.role);
  });

  const isManagerView = computed(() => workflowRoleKey(effectiveRole.value) === ROLES.MANAGER);

  /** @deprecated Use isManagerView */
  const isBossView = isManagerView;

  const toggle = () => {
    const auth = useAuthStore();
    if (isOrganizerEquivalent(auth.role)) {
      viewAsStaff.value = !viewAsStaff.value;
    }
  };

  const reset = () => {
    viewAsStaff.value = false;
  };

  return {
    viewAsStaff,
    effectiveRole,
    isManagerView,
    isBossView,
    toggle,
    reset,
  };
});
