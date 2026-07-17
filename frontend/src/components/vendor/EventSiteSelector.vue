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

    <div v-else-if="!readinessMessage" class="space-y-4 pb-1" data-testid="event-site-map">
      <div
        v-for="row in groupedRows"
        :key="row.rowId"
        class="rounded-2xl border border-ink-200 bg-ink-50/40 p-4"
        :data-row-id="row.rowId"
        :data-testid="`event-site-row-${row.rowId}`"
      >
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-extrabold text-ink-900">{{ row.rowLabel }}</h3>
            <p class="mt-0.5 text-sm font-semibold text-brand-700">{{ row.category?.label }}</p>
            <p v-if="row.description" class="mt-1 text-xs text-ink-600">{{ row.description }}</p>
          </div>
          <div class="text-right text-xs text-ink-600">
            <p><strong>{{ row.availableSiteCount }}</strong> / {{ row.siteCount }} tapak tersedia</p>
            <p v-if="row.spaceNames.length">{{ row.spaceNames.join(', ') }}</p>
            <p v-if="row.prices.length" class="font-bold text-brand-700">RM {{ row.prices.join(' / RM ') }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2 min-[420px]:grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
          <button
            v-for="site in row.sites"
            :key="site.id"
            type="button"
            class="relative flex min-h-20 min-w-0 flex-col items-center justify-center rounded-xl border px-2 py-2 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            :class="tileClass(site)"
            :disabled="!isInteractive(site)"
            :aria-pressed="selectedSet.has(Number(site.id))"
            :aria-disabled="!isInteractive(site)"
            :aria-label="ariaLabel(site)"
            :data-testid="`event-site-tile-${site.label}`"
            @click="handleToggle(site)"
          >
            <span class="text-sm font-extrabold text-ink-900">{{ site.label }}</span>
            <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="statusTextClass(site)">
              {{ statusLabel(site) }}
            </span>
            <span v-if="site.space_name" class="mt-1 text-[10px] text-ink-500 line-clamp-1">{{ shortSpaceName(site.space_name) }}</span>
            <span class="mt-1 text-[11px] font-bold text-brand-700">RM {{ formatPrice(site.price) }}</span>
          </button>
        </div>
      </div>
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
            <div><dt class="text-xs text-ink-500">Tapak Dipilih</dt><dd class="font-semibold">{{ selectedLabels }}</dd></div>
            <div><dt class="text-xs text-ink-500">Bilangan Tapak</dt><dd class="font-semibold">{{ selectedSites.length }}</dd></div>
            <div><dt class="text-xs text-ink-500">Hari Acara</dt><dd class="font-semibold">{{ operationalDays.length }}</dd></div>
            <div><dt class="text-xs text-ink-500">Harga Setiap Tapak</dt><dd class="font-semibold">RM {{ unitPriceFormatted }}</dd></div>
            <div v-if="sharedSpaceName"><dt class="text-xs text-ink-500">Jenis Ruang</dt><dd class="font-semibold">{{ sharedSpaceName }}</dd></div>
          </dl>
        </div>
        <button type="button" class="ml-btn-ghost text-sm shrink-0" data-testid="event-site-clear-selection" @click="clearSelection">
          Kosongkan pilihan
        </button>
      </div>
      <p class="text-lg font-extrabold text-brand-800" data-testid="event-site-preview-amount">
        Jumlah: RM {{ previewAmountFormatted }}
      </p>
      <p class="text-xs text-ink-600">
        Jumlah meliputi keseluruhan tempoh acara dan tidak didarab dengan bilangan hari. Jumlah akhir disahkan oleh pelayan.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
  computePreviewAmount,
  formatOperationalDaysSummary,
  getSelectedSites,
  prepareAvailabilityRows,
  selectionValidationMessage,
  siteAriaLabel,
  toggleSiteSelection,
} from '../../utils/eventSiteSelection';

const props = defineProps({
  sites: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  selectedCategory: { type: Object, default: null },
  operationalDays: { type: Array, default: () => [] },
  selectedSiteIds: { type: Array, default: () => [] },
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
const selectedSet = computed(() => new Set(props.selectedSiteIds.map(Number)));
const selectedSites = computed(() => getSelectedSites(props.sites, props.selectedSiteIds));
const previewAmountFormatted = computed(() => computePreviewAmount(selectedSites.value).toFixed(2));
const selectedLabels = computed(() => selectedSites.value.map((site) => site.label).join(', '));
const sharedSpaceName = computed(() => selectedSites.value[0]?.space_name || '');
const unitPriceFormatted = computed(() => Number(selectedSites.value[0]?.price || 0).toFixed(2));
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

function formatPrice(value) {
  return Number(value || 0).toFixed(2);
}

function shortSpaceName(name) {
  return String(name || '').replace(' (1 Parking Lot)', '').replace(' (2 Parking Lots)', '');
}

function isSelected(site) {
  return selectedSet.value.has(Number(site.id));
}

function isInteractive(site) {
  if (isSelected(site)) return true;
  return Boolean(site.is_selectable);
}

function statusLabel(site) {
  if (isSelected(site)) return 'Selected';
  if (site.availability_status === 'occupied' && site.occupancy_status === 'confirmed') {
    return 'Disahkan';
  }
  if (site.availability_status === 'occupied' && site.occupancy_status === 'reserved') {
    return 'Ditempah';
  }
  return ({
    available: 'Tersedia',
    occupied: 'Ditempah',
    unavailable: 'Tidak tersedia',
    disabled: 'Dinyahaktifkan',
  })[site.availability_status] || site.availability_status;
}

function statusTextClass(site) {
  if (isSelected(site)) return 'text-brand-700';
  return ({
    available: 'text-emerald-700',
    occupied: 'text-rose-700',
    unavailable: 'text-ink-500',
    disabled: 'text-slate-600',
  })[site.availability_status] || 'text-ink-500';
}

function tileClass(site) {
  if (isSelected(site)) {
    return 'border-brand-500 bg-brand-100 shadow-sm';
  }

  if (!site.is_selectable) {
    return 'border-ink-200 bg-ink-50 opacity-80 cursor-not-allowed';
  }

  return 'border-emerald-200 bg-emerald-50 hover:border-brand-400 hover:bg-brand-50 cursor-pointer';
}

function ariaLabel(site) {
  const base = siteAriaLabel(site);
  return isSelected(site) ? `${base}, dipilih` : base;
}

function handleToggle(site) {
  const result = toggleSiteSelection(site, props.selectedSiteIds, props.sites);
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
