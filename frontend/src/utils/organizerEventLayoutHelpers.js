/**
 * Phase 3.6 — pure helpers for Organizer layout UI (preview, ordering, presentation).
 */

import { CMART_CARBOOT_SITES_PER_ROW } from '../config/cmartCarbootPhysicalLayout.js';

export const MAX_GENERATED_SITES = 100;

/**
 * Generate Sites is inactive once a row already holds its complete physical site set.
 * Standard CMart rows are A–D × 16; avoid offering duplicate generation.
 */
export function canGenerateSitesForRow(row = {}) {
  const siteCount = (row.sites || []).length;
  return siteCount < CMART_CARBOOT_SITES_PER_ROW;
}

/**
 * Deterministic label preview matching EventLayoutRowSiteGenerator.
 */
export function previewGeneratedLabels({
  labelPrefix = '',
  count = 0,
  startNumber = 1,
  numberPadding = 2,
} = {}) {
  const prefix = String(labelPrefix || '').trim().toUpperCase();
  const total = Number(count);
  const start = Number(startNumber) || 1;
  const padding = Math.min(6, Math.max(1, Number(numberPadding) || 2));

  if (!prefix || !Number.isFinite(total) || total < 1) {
    return [];
  }

  const safeCount = Math.min(MAX_GENERATED_SITES, Math.floor(total));
  const labels = [];
  for (let offset = 0; offset < safeCount; offset += 1) {
    const number = start + offset;
    labels.push(prefix + String(number).padStart(padding, '0'));
  }
  return labels;
}

export function sortRowsByDisplayOrder(rows = []) {
  return [...rows].sort((left, right) => {
    const orderDiff = Number(left.display_order ?? 0) - Number(right.display_order ?? 0);
    if (orderDiff !== 0) return orderDiff;
    return Number(left.id) - Number(right.id);
  });
}

export function sortSitesByDisplayOrder(sites = []) {
  return [...sites].sort((left, right) => {
    const orderDiff = Number(left.display_order ?? 0) - Number(right.display_order ?? 0);
    if (orderDiff !== 0) return orderDiff;
    const colDiff = Number(left.grid_column ?? 0) - Number(right.grid_column ?? 0);
    if (colDiff !== 0) return colDiff;
    return Number(left.id) - Number(right.id);
  });
}

export function buildRowReorderPayload(rows, fromIndex, toIndex) {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
    return null;
  }
  const ordered = sortRowsByDisplayOrder(rows);
  if (fromIndex >= ordered.length || toIndex >= ordered.length) {
    return null;
  }
  const next = [...ordered];
  const [moved] = next.splice(fromIndex, 1);
  next.splice(toIndex, 0, moved);
  return {
    rows: next.map((row, index) => ({
      id: row.id,
      display_order: index + 1,
    })),
  };
}

export function buildSiteReorderPayload(sites, fromIndex, toIndex) {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
    return null;
  }
  const ordered = sortSitesByDisplayOrder(sites);
  if (fromIndex >= ordered.length || toIndex >= ordered.length) {
    return null;
  }
  const next = [...ordered];
  const [moved] = next.splice(fromIndex, 1);
  next.splice(toIndex, 0, moved);
  return {
    sites: next.map((site, index) => ({
      id: site.id,
      display_order: index + 1,
    })),
  };
}

export function countActiveSites(rows = []) {
  return rows.reduce((total, row) => {
    const sites = row.sites || [];
    return total + sites.filter((site) => site.operational_status === 'active').length;
  }, 0);
}

export function occupancySummaryForRow(sites = []) {
  const summary = {
    available: 0,
    reserved: 0,
    confirmed: 0,
    'released-history': 0,
  };
  for (const site of sites) {
    const key = site.occupancy || 'available';
    if (summary[key] !== undefined) {
      summary[key] += 1;
    }
  }
  return summary;
}

export function siteStateKey(site) {
  if (site?.locks?.structure_locked) return 'structurally_locked';
  if (site?.operational_status === 'disabled') return 'disabled';
  if (site?.operational_status === 'unavailable') return 'unavailable';
  if (site?.occupancy === 'confirmed') return 'confirmed';
  if (site?.occupancy === 'reserved') return 'reserved';
  if (site?.occupancy === 'released-history') return 'released_history';
  return 'available';
}

export function extractErrorCode(error) {
  return error?.response?.data?.error || null;
}
