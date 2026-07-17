export function reassignmentErrorMessage(errorCode, fallback) {
  const messages = {
    BOOKING_NOT_REASSIGNABLE: 'Tempahan ini tidak boleh disusun semula pada peringkat semasa.',
    BOOKING_PAYMENT_LOCKED: 'Tapak tidak boleh diubah selepas bayaran dihantar atau disahkan.',
    BOOKING_ALLOCATION_CONFIRMED: 'Tapak tidak boleh diubah kerana peruntukan tempahan telah disahkan.',
    EVENT_DAY_ALREADY_STARTED: 'Tapak tidak boleh diubah selepas hari acara bermula.',
    SITE_COUNT_CHANGE_NOT_SUPPORTED: 'Bilangan tapak baharu mesti sama dengan tempahan asal.',
    SITE_PRICE_CHANGE_NOT_SUPPORTED: 'Jenis atau harga tapak baharu mesti sama dengan tempahan asal.',
    TARGET_SITE_UNAVAILABLE: 'Satu atau lebih tapak pilihan tidak lagi tersedia.',
    TARGET_SITE_SELECTION_INVALID: 'Pilihan tapak tidak memenuhi peraturan susun atur.',
    TARGET_SITE_MIXED_ROWS: 'Semua tapak mesti dipilih daripada baris yang sama.',
    TARGET_SITE_MIXED_CATEGORIES: 'Semua tapak mesti menggunakan kategori baris yang sama.',
    CATEGORY_OVERRIDE_REQUIRED: 'Pilihan ini memerlukan pengecualian kategori.',
    CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED: 'Sila sahkan bahawa anda memahami pengecualian kategori ini.',
    CATEGORY_OVERRIDE_REASON_REQUIRED: 'Sila berikan sebab pengecualian.',
    CATEGORY_OVERRIDE_REASON_TOO_SHORT: 'Sebab pengecualian terlalu pendek.',
    ASSIGNMENT_CHANGED: 'Susunan tapak telah berubah. Sila muat semula dan semak semula pilihan.',
    EVENT_LAYOUT_NOT_READY: 'Susun atur acara belum bersedia.',
  };

  return messages[errorCode] || fallback || 'Susunan tapak tidak dapat dikemas kini. Sila muat semula dan cuba semula.';
}
