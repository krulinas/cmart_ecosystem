<template>
  <section
    class="rounded-xl border border-ink-200 bg-white p-4 sm:p-5 space-y-4"
    data-testid="event-site-selector"
    :aria-labelledby="headingId"
  >
    <div class="space-y-2">
      <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Langkah 2</p>
      <h2 :id="headingId" class="text-base font-extrabold text-ink-900">Pilih Tapak Fizikal</h2>
      <p class="text-sm text-ink-600">
        Pilih satu atau lebih tapak bersebelahan dalam baris yang sama. Pengesahan akhir dibuat oleh pelayan semasa tempahan dihantar.
      </p>
      <p v-if="selectedCategory" class="text-sm font-semibold text-brand-700" data-testid="event-site-selected-category">
        Kategori dipilih: {{ selectedCategory.label }}
      </p>
      <p v-if="operationalDays.length" class="text-xs text-brand-700 font-medium" data-testid="event-site-days-summary">
        {{ daysSummary }}
      </p>
    </div>

    <div
      v-if="readinessMessage"
      class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      data-testid="event-site-readiness-message"
      role="alert"
    >
      <p>{{ readinessMessage }}</p>
      <div v-if="selectedCategory" class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="ml-btn-ghost text-sm" @click="$emit('choose-category')">
          Pilih kategori lain
        </button>
        <button type="button" class="ml-btn-ghost text-sm" @click="$emit('retry')">
          Muat semula
        </button>
      </div>
    </div>

    <div
      v-if="selectionError"
      class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
      data-testid="event-site-selection-error"
      role="alert"
    >
      {{ selectionError }}
    </div>

    <div
      v-if="removedStaleSiteLabels.length"
      class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      data-testid="event-site-removed-stale"
      role="status"
    >
      Tapak yang dialih keluar: {{ removedStaleSiteLabels.join(', ') }}. Sila semak pilihan sebelum menghantar semula.
    </div>

    <div
      v-if="blockedMessage"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-3 text-sm text-ink-700"
      data-testid="event-site-selection-blocked"
      role="status"
    >
      {{ blockedMessage }}
    </div>

    <div class="flex flex-wrap gap-2 text-xs" data-testid="event-site-legend" aria-label="Petunjuk status tapak">
      <span
        v-for="item in legendItems"
        :key="item.key"
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold"
        :class="item.chipClass"
      >
        <span class="h-2.5 w-2.5 rounded-sm border" :class="item.swatchClass" aria-hidden="true"></span>
        {{ item.label }}
      </span>
    </div>

    <div v-if="loading" class="py-10 text-center text-sm text-ink-500" data-testid="event-site-selector-loading">
      Memuatkan tapak yang tersedia…
    </div>

    <div
      v-else-if="loadError"
      class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-800 space-y-3"
      data-testid="event-site-selector-error"
    >
      <p>{{ loadError }}</p>
      <button type="button" class="ml-btn-ghost text-sm" @click="$emit('retry')">Cuba Lagi</button>
    </div>

    <div
      v-else-if="!selectedCategory"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-4 text-sm text-ink-700"
      data-testid="event-site-category-required"
      role="status"
    >
      Pilih kategori jualan untuk meneruskan.
    </div>

    <div
      v-else-if="!readinessMessage && !groupedRows.length"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-4 text-sm text-ink-700"
      data-testid="event-site-no-compatible-rows"
    >
      Tiada baris susun atur tersedia untuk kategori ini.
    </div>

    <div
      v-else-if="!readinessMessage && allSitesOccupied"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-4 text-sm text-ink-700"
      data-testid="event-site-all-occupied"
    >
      Semua tapak untuk kategori ini telah ditempah atau tidak tersedia.
    </div>

    <div
      v-else-if="!readinessMessage"
      class="space-y-4 pb-1"
      data-testid="event-site-map"
    >
      <VisualParkingLayout
        mode="vendor"
        :rows="visualRows"
        :show-legend="false"
        :show-counts="false"
        :force-orientation="true"
        @activate-site="onVisualActivate"
      />
    </div>

    <div
      v-if="selectedSites.length"
      class="rounded-xl border border-brand-200 bg-brand-50/60 p-4 space-y-3"
      data-testid="event-site-selection-summary"
      aria-live="polite"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Ringkasan Tempahan</p>
          <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-ink-500">Kategori Jualan</dt><dd class="font-semibold">{{ selectedCategory?.label }}</dd></div>
            <div><dt class="text-xs text-ink-500">Baris</dt><dd class="font-semibold">{{ selectedRowLabel }}</dd></div>
            <div><dt class="text-xs text-ink-500">Tapak Dipilih</dt><dd class="font-semibold" data-testid="event-site-selected-labels">{{ selectedLabels }}</dd></div>
            <div><dt class="text-xs text-ink-500">Bilangan Tapak</dt><dd class="font-semibold" data-testid="event-site-selected-count">{{ selectedSites.length }}</dd></div>
            <div><dt class="text-xs text-ink-500">Harga Satu Tapak</dt><dd class="font-semibold" data-testid="event-site-unit-price">RM {{ unitPriceFormatted }}</dd></div>
            <div>
              <dt class="text-xs text-ink-500">Pengiraan</dt>
              <dd class="font-semibold" data-testid="event-site-calculation">
                RM {{ unitPriceFormatted }} × {{ selectedSites.length }} tapak
              </dd>
            </div>
            <div><dt class="text-xs text-ink-500">Hari Acara</dt><dd class="font-semibold">{{ operationalDays.length }}</dd></div>
          </dl>
        </div>
        <button type="button" class="ml-btn-ghost text-sm shrink-0" data-testid="event-site-clear-selection" @click="clearSelection">
          Kosongkan pilihan
        </button>
      </div>
      <p class="text-lg font-extrabold text-brand-800" data-testid="event-site-preview-amount">
        Jumlah: RM {{ previewAmountFormatted }}
      </p>
      <p class="text-xs text-ink-600" data-testid="event-site-day-note">
        Jumlah ini adalah untuk keseluruhan tempoh acara dan tidak didarab dengan bilangan hari.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import VisualParkingLayout from '../layout/VisualParkingLayout.vue';
