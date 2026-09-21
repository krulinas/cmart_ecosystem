/** Presentation labels for Post-Event Summary preview (schema v2). */

import { tt } from '../i18n';
import { formatLocaleMoneyRm } from './localeFormat';

const OPTION_KEYS = new Set([
  'rm_100_299',
  'rm_300_499',
  'rm_500_plus',
  'kurang_rm50',
  'rm51_150',
  'rm151_300',
  'melebihi_rm300',
  'puas_hati',
  'sangat_puas_hati',
  'neutral',
  'kurang_memuaskan',
  'sangat_tidak_memuaskan',
  'pendapatan_utama',
  'pendapatan_sampingan',
  'hobi',
  'terpakai',
  'baru',
  'baharu',
  'tidak_berkenaan',
  'pakaian',
  'buku',
  'barangan_elektronik',
  'makanan_minuman',
  'ya',
  'tidak',
  'tidak_pasti',
  'separuh',
  'kebanyakan',
  'suku',
  'tiada',
  'sumbangkan',
  'kitar_semula',
  'simpan_acara_lain',
  'jual_dalam_talian',
  'buang',
  'whatsapp',
  'media_sosial',
  'rakan_kenalan',
  'pihak_penganjur',
  'yes',
  'no',
]);

const DISTRIBUTION_KEYS = new Set([
  'gross_sales_band',
  'experience_rating',
  'sales_purpose',
  'product_categories',
  'item_conditions',
  'event_info_sources',
  'improvement_areas',
  'supporting_activity_attracted_visitors',
  'supporting_activity_impacts',
  'registration_difficulty',
  'items_sold_band',
  'unsold_item_actions',
]);

const METHODOLOGY_KEYS = new Set([
  'single_event_scope',
  'data_cut_off',
  'timezone',
  'language',
  'provisional_or_final',
  'booking_versus_unique_vendors',
  'approved_not_attendance',
  'attendance_source',
  'site_day_utilisation_formula',
  'survey_respondent_base',
  'multi_select_note',
  'financial_inclusion_rules',
  'missing_data_rule',
  'data_quality_warnings',
  'potentially_incomplete_finances',
  'data_availability',
]);

function translateKnown(prefix, key) {
  if (!key) return null;
  const i18nKey = `${prefix}.${key}`;
  const translated = tt(i18nKey);
  return translated === i18nKey ? null : translated;
}

export function reportOptionLabel(value) {
  if (value == null || value === '') return '—';
  const raw = String(value).trim();
  const key = raw.toLowerCase();
  if (OPTION_KEYS.has(key)) {
    const translated = translateKnown('reports.options', key);
    if (translated) return translated;
  }
  if (OPTION_KEYS.has(raw)) {
    const translated = translateKnown('reports.options', raw);
    if (translated) return translated;
  }
  if (!raw.includes('_')) return raw;
  const spaced = raw.replaceAll('_', ' ');
  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function reportDistributionTitle(key) {
  if (DISTRIBUTION_KEYS.has(key)) {
    const translated = translateKnown('reports.distributions', key);
    if (translated) return translated;
  }
  return String(key).replaceAll('_', ' ');
}

export function reportMethodologyLabel(key) {
  if (METHODOLOGY_KEYS.has(key)) {
    const translated = translateKnown('reports.methodology', key);
    if (translated) return translated;
  }
  return String(key).replaceAll('_', ' ');
}

export function formatReportMoney(value) {
  if (value === undefined || value === null || value === '') return null;
  return formatLocaleMoneyRm(value);
}

export function collectionRate(collected, expected) {
  if (collected == null || expected == null || Number(expected) <= 0) return null;
  return Math.round((Number(collected) / Number(expected)) * 1000) / 10;
}
