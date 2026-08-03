# 09 — Survey Schema Contract: `vendor_post_event_v1`

**Amendment date:** 2026-08-02 (UTC+8)  
**Schema name:** `vendor_post_event_v1`  
**Instrument type:** Vendor post-event survey (Soal Selidik Vendor Carboot@CMart)  
**Source artefacts (external to repo):**

| Artefact | Path (local) | Role |
|----------|--------------|------|
| Data-entry template | `Carboot_Data_Entry_Template_Complete.xlsx` | Canonical 53-column header + Codebook + Panduan |
| Sample CSV export | `Carboot_Data_Entry_Template_Complete.xlsm - Data_Entry.csv` | 9 sample response rows |
| Questionnaire PDFs | `Final Instrument Carboot Vendors*.pdf`, `Final_Instrument Carboot (BM).pdf` | Instrument wording |
| Partial cleaning sheet | `Data Cleaning.xlsx` | Confirms Q1–Q7 Malay question stems |

**Important:** Observed sample values (n=9) do **not** necessarily represent the complete allowed enum set. Canonical allowed values below are taken from the **Codebook** sheet / questionnaire template. Sample-only observations are labelled **observed, not necessarily exhaustive**.

---

## 1. Identity and event attribution rules

| Rule | Decision |
|------|----------|
| Event assignment | `carboot_event_id` is assigned from the **organizer upload route** (selected event at import time). It is **not** required as a column in every CSV row. |
| `respondent_id` | Source-file identifier (e.g. `R001` or `1`). **Not globally unique** across imports or events. |
| Safe row identity | Composite: `import_batch_id + respondent_id` |
| Source row number | Preserve original CSV physical row number (`source_row_number`) |
| Raw file retention | Store original uploaded file in `raw_survey_uploads` (or equivalent) |
| Separation from live feedback | Vendor survey responses stay in **`survey_responses`** (or successor). Do **not** merge into community `feedbacks`. |

---

## 2. Questionnaire grouping (Q1–Q13)

| Q | Malay stem (from instrument / Data Cleaning) | Analytic intent | Selectivity |
|---|----------------------------------------------|-----------------|-------------|
| Q1 | Kategori jualan | Product / item categories | Multi-select (one-hot) + optional free text |
| Q2 | Barangan yang anda jual ialah | New / used / N/A classification | Multi-select with exclusivity rules |
| Q3 | Kesukaran mendaftar atau mendapatkan maklumat acara | Operational difficulty | Single Yes/No + optional free text |
| Q4 | Di manakah anda mendapat maklumat acara ini? | **Vendor event-information source** (how vendors learned about the event) | Multi-select + optional free text |
| Q5 | Anggaran barangan **terpakai** yang berjaya dijual | Used-item sell-through band | Single-select categorical |
| Q6 | Anggaran jualan kasar hari ini | Gross sales band | Single-select categorical |
| Q7 | Tindakan terhadap barangan terpakai tidak terjual | Unsold-item actions / circularity proxy | Multi-select with exclusivity |
| Q8 | Jualan Carboot@CMart ialah | Purpose of selling | Single-select categorical |
| Q9 | Pengalaman vendor hari ini | Experience / satisfaction | Single-select categorical |
| Q10 | Perlu ditambah baik | Improvement areas | Multi-select + optional free text |
| Q11 | Komen atau cadangan lain | Comments and suggestions | Free text |
| Q12 | Aktiviti tambahan menarik lebih ramai pengunjung | Supporting-activity effectiveness | Single-select categorical |
| Q13 | Kesan acara / aktiviti serentak | Supporting-activity impacts | Multi-select + optional free text |
| — | `semakan_automatik`, `catatan_semakan` | Import-review / validation outputs | Not participant responses |

**Clarification on Q4:** Questionnaire wording confirms this is **where the vendor obtained event information**, not organizer outbound promotional-channel tracking and not visitor discovery. Normalized field name: `event_info_sources` (do not mislabel as visitor discovery or generic “promotion channels”).

---

