<template>
  <Teleport to="body">
    <div
      v-if="open && site"
      ref="panelRef"
      class="site-popover"
      :class="`site-popover--${placement}`"
      :style="panelStyle"
      role="dialog"
      :aria-label="dialogLabel"
      data-testid="organizer-site-actions-popover"
      tabindex="-1"
      @keydown.escape.stop.prevent="onEscape"
    >
      <div class="site-popover__arrow" :style="arrowStyle" aria-hidden="true" />

      <header class="site-popover__header">
        <div class="min-w-0">
          <p class="site-popover__code">{{ site.label }}</p>
          <p class="site-popover__meta">
            <span>{{ statusLabel }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ occupancyLabel }}</span>
          </p>
        </div>
        <button
          type="button"
          class="site-popover__close"
          data-testid="site-popover-close"
          :aria-label="copy.closeSitePanel"
          @click="emitClose"
        >
          ×
        </button>
      </header>

      <div class="site-popover__actions" role="group" :aria-label="copy.focusedSiteTitle">
        <button
          type="button"
          class="site-popover__btn"
          :disabled="mutating"
          data-testid="focused-site-edit"
          @click="emit('edit-site', site)"
        >
          {{ copy.editSite }}
        </button>
        <button
          type="button"
          class="site-popover__btn"
          :disabled="mutating || site.locks?.structure_locked"
          data-testid="focused-site-move"
          :title="site.locks?.structure_locked ? copy.structureLockedHint : copy.moveSite"
          @click="emit('move-site', site)"
        >
          {{ copy.moveSite }}
        </button>
        <button
          type="button"
          class="site-popover__btn site-popover__btn--status"
          :disabled="mutating || site.operational_status === 'active'"
          data-testid="focused-site-set-active"
          @click="emit('set-status', site, 'active')"
        >
          {{ mutating && pendingStatus === 'active' ? copy.updatingStatus : copy.setActive }}
        </button>
        <button
          type="button"
          class="site-popover__btn site-popover__btn--status"
          :disabled="mutating || site.operational_status === 'unavailable' || disableLocked"
          data-testid="focused-site-set-unavailable"
          :title="disableLocked ? copy.disableLockedHint : undefined"
          @click="emit('set-status', site, 'unavailable')"
        >
          {{ mutating && pendingStatus === 'unavailable' ? copy.updatingStatus : copy.setUnavailable }}
        </button>
        <button
          type="button"
          class="site-popover__btn site-popover__btn--status"
          :disabled="mutating || site.operational_status === 'disabled' || disableLocked"
          data-testid="focused-site-set-disabled"
          :title="disableLocked ? copy.disableLockedHint : undefined"
          @click="emit('set-status', site, 'disabled')"
        >
          {{ mutating && pendingStatus === 'disabled' ? copy.updatingStatus : copy.setDisabled }}
        </button>
        <button
          type="button"
          class="site-popover__btn site-popover__btn--danger"
          :disabled="mutating || site.locks?.delete_locked"
          data-testid="focused-site-delete"
          :title="site.locks?.delete_locked ? copy.deleteLocked : copy.deleteSite"
          @click="emit('delete-site', site)"
        >
          {{ site.locks?.delete_locked ? copy.deleteLocked : copy.deleteSite }}
        </button>
      </div>

      <p v-if="disableLocked" class="site-popover__hint site-popover__hint--amber">
        {{ copy.disableLockedHint }}
      </p>
      <p v-if="site.locks?.structure_locked" class="site-popover__hint site-popover__hint--violet">
        {{ copy.structureLockedHint }}
      </p>
      <p v-if="site.locks?.delete_locked" class="site-popover__hint">
        Prefer {{ copy.setDisabled }} or {{ copy.setUnavailable }} for locked physical sites.
      </p>
    </div>
  </Teleport>
</template>

<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue';
import {
  LAYOUT_COPY,
  OCCUPANCY_LABELS,
  SITE_STATUS_LABELS,
} from '../../../utils/organizerEventLayoutMessages';

const POPOVER_WIDTH = 248;
const POPOVER_ESTIMATED_HEIGHT = 292;
const VIEWPORT_PAD = 10;
const GAP = 8;
const ARROW_SIZE = 8;

