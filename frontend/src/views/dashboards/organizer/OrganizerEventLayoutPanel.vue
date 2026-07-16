<template>
  <div class="space-y-5" data-testid="organizer-event-layout-panel">
    <section class="ml-card space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ copy.pageTitle }}</p>
          <h2 class="text-xl font-extrabold text-ink-900">
            {{ layout?.event?.name || 'Pilih acara untuk mengurus susun atur' }}
          </h2>
          <p v-if="layout?.event" class="mt-1 text-sm text-ink-500">
            Status: {{ layout.event.status }}
            · {{ rows.length }} baris
            · {{ activeSiteCount }} tapak aktif
            <span v-if="unresolvedSites.length" class="font-semibold text-amber-700">
              · {{ unresolvedSites.length }} belum disusun
            </span>
          </p>
          <p v-if="lastLoadedAt" class="mt-1 text-[11px] text-ink-400">
            Dimuat semula: {{ formatLoadedAt(lastLoadedAt) }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="ml-btn-ghost text-sm" @click="goToEvents">{{ copy.backToEvents }}</button>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="!selectedEventId || loading || mutating"
            data-testid="layout-refresh-button"
            @click="refreshLayout({ force: true })"
          >
            {{ copy.refresh }}
          </button>
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="!selectedEventId || loading"
            data-testid="layout-add-row-button"
            @click="openCreateRow"
          >
            {{ copy.addRow }}
          </button>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
        <div>
          <label class="ml-label" for="layout-event-select">{{ copy.selectEvent }}</label>
          <select
            id="layout-event-select"
            v-model="selectedEventId"
            class="ml-input"
            data-testid="layout-event-select"
            :disabled="loadingEvents"
            @change="onEventSelected"
          >
            <option value="">— Pilih acara —</option>
            <option v-for="event in events" :key="event.id" :value="String(event.id)">
              {{ event.title }} ({{ event.status }})
            </option>
          </select>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-ink-500">
          <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-1 ring-1 ring-brand-200">Tersedia</span>
          <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 ring-1 ring-amber-200">Ditempah</span>
          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 ring-1 ring-emerald-200">Disahkan</span>
          <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-1 ring-1 ring-violet-200">Dikunci</span>
        </div>
      </div>
    </section>

    <div v-if="loading && !layout" class="ml-card animate-pulse space-y-3 py-10 text-center" data-testid="layout-loading-state">
      <div class="mx-auto h-10 w-10 rounded-full bg-ink-200" />
      <p class="text-sm font-medium text-ink-500">Memuatkan susun atur…</p>
    </div>

    <div v-else-if="loadError" class="ml-card space-y-3 border-rose-200 bg-rose-50" data-testid="layout-error-state">
      <p class="font-semibold text-rose-900">{{ copy.loadError }}</p>
      <p class="text-sm text-rose-800">{{ loadError }}</p>
      <button type="button" class="ml-btn-primary text-sm" @click="refreshLayout({ force: true })">{{ copy.tryAgain }}</button>
    </div>

    <template v-else-if="selectedEventId && layout">
      <EventLayoutReadinessPanel :readiness="layout.readiness || {}" />

      <section
        v-if="unresolvedSites.length"
        class="ml-card border-amber-200 bg-amber-50/60 space-y-3"
        data-testid="unresolved-sites-panel"
      >
        <h3 class="text-base font-extrabold text-amber-950">{{ copy.unresolvedTitle }}</h3>
        <p class="text-sm text-amber-900">
          Tapak ini belum dipautkan kepada baris susun atur. Pemetaan automatik tidak dilakukan.
        </p>
        <ul class="space-y-2">
          <li
            v-for="site in unresolvedSites"
            :key="site.id"
            class="rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm"
          >
            <div class="font-bold text-ink-900">{{ site.label }}</div>
            <div class="text-xs text-ink-500">
              Label baris lama: {{ site.row_label || '—' }}
              · {{ site.space?.space_size || 'Tiada ruang' }}
              · {{ site.operational_status }}
            </div>
          </li>
        </ul>
      </section>

      <div v-if="!rows.length" class="ml-card space-y-3 text-center" data-testid="layout-empty-state">
        <p class="text-lg font-extrabold text-ink-900">{{ copy.emptyTitle }}</p>
        <p class="text-sm text-ink-500">{{ copy.emptyBody }}</p>
        <button type="button" class="ml-btn-primary" @click="openCreateRow">{{ copy.addRow }}</button>
      </div>

      <div v-else class="space-y-4" data-testid="layout-rows-workspace">
        <EventLayoutRowCard
          v-for="(row, index) in rows"
          :key="row.id"
          :row="row"
          :can-move-up="index > 0"
          :can-move-down="index < rows.length - 1"
          @edit="openEditRow"
          @delete="confirmDeleteRow"
          @archive="confirmArchiveRow"
          @unarchive="confirmUnarchiveRow"
          @move-up="(target) => moveRow(target, -1)"
          @move-down="(target) => moveRow(target, 1)"
          @add-site="openCreateSite"
          @generate="openGenerateSites"
          @reorder-sites="openReorderSites"
          @edit-site="openEditSite"
          @move-site="openMoveSite"
          @toggle-site-status="toggleSiteStatus"
          @delete-site="confirmDeleteSite"
        />
      </div>
    </template>

    <LayoutRowFormModal
      v-model="rowModalOpen"
      :row="activeRow"
      :categories="categories"
      :submitting="mutating"
      :form-error="formError"
      @submit="submitRowForm"
    />

    <LayoutSiteFormModal
      v-model="siteModalOpen"
      :site="activeSite"
      :row="activeRow"
      :rows="rows"
      :spaces="spaces"
      :submitting="mutating"
      :form-error="formError"
      @submit="submitSiteForm"
    />

    <LayoutSiteGenerationModal
      v-model="generateModalOpen"
      :row="activeRow"
      :spaces="spaces"
      :submitting="mutating"
      :form-error="formError"
      @submit="submitGenerate"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import EventLayoutReadinessPanel from '../../../components/organizer/layout/EventLayoutReadinessPanel.vue';
import EventLayoutRowCard from '../../../components/organizer/layout/EventLayoutRowCard.vue';
import LayoutRowFormModal from '../../../components/organizer/layout/LayoutRowFormModal.vue';
import LayoutSiteFormModal from '../../../components/organizer/layout/LayoutSiteFormModal.vue';
import LayoutSiteGenerationModal from '../../../components/organizer/layout/LayoutSiteGenerationModal.vue';
import * as layoutApi from '../../../services/organizerEventLayoutApi';
import {
  LAYOUT_COPY,
  layoutErrorMessage,
} from '../../../utils/organizerEventLayoutMessages';
import {
  buildRowReorderPayload,
  countActiveSites,
  sortRowsByDisplayOrder,
  sortSitesByDisplayOrder,
} from '../../../utils/organizerEventLayoutHelpers';

const toast = useToast();
const route = useRoute();
const router = useRouter();
const copy = LAYOUT_COPY;

const events = ref([]);
const categories = ref([]);
const spaces = ref([]);
const selectedEventId = ref('');
const layout = ref(null);
const loading = ref(false);
const loadingEvents = ref(false);
const mutating = ref(false);
const loadError = ref('');
const formError = ref('');
const lastLoadedAt = ref(null);
const loadToken = ref(0);

const rowModalOpen = ref(false);
const siteModalOpen = ref(false);
const generateModalOpen = ref(false);
const activeRow = ref(null);
const activeSite = ref(null);

const rows = computed(() => sortRowsByDisplayOrder(layout.value?.rows || []));
const unresolvedSites = computed(() => layout.value?.unresolved_sites || []);
const activeSiteCount = computed(() => countActiveSites(rows.value));

function formatLoadedAt(value) {
  try {
    return new Date(value).toLocaleString();
  } catch {
    return String(value);
  }
}

function goToEvents() {
  router.push({ path: '/admin', hash: '#events' });
}

function openCreateRow() {
  activeRow.value = null;
  formError.value = '';
  rowModalOpen.value = true;
}

function openEditRow(row) {
  activeRow.value = row;
  formError.value = '';
  rowModalOpen.value = true;
}

function openCreateSite(row) {
  activeRow.value = row;
  activeSite.value = null;
  formError.value = '';
  siteModalOpen.value = true;
}

function openEditSite(site) {
  activeSite.value = site;
  activeRow.value = rows.value.find((row) => row.id === site.event_layout_row_id) || null;
  formError.value = '';
  siteModalOpen.value = true;
}

function openMoveSite(site) {
  openEditSite(site);
}

function openGenerateSites(row) {
  activeRow.value = row;
  formError.value = '';
  generateModalOpen.value = true;
}

async function loadCatalogue() {
  loadingEvents.value = true;
  try {
    const [eventsRes, categoriesRes, spacesRes] = await Promise.all([
      layoutApi.getCarbootEvents(),
      layoutApi.getOrganizerVendorCategories(),
      layoutApi.getSpaceCatalogue(),
    ]);
    events.value = Array.isArray(eventsRes.data) ? eventsRes.data : (eventsRes.data?.data || []);
    categories.value = categoriesRes.data?.categories || [];
    spaces.value = Array.isArray(spacesRes.data) ? spacesRes.data : (spacesRes.data?.data || []);
  } catch (error) {
    toast.error(layoutErrorMessage(error));
  } finally {
    loadingEvents.value = false;
  }
}

async function refreshLayout({ force = false } = {}) {
  if (!selectedEventId.value) {
    layout.value = null;
    return;
  }
  if (loading.value && !force) return;

  const token = ++loadToken.value;
  loading.value = true;
  loadError.value = '';

  try {
    const { data } = await layoutApi.getOrganizerEventLayout(selectedEventId.value);
    if (token !== loadToken.value) return;
    layout.value = data;
    lastLoadedAt.value = Date.now();
  } catch (error) {
    if (token !== loadToken.value) return;
    layout.value = null;
    loadError.value = layoutErrorMessage(error);
  } finally {
    if (token === loadToken.value) {
      loading.value = false;
    }
  }
}

function onEventSelected() {
  const query = { ...route.query };
  if (selectedEventId.value) {
    query.eventId = selectedEventId.value;
  } else {
    delete query.eventId;
  }
  router.replace({ path: '/admin', hash: '#layout', query });
  refreshLayout({ force: true });
}

async function withMutation(action) {
  if (mutating.value) return;
  mutating.value = true;
  formError.value = '';
  try {
    await action();
    rowModalOpen.value = false;
    siteModalOpen.value = false;
    generateModalOpen.value = false;
    await refreshLayout({ force: true });
  } catch (error) {
    formError.value = layoutErrorMessage(error);
    toast.error(formError.value);
  } finally {
    mutating.value = false;
  }
}

async function submitRowForm(payload) {
  await withMutation(async () => {
    if (activeRow.value?.id) {
      await layoutApi.updateLayoutRow(selectedEventId.value, activeRow.value.id, payload);
      toast.success(copy.rowUpdated);
    } else {
      await layoutApi.createLayoutRow(selectedEventId.value, payload);
      toast.success(copy.rowCreated);
    }
  });
}

async function submitSiteForm(payload) {
  await withMutation(async () => {
    if (activeSite.value?.id) {
      await layoutApi.updateLayoutSite(selectedEventId.value, activeSite.value.id, payload);
      toast.success(copy.siteUpdated);
    } else {
      await layoutApi.createLayoutSite(selectedEventId.value, activeRow.value.id, payload);
      toast.success(copy.siteCreated);
    }
  });
}

async function submitGenerate(payload) {
  await withMutation(async () => {
    await layoutApi.generateLayoutSites(selectedEventId.value, activeRow.value.id, payload);
    toast.success(copy.sitesGenerated);
  });
}

async function moveRow(row, direction) {
  const ordered = rows.value;
  const index = ordered.findIndex((item) => item.id === row.id);
  const payload = buildRowReorderPayload(ordered, index, index + direction);
  if (!payload) return;
  await withMutation(async () => {
    await layoutApi.reorderLayoutRows(selectedEventId.value, payload);
    toast.success(copy.rowsReordered);
  });
}

async function openReorderSites(row) {
  const sites = sortSitesByDisplayOrder(row.sites || []);
  if (sites.length < 2) return;
  const reversed = [...sites].reverse();
  const nextPayload = {
    sites: reversed.map((site, index) => ({ id: site.id, display_order: index + 1 })),
  };
  if (!window.confirm(`Susun semula tapak dalam ${row.label} mengikut susunan terbalik semasa?\nAnda boleh susun semula lagi selepas ini.`)) {
    return;
  }
  await withMutation(async () => {
    await layoutApi.reorderLayoutSites(selectedEventId.value, row.id, nextPayload);
    toast.success(copy.sitesReordered);
  });
}

async function confirmDeleteRow(row) {
  if (!window.confirm('Padam baris ini?\n\nBaris kosong ini akan dipadam secara kekal.')) return;
  await withMutation(async () => {
    await layoutApi.deleteLayoutRow(selectedEventId.value, row.id);
    toast.success(copy.rowDeleted);
  });
}

async function confirmArchiveRow(row) {
  if (!window.confirm('Arkibkan baris ini?\n\nBaris akan dinyahaktifkan dan disembunyikan daripada paparan awam. Tapak aktif dalam baris ini juga akan dinyahaktifkan. Sejarah tempahan tidak akan dipadam.')) {
    return;
  }
  await withMutation(async () => {
    await layoutApi.archiveLayoutRow(selectedEventId.value, row.id);
    toast.success(copy.rowArchived);
  });
}

async function confirmUnarchiveRow(row) {
  if (!window.confirm(`${copy.unarchiveHint}`)) return;
  await withMutation(async () => {
    await layoutApi.unarchiveLayoutRow(selectedEventId.value, row.id);
    toast.success(copy.rowUnarchived);
  });
}

async function toggleSiteStatus(site) {
  const next = site.operational_status === 'active' ? 'disabled' : 'active';
  if (next !== 'active' && site.locks?.disable_locked) {
    toast.error(copy.disableLockedHint || layoutErrorMessage({ response: { data: { error: 'ACTIVE_ALLOCATIONS_PRESENT' } } }));
    return;
  }
  await withMutation(async () => {
    await layoutApi.updateLayoutSite(selectedEventId.value, site.id, { operational_status: next });
    toast.success(copy.siteUpdated);
  });
}

async function confirmDeleteSite(site) {
  if (!window.confirm('Padam tapak ini?\n\nTapak hanya boleh dipadam jika tidak pernah mempunyai rekod tempahan.')) {
    return;
  }
  await withMutation(async () => {
    await layoutApi.deleteLayoutSite(selectedEventId.value, site.id);
    toast.success(copy.siteDeleted);
  });
}

watch(
  () => route.query.eventId,
  (eventId) => {
    if (!eventId) return;
    if (String(eventId) !== selectedEventId.value) {
      selectedEventId.value = String(eventId);
      refreshLayout({ force: true });
    }
  },
);

onMounted(async () => {
  await loadCatalogue();
  const fromQuery = route.query.eventId ? String(route.query.eventId) : '';
  if (fromQuery) {
    selectedEventId.value = fromQuery;
    await refreshLayout({ force: true });
  }
});

defineExpose({
  load: async () => {
    await loadCatalogue();
    if (selectedEventId.value) {
      await refreshLayout({ force: true });
    }
  },
});
</script>