## 3. Full column catalogue (53 columns)

### Legend

- **Req:** R = required for a complete form; O = optional companion / may be blank when unanswered  
- **Select:** MS = multi-select one-hot; SS = single-select; TXT = free text; ID = identifier; VAL = validation/review  
- **Storage target:** See §5

| # | CSV column | Q | Type | Req | Select | Normalization target | Canonical allowed values (Codebook) | Observed in sample (n=9) | Notes |
|---|------------|---|------|-----|--------|----------------------|-------------------------------------|--------------------------|-------|
| 1 | `respondent_id` | ID | string | R | ID | `respondent_id` | Unique within file | `1`…`9` (**observed**) | Template prefers `R001` style; sample used numeric |
| 2 | `q1_makanan_minuman` | Q1 | binary 0/1/empty | O* | MS | `product_categories[]` ← `makanan_minuman` | 1 / 0 / empty | 1 and 0 | *At least one Q1 option expected if Q1 answered |
| 3 | `q1_pakaian_fesyen` | Q1 | binary | O* | MS | `product_categories[]` ← `pakaian_fesyen` | 1 / 0 / empty | 1 and 0 | |
| 4 | `q1_elektrik_elektronik_gajet` | Q1 | binary | O* | MS | `product_categories[]` ← `elektrik_elektronik_gajet` | 1 / 0 / empty | 0 only (**observed**) | Option confirmed by Codebook; not selected in sample |
| 5 | `q1_barangan_rumah_keperluan_harian` | Q1 | binary | O* | MS | `product_categories[]` ← `barangan_rumah_keperluan_harian` | 1 / 0 / empty | 0 only (**observed**) | |
| 6 | `q1_buku_mainan_kanak_kanak` | Q1 | binary | O* | MS | `product_categories[]` ← `buku_mainan_kanak_kanak` | 1 / 0 / empty | 1 and 0 | |
| 7 | `q1_kecantikan_penjagaan_diri` | Q1 | binary | O* | MS | `product_categories[]` ← `kecantikan_penjagaan_diri` | 1 / 0 / empty | 1 and 0 | |
| 8 | `q1_perkhidmatan_promosi` | Q1 | binary | O* | MS | `product_categories[]` ← `perkhidmatan_promosi` | 1 / 0 / empty | 0 only (**observed**) | |
| 9 | `q1_lain_lain` | Q1 | binary | O* | MS | `product_categories[]` ← `lain_lain` | 1 / 0 / empty | 0 only (**observed**) | |
| 10 | `q1_lain_lain_teks` | Q1 | text | O | TXT | `product_categories_other_text` | Free text | empty | Required if `q1_lain_lain=1` |
| 11 | `q2_baharu` | Q2 | binary | O* | MS | `item_conditions[]` ← `baharu` | 1 / 0 / empty | 1 and 0 | |
| 12 | `q2_terpakai` | Q2 | binary | O* | MS | `item_conditions[]` ← `terpakai` | 1 / 0 / empty | 1 and 0 | |
| 13 | `q2_tidak_berkenaan` | Q2 | binary | O* | MS | `item_conditions[]` ← `tidak_berkenaan` | 1 / 0 / empty | 1 and 0 | Must not combine with baharu/terpakai |
| 14 | `q3_kesukaran` | Q3 | binary | O | SS | `has_difficulty` | 1=Ya; 0=Tidak; empty | 0 only (**observed**) | Ya (1) confirmed by Codebook; not in sample |
| 15 | `q3_kesukaran_teks` | Q3 | text | O | TXT | `difficulty_details` | Free text | empty | Fill only if Ya |
| 16 | `q4_whatsapp` | Q4 | binary | O* | MS | `event_info_sources[]` ← `whatsapp` | 1 / 0 / empty | 1 and 0 | Vendor info source |
| 17 | `q4_media_sosial` | Q4 | binary | O* | MS | `event_info_sources[]` ← `media_sosial` | 1 / 0 / empty | 1 and 0 | |
| 18 | `q4_rakan_kenalan` | Q4 | binary | O* | MS | `event_info_sources[]` ← `rakan_kenalan` | 1 / 0 / empty | 1 and 0 | |
| 19 | `q4_pihak_penganjur` | Q4 | binary | O* | MS | `event_info_sources[]` ← `pihak_penganjur` | 1 / 0 / empty | 1 and 0 | |
| 20 | `q4_lain_lain` | Q4 | binary | O* | MS | `event_info_sources[]` ← `lain_lain` | 1 / 0 / empty | 0 only (**observed**) | |
| 21 | `q4_lain_lain_teks` | Q4 | text | O | TXT | `event_info_sources_other_text` | Free text | empty | |
| 22 | `q5_barang_terjual` | Q5 | category | O | SS | `items_sold_band` | See §4.1 | 3 of 4 bands (**observed**) | Applies to **used goods** |
| 23 | `q6_jualan_kasar` | Q6 | category | O | SS | `gross_sales_band` | See §4.2 | All 4 bands (**observed**) | **Do not convert to exact RM** |
| 24 | `q7_simpan_acara_lain` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `simpan_acara_lain` | 1 / 0 / empty | 1 and 0 | Circularity proxy |
| 25 | `q7_jual_dalam_talian` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `jual_dalam_talian` | 1 / 0 / empty | 1 and 0 | |
| 26 | `q7_sumbangkan` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `sumbangkan` | 1 / 0 / empty | 0 only (**observed**) | |
| 27 | `q7_kitar_semula` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `kitar_semula` | 1 / 0 / empty | 0 only (**observed**) | |
| 28 | `q7_buang` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `buang` | 1 / 0 / empty | 0 only (**observed**) | |
| 29 | `q7_semua_terjual` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `semua_terjual` | 1 / 0 / empty | 0 only (**observed**) | Exclusive of action options |
| 30 | `q7_tidak_berkenaan` | Q7 | binary | O* | MS | `unsold_item_actions[]` ← `tidak_berkenaan` | 1 / 0 / empty | 1 and 0 | Exclusive of action options |
| 31 | `q8_tujuan_jualan` | Q8 | category | O | SS | `sales_purpose` | See §4.3 | 2 of 3 (**observed**) | |
| 32 | `q9_pengalaman` | Q9 | category | O | SS | `experience_rating` | See §4.4 | 2 of 4 (**observed**) | |
| 33 | `q10_promosi_hebahan` | Q10 | binary | O* | MS | `improvement_areas[]` ← `promosi_hebahan` | 1 / 0 / empty | 1 and 0 | |
| 34 | `q10_jumlah_pengunjung` | Q10 | binary | O* | MS | `improvement_areas[]` ← `jumlah_pengunjung` | 1 / 0 / empty | 1 and 0 | |
| 35 | `q10_susun_atur_ruang` | Q10 | binary | O* | MS | `improvement_areas[]` ← `susun_atur_ruang` | 1 / 0 / empty | 1 and 0 | |
| 36 | `q10_kemudahan_lokasi` | Q10 | binary | O* | MS | `improvement_areas[]` ← `kemudahan_lokasi` | 1 / 0 / empty | 0 only (**observed**) | |
| 37 | `q10_pendaftaran_penyertaan` | Q10 | binary | O* | MS | `improvement_areas[]` ← `pendaftaran_penyertaan` | 1 / 0 / empty | 0 only (**observed**) | |
| 38 | `q10_pengurusan_acara` | Q10 | binary | O* | MS | `improvement_areas[]` ← `pengurusan_acara` | 1 / 0 / empty | 1 and 0 | |
| 39 | `q10_tiada_penambahbaikan` | Q10 | binary | O* | MS | `improvement_areas[]` ← `tiada_penambahbaikan` | 1 / 0 / empty | 0 only (**observed**) | Exclusive |
| 40 | `q10_lain_lain` | Q10 | binary | O* | MS | `improvement_areas[]` ← `lain_lain` | 1 / 0 / empty | 1 and 0 | |
| 41 | `q10_lain_lain_teks` | Q10 | text | O | TXT | `improvement_areas_other_text` | Free text | 3 free-text values (**observed**) | |
| 42 | `q11_komen_cadangan` | Q11 | text | O | TXT | `comments_and_suggestions` | Free text | `Tiada` only (**observed**) | |
| 43 | `q12_aktiviti_tarik_pengunjung` | Q12 | category | O | SS | `supporting_activity_attracted_visitors` | See §4.5 | Ya, Tidak pasti (**observed**) | `Tidak` confirmed by Codebook; not in sample |
| 44 | `q13_peluang_jualan_meningkat` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `peluang_jualan_meningkat` | 1 / 0 / empty | 1 and 0 | |
| 45 | `q13_suasana_meriah` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `suasana_meriah` | 1 / 0 / empty | 1 and 0 | |
| 46 | `q13_kawasan_sesak` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `kawasan_sesak` | 1 / 0 / empty | 0 only (**observed**) | |
| 47 | `q13_suasana_bising` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `suasana_bising` | 1 / 0 / empty | 1 and 0 | |
| 48 | `q13_perhatian_pengunjung_teralih` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `perhatian_pengunjung_teralih` | 1 / 0 / empty | 1 and 0 | |
| 49 | `q13_tiada_kesan` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `tiada_kesan` | 1 / 0 / empty | 1 and 0 | Exclusive of other impacts |
| 50 | `q13_lain_lain` | Q13 | binary | O* | MS | `supporting_activity_impacts[]` ← `lain_lain` | 1 / 0 / empty | 1 and 0 | |
| 51 | `q13_lain_lain_teks` | Q13 | text | O | TXT | `supporting_activity_impacts_other_text` | Free text | 1 free-text value (**observed**) | |
| 52 | `semakan_automatik` | — | formula/text | O | VAL | `import_auto_review_flags` (batch/row meta) | Empty = no issue | empty | **Exclude from analytics metrics** |
| 53 | `catatan_semakan` | — | text | O | VAL | `import_review_notes` | Free text | empty | **Exclude from analytics metrics**; reviewer notes |

