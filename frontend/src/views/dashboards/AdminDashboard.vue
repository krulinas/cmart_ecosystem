<template>
  <WorkspaceShell
    :theme="workspaceTheme"
    :nav-groups="groupedNavItems"
    :flat-nav-items="filteredNavItems"
    :workspace-title="heroTitle"
    :workspace-subtitle="heroSubtitle"
    :section-subtitle="sectionSubtitle"
    :user-name="auth.user?.name || 'CMart Staff'"
    :user-role-label="userRoleLabel"
    :role-badge="roleBadge"
    :tier-badge="tierBadge"
    :branch-name="branchName"
    :department="auth.managementProfile?.department || ''"
    :preview-mode="auth.isBoss && bossPreview.viewAsStaff"
  >
    <template #previewBanner>
      <div
        v-if="sessionReady && auth.isBoss && bossPreview.viewAsStaff"
        class="mb-5 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-3 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-start gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-200 text-xs font-bold">Pv</span>
          <div>
            <div class="font-bold">Staff preview mode</div>
            <div class="text-xs text-amber-800/80">You are viewing the Tier 1 operations desk as staff would see it.</div>
          </div>
        </div>
        <button type="button" class="ml-btn-ghost shrink-0 text-sm ring-1 ring-amber-200" @click="bossPreview.toggle()">
          Exit preview
        </button>
      </div>
    </template>

    <template #actions>
      <button
        v-if="sessionReady && auth.isBoss"
        type="button"
        class="ml-btn-ghost text-sm"
        :class="bossPreview.viewAsStaff ? 'ring-2 ring-amber-300 bg-amber-50' : ''"
        @click="bossPreview.toggle()"
        :title="bossPreview.viewAsStaff ? 'Return to your role view' : 'Preview as Staff'"
      >
        {{ bossPreview.viewAsStaff ? 'Exit staff view' : 'View as Staff' }}
      </button>
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
      <p class="mt-4 text-sm font-medium text-ink-500">Preparing your command centre…</p>
    </div>

    <template v-else>
      <ManagementSectionLoader
        v-if="showSectionLoader"
        :message="sectionLoadingMessage"
      />

      <StaffBookingsPanel
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
      <StaffToolsPanel
        v-show="activeSection === 'tools'"
        ref="toolsPanel"
      />

      <template v-if="shouldLoadManagerPanels">
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
    </template>
  </WorkspaceShell>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import WorkspaceShell from '../../layouts/WorkspaceShell.vue';
import ManagementSectionLoader from '../../components/management/ManagementSectionLoader.vue';
import StaffBookingsPanel from './staff/StaffBookingsPanel.vue';
import StaffFeedbackPanel from './staff/StaffFeedbackPanel.vue';
import StaffEventsPanel from './staff/StaffEventsPanel.vue';
import StaffNewsPanel from './staff/StaffNewsPanel.vue';
import StaffToolsPanel from './staff/StaffToolsPanel.vue';
import BossRevenuePanel from './boss/BossRevenuePanel.vue';
import BossWordCloudPanel from './boss/BossWordCloudPanel.vue';
import BossAuditLogsPanel from './boss/BossAuditLogsPanel.vue';
import { useAuthStore } from '../../stores/auth';
import { useBossPreviewStore } from '../../stores/bossPreview';
import { useWorkspaceNav } from '../../composables/useWorkspaceNav';
import { useManagementAccess } from '../../composables/useManagementAccess';
import { useSectionCache } from '../../composables/useSectionCache';
import { ALL_WORKSPACE_HASHES, MANAGER_ONLY_HASHES, SECTION_SUBTITLES } from '../../config/workspaceNav';
import { roleDisplayLabel, managementTierLabel } from '../../utils/managementRoles';

const SECTION_LABELS = {
  bookings: 'Bookings',
  feedback: 'Feedback',
  events: 'Events',
  news: 'News',
  tools: 'Tools',
  revenue: 'Revenue',
  analytics: 'Word Cloud',
  audit: 'Audit Log',
};

const toast = useToast();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const bossPreview = useBossPreviewStore();
const { filteredNavItems, groupedNavItems, canAccessHash } = useWorkspaceNav();
const { shouldLoadManagerPanels, workspaceTheme } = useManagementAccess();
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
const toolsPanel = ref(null);
const revenuePanel = ref(null);
const wordCloudPanel = ref(null);
const auditPanel = ref(null);

const heroTitle = computed(() => workspaceTheme.value.workspaceTitle);
const heroSubtitle = computed(() => workspaceTheme.value.workspaceSubtitle);
const roleBadge = computed(() => workspaceTheme.value.roleBadge);
const tierBadge = computed(() => {
  const fromProfile = managementTierLabel(auth.managementProfile, auth.role);
  return fromProfile || workspaceTheme.value.tierLabel;
});
const branchName = computed(() => {
  if (auth.isSuperAdmin && !bossPreview.viewAsStaff) {
    return auth.managementProfile?.branch_name || 'CMart HQ';
  }
  return auth.managementProfile?.branch_name || 'CMart Main Branch';
});

const userRoleLabel = computed(() => {
  if (auth.isBoss && bossPreview.viewAsStaff) {
    return `${roleDisplayLabel(auth.role, auth.managementProfile)} · previewing Staff`;
  }
  return auth.roleLabel;
});

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
  const hash = (route.hash || '#bookings').replace('#', '');
  if (!canAccessHash(hash)) {
    activeSection.value = 'bookings';
    if (route.hash && route.hash !== '#bookings') {
      router.replace({ path: '/admin', hash: '#bookings' });
    }
    return;
  }
  activeSection.value = ALL_WORKSPACE_HASHES.includes(hash) ? hash : 'bookings';
};

const panelRefForSection = (section) => {
  const map = {
    bookings: bookingsPanel,
    feedback: feedbackPanel,
    events: eventsPanel,
    news: newsPanel,
    tools: toolsPanel,
    revenue: revenuePanel,
    analytics: wordCloudPanel,
    audit: auditPanel,
  };
  return map[section] ?? null;
};

const isManagerOnlySection = (section) => MANAGER_ONLY_HASHES.includes(section);

const canLoadSection = (section) => {
  if (!sessionReady.value) return false;
  if (!canAccessHash(section)) return false;
  if (isManagerOnlySection(section) && !shouldLoadManagerPanels.value) return false;
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

  if (section === 'tools') {
    await instance.refresh?.();
    return;
  }

  await instance.load?.();
};

const loadSection = async (section, { force = false } = {}) => {
  if (!canLoadSection(section)) return;
  if (!force && !shouldAutoLoad(section)) return;

  const state = sectionCache.value[section];
  if (state?.loading) return;

  if (section === 'tools') {
    markLoaded(section);
    return;
  }

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
  if (shouldLoadManagerPanels.value && activeSection.value === 'revenue') {
    loadSection('revenue', { force: true });
  }
};

const logout = async () => {
  bossPreview.reset();
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

watch(() => bossPreview.viewAsStaff, async () => {
  invalidateAll();
  syncSectionFromHash();
  await loadSection(activeSection.value, { force: true });
});

onMounted(async () => {
  await auth.ensureSession({ refresh: true });
  sessionReady.value = auth.sessionReady;
});
</script>
