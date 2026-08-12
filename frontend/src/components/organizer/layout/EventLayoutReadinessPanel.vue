<template>
  <section
    class="rounded-xl border border-ink-200 bg-white p-3 shadow-sm sm:p-4"
    data-testid="event-layout-readiness-panel"
  >
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h3 class="text-sm font-extrabold text-ink-900">{{ copy.setupNoticeTitle }}</h3>
        <p class="mt-0.5 text-xs text-ink-500">{{ copy.availabilityStatusHelp }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
          :class="statusBadgeClass"
          data-testid="operational-readiness-badge"
        >
          {{ statusBadgeLabel }}
        </span>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
          :class="publicReady
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
            : 'bg-slate-50 text-slate-700 ring-slate-200'"
          data-testid="public-readiness-badge"
        >
          {{ publicReady ? copy.publicReady : copy.publicNotReady }}
        </span>
      </div>
    </div>

    <p
      class="mt-3 text-sm"
      :class="primaryMessageClass"
      data-testid="readiness-primary-message"
      role="status"
    >
      {{ primaryMessage }}
    </p>

    <p
      v-if="selectMode"
      class="mt-2 text-sm font-bold text-sky-950"
      data-testid="readiness-selection-count"
    >
      {{ copy.selectOpenSitesCount(selectedCount) }}
    </p>

    <p
      v-if="missingEventDays && !selectMode"
      class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
      data-testid="missing-event-days-warning"
      role="status"
    >
      {{ copy.missingEventDaysWarning }}
    </p>

    <ul
      v-if="!selectMode && !operationalReady && remainingBlockers.length"
      class="mt-3 space-y-1.5"
      data-testid="readiness-blocker-list"
    >
      <li
        v-for="(blocker, index) in remainingBlockers"
        :key="`${blocker.code}-${index}`"
        class="rounded-lg border border-amber-100 bg-amber-50/70 px-3 py-1.5 text-sm text-amber-950"
      >
        {{ messageFor(blocker) }}
      </li>
    </ul>

    <details
      v-if="blockers.length && !selectMode"
      class="mt-3 rounded-lg border border-ink-100 bg-ink-50/70 px-3 py-2"
      data-testid="readiness-technical-details"
    >
      <summary class="cursor-pointer text-xs font-bold text-ink-600">
        {{ copy.technicalDetails }}
      </summary>
      <ul class="mt-2 space-y-1">
        <li
          v-for="(blocker, index) in blockers"
          :key="`tech-${blocker.code}-${index}`"
          class="font-mono text-[11px] text-ink-500"
        >
          {{ blocker.code }}
        </li>
      </ul>
    </details>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { LAYOUT_COPY, readinessMessage } from '../../../utils/organizerEventLayoutMessages';

const SETUP_CODES = new Set([
  'VENDOR_SITE_OPEN_LIMIT_NOT_SET',
  'ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT',
  'ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT',
]);

const props = defineProps({
  readiness: {
    type: Object,
    default: () => ({}),
  },
  selectMode: {
    type: Boolean,
    default: false,
  },
  selectedCount: {
    type: Number,
    default: 0,
  },
  openSiteCount: {
    type: Number,
    default: 0,
  },
});

const copy = LAYOUT_COPY;
const operationalReady = computed(() => Boolean(props.readiness?.operational_ready));
const publicReady = computed(() => Boolean(props.readiness?.public_ready));
const blockers = computed(() => props.readiness?.blocking_reasons || []);
const missingEventDays = computed(() =>
  blockers.value.some((blocker) => blocker.code === 'NO_ACTIVE_EVENT_DAYS'),
);
const needsSiteSetup = computed(() =>
  blockers.value.some((blocker) => SETUP_CODES.has(blocker.code)),
);
const remainingBlockers = computed(() =>
  blockers.value.filter(
    (blocker) =>
      blocker.code !== 'NO_ACTIVE_EVENT_DAYS'
      && !(needsSiteSetup.value && SETUP_CODES.has(blocker.code)),
  ),
);

const statusBadgeLabel = computed(() => {
  if (props.selectMode) return copy.selectionModeBadge;
  if (operationalReady.value) return copy.vendorBookingOpen;
  if (needsSiteSetup.value) return copy.vendorBookingSetupRequired;
  return copy.operationalNotReady;
});

const statusBadgeClass = computed(() => {
  if (props.selectMode) return 'bg-sky-50 text-sky-900 ring-sky-200';
  if (operationalReady.value) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
  return 'bg-amber-50 text-amber-900 ring-amber-200';
});

const primaryMessage = computed(() => {
  if (props.selectMode) return copy.vendorBookingSelectingMessage;
  if (operationalReady.value) {
    return copy.vendorBookingOpenMessage(props.openSiteCount || 0);
  }
  if (needsSiteSetup.value) return copy.vendorBookingSetupMessage;
  const first = remainingBlockers.value[0] || blockers.value.find((b) => !SETUP_CODES.has(b.code));
  return first ? messageFor(first) : copy.vendorBookingSetupMessage;
});

const primaryMessageClass = computed(() => {
  if (operationalReady.value && !props.selectMode) return 'text-emerald-800';
  if (props.selectMode) return 'text-sky-950';
  return 'text-amber-950';
});

function messageFor(blocker) {
  if (blocker?.message) return blocker.message;
  return readinessMessage(blocker?.code);
}
</script>