---

## 4. Confirmed categorical enums (Codebook)

### 4.1 `items_sold_band` (Q5) — confirmed

| Canonical value (BM) | Notes |
|----------------------|-------|
| `Suku (25%)` | Observed |
| `Separuh (50%)` | Observed |
| `Hampir habis (75%-100%)` | Confirmed by Codebook; **not observed in sample** |
| `Tiada (hanya jual barang baharu/makanan)` | Observed |

### 4.2 `gross_sales_band` (Q6) — confirmed

| Canonical value (BM) | Notes |
|----------------------|-------|
| `Kurang daripada RM50` | Observed |
| `RM51 hingga RM150` | Observed |
| `RM151 hingga RM300` | Observed |
| `Melebihi RM300` | Observed |

**Rule:** Analyse as categorical bands only. Never midpoint-impute to exact RM totals without a separate documented methodology.

### 4.3 `sales_purpose` (Q8) — confirmed

| Canonical value (BM) | Notes |
|----------------------|-------|
| `Pendapatan Utama` | Confirmed by Codebook; **not observed in sample** |
| `Pendapatan Sampingan` | Observed |
| `Hobi / Mengosongkan ruang rumah` | Observed |

### 4.4 `experience_rating` (Q9) — confirmed

| Canonical value (BM) | Notes |
|----------------------|-------|
| `Sangat tidak memuaskan` | Confirmed by Codebook; **not observed in sample** |
| `Kurang memuaskan` | Confirmed by Codebook; **not observed in sample** |
| `Memuaskan` | Observed |
| `Sangat memuaskan` | Observed |

