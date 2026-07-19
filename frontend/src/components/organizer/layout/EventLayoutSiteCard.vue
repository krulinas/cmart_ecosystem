<template>
  <article
    class="rounded-xl border p-3 transition"
    :class="tileClasses"
    :data-testid="`layout-site-card-${site.label}`"
    :aria-label="ariaLabel"
  >
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <div class="text-sm font-extrabold text-ink-900">{{ site.label }}</div>
        <div class="truncate text-[11px] text-ink-500">
          {{ spaceLabel }}
        </div>
      </div>
      <span
        v-if="site.locks?.structure_locked || site.locks?.delete_locked || site.locks?.disable_locked"
        class="inline-flex items-center gap-1 rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-bold text-ink-700"
        :title="lockTitle"
      >
        <span aria-hidden="true">🔒</span>
        {{ copy.locked }}
      </span>
    </div>

    <div class="mt-2 flex flex-wrap gap-1">
      <span class="rounded-md bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-ink-200">
        {{ statusLabel }}
      </span>
      <span class="rounded-md bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-ink-200">
        {{ occupancyLabel }}
      </span>
    </div>

    <div class="mt-3 flex flex-wrap gap-1">
      <button
        type="button"
        class="ml-btn-ghost px-2 py-1 text-[11px]"
        :aria-label="`${copy.editSite} ${site.label}`"
        @click="$emit('edit', site)"
      >
        Edit
      </button>
      <button
        type="button"
        class="ml-btn-ghost px-2 py-1 text-[11px]"
        :disabled="site.locks?.structure_locked"
        :title="site.locks?.structure_locked ? copy.structureLockedHint : copy.moveSite"
        :aria-label="`${copy.moveSite} ${site.label}`"
        @click="$emit('move', site)"
      >
        Move
      </button>
      <button
        type="button"
        class="ml-btn-ghost px-2 py-1 text-[11px]"
        :disabled="disableBlocked"
        :title="disableBlocked ? copy.disableLockedHint : statusToggleLabel"
        :aria-label="`${statusToggleLabel} ${site.label}`"
        @click="$emit('toggle-status', site)"
      >
        {{ statusToggleShort }}
      </button>
      <button
        type="button"
        class="ml-btn-ghost px-2 py-1 text-[11px] text-rose-700"
        :disabled="site.locks?.delete_locked"
        :title="site.locks?.delete_locked ? copy.structureLockedHint : copy.deleteSite"
        :aria-label="`${copy.deleteSite} ${site.label}`"
        @click="$emit('delete', site)"
      >
        {{ copy.delete }}
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import {
  LAYOUT_COPY,
  OCCUPANCY_LABELS,
  SITE_STATUS_LABELS,
} from '../../../utils/organizerEventLayoutMessages';
import { siteStateKey } from '../../../utils/organizerEventLayoutHelpers';

const props = defineProps({
  site: { type: Object, required: true },
});

defineEmits(['edit', 'move', 'toggle-status', 'delete']);

const copy = LAYOUT_COPY;

const state = computed(() => siteStateKey(props.site));
const spaceLabel = computed(() => props.site.space?.space_size || `Space #${props.site.space?.id || '—'}`);
const statusLabel = computed(() => SITE_STATUS_LABELS[props.site.operational_status] || props.site.operational_status);
const occupancyLabel = computed(() => OCCUPANCY_LABELS[props.site.occupancy] || props.site.occupancy || copy.available);
const disableBlocked = computed(() => {
  if (props.site.operational_status === 'active') {
    return Boolean(props.site.locks?.disable_locked);
  }
  return false;
});
const statusToggleLabel = computed(() => (
  props.site.operational_status === 'active' ? copy.disableSite : copy.enableSite
));
const statusToggleShort = computed(() => (
  props.site.operational_status === 'active' ? copy.disableSite : copy.enableSite
));

const tileClasses = computed(() => {
  switch (state.value) {
    case 'confirmed':
      return 'border-emerald-300 bg-emerald-50';
    case 'reserved':
      return 'border-amber-300 bg-amber-50';
    case 'released_history':
      return 'border-sky-200 bg-sky-50';
    case 'disabled':
      return 'border-ink-200 bg-ink-50 opacity-80';
    case 'unavailable':
      return 'border-rose-200 bg-rose-50';
    case 'structurally_locked':
      return 'border-violet-300 bg-violet-50';
    default:
      return 'border-brand-200 bg-brand-50/40';
  }
});

const lockTitle = computed(() => {
  const parts = [];
  if (props.site.locks?.structure_locked) parts.push(copy.structureLockedHint);
  if (props.site.locks?.disable_locked) parts.push(copy.disableLockedHint);
  if (props.site.locks?.delete_locked) parts.push(copy.deleteLocked);
  return parts.join(' · ') || copy.locked;
});

const ariaLabel = computed(() => (
  `${props.site.label}, ${statusLabel.value}, ${occupancyLabel.value}`
));
</script>
