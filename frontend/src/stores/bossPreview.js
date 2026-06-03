import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from './auth';

export const useBossPreviewStore = defineStore('bossPreview', () => {
  const viewAsStaff = ref(false);

  const effectiveRole = computed(() => {
    const auth = useAuthStore();
    if (auth.role === 'cmart_admin' && viewAsStaff.value) {
      return 'cmart_staff';
    }
    return auth.role;
  });

  const isBossView = computed(() => effectiveRole.value === 'cmart_admin');

  const toggle = () => {
    const auth = useAuthStore();
    if (auth.role === 'cmart_admin') {
      viewAsStaff.value = !viewAsStaff.value;
    }
  };

  const reset = () => {
    viewAsStaff.value = false;
  };

  return {
    viewAsStaff,
    effectiveRole,
    isBossView,
    toggle,
    reset,
  };
});
