<template>
  <WorkspaceShell
    title="CMart Workspace"
    :subtitle="sectionSubtitle"
    :workspace-label="auth.role === 'cmart_admin' ? 'CMart · Tier 2' : 'CMart · Tier 1'"
    :user-name="auth.user?.name || 'CMart Staff'"
    :user-role-label="auth.role === 'cmart_admin' ? 'CMart Admin' : 'CMart Staff'"
    :nav-items="navItems"
  >
    <template #actions>
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
  </WorkspaceShell>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import WorkspaceShell from './layouts/WorkspaceShell.vue';
import StaffBookingsPanel from './views/staff/StaffBookingsPanel.vue';
import StaffFeedbackPanel from './views/staff/StaffFeedbackPanel.vue';
import StaffEventsPanel from './views/staff/StaffEventsPanel.vue';
import StaffNewsPanel from './views/staff/StaffNewsPanel.vue';
import StaffToolsPanel from './views/staff/StaffToolsPanel.vue';
import { useAuthStore } from './stores/auth';

const toast = useToast();
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const loading = ref(false);
const activeSection = ref('bookings');

const bookingsPanel = ref(null);
const feedbackPanel = ref(null);
const eventsPanel = ref(null);
const newsPanel = ref(null);
const toolsPanel = ref(null);

const navItems = [
  { to: '/admin#bookings', label: 'Bookings', icon: 'B' },
  { to: '/admin#feedback', label: 'Feedback', icon: 'F' },
  { to: '/admin#events', label: 'Events', icon: 'E' },
  { to: '/admin#news', label: 'News', icon: 'N' },
  { to: '/admin#tools', label: 'Tools', icon: 'T' },
];

const sectionSubtitle = computed(() => {
  const map = {
    bookings: 'Approve, reject, and monitor vendor slot bookings.',
    feedback: 'Moderate community reviews and hide inappropriate content.',
    events: 'Manage carboot dates shown on the calendar and portal.',
    news: 'Publish announcements on the community portal.',
    tools: 'Profitability calculator and Python analytics.',
  };
  return map[activeSection.value] || map.bookings;
});

const syncSectionFromHash = () => {
  const hash = (route.hash || '#bookings').replace('#', '');
  const allowed = ['bookings', 'feedback', 'events', 'news', 'tools'];
  activeSection.value = allowed.includes(hash) ? hash : 'bookings';
};

const refreshAll = async () => {
  loading.value = true;
  await Promise.allSettled([
    bookingsPanel.value?.fetchBookings?.(),
    feedbackPanel.value?.load?.(),
    eventsPanel.value?.load?.(),
    newsPanel.value?.load?.(),
    toolsPanel.value?.refresh?.(),
  ]);
  loading.value = false;
};

const onBookingsRefreshed = () => {
  toolsPanel.value?.refresh?.();
};

const logout = async () => {
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

watch(() => route.hash, syncSectionFromHash);

onMounted(() => {
  syncSectionFromHash();
  refreshAll();
});
</script>
