/**
 * Phase 3.6 — Malay Organizer layout copy and backend error/blocker mapping.
 * No full i18n framework; centralised message tables only.
 */

export const LAYOUT_COPY = {
  pageTitle: 'Susun Atur Tapak',
  navLabel: 'Urus Susun Atur',
  manageLayoutAction: 'Urus Susun Atur',
  selectEvent: 'Pilih acara',
  refresh: 'Muat Semula Susun Atur',
  backToEvents: 'Kembali ke Acara',
  addRow: 'Tambah Baris',
  editRow: 'Edit Baris',
  saveOrder: 'Simpan Susunan',
  moveUp: 'Naikkan',
  moveDown: 'Turunkan',
  deleteRow: 'Padam Baris',
  archiveRow: 'Arkibkan Baris',
  unarchiveRow: 'Nyaharkib Baris',
  addSite: 'Tambah Tapak',
  generateSites: 'Jana Tapak',
  editSite: 'Edit Tapak',
  moveSite: 'Pindah Tapak',
  disableSite: 'Nyahaktifkan',
  enableSite: 'Aktifkan',
  deleteSite: 'Padam Tapak',
  save: 'Simpan',
  cancel: 'Batal',
  tryAgain: 'Cuba Lagi',
  locked: 'Dikunci',
  advanced: 'Tetapan Lanjutan',
  unresolvedTitle: 'Tapak Belum Disusun',
  emptyTitle: 'Belum ada susun atur tapak.',
  emptyBody: 'Tambah baris pertama untuk mula membina susun atur acara.',
  loadError: 'Susun atur tidak dapat dimuatkan.',
  conflictRefreshHint: 'Muat Semula Susun Atur',
  operationalReady: 'Bersedia untuk Tempahan',
  operationalNotReady: 'Belum Bersedia untuk Tempahan',
  publicReady: 'Bersedia untuk Paparan Awam',
  publicNotReady: 'Belum Bersedia untuk Paparan Awam',
  rowCreated: 'Baris berjaya ditambah.',
  rowUpdated: 'Baris berjaya dikemas kini.',
  rowDeleted: 'Baris berjaya dipadam.',
  rowArchived: 'Baris berjaya diarkibkan.',
  rowUnarchived: 'Baris berjaya dinyaharkib.',
  rowsReordered: 'Susunan baris berjaya disimpan.',
  siteCreated: 'Tapak berjaya ditambah.',
  sitesGenerated: 'Tapak berjaya dijana.',
  siteUpdated: 'Tapak berjaya dikemas kini.',
  sitesReordered: 'Susunan tapak berjaya disimpan.',
  siteDeleted: 'Tapak berjaya dipadam.',
  fallbackError: 'Tindakan tidak dapat diselesaikan. Sila muat semula susun atur dan cuba semula.',
  renameLockedHint:
    'Nama baris tidak boleh diubah kerana tapak dalam baris ini mempunyai sejarah tempahan.',
  categoryLockedHint:
    'Kategori baris tidak boleh diubah kerana tapak dalam baris ini mempunyai sejarah tempahan.',
  structureLockedHint: 'Struktur tapak ini dikunci kerana mempunyai sejarah tempahan.',
  disableLockedHint: 'Tapak ini tidak boleh dinyahaktifkan kerana mempunyai tempahan aktif.',
  archiveBlockedHint: 'Baris ini tidak boleh diarkibkan kerana masih mempunyai tempahan aktif.',
  unarchiveHint:
    'Baris akan diaktifkan semula, tetapi tapak di dalamnya tidak akan diaktifkan secara automatik.',
  generateAtomicHint:
    'Semua tapak diminta akan dijana sekali gus, atau tiada tapak langsung yang dicipta. Tapak sedia ada tidak akan dipadam atau diganti.',
};

