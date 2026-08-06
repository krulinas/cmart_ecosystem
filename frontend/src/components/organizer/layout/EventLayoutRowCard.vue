<template>
  <section
    class="ml-card space-y-4"
    :data-testid="`layout-row-card-${row.id}`"
    :class="row.archived_at ? 'border-ink-200 bg-ink-50/60' : ''"
  >
    <header class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-lg font-extrabold text-ink-900">{{ row.label }}</h3>
          <span
            v-if="row.outside_venue_template"
            class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-900"
            :title="copy.outsideVenueTemplateHelp"
          >
            {{ copy.outsideVenueTemplateBadge }}
          </span>
          <span
            v-if="row.locks?.rename_locked || row.locks?.category_change_locked || row.locks?.delete_locked || row.locks?.archive_locked"
            class="inline-flex items-center gap-1 rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-bold text-ink-700"
            :title="lockSummary"
          >
            <span aria-hidden="true">🔒</span>
            {{ copy.locked }}
          </span>
        </div>
        <p class="mt-1 text-sm font-semibold text-brand-800">
          {{ row.category?.label || copy.noCategory }}
        </p>
        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
          <span class="rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-ink-200">
            {{ row.is_active && !row.archived_at ? 'Active' : 'Inactive' }}
          </span>
          <span class="rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-ink-200">
            {{ row.is_public ? 'Awam' : 'Persendirian' }}
          </span>
          <span v-if="row.archived_at" class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-900">
            Archived
          </span>
          <span class="rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-ink-200">
            {{ sites.length }} sites
          </span>
          <span class="rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-ink-200">
            {{ copy.available }} {{ occupancy.available }} · {{ copy.reserved }} {{ occupancy.reserved }} · {{ copy.confirmed }} {{ occupancy.confirmed }}
          </span>
        </div>
        <p v-if="row.description" class="mt-2 text-sm text-ink-600">{{ row.description }}</p>
        <p v-if="row.locks?.rename_locked" class="mt-2 text-xs text-amber-800">{{ copy.renameLockedHint }}</p>
        <p v-if="row.locks?.category_change_locked" class="mt-1 text-xs text-amber-800">{{ copy.categoryLockedHint }}</p>
      </div>

      <div class="flex flex-wrap gap-2 shrink-0">
        <button type="button" class="ml-btn-ghost text-xs" :disabled="!canMoveUp" :aria-label="`${copy.moveUp} ${row.label}`" @click="$emit('move-up', row)">
          {{ copy.moveUp }}
        </button>
        <button type="button" class="ml-btn-ghost text-xs" :disabled="!canMoveDown" :aria-label="`${copy.moveDown} ${row.label}`" @click="$emit('move-down', row)">
          {{ copy.moveDown }}
        </button>
        <button type="button" class="ml-btn-ghost text-xs" @click="$emit('edit', row)">{{ copy.editRow }}</button>
        <button type="button" class="ml-btn-ghost text-xs" @click="$emit('add-site', row)">{{ copy.addSite }}</button>
        <button type="button" class="ml-btn-primary text-xs" @click="$emit('generate', row)">{{ copy.generateSites }}</button>
        <button
          v-if="!row.archived_at"
          type="button"
          class="ml-btn-ghost text-xs"
          :disabled="row.locks?.archive_locked"
          :title="row.locks?.archive_locked ? copy.archiveBlockedHint : copy.archiveRow"
          @click="$emit('archive', row)"
        >
          {{ copy.archiveRow }}
        </button>
        <button
          v-else
          type="button"
          class="ml-btn-ghost text-xs"
          @click="$emit('unarchive', row)"
        >
          {{ copy.unarchiveRow }}
        </button>
        <button
          type="button"
          class="ml-btn-ghost text-xs text-rose-700"
          :disabled="row.locks?.delete_locked || sites.length > 0"
          :title="(row.locks?.delete_locked || sites.length > 0) ? copy.rowStillHasSites : copy.deleteRow"
          @click="$emit('delete', row)"
        >
          {{ copy.deleteRow }}
        </button>
      </div>
    </header>

    <div>
      <div class="mb-2 flex items-center justify-between gap-2">
        <h4 class="text-xs font-bold uppercase tracking-wider text-ink-500">{{ copy.siteGrid }}</h4>
        <button
          type="button"
          class="ml-btn-ghost text-[11px]"
          :disabled="sites.length < 2"
          @click="$emit('reorder-sites', row)"
        >
          {{ copy.reorderSites }}
        </button>
      </div>
      <div
        v-if="sites.length"
        class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5"
        data-testid="layout-site-grid"
      >
        <EventLayoutSiteCard
          v-for="site in sites"
          :key="site.id"
          :site="site"
          @edit="$emit('edit-site', $event)"
          @move="$emit('move-site', $event)"
          @toggle-status="$emit('toggle-site-status', $event)"
          @delete="$emit('delete-site', $event)"
        />
      </div>
      <p v-else class="rounded-xl border border-dashed border-ink-200 bg-ink-50 px-3 py-4 text-sm text-ink-500">
        {{ copy.noSitesInRow }}
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import EventLayoutSiteCard from './EventLayoutSiteCard.vue';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';
import { occupancySummaryForRow, sortSitesByDisplayOrder } from '../../../utils/organizerEventLayoutHelpers';

const props = defineProps({
  row: { type: Object, required: true },
  canMoveUp: { type: Boolean, default: false },
  canMoveDown: { type: Boolean, default: false },
});

defineEmits([
  'edit',
  'delete',
  'archive',
  'unarchive',
  'move-up',
  'move-down',
  'add-site',
  'generate',
  'reorder-sites',
  'edit-site',
  'move-site',
  'toggle-site-status',
  'delete-site',
]);

const copy = LAYOUT_COPY;
const sites = computed(() => sortSitesByDisplayOrder(props.row.sites || []));
const occupancy = computed(() => occupancySummaryForRow(sites.value));
const lockSummary = computed(() => {
  const parts = [];
  if (props.row.locks?.rename_locked) parts.push(copy.renameLocked);
  if (props.row.locks?.category_change_locked) parts.push(copy.categoryLocked);
  if (props.row.locks?.delete_locked) parts.push(copy.deleteLocked);
  if (props.row.locks?.archive_locked) parts.push(copy.archiveLocked);
  return parts.join(' · ');
});
</script>
