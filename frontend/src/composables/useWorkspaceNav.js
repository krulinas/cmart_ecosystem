import { computed } from 'vue';
import { WORKSPACE_NAV_GROUPS } from '../config/managementWorkspaceTheme';
import { WORKSPACE_NAV_ITEMS } from '../config/workspaceNav';
import { useManagementAccess } from './useManagementAccess';
import { hasCapability } from '../utils/managementCapabilities';
import { useAuthStore } from '../stores/auth';

export function useWorkspaceNav() {
  const auth = useAuthStore();
  const { canSeeOrganizerAnalytics, canDeleteBookings, governanceCapabilities } = useManagementAccess();

  const visibleItems = computed(() =>
    WORKSPACE_NAV_ITEMS.filter((item) => {
      if (item.analyticsOnly && !canSeeOrganizerAnalytics.value) return false;
      if (
        item.hideWhenCapability
        && hasCapability(auth.role, item.hideWhenCapability, governanceCapabilities.value)
      ) {
        return false;
      }
      if (
        item.requiredCapability
        && !hasCapability(auth.role, item.requiredCapability, governanceCapabilities.value)
      ) {
        return false;
      }
      return true;
    }),
  );

  const filteredNavItems = computed(() =>
    visibleItems.value.map((item) => ({
      to: `/admin#${item.hash}`,
      label: item.label,
      shortIcon: item.shortIcon,
      icon: item.shortIcon,
      id: item.id,
      hash: item.hash,
      group: item.group,
      domain: item.domain,
      analyticsOnly: item.analyticsOnly,
    })),
  );

  const groupedNavItems = computed(() =>
    WORKSPACE_NAV_GROUPS.map((group) => ({
      ...group,
      items: visibleItems.value
        .filter((item) => group.items.includes(item.id))
        .map((item) => ({
          to: `/admin#${item.hash}`,
          label: item.label,
          shortIcon: item.shortIcon,
          id: item.id,
          hash: item.hash,
          analyticsOnly: item.analyticsOnly,
        })),
    })).filter((group) => group.items.length > 0),
  );

  const canAccessHash = (hash) => {
    const item = WORKSPACE_NAV_ITEMS.find((i) => i.hash === hash);
    if (!item) return false;
    if (item.analyticsOnly && !canSeeOrganizerAnalytics.value) return false;
    if (
      item.hideWhenCapability
      && hasCapability(auth.role, item.hideWhenCapability, governanceCapabilities.value)
    ) {
      return false;
    }
    if (
      item.requiredCapability
      && !hasCapability(auth.role, item.requiredCapability, governanceCapabilities.value)
    ) {
      return false;
    }
    return true;
  };

  return {
    filteredNavItems,
    groupedNavItems,
    canAccessHash,
    canDeleteBookings,
    canSeeOrganizerAnalytics,
    /** @deprecated */
    canSeeManagerSections: canSeeOrganizerAnalytics,
  };
}
