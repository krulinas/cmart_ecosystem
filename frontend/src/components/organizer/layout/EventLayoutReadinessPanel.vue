<template>
  <section
    class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm sm:p-5"
    data-testid="event-layout-readiness-panel"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h3 class="text-base font-extrabold text-ink-900">Status Kesediaan</h3>
        <p class="mt-1 text-xs text-ink-500">Operasi tempahan dan paparan awam dinilai berasingan.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1"
          :class="operationalReady
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
            : 'bg-amber-50 text-amber-900 ring-amber-200'"
          data-testid="operational-readiness-badge"
        >
          <span aria-hidden="true">{{ operationalReady ? '✓' : '!' }}</span>
          {{ operationalReady ? copy.operationalReady : copy.operationalNotReady }}
        </span>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1"
          :class="publicReady
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
            : 'bg-slate-50 text-slate-700 ring-slate-200'"
          data-testid="public-readiness-badge"
        >
          <span aria-hidden="true">{{ publicReady ? '✓' : '○' }}</span>
          {{ publicReady ? copy.publicReady : copy.publicNotReady }}
        </span>
      </div>
    </div>

    <ul
      v-if="blockers.length"
      class="mt-4 space-y-2"
      data-testid="readiness-blocker-list"
    >
      <li
        v-for="(blocker, index) in blockers"
        :key="`${blocker.code}-${index}`"
        class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
      >
        <div class="font-semibold">{{ messageFor(blocker.code) }}</div>
        <div v-if="blocker.code" class="mt-0.5 text-[11px] font-mono text-amber-800/70">
          {{ blocker.code }}
        </div>
      </li>
    </ul>
    <p v-else class="mt-4 text-sm text-emerald-800">
      Tiada halangan kesediaan dilapor buat masa ini.
    </p>
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
const messageFor = (code) => readinessMessage(code);
</script>
