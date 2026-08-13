<template>
  <section
    class="vpl"
    :class="[
      `vpl--${mode}`,
      { 'vpl--manage': manageMode },
      { 'vpl--selection': selectionMode },
    ]"
    data-testid="visual-parking-layout"
    :data-mode="mode"
    :data-manage-mode="manageMode ? 'true' : 'false'"
    :data-selection-mode="selectionMode ? 'true' : 'false'"
    :aria-labelledby="headingId"
  >
    <div v-if="showTitle || $slots.headerActions" class="vpl__header">
      <div class="vpl__header-main">
        <h3 v-if="showTitle" :id="headingId" class="vpl__title">{{ copy.title }}</h3>
        <p
          v-if="manageMode"
          class="vpl__manage-badge"
          data-testid="visual-parking-manage-badge"
        >
          {{ manageBadgeLabel }}
        </p>
        <p
          v-if="selectionMode"
          class="vpl__selection-badge"
          data-testid="visual-parking-selection-badge"
        >
          {{ selectionBadgeLabel }}
        </p>
      </div>
      <div v-if="$slots.headerActions" class="vpl__header-actions">
        <slot name="headerActions" />
      </div>
    </div>

    <div
      v-if="showLegend"
      class="vpl__legend"
      data-testid="visual-parking-legend"
      :aria-label="copy.legendAria"
    >
      <span
        v-for="item in legendItems"
        :key="item.key"
        class="vpl__legend-item"
      >
        <span class="vpl__swatch" :class="item.swatchClass" aria-hidden="true"></span>
        {{ item.label }}
      </span>
    </div>

    <div
      v-if="showCounts && statusCounts.total > 0"
      class="vpl__counts"
      data-testid="visual-parking-counts"
    >
      <span>{{ statusCounts.total }} total</span>
      <span v-if="mode !== 'public'">· {{ statusCounts.available }} {{ copy.available }}</span>
      <span v-if="mode === 'vendor' && statusCounts.selected">· {{ statusCounts.selected }} {{ copy.selected }}</span>
      <span v-if="statusCounts.reserved">· {{ statusCounts.reserved }} {{ copy.reserved }}</span>
      <span v-if="statusCounts.confirmed">· {{ statusCounts.confirmed }} {{ copy.confirmed }}</span>
      <span v-if="statusCounts.unavailable">· {{ statusCounts.unavailable }} {{ copy.unavailable }}</span>
      <span v-if="statusCounts.disabled">· {{ statusCounts.disabled }} {{ copy.disabled }}</span>
    </div>

    <div class="vpl__canvas" data-testid="visual-parking-canvas">
      <div
        v-if="showOrientation"
        class="vpl__orientation vpl__orientation--exit"
        data-testid="visual-parking-exit"
      >
        {{ copy.exit }}
      </div>

      <div
        v-for="(segment, index) in segments"
        :key="segment.type === 'aisle' ? `aisle-${index}` : `row-${segment.row.id}`"
        class="vpl__segment"
      >
        <div
          v-if="segment.type === 'aisle'"
          class="vpl__aisle"
          data-testid="visual-parking-aisle"
          role="separator"
          :aria-label="copy.aisle"
        >
          <span class="vpl__aisle-label">{{ copy.aisle }}</span>
        </div>

        <article
          v-else
          class="vpl__row"
          :data-testid="rowTestId(segment.row)"
          :data-row-id="segment.row.id"
          :data-row-label="segment.row.label"
        >
          <div class="vpl__row-meta">
            <div>
              <h4 class="vpl__row-label">{{ segment.row.label }}</h4>
              <p v-if="segment.row.categoryLabel" class="vpl__row-category">
                {{ segment.row.categoryLabel }}
              </p>
              <p v-if="segment.row.description" class="vpl__row-description">
                {{ segment.row.description }}
              </p>
            </div>
            <div class="vpl__row-meta-aside">
              <slot
                name="rowCount"
                :row="segment.row"
                :row-index="rowIndexForSegment(index)"
                :source-row="segment.row.raw || segment.row"
              >
                <p class="vpl__row-count">
                  {{ copy.sitesCount(segment.row.siteCount) }}
                </p>
              </slot>
              <slot
                v-if="manageMode"
                name="rowActions"
                :row="segment.row"
                :row-index="rowIndexForSegment(index)"
                :source-row="segment.row.raw || segment.row"
              />
              <slot
                v-if="selectionMode"
                name="selectionRowActions"
                :row="segment.row"
                :row-index="rowIndexForSegment(index)"
                :source-row="segment.row.raw || segment.row"
              />
            </div>
          </div>

          <div
            class="vpl__site-grid"
            :style="gridStyle(segment.row)"
            role="list"
            :aria-label="`${copy.rowPrefix} ${segment.row.label}`"
          >
            <component
              :is="tileTag()"
              v-for="site in segment.row.sites"
              :key="site.id"
              class="vpl__tile"
              :class="[
                statusTileClass(site.status),
                {
                  'vpl__tile--focused': site.focused && !selectionMode,
                  'vpl__tile--interactive': isClickable(site),
                  'vpl__tile--manage': manageMode && mode === 'organizer',
                  'vpl__tile--selection-selected': selectionMode && site.selected,
                  'vpl__tile--selection-muted': selectionMode && !site.selected,
                  'vpl__tile--selection-locked': selectionMode && site.selectionLocked,
                },
              ]"
              role="listitem"
              :type="tileTag() === 'button' ? 'button' : undefined"
              :disabled="tileTag() === 'button' && !isClickable(site) ? true : undefined"
              :aria-pressed="selectionMode || mode === 'vendor' ? Boolean(site.selected) : undefined"
              :aria-expanded="manageMode && mode === 'organizer' ? (site.focused ? 'true' : 'false') : undefined"
              :aria-haspopup="manageMode && mode === 'organizer' ? 'dialog' : undefined"
              :aria-disabled="!isClickable(site) ? 'true' : undefined"
              :aria-label="selectionAriaLabel(site, segment.row)"
              :title="selectionMode && site.selectionLocked ? selectionLockedHint : undefined"
              :data-testid="tileTestId(site)"
              :data-site-id="site.id"
              :data-status="site.status"
              :data-selection-locked="selectionMode && site.selectionLocked ? 'true' : undefined"
              @click="onTileActivate(site, segment.row, $event)"
            >
              <span
                v-if="selectionMode && site.selected"
                class="vpl__tile-check"
                aria-hidden="true"
              >✓</span>
              <span class="vpl__tile-label">{{ site.label }}</span>
              <span class="vpl__tile-status" :class="statusTextClass(site.status)">
                {{ selectionMode && site.selected
                  ? (site.selectionLocked ? 'Selected · Locked' : 'Selected')
                  : statusLabel(mode, site.status) }}
              </span>
              <span v-if="site.price != null && mode === 'vendor'" class="vpl__tile-price">
                RM {{ site.price }}
              </span>
              <span
                v-if="mode === 'organizer' && site.locks?.disable_locked"
                class="vpl__tile-lock"
                aria-hidden="true"
              >
                🔒
              </span>
            </component>
          </div>
        </article>
      </div>

      <div
        v-if="showOrientation"
        class="vpl__orientation vpl__orientation--entrance"
        data-testid="visual-parking-entrance"
      >
        {{ copy.entrance }}
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import {
  buildRenderSegments,
  countSitesByStatus,
  isStandardParkingLayout,
  legendItemsForMode,
  siteAriaLabel,
  statusLabel,
  statusTextClass,
  statusTileClass,
} from '../../utils/visualParkingLayout';
import { visualParkingCopy } from '../../utils/visualParkingLayoutCopy';

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ['organizer', 'vendor', 'public'].includes(value),
  },
  rows: {
    type: Array,
    default: () => [],
  },
  showLegend: {
    type: Boolean,
    default: true,
  },
  showCounts: {
    type: Boolean,
    default: false,
  },
  showTitle: {
    type: Boolean,
    default: false,
  },
  forceOrientation: {
    type: Boolean,
    default: false,
  },
  manageMode: {
    type: Boolean,
    default: false,
  },
  manageBadgeLabel: {
    type: String,
    default: 'Management mode',
  },
  selectionMode: {
    type: Boolean,
    default: false,
  },
  selectionBadgeLabel: {
    type: String,
    default: 'SELECTION MODE',
  },
  selectionLockedHint: {
    type: String,
    default: 'This site is protected by an existing booking or reservation.',
  },
  siteActivationEnabled: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['activate-site']);

