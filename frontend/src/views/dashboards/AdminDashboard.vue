<template>
  <WorkspaceShell
    title="CMart Workspace"
    :subtitle="sectionSubtitle"
    :workspace-label="workspaceLabel"
    :user-name="auth.user?.name || 'CMart Staff'"
    :user-role-label="userRoleLabel"
    :nav-items="filteredNavItems"
  >
    <template #previewBanner>
      <div
        v-if="auth.isBoss && bossPreview.viewAsStaff"
        class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900 flex items-center justify-between gap-3"
      >
        <span><strong>God Mode:</strong> You are previewing the workspace as a Staff member.</span>
        <button type="button" class="ml-btn-ghost text-sm shrink-0" @click="bossPreview.toggle()">Exit preview</button>
      </div>
    </template>

    <template #actions>
      <button
        v-if="auth.isBoss"
        type="button"
        class="ml-btn-ghost"
        :class="bossPreview.viewAsStaff ? 'ring-2 ring-brand-400' : ''"
        @click="bossPreview.toggle()"
        :title="bossPreview.viewAsStaff ? 'Return to Boss view' : 'Preview as Staff'"
      >
        {{ bossPreview.viewAsStaff ? 'Boss view' : 'View as Staff' }}
      </button>
      <button class="ml-btn-ghost" @click="refreshAll" :disabled="loading">
        <span>↻</span>
        <span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span>
      </button>
      <button class="ml-btn-ghost" @click="logout">Logout</button>
    </template>

    <StaffBookingsPanel v-show="activeSection === 'bookings'" ref="bookingsPanel" @refreshed="onBookingsRefreshed" />
    <StaffFeedbackPanel v-show="activeSection === 'feedback'" ref="feedbackPanel" />
    <StaffEventsPanel v-show="activeSection === 'events'" ref="eventsPanel" />
    <StaffNewsPanel v-show="activeSection === 'news'" ref="newsPanel" />
    <StaffToolsPanel v-show="activeSection === 'tools'" ref="toolsPanel" />
    <BossRevenuePanel v-show="activeSection === 'revenue' && bossPreview.isBossView" ref="revenuePanel" />
    <BossWordCloudPanel v-show="activeSection === 'analytics' && bossPreview.isBossView" ref="wordCloudPanel" />
    <BossAuditLogsPanel v-show="activeSection === 'audit' && bossPreview.isBossView" ref="auditPanel" />
  </WorkspaceShell>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import WorkspaceShell from '../../layouts/WorkspaceShell.vue';
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
import { ALL_WORKSPACE_HASHES, SECTION_SUBTITLES } from '../../config/workspaceNav';

const toast = useToast();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const bossPreview = useBossPreviewStore();
const { filteredNavItems, canAccessHash } = useWorkspaceNav();

const loading = ref(false);
const activeSection = ref('bookings');

const bookingsPanel = ref(null);
const feedbackPanel = ref(null);
const eventsPanel = ref(null);
const newsPanel = ref(null);
const toolsPanel = ref(null);
const revenuePanel = ref(null);
const wordCloudPanel = ref(null);
const auditPanel = ref(null);

const workspaceLabel = computed(() => {
  if (auth.isBoss && bossPreview.viewAsStaff) return 'CMart · Staff preview';
  if (auth.isBoss) return 'CMart · Tier 2';
  return 'CMart · Tier 1';
});

const userRoleLabel = computed(() => {
  if (auth.isBoss && bossPreview.viewAsStaff) return 'Boss (previewing Staff)';
  if (auth.isBoss) return 'CMart Admin';
  return 'CMart Staff';
});

const sectionSubtitle = computed(() => SECTION_SUBTITLES[activeSection.value] || SECTION_SUBTITLES.bookings);

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

const refreshAll = async () => {
  loading.value = true;
  const tasks = [
    bookingsPanel.value?.fetchBookings?.(),
    feedbackPanel.value?.load?.(),
    eventsPanel.value?.load?.(),
    newsPanel.value?.load?.(),
    toolsPanel.value?.refresh?.(),
  ];
  if (bossPreview.isBossView) {
    tasks.push(revenuePanel.value?.load?.());
    tasks.push(wordCloudPanel.value?.load?.());
    tasks.push(auditPanel.value?.load?.());
  }
  await Promise.allSettled(tasks);
  loading.value = false;
};

const onBookingsRefreshed = () => {
  if (bossPreview.isBossView) {
    revenuePanel.value?.load?.();
  }
};

const logout = async () => {
  bossPreview.reset();
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

watch(() => route.hash, syncSectionFromHash);
watch(() => bossPreview.viewAsStaff, () => {
  syncSectionFromHash();
});

onMounted(() => {
  syncSectionFromHash();
  refreshAll();
});
</script>
