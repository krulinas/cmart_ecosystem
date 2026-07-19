<template>
  <section
    class="rounded-xl border border-ink-200 bg-white p-3 shadow-sm sm:p-4"
    data-testid="event-layout-readiness-panel"
  >
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 class="text-sm font-extrabold text-ink-900">{{ copy.setupNoticeTitle }}</h3>
        <p class="mt-0.5 text-xs text-ink-500">{{ copy.availabilityStatusHelp }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
          :class="operationalReady
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
            : 'bg-amber-50 text-amber-900 ring-amber-200'"
          data-testid="operational-readiness-badge"
        >
          {{ operationalReady ? copy.operationalReady : copy.operationalNotReady }}
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
      v-if="missingEventDays"
      class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
      data-testid="missing-event-days-warning"
      role="status"
    >
      {{ copy.missingEventDaysWarning }}
    </p>

    <ul
      v-if="userFacingBlockers.length"
      class="mt-3 space-y-1.5"
      data-testid="readiness-blocker-list"
    >
      <li
        v-for="(blocker, index) in userFacingBlockers"
        :key="`${blocker.code}-${index}`"
        class="rounded-lg border border-amber-100 bg-amber-50/70 px-3 py-1.5 text-sm text-amber-950"
      >
        {{ messageFor(blocker.code) }}
      </li>
    </ul>
    <p v-else-if="!missingEventDays" class="mt-3 text-sm text-emerald-800">
      {{ copy.noReadinessBlockers }}
    </p>

    <details
      v-if="blockers.length"
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

const props = defineProps({
  readiness: {
    type: Object,
    default: () => ({}),
  },
});

const copy = LAYOUT_COPY;
const operationalReady = computed(() => Boolean(props.readiness?.operational_ready));
const publicReady = computed(() => Boolean(props.readiness?.public_ready));
const blockers = computed(() => props.readiness?.blocking_reasons || []);
const missingEventDays = computed(() =>
  blockers.value.some((blocker) => blocker.code === 'NO_ACTIVE_EVENT_DAYS'),
);
const userFacingBlockers = computed(() =>
  blockers.value.filter((blocker) => blocker.code !== 'NO_ACTIVE_EVENT_DAYS'),
);
const messageFor = (code) => readinessMessage(code);
</script>