const copy = computed(() => visualParkingCopy(props.mode));
const headingId = `visual-parking-heading-${Math.random().toString(36).slice(2, 8)}`;
const segments = computed(() => buildRenderSegments(props.rows));
const legendItems = computed(() => legendItemsForMode(props.mode));
const statusCounts = computed(() => countSitesByStatus(props.rows));
const showOrientation = computed(
  () => props.forceOrientation || isStandardParkingLayout(props.rows),
);

function gridStyle(row) {
  const columns = Math.max(1, Number(row.siteCount || row.sites?.length || 1));
  return {
    gridTemplateColumns: `repeat(${columns}, minmax(3.25rem, 1fr))`,
  };
}

function tileTag() {
  if (props.mode === 'public') return 'div';
  return 'button';
}

function rowTestId(row) {
  if (props.mode === 'vendor') return `event-site-row-${row.id}`;
  if (props.mode === 'public') return 'public-layout-row';
  return `visual-parking-row-${row.label}`;
}

function tileTestId(site) {
  if (props.mode === 'vendor') return `event-site-tile-${site.label}`;
  if (props.mode === 'public') return 'public-layout-site';
  return `visual-parking-tile-${site.label}`;
}

function rowIndexForSegment(segmentIndex) {
  let count = 0;
  for (let i = 0; i < segmentIndex; i += 1) {
    if (segments.value[i]?.type === 'row') count += 1;
  }
  return count;
}

