import { computed } from 'vue';
import { WORKSPACE_NAV_ITEMS } from '../config/workspaceNav';
import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';

export function useWorkspaceNav() {
  const auth = useAuthStore();
  const bossPreview = useBossPreviewStore();

  const filteredNavItems = computed(() =>
    WORKSPACE_NAV_ITEMS.filter((item) => {
      if (bossPreview.effectiveRole !== 'cmart_admin' && item.bossOnly) {
        return false;
      }
      return true;
    }).map((item) => ({
      to: `/admin#${item.hash}`,
      label: item.label,
      icon: item.icon,
      id: item.id,
      bossOnly: item.bossOnly,
    })),
  );

  const canAccessHash = (hash) => {
    const item = WORKSPACE_NAV_ITEMS.find((i) => i.hash === hash);
    if (!item) return false;
    if (item.bossOnly && bossPreview.effectiveRole !== 'cmart_admin') {
      return false;
    }
    return true;
  };

  return {
    filteredNavItems,
    canAccessHash,
  };
}