### 4.5 `supporting_activity_attracted_visitors` (Q12) — confirmed

| Canonical value (BM) | Notes |
|----------------------|-------|
| `Ya` | Observed |
| `Tidak pasti` | Observed |
| `Tidak` | Confirmed by Codebook; **not observed in sample** |

### 4.6 Binary coding — confirmed

For all one-hot groups: `1` = selected, `0` = not selected, empty = question unanswered (Panduan rule 5: do not fill 0 for entirely unanswered questions).

---

## 5. Normalized storage mapping (hybrid)

### Design choice: hybrid relational + JSON

Prefer a maintainable schema that supports future questionnaire versions (`vendor_post_event_v2`, …):

| Layer | Table (proposed) | Purpose |
|-------|------------------|---------|
| Raw | `raw_survey_uploads` | File blob path, event id, uploader, status, schema version |
| Normalized row | `survey_responses` | One row per respondent after validation |
| Optional EAV / versioned JSON | `survey_response_payload` JSON column on same row | Preserve unmapped future columns |

### Recommended `survey_responses` columns

| Column | SQL type | Source |
|--------|----------|--------|
| `id` | bigint PK | System |
| `import_batch_id` | FK | Upload batch |
| `carboot_event_id` | FK | From upload route |
| `schema_version` | string | `vendor_post_event_v1` |
| `respondent_id` | string | CSV |
| `source_row_number` | int | Physical CSV row |
| `product_categories` | JSON array | Q1 one-hots |
| `product_categories_other_text` | text nullable | Q1 |
| `item_conditions` | JSON array | Q2 |
| `has_difficulty` | boolean nullable | Q3 |
| `difficulty_details` | text nullable | Q3 |
| `event_info_sources` | JSON array | Q4 (**not** visitor discovery) |
| `event_info_sources_other_text` | text nullable | Q4 |
| `items_sold_band` | string nullable | Q5 |
| `gross_sales_band` | string nullable | Q6 |
| `unsold_item_actions` | JSON array | Q7 |
| `sales_purpose` | string nullable | Q8 |
| `experience_rating` | string nullable | Q9 |
| `improvement_areas` | JSON array | Q10 |
| `improvement_areas_other_text` | text nullable | Q10 |
| `comments_and_suggestions` | text nullable | Q11 |
| `supporting_activity_attracted_visitors` | string nullable | Q12 |
| `supporting_activity_impacts` | JSON array | Q13 |
| `supporting_activity_impacts_other_text` | text nullable | Q13 |
| `import_auto_review_flags` | text/JSON nullable | `semakan_automatik` |
| `import_review_notes` | text nullable | `catatan_semakan` |
| `validation_status` | enum | `valid` / `warning` / `invalid` |
| `created_at` / `updated_at` | timestamps | System |