function isClickable(site) {
  if (props.mode === 'public') return false;
  if (props.mode === 'organizer') {
    if (!props.siteActivationEnabled) return false;
    if (props.selectionMode && site.selectionLocked) return false;
    return true;
  }
  return Boolean(site.interactive);
}

function selectionAriaLabel(site, row) {
  const base = siteAriaLabel(props.mode, site, row);
  if (!props.selectionMode) return base;
  if (site.selectionLocked) {
    return `${base}. Selected and locked. ${props.selectionLockedHint}`;
  }
  return `${base}. ${site.selected ? 'Selected' : 'Not selected'}. Toggle booking site selection.`;
}

function onTileActivate(site, row, event) {
  if (!isClickable(site)) return;
  const anchorEl = event?.currentTarget instanceof Element ? event.currentTarget : null;
  emit('activate-site', {
    site,
    row,
    mode: props.mode,
    anchorEl,
  });
}
</script>

<style scoped>
.vpl {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.vpl--manage {
  border-radius: 1rem;
  box-shadow: inset 0 0 0 2px rgba(14, 165, 233, 0.28);
  padding: 0.75rem;
  background: linear-gradient(180deg, rgba(240, 249, 255, 0.65), rgba(248, 250, 252, 0.2));
}

.vpl--selection {
  border-radius: 1rem;
  box-shadow: inset 0 0 0 2px rgba(2, 132, 199, 0.35);
  padding: 0.75rem;
  background: linear-gradient(180deg, rgba(224, 242, 254, 0.7), rgba(248, 250, 252, 0.25));
}

.vpl__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.vpl__header-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.vpl__title {
  font-size: 1rem;
  font-weight: 800;
  color: #0f172a;
}

.vpl__manage-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  border: 1px solid #7dd3fc;
  background: #e0f2fe;
  color: #0369a1;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 0.2rem 0.55rem;
}

.vpl__selection-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  border: 1px solid #38bdf8;
  background: #bae6fd;
  color: #0c4a6e;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 0.2rem 0.55rem;
}

.vpl__header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.vpl__legend,
.vpl__counts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
}

.vpl__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  border-radius: 9999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.25rem 0.625rem;
}

.vpl__swatch {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 0.125rem;
  border: 1px solid transparent;
}

