<template>
  <section
    class="rounded-xl border border-ink-200 bg-white p-4 sm:p-5 space-y-4"
    data-testid="event-site-selector"
    :aria-labelledby="headingId"
  >
    <div class="space-y-2">
      <p :id="headingId" class="text-sm font-bold text-ink-900">Select your physical tapak sites</p>
      <p class="text-sm text-ink-600">
        Choose one or more adjacent sites in the same row. Availability is live, but final confirmation happens when you submit your booking.
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
      {{ readinessMessage }}
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
      v-if="blockedMessage"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-3 text-sm text-ink-700"
      data-testid="event-site-selection-blocked"
      role="status"
    >
      {{ blockedMessage }}
    </div>

    <div class="flex flex-wrap gap-2 text-xs" data-testid="event-site-legend" aria-label="Site availability legend">
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
      Loading site availability…
    </div>

    <div
      v-else-if="loadError"
      class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-800 space-y-3"
      data-testid="event-site-selector-error"
    >
      <p>{{ loadError }}</p>
      <button type="button" class="ml-btn-ghost text-sm" @click="$emit('retry')">Retry</button>
    </div>

    <div
      v-else-if="allSitesOccupied"
      class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-4 text-sm text-ink-700"
      data-testid="event-site-all-occupied"
    >
      All physical sites are currently reserved or unavailable.
    </div>

    <div v-else class="space-y-4 overflow-x-auto pb-1" data-testid="event-site-map">
      <div
        v-for="row in groupedRows"
        :key="row.rowLabel"
        class="min-w-max"
        :data-testid="`event-site-row-${row.rowLabel}`"
      >
        <div class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-500">
          Row {{ row.rowLabel }}
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="site in row.sites"
            :key="site.id"
            type="button"
            class="relative flex min-w-[4.5rem] flex-col items-center rounded-xl border px-2 py-2 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            :class="tileClass(site)"
            :disabled="!isInteractive(site)"
            :aria-pressed="selectedSet.has(Number(site.id))"
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
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Selected sites</p>
          <p class="mt-1 text-sm font-semibold text-ink-900">
            {{ selectedLabels }}
          </p>
          <p class="mt-1 text-xs text-ink-600">
            {{ selectedSites.length }} site{{ selectedSites.length === 1 ? '' : 's' }}
            <span v-if="sharedSpaceName"> · {{ sharedSpaceName }}</span>
          </p>
        </div>
        <button type="button" class="ml-btn-ghost text-sm shrink-0" data-testid="event-site-clear-selection" @click="clearSelection">
          Clear selection
        </button>
      </div>
      <p class="text-lg font-extrabold text-brand-800" data-testid="event-site-preview-amount">
        Preview total: RM {{ previewAmountFormatted }}
      </p>
      <p class="text-xs text-ink-600">
        Preview amount covers the complete event duration and is not multiplied by day count. Final amount is confirmed by the server.
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
  groupSitesByRow,
  selectionValidationMessage,
  siteAriaLabel,
  toggleSiteSelection,
} from '../../utils/eventSiteSelection';

const props = defineProps({
  sites: { type: Array, default: () => [] },
  operationalDays: { type: Array, default: () => [] },
  selectedSiteIds: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  readinessMessage: { type: String, default: '' },
  selectionError: { type: String, default: '' },
});

const emit = defineEmits(['update:selectedSiteIds', 'retry']);

const headingId = `event-site-selector-heading-${Math.random().toString(36).slice(2, 8)}`;
const blockedMessage = ref('');

const legendItems = [
  { key: 'available', label: 'Available', chipClass: 'border-emerald-200 bg-emerald-50 text-emerald-800', swatchClass: 'bg-emerald-400 border-emerald-500' },
  { key: 'selected', label: 'Selected', chipClass: 'border-brand-300 bg-brand-100 text-brand-800', swatchClass: 'bg-brand-500 border-brand-600' },
  { key: 'occupied', label: 'Occupied', chipClass: 'border-rose-200 bg-rose-50 text-rose-800', swatchClass: 'bg-rose-300 border-rose-400' },
  { key: 'unavailable', label: 'Unavailable', chipClass: 'border-ink-200 bg-ink-50 text-ink-700', swatchClass: 'bg-ink-200 border-ink-300' },
  { key: 'disabled', label: 'Disabled', chipClass: 'border-slate-200 bg-slate-50 text-slate-700', swatchClass: 'bg-slate-300 border-slate-400' },
];

const groupedRows = computed(() => groupSitesByRow(props.sites));
const selectedSet = computed(() => new Set(props.selectedSiteIds.map(Number)));
const selectedSites = computed(() => getSelectedSites(props.sites, props.selectedSiteIds));
const previewAmountFormatted = computed(() => computePreviewAmount(selectedSites.value).toFixed(2));
const selectedLabels = computed(() => selectedSites.value.map((site) => site.label).join(', '));
const sharedSpaceName = computed(() => selectedSites.value[0]?.space_name || '');
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
  return ({
    available: 'Available',
    occupied: 'Occupied',
    unavailable: 'Unavailable',
    disabled: 'Disabled',
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
  return isSelected(site) ? `${base}, selected` : base;
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
