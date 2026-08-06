<template>
  <div class="space-y-5" data-testid="organizer-event-layout-panel">
    <section class="ml-card space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-blue-800">{{ copy.pageTitle }}</p>
          <h2 class="text-xl font-extrabold text-ink-900">
            {{ displayEventName || copy.selectEventPrompt }}
          </h2>
          <p v-if="layout?.event && !switchingEvents" class="mt-1 text-sm text-ink-500">
            Status: {{ layout.event.status }}
            · {{ rows.length }} rows
            · {{ activeSiteCount }} active sites
            <span v-if="physicalSiteCount != null">
              · {{ copy.physicalSitesSummary(physicalSiteCount, activeSiteCount, vendorSiteOpenLimit) }}
            </span>
            <span v-if="unresolvedSites.length" class="font-semibold text-amber-700">
              · {{ unresolvedSites.length }} unassigned
            </span>
          </p>
          <p v-if="lastLoadedAt && !switchingEvents" class="mt-1 text-[11px] text-ink-400">
            Last refreshed: {{ formatLoadedAt(lastLoadedAt) }}
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
            class="ml-btn-ghost text-sm"
            :disabled="!selectedEventId || loading || addRowDisabled"
            :title="addRowDisabled ? copy.allPhysicalRowsInUse : copy.addRow"
            data-testid="layout-add-row-button"
            @click="openCreateRow"
          >
            {{ copy.addRow }}
          </button>
        </div>
      </div>

      <p
        v-if="addRowDisabled && selectedEventId"
        class="text-xs text-ink-500"
        data-testid="layout-add-row-disabled-hint"
      >
        {{ copy.allPhysicalRowsInUse }}
      </p>

      <div>
        <label class="ml-label" for="layout-event-select">{{ copy.selectEvent }}</label>
        <select
          id="layout-event-select"
          v-model="selectedEventId"
          class="ml-input"
          data-testid="layout-event-select"
          :disabled="loadingEvents || loading || switchingEvents"
          @change="onEventSelected"
        >
          <option value="">{{ copy.selectEventOption }}</option>
          <option v-for="event in events" :key="event.id" :value="String(event.id)">
            {{ event.title }} ({{ event.status }})
          </option>
        </select>
      </div>

      <div
        class="sr-only"
        aria-live="polite"
        data-testid="layout-live-status"
      >
        {{ liveStatusMessage }}
      </div>
    </section>

    <div
      v-if="loading && !layout"
      class="ml-card animate-pulse space-y-3 py-10 text-center motion-reduce:animate-none"
      data-testid="layout-loading-state"
    >
      <div class="mx-auto h-10 w-10 rounded-full bg-ink-200" />
      <p class="text-sm font-medium text-ink-500">{{ loadingMessage }}</p>
    </div>

    <div
      v-else-if="loadError && !layout"
      class="ml-card space-y-3 border-rose-200 bg-rose-50"
      data-testid="layout-error-state"
    >
      <p class="font-semibold text-rose-900">{{ copy.loadError }}</p>
      <p class="text-sm text-rose-800">{{ loadError }}</p>
      <button type="button" class="ml-btn-primary text-sm" @click="refreshLayout({ force: true })">
        {{ copy.tryAgain }}
      </button>
    </div>

    <template v-else-if="selectedEventId && layout">
      <div class="relative" data-testid="layout-content-shell">
        <div
          v-if="switchingEvents"
          class="absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-white/80 backdrop-blur-[2px] motion-safe:transition-opacity"
          data-testid="layout-switch-overlay"
        >
          <div class="space-y-2 px-4 text-center">
            <div class="mx-auto h-8 w-8 animate-pulse rounded-full bg-ink-200 motion-reduce:animate-none" />
            <p class="text-sm font-semibold text-ink-700">{{ loadingMessage }}</p>
          </div>
        </div>

        <div
          v-if="loadError"
          class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
          data-testid="layout-inline-error"
        >
          {{ loadError }}
        </div>

        <div :class="switchingEvents ? 'pointer-events-none opacity-40 motion-safe:transition-opacity' : ''">
          <EventLayoutReadinessPanel
            v-if="!switchingEvents"
            :readiness="layout.readiness || {}"
          />

          <section
            v-if="selectOpenMode"
            class="ml-card mb-4 space-y-3 border-sky-200 bg-sky-50/70"
            data-testid="select-open-sites-panel"
          >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 class="text-base font-extrabold text-ink-900">{{ copy.selectOpenSitesTitle }}</h3>
                <p class="mt-1 text-sm text-ink-600">{{ copy.selectOpenSitesHelp }}</p>
                <p class="mt-2 text-sm font-bold text-sky-950" data-testid="select-open-sites-count">
                  {{ copy.selectOpenSitesCount(selectedOpenSiteIds.length, vendorSiteOpenLimit || 0) }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="ml-btn-ghost text-sm" :disabled="mutating" @click="cancelSelectOpenMode">
                  {{ copy.cancel }}
                </button>
                <button
                  type="button"
                  class="ml-btn-primary text-sm"
                  :disabled="mutating || !canConfirmOpenSites"
                  data-testid="confirm-open-sites-button"
                  @click="confirmOpenSites"
                >
                  {{ copy.confirmOpenSites }}
                </button>
              </div>
            </div>
          </section>

          <section
            v-if="!rows.length"
            class="ml-card space-y-3 text-center"
            data-testid="layout-empty-state"
          >
            <p class="text-lg font-extrabold text-ink-900">{{ copy.emptyTitle }}</p>
            <p class="text-sm text-ink-500">{{ copy.emptyBody }}</p>
            <p class="text-xs text-ink-500">
              {{ copy.generateStandardOpenCount(vendorSiteOpenLimit) }}
            </p>
            <div class="flex flex-wrap justify-center gap-2">
              <button
                type="button"
                class="ml-btn-primary"
                data-testid="layout-empty-generate-standard"
                @click="openStandardGenerator"
              >
                {{ copy.generateStandardLayout }}
              </button>
              <button
                type="button"
                class="ml-btn-ghost"
                :disabled="addRowDisabled"
                :title="addRowDisabled ? copy.allPhysicalRowsInUse : copy.addRow"
                @click="openCreateRow"
              >
                {{ copy.addRow }}
              </button>
            </div>
          </section>

          <section
            v-else
            class="ml-card space-y-4"
            data-testid="layout-visual-workspace"
          >
            <div v-if="needsOpenSiteSelection && !selectOpenMode" class="flex flex-wrap gap-2">
              <button
                type="button"
                class="ml-btn-primary text-sm"
                data-testid="start-select-open-sites"
                @click="enterSelectOpenMode"
              >
                {{ copy.startSelectOpenSites }}
              </button>
            </div>
            <VisualParkingLayout
              mode="organizer"
              :rows="visualRows"
              :show-legend="true"
              :show-counts="true"
              :show-title="true"
              @activate-site="onVisualSiteActivate"
            />
          </section>

          <OrganizerFocusedSiteControls
            v-if="rows.length && !selectOpenMode"
            :site="focusedSite"
            :row="focusedRow"
            :mutating="mutating"
            @edit-site="openEditSite"
            @set-status="setFocusedSiteStatus"
            @delete-site="confirmDeleteSite"
            @edit-row="openEditRow"
            @archive-row="confirmArchiveRow"
            @delete-row="confirmDeleteRow"
            @add-site="openCreateSite"
            @generate="openGenerateSites"
          />

          <section
            v-if="unresolvedSites.length"
            class="ml-card border-amber-200 bg-amber-50/60 space-y-3"
            data-testid="unresolved-sites-panel"
          >
            <h3 class="text-base font-extrabold text-amber-950">{{ copy.unresolvedTitle }}</h3>
            <p class="text-sm text-amber-900">
              {{ copy.unresolvedHelp }}
            </p>
            <ul class="space-y-2">
              <li
                v-for="site in unresolvedSites"
                :key="site.id"
                class="rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm"
              >
                <div class="font-bold text-ink-900">{{ site.label }}</div>
                <div class="text-xs text-ink-500">
                  Legacy row label: {{ site.row_label || '—' }}
                  · {{ site.space?.space_size || copy.noSpace }}
                  · {{ site.operational_status }}
                </div>
              </li>
            </ul>
          </section>

          <details
            v-if="rows.length"
            class="ml-card"
            data-testid="layout-advanced-rows"
          >
            <summary class="cursor-pointer text-sm font-extrabold text-ink-900">
              {{ copy.advancedRowsTitle }}
            </summary>
            <div class="mt-4 space-y-4" data-testid="layout-rows-workspace">
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
          </details>

          <section class="ml-card space-y-3" data-testid="layout-publication-panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h3 class="text-base font-extrabold text-ink-900">{{ copy.publicationTitle }}</h3>
                <p class="mt-1 text-sm text-ink-600">
                  {{ copy.publicationHelp }}
                </p>
                <p class="mt-2 text-sm font-semibold" :class="layout.event.public_layout_published ? 'text-emerald-700' : 'text-amber-700'">
                  {{ layout.event.public_layout_published ? copy.published : copy.notPublished }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="!layout.event.public_layout_published"
                  type="button"
                  class="ml-btn-primary text-sm"
                  :disabled="mutating || !layout.readiness?.public_ready"
                  data-testid="layout-publish-button"
                  @click="publishPublicLayout"
                >
                  {{ copy.publishPublicMap }}
                </button>
                <button
                  v-else
                  type="button"
                  class="ml-btn-ghost text-sm"
                  :disabled="mutating"
                  data-testid="layout-unpublish-button"
                  @click="unpublishPublicLayout"
                >
                  {{ copy.unpublishPublicMap }}
                </button>
              </div>
            </div>
            <div>
              <label for="layout-entrance-note" class="ml-label">{{ copy.entranceNoteLabel }}</label>
              <textarea
                id="layout-entrance-note"
                v-model="entranceNote"
                rows="2"
                maxlength="1000"
                class="ml-input"
                :disabled="mutating || layout.event.public_layout_published"
                placeholder="Example: Enter through the main door beside the food court."
              />
            </div>
          </section>
        </div>
      </div>
    </template>

    <LayoutRowFormModal
      v-model="rowModalOpen"
      :row="activeRow"
      :categories="categories"
      :spaces="spaces"
      :unused-row-labels="unusedRowLabels"
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

    <StandardParkingLayoutModal
      v-model="standardModalOpen"
      :categories="categories"
      :spaces="spaces"
      :vendor-site-open-limit="vendorSiteOpenLimit"
      :submitting="mutating"
      :form-error="formError"
      @submit="submitStandardGenerate"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import VisualParkingLayout from '../../../components/layout/VisualParkingLayout.vue';
import EventLayoutReadinessPanel from '../../../components/organizer/layout/EventLayoutReadinessPanel.vue';
import EventLayoutRowCard from '../../../components/organizer/layout/EventLayoutRowCard.vue';
import LayoutRowFormModal from '../../../components/organizer/layout/LayoutRowFormModal.vue';
import LayoutSiteFormModal from '../../../components/organizer/layout/LayoutSiteFormModal.vue';
import LayoutSiteGenerationModal from '../../../components/organizer/layout/LayoutSiteGenerationModal.vue';
import OrganizerFocusedSiteControls from '../../../components/organizer/layout/OrganizerFocusedSiteControls.vue';
import StandardParkingLayoutModal from '../../../components/organizer/layout/StandardParkingLayoutModal.vue';
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
import {
  adaptOrganizerRows,
  canOrganizerChangeSiteStatus,
} from '../../../utils/visualParkingLayout';

const toast = useToast();
const route = useRoute();
const router = useRouter();
const copy = LAYOUT_COPY;

const events = ref([]);
const categories = ref([]);
const spaces = ref([]);
const selectedEventId = ref('');
const previousSuccessfulEventId = ref('');
const layout = ref(null);
const loading = ref(false);
const switchingEvents = ref(false);
const loadingEvents = ref(false);
const mutating = ref(false);
const loadError = ref('');
const formError = ref('');
const lastLoadedAt = ref(null);
const loadToken = ref(0);
const entranceNote = ref('');
const focusedSiteId = ref(null);
const selectOpenMode = ref(false);
const selectedOpenSiteIds = ref([]);
const liveStatusMessage = ref('');

const rowModalOpen = ref(false);
const siteModalOpen = ref(false);
const generateModalOpen = ref(false);
const standardModalOpen = ref(false);
const activeRow = ref(null);
const activeSite = ref(null);

const rows = computed(() => sortRowsByDisplayOrder(layout.value?.rows || []));
const unresolvedSites = computed(() => layout.value?.unresolved_sites || []);
const activeSiteCount = computed(() => countActiveSites(rows.value));
const physicalSiteCount = computed(() => layout.value?.counts?.physical_sites ?? null);
const vendorSiteOpenLimit = computed(() => {
  const value = layout.value?.event?.vendor_site_open_limit;
  return value == null ? null : Number(value);
});
const unusedRowLabels = computed(() => layout.value?.venue_template?.unused_row_labels || []);
const addRowDisabled = computed(() => Boolean(layout.value?.venue_template?.all_rows_in_use));
const needsOpenSiteSelection = computed(() => {
  if (vendorSiteOpenLimit.value == null) return false;
  return activeSiteCount.value !== vendorSiteOpenLimit.value;
});
const canConfirmOpenSites = computed(
  () =>
    vendorSiteOpenLimit.value != null
    && selectedOpenSiteIds.value.length === Number(vendorSiteOpenLimit.value),
);

const visualRows = computed(() => {
  const adapted = adaptOrganizerRows(rows.value, { focusedSiteId: focusedSiteId.value });
  if (!selectOpenMode.value) return adapted;
  const selected = new Set(selectedOpenSiteIds.value.map(Number));
  return adapted.map((row) => ({
    ...row,
    sites: (row.sites || []).map((site) => ({
      ...site,
      selected: selected.has(Number(site.id)),
      focused: selected.has(Number(site.id)),
      status: selected.has(Number(site.id)) ? 'selected' : site.status,
    })),
  }));
});

const focusedSite = computed(() => {
  if (focusedSiteId.value == null) return null;
  for (const row of rows.value) {
    const match = (row.sites || []).find((site) => Number(site.id) === Number(focusedSiteId.value));
    if (match) return match;
  }
  return null;
});
const focusedRow = computed(() => {
  if (!focusedSite.value) return null;
  return rows.value.find((row) => Number(row.id) === Number(focusedSite.value.event_layout_row_id)) || null;
});

const displayEventName = computed(() => {
  if (switchingEvents.value) {
    const pending = events.value.find((event) => String(event.id) === String(selectedEventId.value));
    return pending?.title || copy.selectEventPrompt;
  }
  return layout.value?.event?.name || copy.selectEventPrompt;
});

const loadingMessage = computed(() => {
  const pending = events.value.find((event) => String(event.id) === String(selectedEventId.value));
  if (pending?.title) return copy.loadingLayoutFor(pending.title);
  return copy.loadingLayout;
});

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
  if (addRowDisabled.value && !activeRow.value) return;
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
  focusedSiteId.value = site.id;
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

function openStandardGenerator() {
  if (rows.value.length > 0 || unresolvedSites.value.length > 0) {
    toast.error(copy.layoutExistsHint);
    return;
  }
  if (!vendorSiteOpenLimit.value) {
    toast.error(copy.generateStandardLimitRequired);
    return;
  }
  formError.value = '';
  standardModalOpen.value = true;
}

function enterSelectOpenMode() {
  selectOpenMode.value = true;
  selectedOpenSiteIds.value = [];
  focusedSiteId.value = null;
}

function cancelSelectOpenMode() {
  selectOpenMode.value = false;
  selectedOpenSiteIds.value = [];
}

function onVisualSiteActivate({ site }) {
  if (selectOpenMode.value) {
    const id = Number(site.id);
    const next = new Set(selectedOpenSiteIds.value.map(Number));
    if (next.has(id)) {
      next.delete(id);
    } else if (vendorSiteOpenLimit.value == null || next.size < Number(vendorSiteOpenLimit.value)) {
      next.add(id);
    } else {
      toast.error(copy.selectOpenSitesCount(next.size, vendorSiteOpenLimit.value));
      return;
    }
    selectedOpenSiteIds.value = [...next];
    return;
  }
  focusedSiteId.value = site.id;
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
    if (!error.forbiddenMessage) {
      toast.error(layoutErrorMessage(error));
    }
  } finally {
    loadingEvents.value = false;
  }
}

async function refreshLayout({ force = false, isSwitch = false } = {}) {
  if (!selectedEventId.value) {
    layout.value = null;
    focusedSiteId.value = null;
    selectOpenMode.value = false;
    switchingEvents.value = false;
    return;
  }
  if (loading.value && !force) return;

  const token = ++loadToken.value;
  loading.value = true;
  if (isSwitch) {
    switchingEvents.value = true;
    focusedSiteId.value = null;
    selectOpenMode.value = false;
    selectedOpenSiteIds.value = [];
    liveStatusMessage.value = loadingMessage.value;
  }
  loadError.value = '';

  try {
    const { data } = await layoutApi.getOrganizerEventLayout(selectedEventId.value);
    if (token !== loadToken.value) return;
    layout.value = data;
    entranceNote.value = data.event?.public_layout_entrance_note || '';
    lastLoadedAt.value = Date.now();
    previousSuccessfulEventId.value = String(selectedEventId.value);
    liveStatusMessage.value = `Loaded layout for ${data.event?.name || 'event'}.`;
    if (focusedSiteId.value != null) {
      const stillPresent = (data.rows || []).some((row) =>
        (row.sites || []).some((site) => Number(site.id) === Number(focusedSiteId.value)),
      );
      if (!stillPresent) focusedSiteId.value = null;
    }
  } catch (error) {
    if (token !== loadToken.value) return;
    loadError.value = layoutErrorMessage(error);
    liveStatusMessage.value = copy.loadError;
    if (isSwitch && previousSuccessfulEventId.value) {
      selectedEventId.value = previousSuccessfulEventId.value;
      const query = { ...route.query, eventId: previousSuccessfulEventId.value };
      router.replace({ path: '/admin', hash: '#layout', query });
      toast.error(loadError.value);
    } else if (!layout.value) {
      // keep empty error state
    } else {
      toast.error(loadError.value);
    }
  } finally {
    if (token === loadToken.value) {
      loading.value = false;
      switchingEvents.value = false;
    }
  }
}

function onEventSelected() {
  focusedSiteId.value = null;
  selectOpenMode.value = false;
  selectedOpenSiteIds.value = [];
  const query = { ...route.query };
  if (selectedEventId.value) {
    query.eventId = selectedEventId.value;
  } else {
    delete query.eventId;
    layout.value = null;
  }
  router.replace({ path: '/admin', hash: '#layout', query });
  if (selectedEventId.value) {
    refreshLayout({ force: true, isSwitch: Boolean(layout.value) || Boolean(previousSuccessfulEventId.value) });
  }
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
    standardModalOpen.value = false;
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

async function publishPublicLayout() {
  await withMutation(async () => {
    await layoutApi.publishOrganizerEventLayout(selectedEventId.value, entranceNote.value);
    toast.success(copy.publicPublishedToast);
  });
}

async function unpublishPublicLayout() {
  if (!window.confirm(copy.confirmUnpublish)) return;
  await withMutation(async () => {
    await layoutApi.unpublishOrganizerEventLayout(selectedEventId.value);
    toast.success(copy.publicUnpublishedToast);
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

async function submitStandardGenerate(payload) {
  await withMutation(async () => {
    await layoutApi.generateStandardParkingLayout(selectedEventId.value, payload);
    toast.success(copy.standardLayoutGenerated);
    selectOpenMode.value = true;
    selectedOpenSiteIds.value = [];
  });
}

async function confirmOpenSites() {
  if (!canConfirmOpenSites.value) return;
  await withMutation(async () => {
    await layoutApi.setOpenLayoutSites(selectedEventId.value, selectedOpenSiteIds.value);
    toast.success(copy.openSitesConfirmed);
    selectOpenMode.value = false;
    selectedOpenSiteIds.value = [];
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
  if (!window.confirm(copy.confirmReorderSites(row.label))) {
    return;
  }
  await withMutation(async () => {
    await layoutApi.reorderLayoutSites(selectedEventId.value, row.id, nextPayload);
    toast.success(copy.sitesReordered);
  });
}

async function confirmDeleteRow(row) {
  if (!window.confirm(copy.confirmDeleteRow)) return;
  await withMutation(async () => {
    await layoutApi.deleteLayoutRow(selectedEventId.value, row.id);
    toast.success(copy.rowDeleted);
  });
}

async function confirmArchiveRow(row) {
  if (!window.confirm(copy.confirmArchiveRow)) {
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

async function setFocusedSiteStatus(site, nextStatus) {
  if (!canOrganizerChangeSiteStatus(site, nextStatus)) {
    toast.error(copy.disableLockedHint);
    return;
  }
  await withMutation(async () => {
    await layoutApi.updateLayoutSite(selectedEventId.value, site.id, {
      operational_status: nextStatus,
    });
    toast.success(copy.siteUpdated);
  });
}

async function toggleSiteStatus(site) {
  const next = site.operational_status === 'active' ? 'disabled' : 'active';
  await setFocusedSiteStatus(site, next);
}

async function confirmDeleteSite(site) {
  if (!window.confirm(copy.confirmDeleteSite)) {
    return;
  }
  await withMutation(async () => {
    await layoutApi.deleteLayoutSite(selectedEventId.value, site.id);
    if (Number(focusedSiteId.value) === Number(site.id)) {
      focusedSiteId.value = null;
    }
    toast.success(copy.siteDeleted);
  });
}

watch(
  () => route.query.eventId,
  (eventId) => {
    if (!eventId) return;
    if (String(eventId) !== selectedEventId.value) {
      selectedEventId.value = String(eventId);
      focusedSiteId.value = null;
      refreshLayout({ force: true, isSwitch: Boolean(layout.value) });
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
