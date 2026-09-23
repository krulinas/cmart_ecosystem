<template>
  <div
    class="rounded-xl border border-sky-100 bg-sky-50/40 p-3"
    data-testid="reservation-lifecycle"
    :aria-label="lifecycle.summary"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-xs font-bold uppercase tracking-wider text-ink-500">
          {{ t('reservation.lifecycle.title') }}
        </h4>
        <p class="mt-0.5 text-sm font-semibold text-ink-900">{{ lifecycle.summary }}</p>
      </div>
      <p
        v-if="lifecycle.nextAction"
        class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-950"
        data-testid="reservation-lifecycle-next-action"
      >
        {{ lifecycle.nextAction }}
      </p>
    </div>

    <ol
      class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-1.5"
      data-testid="reservation-lifecycle-stages"
    >
      <li
        v-for="(stage, idx) in lifecycle.stages"
        :key="stage.id"
        class="inline-flex items-center gap-1.5"
      >
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
          :class="stageClass(stage.state)"
          :data-stage-id="stage.id"
          :data-stage-state="stage.state"
          :aria-label="stage.a11yLabel || stage.label"
          :title="stage.a11yLabel && stage.a11yLabel !== stage.label ? stage.a11yLabel : undefined"
        >
          <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[9px] ring-1 ring-current/30">
            {{ stageGlyph(stage.state) }}
          </span>
          <span>{{ stage.label }}</span>
        </span>
        <span
          v-if="idx < lifecycle.stages.length - 1"
          class="hidden text-ink-300 sm:inline"
          aria-hidden="true"
        >→</span>
        <span
          v-else-if="lifecycle.terminal"
          class="hidden text-ink-300 sm:inline"
          aria-hidden="true"
        >·</span>
      </li>
      <li
        v-if="lifecycle.terminal"
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
        :class="stageClass('terminal')"
        :data-stage-id="lifecycle.terminal.id"
        data-stage-state="terminal"
      >
        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[9px] ring-1 ring-current/30">×</span>
        <span>{{ lifecycle.terminal.label }}</span>
      </li>
    </ol>

    <p class="mt-2 text-[11px] text-ink-500" data-testid="reservation-lifecycle-fallback">
      {{ t('reservation.lifecycle.chargeNote', { charge: lifecycle.chargeLabel }) }}
      {{ t('reservation.lifecycle.manualChargeDisclaimer') }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { buildReservationLifecycle } from '../../utils/itemReservationStatusCore.js';

const props = defineProps({
  reservation: { type: Object, required: true },
  /** Audit trail when available — used to resolve Confirmed for terminal reservations. */
  audits: { type: Array, default: null },
});

const { t } = useI18n();

const lifecycle = computed(() =>
  buildReservationLifecycle(props.reservation, t, { audits: props.audits }),
);

const stageClass = (state) => {
  switch (state) {
    case 'complete':
      return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    case 'current':
      return 'bg-amber-50 text-amber-900 ring-amber-300';
    case 'terminal':
      return 'bg-rose-50 text-rose-800 ring-rose-200';
    case 'skipped':
      return 'bg-ink-50 text-ink-400 ring-ink-100 line-through';
    case 'unknown':
      return 'bg-white text-ink-500 ring-ink-200 border-dashed';
    default:
      return 'bg-white text-ink-500 ring-ink-200';
  }
};

const stageGlyph = (state) => {
  if (state === 'complete') return '✓';
  if (state === 'current') return '•';
  if (state === 'terminal') return '×';
  if (state === 'unknown') return '?';
  if (state === 'skipped') return '–';
  return '○';
};
</script>