const props = defineProps({
  open: { type: Boolean, default: false },
  site: { type: Object, default: null },
  row: { type: Object, default: null },
  mutating: { type: Boolean, default: false },
  pendingStatus: { type: String, default: '' },
  /** DOMRect-like { top, left, bottom, right, width, height } */
  anchorRect: { type: Object, default: null },
});

const emit = defineEmits([
  'edit-site',
  'move-site',
  'set-status',
  'delete-site',
  'close',
]);

const copy = LAYOUT_COPY;
const panelRef = ref(null);
const placement = ref('bottom');
const coords = ref({ top: 0, left: 0 });
const arrowOffsetX = ref(POPOVER_WIDTH / 2);

const disableLocked = computed(() => Boolean(props.site?.locks?.disable_locked));
const occupancyLabel = computed(
  () => OCCUPANCY_LABELS[props.site?.occupancy] || props.site?.occupancy || copy.available,
);
const statusLabel = computed(
  () => SITE_STATUS_LABELS[props.site?.operational_status] || props.site?.operational_status,
);
const dialogLabel = computed(() => (
  props.site
    ? `${copy.focusedSiteTitle}: ${props.site.label}`
    : copy.focusedSiteTitle
));

const panelStyle = computed(() => ({
  top: `${coords.value.top}px`,
  left: `${coords.value.left}px`,
  width: `${POPOVER_WIDTH}px`,
}));

const arrowStyle = computed(() => ({
  left: `${arrowOffsetX.value}px`,
}));

function emitClose() {
  emit('close');
}

function onEscape() {
  emitClose();
}

function measureAndPosition() {
  if (!props.open || !props.anchorRect) return;

  const rect = props.anchorRect;
  const viewportW = window.innerWidth;
  const viewportH = window.innerHeight;
  const measuredHeight = panelRef.value?.offsetHeight || POPOVER_ESTIMATED_HEIGHT;

  const spaceBelow = viewportH - rect.bottom - VIEWPORT_PAD;
  const spaceAbove = rect.top - VIEWPORT_PAD;
  const preferBottom = spaceBelow >= measuredHeight + GAP || spaceBelow >= spaceAbove;
  placement.value = preferBottom ? 'bottom' : 'top';

  let left = rect.left + rect.width / 2 - POPOVER_WIDTH / 2;
  left = Math.min(left, viewportW - POPOVER_WIDTH - VIEWPORT_PAD);
  left = Math.max(VIEWPORT_PAD, left);

  let top;
  if (placement.value === 'bottom') {
    top = rect.bottom + GAP;
    if (top + measuredHeight > viewportH - VIEWPORT_PAD) {
      top = Math.max(VIEWPORT_PAD, viewportH - measuredHeight - VIEWPORT_PAD);
    }
  } else {
    top = rect.top - measuredHeight - GAP;
    if (top < VIEWPORT_PAD) {
      top = VIEWPORT_PAD;
    }
  }

  coords.value = { top, left };

  const anchorCenterX = rect.left + rect.width / 2;
  arrowOffsetX.value = Math.min(
    POPOVER_WIDTH - ARROW_SIZE * 2,
    Math.max(ARROW_SIZE * 2, anchorCenterX - left),
  );
}

async function reposition() {
  await nextTick();
  measureAndPosition();
  await nextTick();
  measureAndPosition();
}

function focusPanel() {
  nextTick(() => {
    const firstEnabled = panelRef.value?.querySelector('button:not([disabled])');
    if (firstEnabled instanceof HTMLElement) {
      firstEnabled.focus();
      return;
    }
    panelRef.value?.focus();
  });
}

function onDocumentPointerDown(event) {
  if (!props.open) return;
  const target = event.target;
  if (!(target instanceof Element)) return;
  if (panelRef.value?.contains(target)) return;
  // Site tiles and row menus manage their own open/close; ignore to avoid toggle races.
  if (target.closest('[data-site-id]')) return;
  if (target.closest('[data-testid="layout-row-actions-menu"]')) {
    emitClose();
    return;
  }
  emitClose();
}

function onDocumentKeydown(event) {
  if (!props.open) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    emitClose();
  }
}

function onWindowChange() {
  if (!props.open) return;
  reposition();
}

