<template>
  <WorkspaceShell
    v-if="authorized"
    :theme="workspaceTheme"
    :nav-groups="groupedNavItems"
    :flat-nav-items="filteredNavItems"
    :workspace-title="heroTitle"
    :workspace-subtitle="heroSubtitle"
    :section-subtitle="sectionSubtitle"
    :user-name="auth.user?.name || 'Management User'"
    :user-role-label="userRoleLabel"
    :role-badge="roleBadge"
    :tier-badge="tierBadge"
    :branch-name="branchName"
    :department="auth.managementProfile?.department || ''"
  >
    <template #previewBanner>
      <div
        v-if="sessionReady && showReservedHqNotice"
        class="mb-5 flex flex-col gap-2 rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 to-cyan-50 px-4 py-3 text-sm text-sky-950"
      >
        <div class="font-bold">Tier 3 · Reserved HQ Access</div>
        <div class="text-xs text-sky-800/80">Technical override mode for Carboot operations and analytics.</div>
      </div>
    </template>

    <template #actions>
      <button
        class="ml-btn-ghost text-sm ring-1 ring-ink-200/80 bg-white/70"
        @click="refreshActiveSection"
        :disabled="isRefreshing || !sessionReady"
        :title="refreshButtonTitle"
      >
        <span>↻</span>
        <span class="hidden sm:inline">{{ refreshButtonLabel }}</span>
      </button>
      <button class="ml-btn-ghost text-sm ring-1 ring-ink-200/80 bg-white/70" @click="logout">
        Logout
      </button>
    </template>

    <div v-if="!sessionReady" class="flex flex-col items-center justify-center rounded-2xl border border-ink-200 bg-white py-20 text-center shadow-sm">
      <div class="h-10 w-10 animate-pulse rounded-full bg-ink-200" />
      <p class="mt-4 text-sm font-medium text-ink-500">Preparing your management workspace…</p>
    </div>

    <template v-else>
      <div data-testid="management-dashboard-root">
      <ManagementSectionLoader
        v-if="showSectionLoader"
        :message="sectionLoadingMessage"
      />

      <OrganizerBookingsPanel
        v-show="activeSection === 'bookings' && !showSectionLoader"
        ref="bookingsPanel"
        @refreshed="onBookingsRefreshed"
      />
      <StaffFeedbackPanel
        v-show="activeSection === 'feedback' && !showSectionLoader"
        ref="feedbackPanel"
      />
      <StaffEventsPanel
        v-show="activeSection === 'events' && !showSectionLoader"
        ref="eventsPanel"
      />
      <StaffNewsPanel
        v-show="activeSection === 'news' && !showSectionLoader"
        ref="newsPanel"
      />

      <ManagementReportsPanel
        v-show="activeSection === 'reports' && !showSectionLoader"
        ref="reportsPanel"
      />

      <template v-if="shouldLoadOrganizerAnalyticsPanels">
        <BossRevenuePanel
          v-show="activeSection === 'revenue' && !showSectionLoader"
          ref="revenuePanel"
        />
        <BossWordCloudPanel
          v-show="activeSection === 'analytics' && !showSectionLoader"
          ref="wordCloudPanel"
        />
        <BossAuditLogsPanel
          v-show="activeSection === 'audit' && !showSectionLoader"
          ref="auditPanel"
        />
      </template>
      </div>
    </template>
  </WorkspaceShell>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import WorkspaceShell from '../../layouts/WorkspaceShell.vue';
import ManagementSectionLoader from '../../components/management/ManagementSectionLoader.vue';
import OrganizerBookingsPanel from './organizer/OrganizerBookingsPanel.vue';
import StaffFeedbackPanel from './staff/StaffFeedbackPanel.vue';
import StaffEventsPanel from './staff/StaffEventsPanel.vue';
import StaffNewsPanel from './staff/StaffNewsPanel.vue';
import BossRevenuePanel from './boss/BossRevenuePanel.vue';
import BossWordCloudPanel from './boss/BossWordCloudPanel.vue';
import BossAuditLogsPanel from './boss/BossAuditLogsPanel.vue';
import ManagementReportsPanel from './management/ManagementReportsPanel.vue';
import { useAuthStore } from '../../stores/auth';
import { useWorkspaceNav } from '../../composables/useWorkspaceNav';
import { useManagementAccess } from '../../composables/useManagementAccess';
import { useSectionCache } from '../../composables/useSectionCache';
import { ALL_WORKSPACE_HASHES, CARBOOT_ANALYTICS_HASHES, SECTION_SUBTITLES } from '../../config/workspaceNav';
import { MANAGEMENT_WORKSPACE_ROLES, managementTierLabel, defaultManagementHashForRole } from '../../utils/managementRoles';

const SECTION_LABELS = {
  bookings: 'Bookings',
  feedback: 'Feedback',
  events: 'Events',
  news: 'News',
  revenue: 'Revenue',
  analytics: 'Word Cloud',
  audit: 'Audit Log',
  reports: 'Reports',
};

const toast = useToast();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const { filteredNavItems, groupedNavItems, canAccessHash } = useWorkspaceNav();
const { shouldLoadOrganizerAnalyticsPanels, workspaceTheme } = useManagementAccess();
const {
  sections: sectionCache,
  shouldAutoLoad,
  markLoading,
  markLoaded,
  markError,
  invalidate,
  invalidateAll,
} = useSectionCache();

const sessionReady = ref(false);
const activeSection = ref('bookings');
const isRefreshing = ref(false);

