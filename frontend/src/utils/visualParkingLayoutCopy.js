/**
 * Explicit audience/mode copy for VisualParkingLayout.
 * Organizer and public: English. Vendor booking: Bahasa Melayu.
 * Never infer mode from auth role — callers pass mode explicitly.
 */

export const VISUAL_PARKING_MODES = Object.freeze(['organizer', 'vendor', 'public']);

export const VISUAL_PARKING_COPY = Object.freeze({
  organizer: Object.freeze({
    title: 'Parking Layout',
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
    sitesCount: (count) => `${count} sites`,
    categoryFallback: 'No category',
    selectSite: 'Select site',
    focused: 'Focused',
  }),
  vendor: Object.freeze({
    title: 'Pilih Tapak',
    legendAria: 'Petunjuk status tapak',
    exit: 'Keluar',
    entrance: 'Masuk',
    aisle: 'Laluan Kenderaan',
    available: 'Tersedia',
    selected: 'Dipilih',
    reserved: 'Ditempah',
    confirmed: 'Disahkan',
    unavailable: 'Tidak Tersedia',
    disabled: 'Dinyahaktifkan',
    publicSite: 'Tapak',
    rowPrefix: 'Baris',
    sitesCount: (count) => `${count} tapak`,
    categoryFallback: 'Tiada kategori',
    selectSite: 'Pilih tapak',
    focused: 'Difokuskan',
  }),
  public: Object.freeze({
    title: 'Event Layout Map',
    legendAria: 'Layout legend',
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
    sitesCount: (count) => `${count} physical sites`,
    categoryFallback: 'Category',
    selectSite: 'Site',
    focused: 'Focused',
  }),
});

export function visualParkingCopy(mode) {
  if (!VISUAL_PARKING_MODES.includes(mode)) {
    throw new Error(`Unsupported visual parking layout mode: ${mode}`);
  }
  return VISUAL_PARKING_COPY[mode];
}