export const READINESS_BLOCKER_MESSAGES = {
  NO_ACTIVE_EVENT_DAYS: 'Tiada hari acara aktif ditetapkan.',
  NO_ACTIVE_LAYOUT_ROWS: 'Tiada baris susun atur aktif.',
  ACTIVE_ROW_MISSING_CATEGORY: 'Satu atau lebih baris belum mempunyai kategori.',
  ROW_CATEGORY_INACTIVE: 'Kategori bagi satu atau lebih baris tidak lagi aktif.',
  ACTIVE_ROW_HAS_NO_ACTIVE_SITES: 'Satu atau lebih baris tidak mempunyai tapak aktif.',
  ACTIVE_SITE_MISSING_ROW: 'Terdapat tapak aktif yang belum dipautkan kepada baris.',
  SITE_EVENT_ROW_MISMATCH: 'Terdapat tapak yang tidak sepadan dengan acara barisnya.',
  ACTIVE_SITE_MISSING_SPACE: 'Terdapat tapak tanpa jenis ruang yang sah.',
  ACTIVE_SITE_INVALID_LABEL: 'Terdapat tapak dengan label yang tidak sah.',
  UNRESOLVED_ACTIVE_SITES: 'Terdapat tapak lama yang masih belum disusun ke dalam baris.',
  DUPLICATE_ACTIVE_SITE_IDENTITY: 'Terdapat tapak aktif dengan identiti berganda.',
  NO_PUBLIC_ROWS: 'Tiada baris yang ditetapkan untuk paparan awam.',
  PUBLIC_ROW_CATEGORY_NOT_PUBLIC: 'Kategori baris awam tidak dibenarkan untuk paparan awam.',
  PUBLIC_ROW_HAS_NO_VISIBLE_SITES: 'Baris awam tidak mempunyai tapak yang boleh dipaparkan.',
  EMPTY_PUBLIC_LAYOUT: 'Susun atur awam masih kosong.',
  INVALID_PUBLIC_ROW_ORDER: 'Susunan baris awam tidak sah.',
};

export const LAYOUT_ERROR_MESSAGES = {
  ROW_LABEL_LOCKED: 'Nama baris tidak boleh diubah kerana mempunyai sejarah tempahan.',
  ROW_CATEGORY_LOCKED: 'Kategori baris tidak boleh diubah kerana mempunyai sejarah tempahan.',
  ROW_NOT_EMPTY: 'Baris ini masih mempunyai tapak dan tidak boleh dipadam.',
  ACTIVE_ALLOCATIONS_PRESENT: 'Tindakan ini tidak dibenarkan kerana terdapat tempahan aktif.',
  SITE_STRUCTURE_LOCKED: 'Struktur tapak ini dikunci kerana mempunyai sejarah tempahan.',
  SITE_HAS_ALLOCATION_HISTORY: 'Tapak ini mempunyai sejarah tempahan dan tidak boleh dipadam.',
  SITE_LABEL_CONFLICT: 'Label tapak ini telah digunakan.',
  SITE_POSITION_CONFLICT: 'Kedudukan tapak ini bertindih dengan tapak lain.',
  ROW_LABEL_CONFLICT: 'Nama baris ini telah digunakan dalam acara yang sama.',
  LAYOUT_GENERATION_CONFLICT: 'Tapak tidak dapat dijana kerana berlaku konflik susun atur.',
  CATEGORY_INACTIVE: 'Kategori ini tidak aktif dan tidak boleh digunakan.',
  INVALID_LAYOUT_ROW: 'Baris susun atur tidak sah.',
  INVALID_VENDOR_CATEGORY: 'Kategori vendor tidak sah.',
  INVALID_SITE_COUNT: 'Bilangan tapak tidak sah.',
  INVALID_SITE_LABEL: 'Label tapak tidak sah.',
  INVALID_DISPLAY_ORDER: 'Susunan paparan tidak sah.',
  INVALID_SITE_STATUS: 'Status tapak tidak sah.',
};

export function readinessMessage(code) {
  return READINESS_BLOCKER_MESSAGES[code] || code;
}

export function layoutErrorMessage(error) {
  const code = error?.response?.data?.error;
  if (code && LAYOUT_ERROR_MESSAGES[code]) {
    return LAYOUT_ERROR_MESSAGES[code];
  }
  const message = error?.response?.data?.message;
  if (typeof message === 'string' && message.trim() !== '') {
    // Strip HTTP status prefix if present for cleaner BM UI.
    return message.replace(/^\d{3}\s+[A-Za-z ]+:\s*/, '');
  }
  return LAYOUT_COPY.fallbackError;
}

export const OCCUPANCY_LABELS = {
  available: 'Tersedia',
  reserved: 'Ditempah',
  confirmed: 'Disahkan',
  'released-history': 'Sejarah dilepas',
};

export const SITE_STATUS_LABELS = {
  active: 'Aktif',
  unavailable: 'Tidak tersedia',
  disabled: 'Dinyahaktif',
};