const bookingsPanel = ref(null);
const feedbackPanel = ref(null);
const eventsPanel = ref(null);
const newsPanel = ref(null);
const revenuePanel = ref(null);
const wordCloudPanel = ref(null);
const reportsPanel = ref(null);
const auditPanel = ref(null);

const authorized = computed(() => auth.hasAnyRole(MANAGEMENT_WORKSPACE_ROLES));

const heroTitle = computed(() => workspaceTheme.value.workspaceTitle);
const heroSubtitle = computed(() => workspaceTheme.value.workspaceSubtitle);
const showReservedHqNotice = computed(() => auth.isSuperAdmin);
const roleBadge = computed(() => {
  if (showReservedHqNotice.value) return 'Tier 3 · Reserved HQ Access';
  return workspaceTheme.value.roleBadge;
});
const tierBadge = computed(() => {
  const fromProfile = managementTierLabel(auth.managementProfile, auth.role);
  return fromProfile || workspaceTheme.value.tierLabel;
});
const branchName = computed(() => auth.managementProfile?.branch_name || 'CMart Main Branch');

const userRoleLabel = computed(() => auth.roleLabel);

const sectionSubtitle = computed(() => SECTION_SUBTITLES[activeSection.value] || SECTION_SUBTITLES.bookings);

const activeSectionState = computed(() => sectionCache.value[activeSection.value] ?? {});

const showSectionLoader = computed(() => {
  const state = activeSectionState.value;
  return Boolean(state.loading && !state.loaded);
});

const sectionLoadingMessage = computed(() => {
  const label = SECTION_LABELS[activeSection.value] || 'section';
  return `Loading ${label.toLowerCase()}…`;
});

const refreshButtonLabel = computed(() => {
  const label = SECTION_LABELS[activeSection.value] || 'Section';
  return isRefreshing.value ? 'Refreshing…' : `Refresh ${label}`;
});

const refreshButtonTitle = computed(() => {
  const label = SECTION_LABELS[activeSection.value] || 'active section';
  return isRefreshing.value ? 'Reloading data…' : `Reload ${label} data`;
});

const syncSectionFromHash = () => {
  const hash = (route.hash || `#${defaultManagementHashForRole(auth.role)}`).replace('#', '');
  if (!canAccessHash(hash)) {
    const fallback = defaultManagementHashForRole(auth.role);
    activeSection.value = canAccessHash(fallback) ? fallback : 'news';
    if (route.hash && route.hash !== `#${activeSection.value}`) {
      router.replace({ path: '/admin', hash: `#${activeSection.value}` });
    }
    return;
  }
  activeSection.value = ALL_WORKSPACE_HASHES.includes(hash) ? hash : defaultManagementHashForRole(auth.role);
};

const panelRefForSection = (section) => {
  const map = {
    bookings: bookingsPanel,
    feedback: feedbackPanel,
    events: eventsPanel,
    news: newsPanel,
    revenue: revenuePanel,
    analytics: wordCloudPanel,
    audit: auditPanel,
    reports: reportsPanel,
  };
  return map[section] ?? null;
};

const isAnalyticsSection = (section) => CARBOOT_ANALYTICS_HASHES.includes(section);

const canLoadSection = (section) => {
  if (!sessionReady.value) return false;
  if (!canAccessHash(section)) return false;
  if (isAnalyticsSection(section) && !shouldLoadOrganizerAnalyticsPanels.value) return false;
  return true;
};

const resolvePanelInstance = async (section) => {
  for (let attempt = 0; attempt < 5; attempt += 1) {
    await nextTick();
    const instance = panelRefForSection(section)?.value;
    if (instance) return instance;
  }
  return null;
};

const invokePanelLoad = async (section, instance) => {
  if (section === 'bookings') {
    await instance.fetchBookings?.();
    return;
  }

  await instance.load?.();
};

const loadSection = async (section, { force = false } = {}) => {
  if (!canLoadSection(section)) return;
  if (!force && !shouldAutoLoad(section)) return;

  const state = sectionCache.value[section];
  if (state?.loading) return;

  markLoading(section);
  if (section === activeSection.value) {
    isRefreshing.value = true;
  }

  try {
    const instance = await resolvePanelInstance(section);
    if (!instance) {
      markError(section, new Error('Panel not ready'));
      return;
    }

    await invokePanelLoad(section, instance);
    markLoaded(section);
  } catch (error) {
    markError(section, error);
  } finally {
    if (section === activeSection.value) {
      isRefreshing.value = false;
    }
  }
};

const refreshActiveSection = () => loadSection(activeSection.value, { force: true });

const onBookingsRefreshed = () => {
  invalidate('revenue');
  if (shouldLoadOrganizerAnalyticsPanels.value && activeSection.value === 'revenue') {
    loadSection('revenue', { force: true });
  }
};

const logout = async () => {
  invalidateAll();
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

watch(() => route.hash, syncSectionFromHash);

watch(activeSection, (section) => {
  if (!sessionReady.value) return;
  loadSection(section);
});

watch(sessionReady, (ready) => {
  if (!ready) return;
  syncSectionFromHash();
  loadSection(activeSection.value);
});

onMounted(async () => {
  if (!auth.hasAnyRole(MANAGEMENT_WORKSPACE_ROLES)) {
    router.replace(auth.homeForUser());
    return;
  }

  await auth.ensureSession({ refresh: true });
  sessionReady.value = auth.sessionReady;
});
</script>
