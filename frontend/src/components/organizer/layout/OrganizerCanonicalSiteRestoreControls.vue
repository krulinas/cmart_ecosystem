<template>
  <div
    ref="rootEl"
    class="canonical-restore"
    data-testid="canonical-site-restore-controls"
    @keydown.escape.stop="closeMenu"
  >
    <p
      class="canonical-restore__count"
      data-testid="canonical-site-count"
    >
      {{ countLabel }}
    </p>

    <template v-if="missingLabels.length === 1">
      <button
        type="button"
        class="ml-btn-ghost text-xs px-2 py-1"
        :disabled="disabled || restoringLabel != null"
        :aria-label="restoreLabelAria(missingLabels[0])"
        data-testid="restore-single-canonical-site"
        @click="restoreOne(missingLabels[0])"
      >
        {{ restoringLabel === missingLabels[0]
          ? copy.restoringSite(missingLabels[0])
          : copy.restoreSite(missingLabels[0]) }}
      </button>
    </template>

    <div v-else-if="missingLabels.length > 1" class="relative">
      <button
        type="button"
        class="ml-btn-ghost text-xs px-2 py-1"
        :aria-expanded="menuOpen ? 'true' : 'false'"
        aria-haspopup="menu"
        :disabled="disabled || restoringLabel != null"
        data-testid="restore-missing-sites-trigger"
        @click="toggleMenu"
      >
        {{ restoringLabel ? copy.restoringSite(restoringLabel) : copy.restoreMissingSites }}
      </button>

      <div
        v-if="menuOpen"
        class="canonical-restore__menu"
        role="menu"
        :aria-label="copy.restoreMissingSites"
        data-testid="restore-missing-sites-menu"
      >
        <button
          v-for="label in missingLabels"
          :key="label"
          type="button"
          class="canonical-restore__item"
          role="menuitem"
          :disabled="disabled || restoringLabel != null"
          :data-testid="`restore-canonical-site-${label}`"
          @click="restoreOne(label)"
        >
          {{ restoringLabel === label ? copy.restoringSite(label) : copy.restoreSite(label) }}
        </button>
        <button
          type="button"
          class="canonical-restore__item canonical-restore__item--all"
          role="menuitem"
          :disabled="disabled || restoringLabel != null"
          data-testid="restore-all-missing-canonical-sites"
          @click="restoreAll"
        >
          {{ restoringLabel === '__all__'
            ? copy.restoringAllMissingSites
            : copy.restoreAllMissingSites }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
  CMART_CARBOOT_SITES_PER_ROW,
  isAllowedPhysicalRowLabel,
  missingCanonicalSiteLabels,
} from '../../../config/cmartCarbootPhysicalLayout';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';

const props = defineProps({
  row: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  restoringLabel: { type: String, default: null },
});

const emit = defineEmits(['restore-label', 'restore-all']);

const copy = LAYOUT_COPY;
const rootEl = ref(null);
const menuOpen = ref(false);

const siteLabels = computed(() =>
  (props.row?.sites || []).map((site) => site.label).filter(Boolean),
);

const missingLabels = computed(() => {
  if (!isAllowedPhysicalRowLabel(props.row?.label)) return [];
  return missingCanonicalSiteLabels(props.row.label, siteLabels.value);
});

const presentCount = computed(() => (props.row?.sites || []).length);

const countLabel = computed(() => {
  if (!isAllowedPhysicalRowLabel(props.row?.label)) {
    return copy.sitesCountFallback((props.row?.sites || []).length);
  }
  return copy.physicalSitesOfTotal(presentCount.value, CMART_CARBOOT_SITES_PER_ROW);
});

function restoreLabelAria(label) {
  return copy.restoreSite(label);
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}

function closeMenu() {
  menuOpen.value = false;
}

function restoreOne(label) {
  closeMenu();
  emit('restore-label', label);
}

function restoreAll() {
  closeMenu();
  emit('restore-all');
}

function onDocumentClick(event) {
  if (!menuOpen.value) return;
  if (rootEl.value && !rootEl.value.contains(event.target)) {
    closeMenu();
  }
}

watch(
  () => props.restoringLabel,
  (value) => {
    if (value) closeMenu();
  },
);

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
});
</script>

<style scoped>
.canonical-restore {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.35rem;
}

.canonical-restore__count {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
}

.canonical-restore__menu {
  position: absolute;
  right: 0;
  z-index: 30;
  margin-top: 0.25rem;
  min-width: 11rem;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.25rem;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.canonical-restore__item {
  display: block;
  width: 100%;
  border: 0;
  border-radius: 0.5rem;
  background: transparent;
  padding: 0.45rem 0.6rem;
  text-align: left;
  font-size: 0.72rem;
  font-weight: 700;
  color: #0f172a;
  cursor: pointer;
}

.canonical-restore__item:hover:not(:disabled),
.canonical-restore__item:focus-visible {
  background: #f0f9ff;
  outline: none;
}

.canonical-restore__item:focus-visible {
  box-shadow: inset 0 0 0 2px #38bdf8;
}

.canonical-restore__item:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.canonical-restore__item--all {
  margin-top: 0.15rem;
  border-top: 1px solid #e2e8f0;
  border-radius: 0 0 0.5rem 0.5rem;
  color: #0369a1;
}
</style>
