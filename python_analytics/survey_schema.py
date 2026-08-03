"""Canonical vendor_post_event_v1 survey schema contract.

Enums are taken from the instrument Codebook. Sample-observed values are not
treated as the complete allowed set.
"""

from __future__ import annotations

SCHEMA_NAME = "vendor_post_event_v1"
SCHEMA_VERSION = "1"

REQUIRED_HEADERS: list[str] = [
    "respondent_id",
    "q1_makanan_minuman",
    "q1_pakaian_fesyen",
    "q1_elektrik_elektronik_gajet",
    "q1_barangan_rumah_keperluan_harian",
    "q1_buku_mainan_kanak_kanak",
    "q1_kecantikan_penjagaan_diri",
    "q1_perkhidmatan_promosi",
    "q1_lain_lain",
    "q1_lain_lain_teks",
    "q2_baharu",
    "q2_terpakai",
    "q2_tidak_berkenaan",
    "q3_kesukaran",
    "q3_kesukaran_teks",
    "q4_whatsapp",
    "q4_media_sosial",
    "q4_rakan_kenalan",
    "q4_pihak_penganjur",
    "q4_lain_lain",
    "q4_lain_lain_teks",
    "q5_barang_terjual",
    "q6_jualan_kasar",
    "q7_simpan_acara_lain",
    "q7_jual_dalam_talian",
    "q7_sumbangkan",
    "q7_kitar_semula",
    "q7_buang",
    "q7_semua_terjual",
    "q7_tidak_berkenaan",
    "q8_tujuan_jualan",
    "q9_pengalaman",
    "q10_promosi_hebahan",
    "q10_jumlah_pengunjung",
    "q10_susun_atur_ruang",
    "q10_kemudahan_lokasi",
    "q10_pendaftaran_penyertaan",
    "q10_pengurusan_acara",
    "q10_tiada_penambahbaikan",
    "q10_lain_lain",
    "q10_lain_lain_teks",
    "q11_komen_cadangan",
    "q12_aktiviti_tarik_pengunjung",
    "q13_peluang_jualan_meningkat",
    "q13_suasana_meriah",
    "q13_kawasan_sesak",
    "q13_suasana_bising",
    "q13_perhatian_pengunjung_teralih",
    "q13_tiada_kesan",
    "q13_lain_lain",
    "q13_lain_lain_teks",
    "semakan_automatik",
    "catatan_semakan",
]

PRODUCT_CATEGORY_COLUMNS = {
    "q1_makanan_minuman": "makanan_minuman",
    "q1_pakaian_fesyen": "pakaian_fesyen",
    "q1_elektrik_elektronik_gajet": "elektrik_elektronik_gajet",
    "q1_barangan_rumah_keperluan_harian": "barangan_rumah_keperluan_harian",
    "q1_buku_mainan_kanak_kanak": "buku_mainan_kanak_kanak",
    "q1_kecantikan_penjagaan_diri": "kecantikan_penjagaan_diri",
    "q1_perkhidmatan_promosi": "perkhidmatan_promosi",
    "q1_lain_lain": "lain_lain",
}

ITEM_CONDITION_COLUMNS = {
    "q2_baharu": "baharu",
    "q2_terpakai": "terpakai",
    "q2_tidak_berkenaan": "tidak_berkenaan",
}

EVENT_INFO_SOURCE_COLUMNS = {
    "q4_whatsapp": "whatsapp",
    "q4_media_sosial": "media_sosial",
    "q4_rakan_kenalan": "rakan_kenalan",
    "q4_pihak_penganjur": "pihak_penganjur",
    "q4_lain_lain": "lain_lain",
}

UNSOLD_ACTION_COLUMNS = {
    "q7_simpan_acara_lain": "simpan_acara_lain",
    "q7_jual_dalam_talian": "jual_dalam_talian",
    "q7_sumbangkan": "sumbangkan",
    "q7_kitar_semula": "kitar_semula",
    "q7_buang": "buang",
    "q7_semua_terjual": "semua_terjual",
    "q7_tidak_berkenaan": "tidak_berkenaan",
}

IMPROVEMENT_AREA_COLUMNS = {
    "q10_promosi_hebahan": "promosi_hebahan",
    "q10_jumlah_pengunjung": "jumlah_pengunjung",
    "q10_susun_atur_ruang": "susun_atur_ruang",
    "q10_kemudahan_lokasi": "kemudahan_lokasi",
    "q10_pendaftaran_penyertaan": "pendaftaran_penyertaan",
    "q10_pengurusan_acara": "pengurusan_acara",
    "q10_tiada_penambahbaikan": "tiada_penambahbaikan",
    "q10_lain_lain": "lain_lain",
}

SUPPORTING_IMPACT_COLUMNS = {
    "q13_peluang_jualan_meningkat": "peluang_jualan_meningkat",
    "q13_suasana_meriah": "suasana_meriah",
    "q13_kawasan_sesak": "kawasan_sesak",
    "q13_suasana_bising": "suasana_bising",
    "q13_perhatian_pengunjung_teralih": "perhatian_pengunjung_teralih",
    "q13_tiada_kesan": "tiada_kesan",
    "q13_lain_lain": "lain_lain",
}

ITEMS_SOLD_BANDS = {
    "Suku (25%)",
    "Separuh (50%)",
    "Hampir habis (75%-100%)",
    "Tiada (hanya jual barang baharu/makanan)",
}

GROSS_SALES_BANDS = {
    "Kurang daripada RM50",
    "RM51 hingga RM150",
    "RM151 hingga RM300",
    "Melebihi RM300",
}

SALES_PURPOSES = {
    "Pendapatan Utama",
    "Pendapatan Sampingan",
    "Hobi / Mengosongkan ruang rumah",
}

EXPERIENCE_RATINGS = {
    "Sangat tidak memuaskan",
    "Kurang memuaskan",
    "Memuaskan",
    "Sangat memuaskan",
}

SUPPORTING_ACTIVITY_ATTRACTED = {
    "Ya",
    "Tidak pasti",
    "Tidak",
}

CIRCULARITY_POSITIVE_ACTIONS = {
    "simpan_acara_lain",
    "jual_dalam_talian",
    "sumbangkan",
    "kitar_semula",
}

CIRCULARITY_NEGATIVE_ACTIONS = {
    "buang",
}

CALCULATION_VERSION = "survey_analytics_v1"
SMALL_SAMPLE_THRESHOLD = 5