.vpl-swatch--available { background: #34d399; border-color: #059669; }
.vpl-swatch--selected { background: #38bdf8; border-color: #0284c7; }
.vpl-swatch--reserved { background: #fbbf24; border-color: #d97706; }
.vpl-swatch--confirmed { background: #f87171; border-color: #dc2626; }
.vpl-swatch--unavailable { background: #cbd5e1; border-color: #64748b; }
.vpl-swatch--disabled { background: #94a3b8; border-color: #475569; }
.vpl-swatch--public { background: #7dd3fc; border-color: #0284c7; }

.vpl__canvas {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
}

.vpl__orientation {
  text-align: center;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #0f766e;
  border: 1px dashed #99f6e4;
  background: #f0fdfa;
  border-radius: 0.75rem;
  padding: 0.5rem 0.75rem;
}

.vpl__row {
  min-width: min(100%, 64rem);
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  background: #f8fafc;
  padding: 0.875rem;
}

.vpl__row-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.vpl__row-meta-aside {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.vpl__row-label {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
}

.vpl__row-category {
  margin-top: 0.125rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #0369a1;
}

.vpl__row-description {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #64748b;
}

.vpl__row-count {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
}

.vpl__site-grid {
  display: grid;
  gap: 0.375rem;
  min-width: max-content;
}

.vpl__tile {
  position: relative;
  display: flex;
  min-height: 4.25rem;
  min-width: 3.25rem;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  border: 1px solid #cbd5e1;
  background: #fff;
  padding: 0.35rem 0.25rem;
  text-align: center;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.vpl__tile--interactive {
  cursor: pointer;
}

.vpl__tile--interactive:hover {
  box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.25);
}

.vpl__tile--interactive:focus {
  outline: none;
}

.vpl__tile--interactive:focus-visible {
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.45);
}

.vpl__tile--focused {
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.55);
}

.vpl__tile--manage:hover {
  transform: translateY(-1px);
}

.vpl__tile-label {
  font-size: 0.8rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
}

.vpl__tile-status {
  margin-top: 0.2rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.vpl__tile-space,
.vpl__tile-price {
  margin-top: 0.15rem;
  font-size: 0.6rem;
  color: #64748b;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.vpl__tile-price {
  font-weight: 800;
  color: #0369a1;
}

.vpl__tile-lock {
  position: absolute;
  top: 0.15rem;
  right: 0.2rem;
  font-size: 0.65rem;
}

.vpl__tile-check {
  position: absolute;
  top: 0.15rem;
  left: 0.2rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 0.95rem;
  height: 0.95rem;
  border-radius: 9999px;
  background: #0284c7;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  line-height: 1;
}

.vpl__tile--selection-selected {
  border-color: #0284c7 !important;
  background: #bae6fd !important;
  box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.35);
  opacity: 1 !important;
}

.vpl__tile--selection-muted {
  opacity: 0.55;
  filter: saturate(0.65);
}

.vpl__tile--selection-locked {
  cursor: not-allowed;
}

.vpl-tile--available {
  border-color: #6ee7b7;
  background: #ecfdf5;
}

.vpl-tile--selected {
  border-color: #0ea5e9;
  background: #e0f2fe;
}

.vpl-tile--reserved {
  border-color: #fbbf24;
  background:
    repeating-linear-gradient(
      -45deg,
      #fffbeb,
      #fffbeb 6px,
      #fef3c7 6px,
      #fef3c7 12px
    );
}

.vpl-tile--confirmed {
  border-color: #f87171;
  background:
    repeating-linear-gradient(
      45deg,
      #fff1f2,
      #fff1f2 6px,
      #ffe4e6 6px,
      #ffe4e6 12px
    );
}

.vpl-tile--unavailable {
  border-color: #94a3b8;
  background: #f1f5f9;
  opacity: 0.92;
}

.vpl-tile--disabled {
  border-color: #64748b;
  background: #e2e8f0;
  opacity: 0.85;
}

.vpl-tile--public {
  border-color: #7dd3fc;
  background: #f0f9ff;
}

.vpl-status--available { color: #047857; }
.vpl-status--selected { color: #0369a1; }
.vpl-status--reserved { color: #b45309; }
.vpl-status--confirmed { color: #b91c1c; }
.vpl-status--unavailable { color: #475569; }
.vpl-status--disabled { color: #334155; }
.vpl-status--public { color: #0369a1; }

.vpl__aisle {
  min-width: min(100%, 64rem);
  border-radius: 0.75rem;
  border: 2px dashed #64748b;
  background:
    repeating-linear-gradient(
      90deg,
      #e2e8f0,
      #e2e8f0 18px,
      #cbd5e1 18px,
      #cbd5e1 36px
    );
  padding: 0.85rem 0.75rem;
  text-align: center;
}

.vpl__aisle-label {
  display: inline-block;
  border-radius: 9999px;
  background: #0f172a;
  color: #f8fafc;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 0.35rem 0.85rem;
}
</style>