import {
  computePreviewAmount,
  formatOperationalDaysSummary,
  getSelectedSites,
  prepareAvailabilityRows,
  resolveEventUnitPrice,
  selectionValidationMessage,
  toggleSiteSelection,
} from '../../utils/eventSiteSelection';
import { adaptVendorRows } from '../../utils/visualParkingLayout';

const props = defineProps({
  sites: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  selectedCategory: { type: Object, default: null },
  operationalDays: { type: Array, default: () => [] },
  selectedSiteIds: { type: Array, default: () => [] },
  sitePrice: { type: [Number, String], default: null },
  loading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  readinessMessage: { type: String, default: '' },
  selectionError: { type: String, default: '' },
  removedStaleSiteLabels: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:selectedSiteIds', 'retry', 'choose-category']);

const headingId = `event-site-selector-heading-${Math.random().toString(36).slice(2, 8)}`;
const blockedMessage = ref('');

const legendItems = [
  { key: 'available', label: 'Tersedia', chipClass: 'border-emerald-200 bg-emerald-50 text-emerald-800', swatchClass: 'bg-emerald-400 border-emerald-500' },
  { key: 'selected', label: 'Dipilih', chipClass: 'border-brand-300 bg-brand-100 text-brand-800', swatchClass: 'bg-brand-500 border-brand-600' },
  { key: 'occupied', label: 'Ditempah', chipClass: 'border-rose-200 bg-rose-50 text-rose-800', swatchClass: 'bg-rose-300 border-rose-400' },
  { key: 'unavailable', label: 'Tidak tersedia', chipClass: 'border-ink-200 bg-ink-50 text-ink-700', swatchClass: 'bg-ink-200 border-ink-300' },
  { key: 'disabled', label: 'Dinyahaktifkan', chipClass: 'border-slate-200 bg-slate-50 text-slate-700', swatchClass: 'bg-slate-300 border-slate-400' },
];

const groupedRows = computed(() => prepareAvailabilityRows(props.rows));
const visualRows = computed(() => adaptVendorRows(props.rows, props.selectedSiteIds));
const selectedSites = computed(() => getSelectedSites(props.sites, props.selectedSiteIds));
const unitPrice = computed(() => resolveEventUnitPrice(selectedSites.value, props.sitePrice));
const previewAmountFormatted = computed(() => computePreviewAmount(selectedSites.value, unitPrice.value).toFixed(2));
const selectedLabels = computed(() => selectedSites.value.map((site) => site.label).join(', '));
const unitPriceFormatted = computed(() => Number(unitPrice.value || 0).toFixed(2));
const selectedRowLabel = computed(() => {
  const rowId = Number(selectedSites.value[0]?.event_layout_row_id);
  return groupedRows.value.find((row) => row.rowId === rowId)?.rowLabel || '—';
});
const daysSummary = computed(() => formatOperationalDaysSummary(props.operationalDays));

const allSitesOccupied = computed(() => {
  if (!props.sites.length) return false;
  return props.sites.every((site) => !site.is_selectable);
});

watch(
  () => props.selectedSiteIds,
  () => {
    blockedMessage.value = '';
  },
);

function onVisualActivate({ site }) {
  const rawSite = site.raw || site;
  const result = toggleSiteSelection(rawSite, props.selectedSiteIds, props.sites);
  blockedMessage.value = result.blockedMessage;
  emit('update:selectedSiteIds', result.selectedIds);
}

function clearSelection() {
  blockedMessage.value = '';
  emit('update:selectedSiteIds', []);
}

defineExpose({
  selectionValidationMessage: () => selectionValidationMessage(selectedSites.value),
});
</script>
