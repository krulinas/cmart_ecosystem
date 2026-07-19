<template>
  <section
    class="ml-card space-y-4"
    data-testid="organizer-focused-site-controls"
  >
    <div>
      <h3 class="text-base font-extrabold text-ink-900">{{ copy.focusedSiteTitle }}</h3>
      <p v-if="!site" class="mt-1 text-sm text-ink-500">{{ copy.noSiteSelected }}</p>
    </div>

    <template v-if="site">
      <div class="rounded-xl border border-ink-200 bg-ink-50/60 px-4 py-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-lg font-extrabold text-ink-900">{{ site.label }}</p>
            <p class="text-sm text-brand-700 font-semibold">
              {{ row?.label }} · {{ row?.category?.label || copy.noCategory }}
            </p>
            <p class="mt-1 text-xs text-ink-500">
              {{ site.space?.space_size || copy.noSpace }}
              · {{ occupancyLabel }}
              · {{ statusLabel }}
            </p>
          </div>
          <button type="button" class="ml-btn-ghost text-sm" @click="$emit('edit-site', site)">
            {{ copy.editSite }}
          </button>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="ml-btn-ghost text-sm"
          :disabled="mutating || site.operational_status === 'active'"
          data-testid="focused-site-set-active"
          @click="$emit('set-status', site, 'active')"
        >
          {{ copy.setActive }}
        </button>
        <button
          type="button"
          class="ml-btn-ghost text-sm"
          :disabled="mutating || site.operational_status === 'unavailable' || disableLocked"
          data-testid="focused-site-set-unavailable"
          :title="disableLocked ? copy.disableLockedHint : undefined"
          @click="$emit('set-status', site, 'unavailable')"
        >
          {{ copy.setUnavailable }}
        </button>
        <button
          type="button"
          class="ml-btn-ghost text-sm"
          :disabled="mutating || site.operational_status === 'disabled' || disableLocked"
          data-testid="focused-site-set-disabled"
          :title="disableLocked ? copy.disableLockedHint : undefined"
          @click="$emit('set-status', site, 'disabled')"
        >
          {{ copy.setDisabled }}
        </button>
        <button
          type="button"
          class="ml-btn-ghost text-sm text-rose-700"
          :disabled="mutating || site.locks?.delete_locked"
          data-testid="focused-site-delete"
          @click="$emit('delete-site', site)"
        >
          {{ site.locks?.delete_locked ? copy.deleteLocked : copy.deleteSite }}
        </button>
      </div>

      <p v-if="disableLocked" class="text-xs text-amber-800">
        {{ copy.disableLockedHint }}
      </p>
      <p v-if="site.locks?.structure_locked" class="text-xs text-violet-800">
        {{ copy.structureLockedHint }}
      </p>
    </template>

    <div v-if="row" class="border-t border-ink-100 pt-4 space-y-3">
      <h4 class="text-sm font-extrabold text-ink-900">{{ copy.focusedRowTitle }}</h4>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="ml-btn-ghost text-sm" @click="$emit('edit-row', row)">
          {{ copy.editRow }}
        </button>
        <button type="button" class="ml-btn-ghost text-sm" @click="$emit('add-site', row)">
          {{ copy.addSite }}
        </button>
        <button type="button" class="ml-btn-ghost text-sm" @click="$emit('generate', row)">
          {{ copy.generateSites }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import {
  LAYOUT_COPY,
  OCCUPANCY_LABELS,
  SITE_STATUS_LABELS,
} from '../../../utils/organizerEventLayoutMessages';

const props = defineProps({
  site: { type: Object, default: null },
  row: { type: Object, default: null },
  mutating: { type: Boolean, default: false },
});

defineEmits(['edit-site', 'set-status', 'delete-site', 'edit-row', 'add-site', 'generate']);

const copy = LAYOUT_COPY;
const disableLocked = computed(() => Boolean(props.site?.locks?.disable_locked));
const occupancyLabel = computed(
  () => OCCUPANCY_LABELS[props.site?.occupancy] || props.site?.occupancy || copy.available,
);
const statusLabel = computed(
  () => SITE_STATUS_LABELS[props.site?.operational_status] || props.site?.operational_status,
);
</script>
