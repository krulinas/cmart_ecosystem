export async function fetchVendorCategories(api) {
  const { data } = await api.get('/vendor-categories');
  return Array.isArray(data?.categories) ? data.categories : [];
}

export function categoryConflictMessage(errorCode, fallback) {
  const messages = {
    CATEGORY_REQUIRED: 'Sila pilih kategori jualan terlebih dahulu.',
    SITE_CATEGORY_INCOMPATIBLE: 'Tapak yang dipilih tidak sepadan dengan kategori jualan anda.',
    MIXED_CATEGORY_SITE_SELECTION: 'Semua tapak mesti berada dalam kategori yang sama.',
    LAYOUT_CHANGED: 'Susun atur acara telah berubah. Sila semak dan pilih tapak semula.',
    EVENT_LAYOUT_NOT_READY: 'Susun atur acara belum bersedia untuk tempahan.',
    CATEGORY_INACTIVE: 'Kategori ini tidak lagi aktif.',
    CATEGORY_ARCHIVED: 'Kategori ini tidak lagi tersedia.',
    SITE_MISSING_LAYOUT_ROW: 'Tapak ini belum disusun dengan betul dan tidak boleh ditempah.',
    SITE_ROW_INACTIVE: 'Baris tapak ini tidak lagi aktif.',
    site_day_occupied: 'Satu atau lebih tapak tidak lagi tersedia.',
    UNKNOWN_LEGACY_CATEGORY: 'Kategori ini tidak lagi aktif.',
    CATEGORY_NOT_FOUND: 'Kategori ini tidak lagi aktif.',
  };

  return messages[errorCode] || fallback || 'Unable to submit booking.';
}
