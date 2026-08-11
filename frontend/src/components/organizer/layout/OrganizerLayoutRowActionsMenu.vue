<template>
  <div
    ref="rootEl"
    class="relative"
    data-testid="layout-row-actions-menu"
    @keydown.escape="closeMenu"
  >
    <button
      type="button"
      class="ml-btn-ghost text-xs"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="menu"
      :aria-label="`${copy.rowMenuLabel} ${row.label}`"
      :disabled="mutating"
      data-testid="layout-row-actions-trigger"
      @click="toggleMenu"
    >
      {{ copy.rowMenuLabel }}
    </button>

    <div
      v-if="open"
      class="absolute right-0 z-30 mt-1 w-52 rounded-xl border border-ink-200 bg-white p-1 shadow-lg"
      role="menu"
      :aria-label="`${copy.rowMenuLabel} ${row.label}`"
      data-testid="layout-row-actions-dropdown"
    >
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="!canMoveUp || mutating"
        :title="!canMoveUp ? copy.moveUpDisabled : copy.moveUp"
        @click="emitAction('move-up')"
      >
        {{ copy.moveUp }}
      </button>
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="!canMoveDown || mutating"
        :title="!canMoveDown ? copy.moveDownDisabled : copy.moveDown"
        @click="emitAction('move-down')"
      >
        {{ copy.moveDown }}
      </button>
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating"
        @click="emitAction('edit')"
      >
        {{ copy.editRow }}
      </button>
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating"
        @click="emitAction('add-site')"
      >
        {{ copy.addSite }}
      </button>
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating || !canGenerate"
        :title="canGenerate ? copy.generateSites : copy.generateSitesComplete"
        @click="emitAction('generate')"
      >
        {{ copy.generateSites }}
      </button>
      <button
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating || sites.length < 2"
        :title="sites.length < 2 ? copy.reorderSitesDisabled : copy.reorderSites"
        @click="emitAction('reorder-sites')"
      >
        {{ copy.reorderSites }}
      </button>
      <button
        v-if="!row.archived_at"
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating || row.locks?.archive_locked"
        :title="row.locks?.archive_locked ? copy.archiveBlockedHint : copy.archiveRow"
        @click="emitAction('archive')"
      >
        {{ copy.archiveRow }}
      </button>
      <button
        v-else
        type="button"
        class="row-menu-item"
        role="menuitem"
        :disabled="mutating"
        @click="emitAction('unarchive')"
      >
        {{ copy.unarchiveRow }}
      </button>
      <button
        type="button"
        class="row-menu-item row-menu-item--danger"
        role="menuitem"
        :disabled="mutating || deleteDisabled"
        :title="deleteDisabled ? copy.rowStillHasSites : copy.deleteRow"
        @click="emitAction('delete')"
      >
        {{ copy.deleteRow }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';
import {
  canGenerateSitesForRow,
  sortSitesByDisplayOrder,
} from '../../../utils/organizerEventLayoutHelpers';

const props = defineProps({
  row: { type: Object, required: true },
  canMoveUp: { type: Boolean, default: false },
  canMoveDown: { type: Boolean, default: false },
  mutating: { type: Boolean, default: false },
});

const emit = defineEmits([
  'edit',
  'delete',
  'archive',
  'unarchive',
  'move-up',
  'move-down',
  'add-site',
  'generate',
  'reorder-sites',
]);

const copy = LAYOUT_COPY;
const open = ref(false);
const rootEl = ref(null);
const sites = computed(() => sortSitesByDisplayOrder(props.row.sites || []));
const canGenerate = computed(() => canGenerateSitesForRow(props.row));
const deleteDisabled = computed(
  () => Boolean(props.row.locks?.delete_locked) || sites.value.length > 0,
);

function toggleMenu() {
  open.value = !open.value;
  if (open.value) {
    document.dispatchEvent(new CustomEvent('cmart:layout-close-site-popover'));
  }
}

function closeMenu() {
  open.value = false;
}

function emitAction(name) {
  emit(name, props.row);
  closeMenu();
}

function onDocumentClick(event) {
  if (!open.value) return;
  if (rootEl.value && !rootEl.value.contains(event.target)) {
    closeMenu();
  }
}

function onCloseFromLayout() {
  closeMenu();
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('cmart:layout-close-row-menus', onCloseFromLayout);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('cmart:layout-close-row-menus', onCloseFromLayout);
});
</script>

<style scoped>
.row-menu-item {
  display: block;
  width: 100%;
  border-radius: 0.5rem;
  padding: 0.45rem 0.65rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  color: #0f172a;
  background: transparent;
}

.row-menu-item:hover:not(:disabled) {
  background: #f1f5f9;
}

.row-menu-item:focus-visible {
  outline: 2px solid rgba(14, 165, 233, 0.55);
  outline-offset: 1px;
}

.row-menu-item:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.row-menu-item--danger {
  color: #be123c;
}
</style>
