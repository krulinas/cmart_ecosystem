/** English presentation labels for Post-Event Summary preview (schema v2). */

const OPTION_LABELS = {
  rm_100_299: 'RM 100 – RM 299',
  rm_300_499: 'RM 300 – RM 499',
  rm_500_plus: 'RM 500 and above',
  kurang_rm50: 'Less than RM 50',
  rm51_150: 'RM 51 – RM 150',
  rm151_300: 'RM 151 – RM 300',
  melebihi_rm300: 'More than RM 300',
  puas_hati: 'Satisfied',
  sangat_puas_hati: 'Very satisfied',
  neutral: 'Neutral',
  kurang_memuaskan: 'Somewhat dissatisfied',
  sangat_tidak_memuaskan: 'Very dissatisfied',
  pendapatan_utama: 'Primary income',
  pendapatan_sampingan: 'Side income',
  hobi: 'Hobby / clearing space',
  terpakai: 'Reused / preloved',
  baru: 'New',
  baharu: 'New',
  tidak_berkenaan: 'Not applicable',
  pakaian: 'Clothing',
  buku: 'Books',
  barangan_elektronik: 'Electronics',
  makanan_minuman: 'Food & beverages',
  ya: 'Yes',
  tidak: 'No',
  tidak_pasti: 'Unsure',
  separuh: 'About half (50%)',
  kebanyakan: 'Mostly sold (75%–100%)',
  suku: 'About a quarter (25%)',
  tiada: 'None',
  sumbangkan: 'Donate',
  kitar_semula: 'Recycle',
  simpan_acara_lain: 'Keep for another event',
  jual_dalam_talian: 'Sell online',
  buang: 'Dispose',
  whatsapp: 'WhatsApp',
  media_sosial: 'Social media',
  rakan_kenalan: 'Friends / contacts',
  pihak_penganjur: 'Organizer',
  yes: 'Reported difficulty',
  no: 'No difficulty reported',
};

const DISTRIBUTION_TITLES = {
  gross_sales_band: 'Reported gross sales',
  experience_rating: 'Overall experience',
  sales_purpose: 'Sales purpose',
  product_categories: 'Product categories',
  item_conditions: 'Item condition',
  event_info_sources: 'How vendors heard about the event',
  improvement_areas: 'Improvement priorities',
  supporting_activity_attracted_visitors: 'Effect of supporting activities',
  supporting_activity_impacts: 'Supporting-activity impacts',
  registration_difficulty: 'Registration difficulty',
  items_sold_band: 'Used-stock sold',
  unsold_item_actions: 'Plans for unsold items',
};

const METHODOLOGY_LABELS = {
  single_event_scope: 'Report scope',
  data_cut_off: 'Data cut-off',
  timezone: 'Timezone',
  language: 'Language',
  provisional_or_final: 'Report status',
  booking_versus_unique_vendors: 'Applications and unique vendors',
  approved_not_attendance: 'Approved bookings and attendance',
  attendance_source: 'Attendance source',
  site_day_utilisation_formula: 'Site-day utilisation',
  survey_respondent_base: 'Survey response base',
  multi_select_note: 'Multi-select questions',
  financial_inclusion_rules: 'Financial inclusion',
  missing_data_rule: 'Missing data',
  data_quality_warnings: 'Data-quality warnings',
  potentially_incomplete_finances: 'Incomplete finances',
  data_availability: 'Data availability',
};

export function reportOptionLabel(value) {
  if (value == null || value === '') return '—';
  const raw = String(value).trim();
  const key = raw.toLowerCase();
  if (OPTION_LABELS[key]) return OPTION_LABELS[key];
  if (OPTION_LABELS[raw]) return OPTION_LABELS[raw];
  if (!raw.includes('_')) return raw;
  const spaced = raw.replaceAll('_', ' ');
  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function reportDistributionTitle(key) {
  return DISTRIBUTION_TITLES[key] || String(key).replaceAll('_', ' ');
}

export function reportMethodologyLabel(key) {
  return METHODOLOGY_LABELS[key] || String(key).replaceAll('_', ' ');
}

export function formatReportMoney(value) {
  if (value === undefined || value === null || value === '') return null;
  return `RM ${Number(value).toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function collectionRate(collected, expected) {
  if (collected == null || expected == null || Number(expected) <= 0) return null;
  return Math.round((Number(collected) / Number(expected)) * 1000) / 10;
}
