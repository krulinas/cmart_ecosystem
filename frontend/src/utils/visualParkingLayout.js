/**
 * Shared visual parking layout adapters and geometry helpers.
 * Presentation only — backend availability and booking rules remain authoritative.
 */
import {
  CMART_CARBOOT_ROW_LABELS,
  CMART_CARBOOT_SITES_PER_ROW,
} from '../config/cmartCarbootPhysicalLayout.js';
import { visualParkingCopy } from './visualParkingLayoutCopy.js';

export const STANDARD_ROW_LABELS = CMART_CARBOOT_ROW_LABELS;
export const STANDARD_SITES_PER_ROW = CMART_CARBOOT_SITES_PER_ROW;

export const VISUAL_STATUS = Object.freeze({
  available: 'available',
  selected: 'selected',
  reserved: 'reserved',
  confirmed: 'confirmed',
  unavailable: 'unavailable',
  disabled: 'disabled',
  public: 'public',
});

/**
 * Visual display state for organizer tiles.
 * Occupancy is shown ahead of structural locks so booked/confirmed sites remain visible.
 */
export function organizerVisualStatus(site = {}) {
  const status = String(site.operational_status || '').toLowerCase();
  if (status === 'disabled') return VISUAL_STATUS.disabled;
  if (status === 'unavailable') return VISUAL_STATUS.unavailable;

  const occupancy = String(site.occupancy || '').toLowerCase();
  if (occupancy === 'confirmed') return VISUAL_STATUS.confirmed;
  if (occupancy === 'reserved') return VISUAL_STATUS.reserved;

  return VISUAL_STATUS.available;
}

export function vendorVisualStatus(site = {}, selectedIds = []) {
  const selected = new Set((selectedIds || []).map(Number));
  if (selected.has(Number(site.id))) return VISUAL_STATUS.selected;

  const availability = String(site.availability_status || '').toLowerCase();
  if (availability === 'unavailable') return VISUAL_STATUS.unavailable;
  if (availability === 'disabled') return VISUAL_STATUS.disabled;
  if (availability === 'occupied') {
    const occupancy = String(site.occupancy_status || '').toLowerCase();
    if (occupancy === 'confirmed') return VISUAL_STATUS.confirmed;
    return VISUAL_STATUS.reserved;
  }

  return VISUAL_STATUS.available;
}

export function statusLabel(mode, status) {
  const copy = visualParkingCopy(mode);
  return ({
    [VISUAL_STATUS.available]: copy.available,
    [VISUAL_STATUS.selected]: copy.selected,
    [VISUAL_STATUS.reserved]: copy.reserved,
    [VISUAL_STATUS.confirmed]: copy.confirmed,
    [VISUAL_STATUS.unavailable]: copy.unavailable,
    [VISUAL_STATUS.disabled]: copy.disabled,
    [VISUAL_STATUS.public]: copy.publicSite,
  })[status] || status;
}

export function statusTileClass(status) {
  return ({
    [VISUAL_STATUS.available]: 'vpl-tile--available',
    [VISUAL_STATUS.selected]: 'vpl-tile--selected',
    [VISUAL_STATUS.reserved]: 'vpl-tile--reserved',
    [VISUAL_STATUS.confirmed]: 'vpl-tile--confirmed',
    [VISUAL_STATUS.unavailable]: 'vpl-tile--unavailable',
    [VISUAL_STATUS.disabled]: 'vpl-tile--disabled',
    [VISUAL_STATUS.public]: 'vpl-tile--public',
  })[status] || 'vpl-tile--unavailable';
}

export function statusTextClass(status) {
  return ({
    [VISUAL_STATUS.available]: 'vpl-status--available',
    [VISUAL_STATUS.selected]: 'vpl-status--selected',
    [VISUAL_STATUS.reserved]: 'vpl-status--reserved',
    [VISUAL_STATUS.confirmed]: 'vpl-status--confirmed',
    [VISUAL_STATUS.unavailable]: 'vpl-status--unavailable',
    [VISUAL_STATUS.disabled]: 'vpl-status--disabled',
    [VISUAL_STATUS.public]: 'vpl-status--public',
  })[status] || 'vpl-status--unavailable';
}

