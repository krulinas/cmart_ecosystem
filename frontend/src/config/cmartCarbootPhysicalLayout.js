/**
 * Interim authoritative CMart Changlun Carboot physical layout.
 * Keep in sync with backend App\Support\CmartCarbootPhysicalLayout.
 */
export const CMART_CARBOOT_ROW_LABELS = Object.freeze(['A', 'B', 'C', 'D']);
export const CMART_CARBOOT_SITES_PER_ROW = 16;
export const CMART_CARBOOT_PHYSICAL_CAPACITY =
  CMART_CARBOOT_ROW_LABELS.length * CMART_CARBOOT_SITES_PER_ROW;

export function isAllowedPhysicalRowLabel(label) {
  return CMART_CARBOOT_ROW_LABELS.includes(String(label || '').trim().toUpperCase());
}

export function normalizePhysicalRowLabel(label) {
  return String(label || '').trim().toUpperCase();
}

export function canonicalSiteLabelFor(rowLabel, position) {
  const row = normalizePhysicalRowLabel(rowLabel);
  const padded = String(Number(position)).padStart(2, '0');
  return `${row}${padded}`;
}

export function expectedSiteLabelsForRow(rowLabel) {
  const row = normalizePhysicalRowLabel(rowLabel);
  if (!isAllowedPhysicalRowLabel(row)) return [];
  const labels = [];
  for (let position = 1; position <= CMART_CARBOOT_SITES_PER_ROW; position += 1) {
    labels.push(canonicalSiteLabelFor(row, position));
  }
  return labels;
}

export function parseCanonicalSiteLabel(label) {
  const normalized = String(label || '').trim().toUpperCase();
  const match = normalized.match(/^([A-D])(0[1-9]|1[0-6])$/);
  if (!match) return null;
  return { row: match[1], position: Number(match[2]) };
}

export function isCanonicalSiteLabel(label) {
  return parseCanonicalSiteLabel(label) != null;
}

/**
 * Exact missing canonical labels for a physical row, in numeric order.
 * Does not repair duplicates/invalid/legacy labels.
 */
export function missingCanonicalSiteLabels(rowLabel, existingLabels = []) {
  const row = normalizePhysicalRowLabel(rowLabel);
  if (!isAllowedPhysicalRowLabel(row)) return [];

  const present = new Set();
  for (const label of existingLabels || []) {
    const parsed = parseCanonicalSiteLabel(label);
    if (!parsed || parsed.row !== row) continue;
    present.add(canonicalSiteLabelFor(row, parsed.position));
  }

  return expectedSiteLabelsForRow(row).filter((expected) => !present.has(expected));
}

export function unusedPhysicalRowLabels(existingLabels = []) {
  const used = new Set(
    (existingLabels || [])
      .map((label) => String(label || '').trim().toUpperCase())
      .filter((label) => CMART_CARBOOT_ROW_LABELS.includes(label)),
  );
  return CMART_CARBOOT_ROW_LABELS.filter((label) => !used.has(label));
}

export function allPhysicalRowsInUse(existingLabels = []) {
  return unusedPhysicalRowLabels(existingLabels).length === 0;
}
