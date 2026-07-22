/**
 * Explicit audience/mode copy for VisualParkingLayout.
 * English-only baseline — all modes use English until BM is re-enabled.
 * Never infer mode from auth role — callers pass mode explicitly.
 */

export const VISUAL_PARKING_MODES = Object.freeze(['organizer', 'vendor', 'public']);

const ENGLISH_COPY = Object.freeze({
  title: 'Select Site',
  legendAria: 'Site status legend',
  exit: 'Exit',
  entrance: 'Entrance',
  aisle: 'Vehicle Aisle',
  available: 'Available',
  selected: 'Selected',
  reserved: 'Reserved',
  confirmed: 'Booked',
  unavailable: 'Unavailable',
  disabled: 'Disabled',
  publicSite: 'Site',
  rowPrefix: 'Row',
  sitesCount: (count) => `${count} site${count === 1 ? '' : 's'}`,
  categoryFallback: 'No category',
  selectSite: 'Select site',
  focused: 'Focused',
});

export const VISUAL_PARKING_COPY = Object.freeze({
  organizer: Object.freeze({
    ...ENGLISH_COPY,
    title: 'Parking Layout',
    publicSite: 'Site',
    sitesCount: (count) => `${count} sites`,
    categoryFallback: 'No category',
  }),
  vendor: Object.freeze({ ...ENGLISH_COPY }),
  public: Object.freeze({
    ...ENGLISH_COPY,
    title: 'Event Layout Map',
    legendAria: 'Layout legend',
    sitesCount: (count) => `${count} physical sites`,
    categoryFallback: 'Category',
    selectSite: 'Site',
  }),
});

export function visualParkingCopy(mode) {
  if (!VISUAL_PARKING_MODES.includes(mode)) {
    throw new Error(`Unsupported visual parking layout mode: ${mode}`);
  }
  return VISUAL_PARKING_COPY[mode];
}