export function legendItemsForMode(mode) {
  const copy = visualParkingCopy(mode);
  if (mode === 'public') {
    return [
      { key: VISUAL_STATUS.public, label: copy.publicSite, swatchClass: 'vpl-swatch--public' },
    ];
  }

  const items = [
    { key: VISUAL_STATUS.available, label: copy.available, swatchClass: 'vpl-swatch--available' },
    { key: VISUAL_STATUS.selected, label: copy.selected, swatchClass: 'vpl-swatch--selected' },
    { key: VISUAL_STATUS.reserved, label: copy.reserved, swatchClass: 'vpl-swatch--reserved' },
    { key: VISUAL_STATUS.confirmed, label: copy.confirmed, swatchClass: 'vpl-swatch--confirmed' },
    { key: VISUAL_STATUS.unavailable, label: copy.unavailable, swatchClass: 'vpl-swatch--unavailable' },
    { key: VISUAL_STATUS.disabled, label: copy.disabled, swatchClass: 'vpl-swatch--disabled' },
  ];

  if (mode === 'organizer') {
    return items.filter((item) => item.key !== VISUAL_STATUS.selected);
  }

  return items;
}

function sortSites(sites = []) {
  return [...sites].sort((left, right) => {
    const leftPos = Number(left.position_number ?? left.grid_column ?? 0);
    const rightPos = Number(right.position_number ?? right.grid_column ?? 0);
    if (leftPos !== rightPos) return leftPos - rightPos;
    return String(left.label || '').localeCompare(String(right.label || ''));
  });
}

function sortRows(rows = []) {
  return [...rows].sort((left, right) => {
    const orderDiff = Number(left.display_order ?? 0) - Number(right.display_order ?? 0);
    if (orderDiff !== 0) return orderDiff;
    return Number(left.id) - Number(right.id);
  });
}

/**
 * Detects the standard A–D parking template for orientation/aisle presentation.
 */
export function isStandardParkingLayout(rows = []) {
  if (rows.length !== 4) return false;
  const labels = sortRows(rows).map((row) => String(row.label || '').trim().toUpperCase());
  if (labels.join('') !== STANDARD_ROW_LABELS.join('')) return false;
  return sortRows(rows).every((row) => (row.sites || []).length === STANDARD_SITES_PER_ROW);
}

export function shouldInsertAisleBetween(previousRow, nextRow) {
  const previous = String(previousRow?.label || '').trim().toUpperCase();
  const next = String(nextRow?.label || '').trim().toUpperCase();
  return previous === 'B' && next === 'C';
}

/**
 * Normalize organizer layout rows into the shared visual model.
 */
export function adaptOrganizerRows(rows = [], { focusedSiteId = null } = {}) {
  return sortRows(rows).map((row) => {
    const sites = sortSites(row.sites || []).map((site) => {
      const status = organizerVisualStatus(site);
      const focused = focusedSiteId != null && Number(focusedSiteId) === Number(site.id);
      return {
        id: site.id,
        label: site.label,
        status,
        interactive: true,
        selected: false,
        focused,
        disabled: false,
        price: null,
        spaceName: null,
        categoryLabel: row.category?.label || null,
        rowId: row.id,
        rowLabel: row.label,
        positionNumber: site.position_number,
        locks: site.locks || {},
        occupancy: site.occupancy || 'available',
        operationalStatus: site.operational_status,
        raw: site,
      };
    });

    return {
      id: row.id,
      label: row.label,
      description: row.description || '',
      displayOrder: row.display_order,
      categoryLabel: row.category?.label || null,
      isActive: row.is_active !== false && !row.archived_at,
      siteCount: sites.length,
      sites,
      raw: row,
    };
  });
}

/**
 * Normalize vendor availability rows into the shared visual model.
 */