**Unique key:** `(import_batch_id, respondent_id)`.

**Why hybrid:** Multi-select questions change between instrument versions; JSON arrays avoid schema churn. Scalar single-selects remain typed columns for easy SQL aggregation.

---

## 6. Validation rules

| Rule ID | Rule |
|---------|------|
| V-01 | Header must match exact 53 column names for `vendor_post_event_v1` |
| V-02 | `respondent_id` required, unique within file |
| V-03 | Binary cells must be `1`, `0`, or empty |
| V-04 | If all binary columns of a question are empty → treat question as unanswered (not as all-false) |
| V-05 | Q2: `tidak_berkenaan=1` must not coexist with `baharu=1` or `terpakai=1` |
| V-06 | Q7: `semua_terjual=1` or `tidak_berkenaan=1` must not coexist with other action flags |
| V-07 | Q10: `tiada_penambahbaikan=1` must not coexist with other improvement flags |
| V-08 | Q13: `tiada_kesan=1` must not coexist with other impact flags |
| V-09 | Free-text companion required when corresponding `lain_lain=1` (warning if missing) |
| V-10 | Q3 text expected when `q3_kesukaran=1` (warning if missing) |
| V-11 | Categorical fields must match Codebook enum set (reject unknown strings) |
| V-12 | Retain rows with warnings; quarantine only hard invalids (missing id, unknown headers, illegal categorical) |

---

## 7. Null-handling rules

