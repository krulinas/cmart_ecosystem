/** Human-readable labels for survey keys and operational codes. */
import { tt } from '../i18n';

const LABEL_KEYS = {
  makanan_minuman: 'organizer.analytics.labels.makanan_minuman',
  pakaian_fesyen: 'organizer.analytics.labels.pakaian_fesyen',
  elektrik_elektronik_gajet: 'organizer.analytics.labels.elektrik_elektronik_gajet',
  barangan_rumah_keperluan_harian: 'organizer.analytics.labels.barangan_rumah_keperluan_harian',
  buku_mainan_kanak_kanak: 'organizer.analytics.labels.buku_mainan_kanak_kanak',
  kecantikan_penjagaan_diri: 'organizer.analytics.labels.kecantikan_penjagaan_diri',
  perkhidmatan_promosi: 'organizer.analytics.labels.perkhidmatan_promosi',
  lain_lain: 'organizer.analytics.labels.lain_lain',
  baharu: 'organizer.analytics.labels.baharu',
  terpakai: 'organizer.analytics.labels.terpakai',
  tidak_berkenaan: 'organizer.analytics.labels.tidak_berkenaan',
  whatsapp: 'organizer.analytics.labels.whatsapp',
  media_sosial: 'organizer.analytics.labels.media_sosial',
  rakan_kenalan: 'organizer.analytics.labels.rakan_kenalan',
  pihak_penganjur: 'organizer.analytics.labels.pihak_penganjur',
  simpan_acara_lain: 'organizer.analytics.labels.simpan_acara_lain',
  jual_dalam_talian: 'organizer.analytics.labels.jual_dalam_talian',
  sumbangkan: 'organizer.analytics.labels.sumbangkan',
  kitar_semula: 'organizer.analytics.labels.kitar_semula',
  buang: 'organizer.analytics.labels.buang',
  semua_terjual: 'organizer.analytics.labels.semua_terjual',
  promosi_hebahan: 'organizer.analytics.labels.promosi_hebahan',
  jumlah_pengunjung: 'organizer.analytics.labels.jumlah_pengunjung',
  susun_atur_ruang: 'organizer.analytics.labels.susun_atur_ruang',
  kemudahan_lokasi: 'organizer.analytics.labels.kemudahan_lokasi',
  pendaftaran_penyertaan: 'organizer.analytics.labels.pendaftaran_penyertaan',
  pengurusan_acara: 'organizer.analytics.labels.pengurusan_acara',
  tiada_penambahbaikan: 'organizer.analytics.labels.tiada_penambahbaikan',
  peluang_jualan_meningkat: 'organizer.analytics.labels.peluang_jualan_meningkat',
  suasana_meriah: 'organizer.analytics.labels.suasana_meriah',
  kawasan_sesak: 'organizer.analytics.labels.kawasan_sesak',
  suasana_bising: 'organizer.analytics.labels.suasana_bising',
  'Pendapatan Utama': 'organizer.analytics.labels.pendapatan_utama',
  'Pendapatan Sampingan': 'organizer.analytics.labels.pendapatan_sampingan',
  'Hobi / Mengosongkan ruang rumah': 'organizer.analytics.labels.hobi_mengosongkan',
  'Kurang daripada RM50': 'organizer.analytics.labels.kurang_rm50',
  'RM51 hingga RM150': 'organizer.analytics.labels.rm51_rm150',
  'RM151 hingga RM300': 'organizer.analytics.labels.rm151_rm300',
  'Melebihi RM300': 'organizer.analytics.labels.melebihi_rm300',
  'Tiada (hanya jual barang baharu/makanan)': 'organizer.analytics.labels.tiada_jual_baharu',
  'Suku (25%)': 'organizer.analytics.labels.suku_25',
  'Separuh (50%)': 'organizer.analytics.labels.separuh_50',
  'Hampir habis (75%-100%)': 'organizer.analytics.labels.hampir_habis',
};

/**
 * @param {string|null|undefined} value
 * @returns {string}
 */
export function humanizeAnalyticsLabel(value) {
  if (value == null || value === '') return tt('common.none');
  const raw = String(value).trim();
  const key = LABEL_KEYS[raw] || LABEL_KEYS[raw.toLowerCase()];
  if (key) {
    const translated = tt(key);
    return translated === key ? raw : translated;
  }

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