watch(
  () => [props.open, props.site?.id, props.anchorRect],
  async ([isOpen, siteId], previous) => {
    if (!isOpen) return;
    const wasOpen = previous?.[0];
    const prevSiteId = previous?.[1];
    await reposition();
    if (!wasOpen || Number(siteId) !== Number(prevSiteId)) {
      focusPanel();
    }
  },
  { flush: 'post' },
);

watch(
  () => [props.mutating, props.site?.operational_status, props.site?.occupancy],
  async () => {
    if (!props.open) return;
    await reposition();
  },
  { flush: 'post' },
);

watch(
  () => props.open,
  (isOpen, wasOpen) => {
    if (isOpen && !wasOpen) {
      document.addEventListener('pointerdown', onDocumentPointerDown, true);
      document.addEventListener('keydown', onDocumentKeydown, true);
      window.addEventListener('resize', onWindowChange);
      window.addEventListener('scroll', onWindowChange, true);
    } else if (!isOpen && wasOpen) {
      document.removeEventListener('pointerdown', onDocumentPointerDown, true);
      document.removeEventListener('keydown', onDocumentKeydown, true);
      window.removeEventListener('resize', onWindowChange);
      window.removeEventListener('scroll', onWindowChange, true);
    }
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
  document.removeEventListener('keydown', onDocumentKeydown, true);
  window.removeEventListener('resize', onWindowChange);
  window.removeEventListener('scroll', onWindowChange, true);
});
</script>

<style scoped>
.site-popover {
  position: fixed;
  z-index: 80;
  border-radius: 0.85rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  box-shadow:
    0 10px 28px rgba(15, 23, 42, 0.14),
    0 0 0 1px rgba(14, 165, 233, 0.08);
  padding: 0.65rem 0.7rem 0.7rem;
  outline: none;
}

.site-popover__arrow {
  position: absolute;
  width: 0.75rem;
  height: 0.75rem;
  background: #fff;
  border-left: 1px solid #e2e8f0;
  border-top: 1px solid #e2e8f0;
  transform: translateX(-50%) rotate(45deg);
}

.site-popover--bottom .site-popover__arrow {
  top: -0.4rem;
}

.site-popover--top .site-popover__arrow {
  bottom: -0.4rem;
  border-left: 0;
  border-top: 0;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
}

.site-popover__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.55rem;
  padding-bottom: 0.45rem;
  border-bottom: 1px solid #f1f5f9;
}

.site-popover__code {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.15;
}

.site-popover__meta {
  margin-top: 0.15rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: #0369a1;
}

.site-popover__space {
  margin-top: 0.15rem;
  font-size: 0.65rem;
  font-weight: 600;
  color: #64748b;
}

.site-popover__close {
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.4rem;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 1rem;
  line-height: 1;
  font-weight: 700;
}

.site-popover__close:hover {
  background: #e2e8f0;
}

.site-popover__close:focus-visible {
  outline: 2px solid rgba(14, 165, 233, 0.55);
  outline-offset: 1px;
}

.site-popover__actions {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.site-popover__btn {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.4rem 0.55rem;
  text-align: left;
  font-size: 0.72rem;
  font-weight: 700;
  color: #0f172a;
}

.site-popover__btn:hover:not(:disabled) {
  background: #f1f5f9;
}

.site-popover__btn:focus-visible {
  outline: 2px solid rgba(14, 165, 233, 0.55);
  outline-offset: 1px;
}

.site-popover__btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.site-popover__btn--status {
  border-color: #bae6fd;
  background: #f0f9ff;
  color: #0c4a6e;
}

.site-popover__btn--status:hover:not(:disabled) {
  background: #e0f2fe;
}

.site-popover__btn--danger {
  border-color: #fecdd3;
  background: #fff1f2;
  color: #be123c;
}

.site-popover__btn--danger:hover:not(:disabled) {
  background: #ffe4e6;
}

.site-popover__hint {
  margin-top: 0.45rem;
  font-size: 0.65rem;
  line-height: 1.35;
  color: #64748b;
}

.site-popover__hint--amber {
  color: #92400e;
}

.site-popover__hint--violet {
  color: #5b21b6;
}

@media (max-width: 420px) {
  .site-popover {
    max-width: calc(100vw - 1.25rem);
  }
}
</style>