| Case | Handling |
|------|----------|
| Entire question unanswered (all related cells empty) | Normalized field = `null` / empty array; do not coerce to 0 |
| Binary `0` present | Explicit non-selection; include in denominator for answered questions |
| Free text empty | Store `null` |
| `q11_komen_cadangan` = `Tiada` | Store literal string; analytics may treat as non-substantive comment |
| Validation columns empty | No import warning |

---

## 8. Deduplication rules

1. Within one import batch: duplicate `respondent_id` → reject second occurrence.  
2. Across batches for same `carboot_event_id`: default **allow multiple batches**; latest valid batch may supersede earlier batch for reporting if organizer chooses “replace event survey data”.  
3. Do not dedupe by free-text similarity.  
4. Do not link to `users.id` unless a future instrument adds an explicit vendor account identifier.

---

## 9. Privacy considerations

- Survey rows may contain free-text that identifies vendors or third parties — restrict raw row access to organizer/super_admin.  
- Published Post-Event Summary must use **aggregates only** (band counts, multi-select frequencies).  
- Do not publish individual `comments_and_suggestions` without redaction policy.  
- `respondent_id` is a form ID, not a public user handle — still treat as potentially identifying within an event.  
- Small-n suppression (e.g. n < 5) for cross-tabs (category × sales band).

---

## 10. Metrics enabled by each question

| Q | Metrics enabled after import |
|---|------------------------------|
| Q1 | Survey product-category distribution; compare with booking categories |
| Q2 | New vs used vs N/A mix; filter for reuse-relevant subset |
| Q3 | % vendors reporting registration/info difficulty; qualitative themes |
| Q4 | Vendor event-information source mix |
| Q5 | Used-item sell-through band distribution |
| Q6 | Gross sales band distribution (categorical) |
| Q7 | Unsold-item action mix; circularity / divert-from-waste proxies (`sumbangkan`, `kitar_semula`, `simpan_acara_lain` vs `buang`) |
| Q8 | Purpose-of-selling distribution |
| Q9 | Vendor experience / satisfaction distribution |
| Q10 | Improvement priority ranking |
| Q11 | Suggestion themes (text analytics) |
| Q12 | Perceived effectiveness of supporting activities |
| Q13 | Positive/negative impact mix of concurrent activities |

---

## 11. Columns excluded from analytics

| Column | Reason |
|--------|--------|
| `semakan_automatik` | Template formula / import-review flag, not participant response |
| `catatan_semakan` | Human reviewer note during data entry |
| System fields after import (`validation_status`, paths) | Operational metadata |

---

## 12. One-hot groups summary

| Group | Columns | Exclusive notes |
|-------|---------|-----------------|
| Q1 product categories | 8 binaries + teks | Multi OK |
| Q2 item conditions | 3 binaries | `tidak_berkenaan` exclusive vs baharu/terpakai |
| Q4 event info sources | 5 binaries + teks | Multi OK |
| Q7 unsold actions | 7 binaries | `semua_terjual` / `tidak_berkenaan` exclusive vs actions |
| Q10 improvements | 8 binaries + teks | `tiada_penambahbaikan` exclusive |
| Q13 activity impacts | 7 binaries + teks | `tiada_kesan` exclusive |

---

## 13. Free-text companion fields

| Companion | Parent flag |
|-----------|-------------|
| `q1_lain_lain_teks` | `q1_lain_lain` |
| `q3_kesukaran_teks` | `q3_kesukaran=1` |
| `q4_lain_lain_teks` | `q4_lain_lain` |
| `q10_lain_lain_teks` | `q10_lain_lain` |
| `q11_komen_cadangan` | Standalone |
| `q13_lain_lain_teks` | `q13_lain_lain` |

---

## 14. Sample dataset note

Current sample CSV contains **9 respondent rows**. Several Codebook-confirmed options never appear (e.g. `Hampir habis (75%-100%)`, `Pendapatan Utama`, `Sangat tidak memuaskan`, `Kurang memuaskan`, `Tidak` on Q12, and several binary options with zeros only). Validators and UI dropdowns must use the **Codebook set**, not the sample-observed subset.