export function adaptVendorRows(rows = [], selectedIds = []) {
  const selected = new Set((selectedIds || []).map(Number));
  return sortRows(rows).map((row) => {
    const sites = sortSites(row.sites || []).map((site) => {
      const status = vendorVisualStatus(site, selectedIds);
      const isSelected = selected.has(Number(site.id));
      return {
        id: site.id,
        label: site.label,
        status,
        interactive: Boolean(site.is_selectable) || isSelected,
        selected: isSelected,
        focused: false,
        disabled: !(Boolean(site.is_selectable) || isSelected),
        price: site.price != null ? Number(site.price).toFixed(2) : null,
        spaceName: null,
        categoryLabel: row.category?.label || null,
        rowId: row.id,
        rowLabel: row.label,
        positionNumber: site.position_number,
        locks: {},
        occupancy: site.occupancy_status || null,
        operationalStatus: site.operational_status,
        raw: site,
      };
    });

    return {
      id: row.id,
      label: row.label,
      description: row.description || '',
      displayOrder: row.display_order,
      categoryLabel: row.category?.label || null,
      isActive: true,
      siteCount: sites.length,
      availableSiteCount: sites.filter((site) => site.status === VISUAL_STATUS.available).length,
      sites,
      raw: row,
    };
  });
}

/**
 * Normalize public allowlisted rows into the shared visual model.
 */
export function adaptPublicRows(rows = []) {
  return sortRows(rows).map((row) => {
    const sites = sortSites(row.sites || []).map((site) => ({
      id: site.id,
      label: site.label,
      status: VISUAL_STATUS.public,
      interactive: false,
      selected: false,
      focused: false,
      disabled: true,
      price: null,
      spaceName: site.space?.name || null,
      categoryLabel: row.category?.label || null,
      rowId: row.id,
      rowLabel: row.label,
      positionNumber: site.position_number,
      locks: {},
      occupancy: null,
      operationalStatus: null,
      raw: site,
    }));

    return {
      id: row.id,
      label: row.label,
      description: row.description || '',
      displayOrder: row.display_order,
      categoryLabel: row.category?.label || null,
      isActive: true,
      siteCount: sites.length,
      sites,
      raw: row,
    };
  });
}

/**
 * Build ordered render segments: row | aisle.
 */
export function buildRenderSegments(rows = []) {
  const segments = [];
  for (let index = 0; index < rows.length; index += 1) {
    const row = rows[index];
    segments.push({ type: 'row', row });
    const next = rows[index + 1];
    if (next && shouldInsertAisleBetween(row, next)) {
      segments.push({ type: 'aisle' });
    }
  }
  return segments;
}

export function countSitesByStatus(rows = []) {
  const counts = {
    total: 0,
    available: 0,
    selected: 0,
    reserved: 0,
    confirmed: 0,
    unavailable: 0,
    disabled: 0,
    public: 0,
  };

  for (const row of rows) {
    for (const site of row.sites || []) {
      counts.total += 1;
      if (counts[site.status] !== undefined) {
        counts[site.status] += 1;
      }
    }
  }

  return counts;
}

export function siteAriaLabel(mode, site, row) {
  const copy = visualParkingCopy(mode);
  const status = statusLabel(mode, site.status);
  const category = site.categoryLabel || row?.categoryLabel || copy.categoryFallback;
  const parts = [
    `${copy.rowPrefix} ${row?.label || site.rowLabel || ''}`,
    site.label,
    status,
    category,
  ];
  if (site.price != null && mode === 'vendor') {
    parts.push(`RM ${site.price}`);
  }
  if (site.focused && mode === 'organizer') {
    parts.push(copy.focused);
  }
  return parts.filter(Boolean).join(', ');
}

/**
 * Preview labels for the standard 4×16 template.
 */
export function previewStandardParkingLabels() {
  const labels = [];
  for (const row of STANDARD_ROW_LABELS) {
    for (let position = 1; position <= STANDARD_SITES_PER_ROW; position += 1) {
      labels.push(row + String(position).padStart(2, '0'));
    }
  }
  return labels;
}

export function canOrganizerChangeSiteStatus(site, nextStatus) {
  if (!site) return false;
  if (nextStatus === 'active') return true;
  if (['unavailable', 'disabled'].includes(nextStatus) && site.locks?.disable_locked) {
    return false;
  }
  return true;
}
