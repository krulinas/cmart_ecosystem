/** Human-readable labels for survey keys and operational codes. */

const LABEL_MAP = {
  makanan_minuman: 'Makanan & minuman',
  pakaian_fesyen: 'Pakaian & fesyen',
  elektrik_elektronik_gajet: 'Elektrik, elektronik & gajet',
  barangan_rumah_keperluan_harian: 'Barangan rumah & keperluan harian',
  buku_mainan_kanak_kanak: 'Buku & mainan kanak-kanak',
  kecantikan_penjagaan_diri: 'Kecantikan & penjagaan diri',
  perkhidmatan_promosi: 'Perkhidmatan & promosi',
  lain_lain: 'Lain-lain',
  baharu: 'Baharu',
  terpakai: 'Terpakai',
  tidak_berkenaan: 'Tidak berkenaan',
  whatsapp: 'WhatsApp',
  media_sosial: 'Media sosial',
  rakan_kenalan: 'Rakan / kenalan',
  pihak_penganjur: 'Pihak penganjur',
  simpan_acara_lain: 'Simpan untuk acara lain',
  jual_dalam_talian: 'Jual dalam talian',
  sumbangkan: 'Sumbangkan',
  kitar_semula: 'Kitar semula',
  buang: 'Buang',
  semua_terjual: 'Semua terjual',
  promosi_hebahan: 'Promosi & hebahan',
  jumlah_pengunjung: 'Jumlah pengunjung',
  susun_atur_ruang: 'Susun atur ruang',
  kemudahan_lokasi: 'Kemudahan & lokasi',
  pendaftaran_penyertaan: 'Pendaftaran & penyertaan',
  pengurusan_acara: 'Pengurusan acara',
  tiada_penambahbaikan: 'Tiada penambahbaikan',
  peluang_jualan_meningkat: 'Peluang jualan meningkat',
  suasana_meriah: 'Suasana meriah',
  kawasan_sesak: 'Kawasan sesak',
  suasana_bising: 'Suasana bising',
};

/**
 * @param {string|null|undefined} value
 * @returns {string}
 */
export function humanizeAnalyticsLabel(value) {
  if (value == null || value === '') return '—';
  const raw = String(value).trim();
  if (LABEL_MAP[raw]) return LABEL_MAP[raw];
  if (LABEL_MAP[raw.toLowerCase()]) return LABEL_MAP[raw.toLowerCase()];

  if (raw.includes('_')) {
    const spaced = raw.replace(/_/g, ' ');
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
  }

  return raw;
}

/**
 * @param {Array<{key?: string, label?: string, count?: number, display?: string, percent?: number, denominator?: number}>} rows
 */
export function withHumanizedLabels(rows = []) {
  return (Array.isArray(rows) ? rows : []).map((row) => {
    const key = row.key ?? row.label;
    const label = humanizeAnalyticsLabel(key);
    return { ...row, key, label };
  });
}
