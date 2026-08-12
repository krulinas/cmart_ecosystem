<template>
  <div
    ref="rootEl"
    class="relative w-full sm:w-auto"
    data-testid="manage-parking-layout-menu"
    @keydown.escape.stop="closeMenu"
  >
    <button
      ref="triggerEl"
      type="button"
      class="ml-btn-primary text-sm w-full sm:w-auto"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="menu"
      :aria-controls="menuId"
      :disabled="disabled"
      data-testid="manage-parking-layout-trigger"
      @click="toggleMenu"
    >
      {{ copy.manageParkingLayout }}
    </button>

    <div
      v-if="open"
      :id="menuId"
      class="absolute right-0 z-40 mt-1 w-full min-w-[18rem] max-w-[22rem] rounded-xl border border-ink-200 bg-white p-1.5 shadow-lg sm:w-80"
      role="menu"
      :aria-label="copy.manageParkingLayout"
      data-testid="manage-parking-layout-dropdown"
    >
      <button
        type="button"
        class="manage-menu-item"
        role="menuitem"
        data-testid="manage-menu-choose-booking-sites"
        @click="chooseBookingSites"
      >
        <span class="flex items-start justify-between gap-2">
          <span class="font-bold text-ink-900">{{ copy.startSelectOpenSites }}</span>
          <span
            v-if="recommendBookingSites"
            class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-amber-900"
          >
            {{ copy.recommendedBadge }}
          </span>
        </span>
        <span class="mt-0.5 block text-xs font-medium text-ink-500">
          {{ copy.chooseBookingSitesMenuHelp }}
        </span>
      </button>

      <button
        type="button"
        class="manage-menu-item"
        role="menuitem"
        data-testid="manage-menu-edit-layout-structure"
        @click="editLayoutStructure"
      >
        <span class="flex items-start justify-between gap-2">
          <span class="font-bold text-ink-900">{{ copy.editLayoutStructure }}</span>
          <span
            class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-slate-700"
          >
            {{ copy.advancedBadge }}
          </span>
        </span>
        <span class="mt-0.5 block text-xs font-medium text-ink-500">
          {{ copy.editLayoutStructureHelp }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  recommendBookingSites: { type: Boolean, default: false },
});

const emit = defineEmits(['choose-booking-sites', 'edit-layout-structure']);

const copy = LAYOUT_COPY;
const open = ref(false);
const rootEl = ref(null);
const triggerEl = ref(null);
const menuId = `manage-parking-layout-menu-${Math.random().toString(36).slice(2, 8)}`;

function toggleMenu() {
  if (props.disabled) return;
  open.value = !open.value;
  if (open.value) {
    document.dispatchEvent(new CustomEvent('cmart:layout-close-site-popover'));
    document.dispatchEvent(new CustomEvent('cmart:layout-close-row-menus'));
  }
}

function closeMenu() {
  open.value = false;
}

function chooseBookingSites() {
  closeMenu();
  emit('choose-booking-sites');
}

function editLayoutStructure() {
  closeMenu();
  emit('edit-layout-structure');
}

function onDocumentClick(event) {
  if (!open.value) return;
  if (rootEl.value && !rootEl.value.contains(event.target)) {
    closeMenu();
  }
}

function onDocumentKeydown(event) {
  if (!open.value) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    closeMenu();
    nextTick(() => triggerEl.value?.focus?.());
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown, true);
});
</script>

<style scoped>
.manage-menu-item {
  display: block;
  width: 100%;
  border-radius: 0.65rem;
  border: 0;
  background: transparent;
  padding: 0.7rem 0.75rem;
  text-align: left;
  cursor: pointer;
}

.manage-menu-item:hover,
.manage-menu-item:focus-visible {
  background: #f0f9ff;
  outline: none;
}

.manage-menu-item:focus-visible {
  box-shadow: inset 0 0 0 2px #38bdf8;
}
</style>
