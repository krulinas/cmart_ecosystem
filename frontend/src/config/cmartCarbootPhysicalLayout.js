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
