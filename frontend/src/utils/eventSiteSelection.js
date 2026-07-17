/**
 * Phase 2A.8 — client-side EventSite selection helpers (UX only; backend is authoritative).
 */

export const AVAILABILITY_AVAILABLE = 'available';

export function prepareAvailabilityRows(rows = []) {
  return [...rows]
    .filter((row) => row && row.id != null)
    .sort((left, right) => {
      const leftOrder = Number(left.display_order ?? left.id);
      const rightOrder = Number(right.display_order ?? right.id);
      return leftOrder - rightOrder;
    })
    .map((row) => {
      const sites = [...(Array.isArray(row.sites) ? row.sites : [])].sort((left, right) => {
        const leftPos = Number(left.position_number ?? 0);
        const rightPos = Number(right.position_number ?? 0);
        if (leftPos !== rightPos) return leftPos - rightPos;
        return String(left.label).localeCompare(String(right.label));
      });

      return {
        ...row,
        rowId: Number(row.id),
        rowLabel: row.label,
        sites,
        siteCount: sites.length,
        availableSiteCount: sites.filter((site) => site.is_selectable).length,
        spaceNames: [...new Set(sites.map((site) => site.space_name).filter(Boolean))],
        prices: [...new Set(sites.map((site) => Number(site.price || 0).toFixed(2)))],
      };
    });
}

export function groupSitesByRow(sites = []) {
  const rows = new Map();

  for (const site of sites) {
    const key = site.row_label || 'Row';
    if (!rows.has(key)) {
      rows.set(key, []);
    }
    rows.get(key).push(site);
  }

  return [...rows.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([rowLabel, rowSites]) => ({
      rowLabel,
      sites: [...rowSites].sort((left, right) => {
        const leftPos = Number(left.position_number ?? 0);
        const rightPos = Number(right.position_number ?? 0);
        if (leftPos !== rightPos) return leftPos - rightPos;
        return String(left.label).localeCompare(String(right.label));
      }),
    }));
}

export function getSelectedSites(sites = [], selectedIds = []) {
  const lookup = new Map(sites.map((site) => [Number(site.id), site]));
  return selectedIds
    .map((id) => lookup.get(Number(id)))
    .filter(Boolean)
    .sort((left, right) => Number(left.position_number) - Number(right.position_number));
}

export function computePreviewAmount(selectedSites = []) {
  return selectedSites.reduce((total, site) => total + Number(site.price || 0), 0);
}

export function arePositionsContiguous(sites = []) {
  if (sites.length <= 1) return true;

  const ordered = [...sites].sort(
    (left, right) => Number(left.position_number) - Number(right.position_number),
  );

  for (let index = 1; index < ordered.length; index += 1) {
    if (Number(ordered[index].position_number) !== Number(ordered[index - 1].position_number) + 1) {
      return false;
    }
  }

  return true;
}

export function selectionValidationMessage(selectedSites = []) {
  if (selectedSites.length === 0) return null;

  const rowIds = new Set(selectedSites.map((site) => Number(site.event_layout_row_id)));
  if (rowIds.size > 1) {
    return 'Pilih tapak dalam baris yang sama.';
  }

  const spaceIds = new Set(selectedSites.map((site) => site.space_id));
  if (spaceIds.size > 1) {
    return 'Semua tapak yang dipilih mestilah daripada jenis ruang yang sama.';
  }

  if (!arePositionsContiguous(selectedSites)) {
    return 'Pilih tapak yang bersebelahan.';
  }

  return null;
}

export function canSelectSite(site, selectedSites = []) {
  if (!site?.is_selectable || site.availability_status !== AVAILABILITY_AVAILABLE) {
    return false;
  }

  if (selectedSites.length === 0) {
    return true;
  }

  const rowIds = new Set(selectedSites.map((item) => Number(item.event_layout_row_id)));
  if (!rowIds.has(Number(site.event_layout_row_id))) {
    return false;
  }

  const spaceIds = new Set(selectedSites.map((item) => item.space_id));
  if (!spaceIds.has(site.space_id)) {
    return false;
  }

  const positions = selectedSites.map((item) => Number(item.position_number));
  const min = Math.min(...positions);
  const max = Math.max(...positions);
  const nextPosition = Number(site.position_number);

  return nextPosition === min - 1 || nextPosition === max + 1;
}

export function toggleSiteSelection(site, selectedIds = [], sites = []) {
  const siteId = Number(site.id);
  const selectedSet = new Set(selectedIds.map(Number));
  const selectedSites = getSelectedSites(sites, [...selectedSet]);

  if (selectedSet.has(siteId)) {
    const remaining = selectedSites.filter((item) => Number(item.id) !== siteId);
    if (!arePositionsContiguous(remaining)) {
      return {
        selectedIds: [...selectedSet],
        blockedMessage: 'Nyahpilih tapak dari hujung pilihan atau kosongkan semua pilihan.',
      };
    }

    selectedSet.delete(siteId);
    return { selectedIds: [...selectedSet], blockedMessage: null };
  }

  if (!canSelectSite(site, selectedSites)) {
    return {
      selectedIds: [...selectedSet],
      blockedMessage: 'Pilih tapak yang bersebelahan dalam baris dan jenis ruang yang sama.',
    };
  }

  selectedSet.add(siteId);
  return { selectedIds: [...selectedSet].sort((a, b) => a - b), blockedMessage: null };
}

export function pruneInvalidSelections(selectedIds = [], sites = []) {
  const selectableIds = new Set(
    sites
      .filter((site) => site.is_selectable && site.availability_status === AVAILABILITY_AVAILABLE)
      .map((site) => Number(site.id)),
  );

  let nextIds = selectedIds.map(Number).filter((id) => selectableIds.has(id));
  let selectedSites = getSelectedSites(sites, nextIds);

  while (selectedSites.length > 0 && !arePositionsContiguous(selectedSites)) {
    nextIds.pop();
    selectedSites = getSelectedSites(sites, nextIds);
  }

  while (selectedSites.length > 0 && selectionValidationMessage(selectedSites)) {
    nextIds = [];
    selectedSites = [];
  }

  return nextIds;
}

export function formatOperationalDaysSummary(days = []) {
  if (!days.length) return 'Tiada hari acara aktif dikonfigurasi.';
  if (days.length === 1) {
    return `Tapak pilihan akan ditempah untuk ${days[0].operational_date}.`;
  }

  const labels = days.map((day) => day.operational_date).join(', ');
  return `Tapak pilihan akan ditempah untuk semua hari acara aktif (${labels}).`;
}

export function siteAriaLabel(site) {
  const price = Number(site.price || 0).toFixed(2);
  const occupiedLabel = site.occupancy_status === 'confirmed'
    ? 'ditempah dan disahkan'
    : 'ditempah';
  const statusLabel = {
    available: 'tersedia',
    occupied: occupiedLabel,
    unavailable: 'tidak tersedia',
    disabled: 'dinyahaktifkan',
  }[site.availability_status] || site.availability_status;

  return `Tapak ${site.label}, ${site.space_name || 'Ruang'}, RM${price}, ${statusLabel}`;
}
