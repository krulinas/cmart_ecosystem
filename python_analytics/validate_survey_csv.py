"""Validate and normalize vendor_post_event_v1 CSV content."""

from __future__ import annotations

import csv
import io
from typing import Any

from survey_schema import (
    EVENT_INFO_SOURCE_COLUMNS,
    EXPERIENCE_RATINGS,
    GROSS_SALES_BANDS,
    IMPROVEMENT_AREA_COLUMNS,
    ITEM_CONDITION_COLUMNS,
    ITEMS_SOLD_BANDS,
    PRODUCT_CATEGORY_COLUMNS,
    REQUIRED_HEADERS,
    SALES_PURPOSES,
    SCHEMA_NAME,
    SCHEMA_VERSION,
    SUPPORTING_ACTIVITY_ATTRACTED,
    SUPPORTING_IMPACT_COLUMNS,
    UNSOLD_ACTION_COLUMNS,
)


def _cell(row: dict[str, str], key: str) -> str:
    value = row.get(key)
    if value is None:
        return ""
    return str(value).strip()


def _parse_binary(raw: str) -> tuple[int | None, str | None]:
    if raw == "":
        return None, None
    if raw in {"0", "1"}:
        return int(raw), None
    return None, f"Invalid binary value '{raw}' (expected 0, 1, or empty)"


def _selected_keys(row: dict[str, str], mapping: dict[str, str]) -> tuple[list[str], list[str], bool]:
    """Return (selected_keys, errors, any_answered)."""
    selected: list[str] = []
    errors: list[str] = []
    answered = False
    for column, key in mapping.items():
        parsed, err = _parse_binary(_cell(row, column))
        if err:
            errors.append(f"{column}: {err}")
            continue
        if parsed is None:
            continue
        answered = True
        if parsed == 1:
            selected.append(key)
    return selected, errors, answered


def _validate_row(row: dict[str, str], source_row_number: int) -> tuple[dict[str, Any] | None, list[dict[str, Any]], list[str]]:
    errors: list[dict[str, Any]] = []
    warnings: list[str] = []

    def add_error(field: str, message: str) -> None:
        errors.append({"source_row_number": source_row_number, "field": field, "message": message})

    respondent_id = _cell(row, "respondent_id")
    if not respondent_id:
        add_error("respondent_id", "respondent_id is required")

    product_categories, q1_errors, q1_answered = _selected_keys(row, PRODUCT_CATEGORY_COLUMNS)
    for message in q1_errors:
        add_error("product_categories", message)
    product_other = _cell(row, "q1_lain_lain_teks") or None
    if "lain_lain" in product_categories and not product_other:
        warnings.append("q1_lain_lain selected without companion text")

    item_conditions, q2_errors, _q2_answered = _selected_keys(row, ITEM_CONDITION_COLUMNS)
    for message in q2_errors:
        add_error("item_conditions", message)
    if "tidak_berkenaan" in item_conditions and (
        "baharu" in item_conditions or "terpakai" in item_conditions
    ):
        add_error("item_conditions", "tidak_berkenaan cannot combine with baharu/terpakai")

    has_difficulty_raw = _cell(row, "q3_kesukaran")
    has_difficulty, q3_err = _parse_binary(has_difficulty_raw)
    if q3_err:
        add_error("has_difficulty", q3_err)
    difficulty_details = _cell(row, "q3_kesukaran_teks") or None
    if has_difficulty == 1 and not difficulty_details:
        warnings.append("q3_kesukaran=1 without difficulty details")

    event_info_sources, q4_errors, _q4_answered = _selected_keys(row, EVENT_INFO_SOURCE_COLUMNS)
    for message in q4_errors:
        add_error("event_info_sources", message)
    event_info_other = _cell(row, "q4_lain_lain_teks") or None
    if "lain_lain" in event_info_sources and not event_info_other:
        warnings.append("q4_lain_lain selected without companion text")

    items_sold_band = _cell(row, "q5_barang_terjual") or None
    if items_sold_band and items_sold_band not in ITEMS_SOLD_BANDS:
        add_error("items_sold_band", f"Unknown items_sold_band '{items_sold_band}'")

    gross_sales_band = _cell(row, "q6_jualan_kasar") or None
    if gross_sales_band and gross_sales_band not in GROSS_SALES_BANDS:
        add_error("gross_sales_band", f"Unknown gross_sales_band '{gross_sales_band}'")

    unsold_item_actions, q7_errors, _q7_answered = _selected_keys(row, UNSOLD_ACTION_COLUMNS)
    for message in q7_errors:
        add_error("unsold_item_actions", message)
    action_flags = set(unsold_item_actions) - {"semua_terjual", "tidak_berkenaan"}
    if "semua_terjual" in unsold_item_actions and action_flags:
        add_error("unsold_item_actions", "semua_terjual cannot combine with other actions")
    if "tidak_berkenaan" in unsold_item_actions and (
        action_flags or "semua_terjual" in unsold_item_actions
    ):
        add_error("unsold_item_actions", "tidak_berkenaan cannot combine with other Q7 options")

    sales_purpose = _cell(row, "q8_tujuan_jualan") or None
    if sales_purpose and sales_purpose not in SALES_PURPOSES:
        add_error("sales_purpose", f"Unknown sales_purpose '{sales_purpose}'")

    experience_rating = _cell(row, "q9_pengalaman") or None
    if experience_rating and experience_rating not in EXPERIENCE_RATINGS:
        add_error("experience_rating", f"Unknown experience_rating '{experience_rating}'")

    improvement_areas, q10_errors, _q10_answered = _selected_keys(row, IMPROVEMENT_AREA_COLUMNS)
    for message in q10_errors:
        add_error("improvement_areas", message)
    improvement_other = set(improvement_areas) - {"tiada_penambahbaikan"}
    if "tiada_penambahbaikan" in improvement_areas and improvement_other:
        add_error("improvement_areas", "tiada_penambahbaikan cannot combine with other improvements")
    improvement_other_text = _cell(row, "q10_lain_lain_teks") or None
    if "lain_lain" in improvement_areas and not improvement_other_text:
        warnings.append("q10_lain_lain selected without companion text")

    comments = _cell(row, "q11_komen_cadangan") or None

    supporting_activity = _cell(row, "q12_aktiviti_tarik_pengunjung") or None
    if supporting_activity and supporting_activity not in SUPPORTING_ACTIVITY_ATTRACTED:
        add_error(
            "supporting_activity_attracted_visitors",
            f"Unknown supporting_activity_attracted_visitors '{supporting_activity}'",
        )

    supporting_impacts, q13_errors, _q13_answered = _selected_keys(row, SUPPORTING_IMPACT_COLUMNS)
    for message in q13_errors:
        add_error("supporting_activity_impacts", message)
    impact_other = set(supporting_impacts) - {"tiada_kesan"}
    if "tiada_kesan" in supporting_impacts and impact_other:
        add_error("supporting_activity_impacts", "tiada_kesan cannot combine with other impacts")
    supporting_impact_other_text = _cell(row, "q13_lain_lain_teks") or None
    if "lain_lain" in supporting_impacts and not supporting_impact_other_text:
        warnings.append("q13_lain_lain selected without companion text")

    if errors:
        return None, errors, warnings

    record = {
        "respondent_id": respondent_id,
        "source_row_number": source_row_number,
        "schema_name": SCHEMA_NAME,
        "schema_version": SCHEMA_VERSION,
        "product_categories": product_categories or None,
        "product_categories_other_text": product_other,
        "item_conditions": item_conditions or None,
        "has_difficulty": None if has_difficulty is None else bool(has_difficulty),
        "difficulty_details": difficulty_details,
        "event_info_sources": event_info_sources or None,
        "event_info_sources_other_text": event_info_other,
        "items_sold_band": items_sold_band,
        "gross_sales_band": gross_sales_band,
        "unsold_item_actions": unsold_item_actions or None,
        "sales_purpose": sales_purpose,
        "experience_rating": experience_rating,
        "improvement_areas": improvement_areas or None,
        "improvement_areas_other_text": improvement_other_text,
        "comments_and_suggestions": comments,
        "supporting_activity_attracted_visitors": supporting_activity,
        "supporting_activity_impacts": supporting_impacts or None,
        "supporting_activity_impacts_other_text": supporting_impact_other_text,
        "import_auto_review_flags": _cell(row, "semakan_automatik") or None,
        "import_review_notes": _cell(row, "catatan_semakan") or None,
        "validation_status": "valid",
        "_meta": {
            "q1_answered": q1_answered,
        },
    }
    return record, [], warnings


def validate_survey_csv_text(csv_text: str) -> dict[str, Any]:
    if csv_text.startswith("\ufeff"):
        csv_text = csv_text.lstrip("\ufeff")

    reader = csv.DictReader(io.StringIO(csv_text))
    if reader.fieldnames is None:
        return {
            "schema_name": SCHEMA_NAME,
            "schema_version": SCHEMA_VERSION,
            "valid": False,
            "total_rows": 0,
            "valid_rows": 0,
            "invalid_rows": 0,
            "normalized_records": [],
            "row_errors": [
                {"source_row_number": 1, "field": "headers", "message": "CSV has no header row"}
            ],
            "warnings": [],
            "observed_unknown_values": {},
            "data_completeness": {},
            "missing_headers": REQUIRED_HEADERS,
            "unexpected_headers": [],
        }

    headers = [h.strip() if h else "" for h in reader.fieldnames]
    missing = [h for h in REQUIRED_HEADERS if h not in headers]
    unexpected = [h for h in headers if h and h not in REQUIRED_HEADERS]

    row_errors: list[dict[str, Any]] = []
    warnings: list[str] = []
    normalized: list[dict[str, Any]] = []
    respondent_ids: list[str] = []
    unknown_values: dict[str, list[str]] = {}

    if missing:
        row_errors.append(
            {
                "source_row_number": 1,
                "field": "headers",
                "message": f"Missing required headers: {', '.join(missing)}",
            }
        )

    for index, raw_row in enumerate(reader, start=2):
        row = { (k.strip() if k else k): (v if v is not None else "") for k, v in raw_row.items() }
        record, errors, row_warnings = _validate_row(row, index)
        warnings.extend([f"row {index}: {w}" for w in row_warnings])
        if errors:
            row_errors.extend(errors)
            continue
        assert record is not None
        rid = record["respondent_id"]
        if rid in respondent_ids:
            row_errors.append(
                {
                    "source_row_number": index,
                    "field": "respondent_id",
                    "message": f"Duplicate respondent_id '{rid}' within import",
                }
            )
            continue
        respondent_ids.append(rid)
        meta = record.pop("_meta", {})
        normalized.append(record)
        if not meta.get("q1_answered"):
            warnings.append(f"row {index}: Q1 unanswered")

    physical_rows = 0
    for _ in csv.DictReader(io.StringIO(csv_text)):
        physical_rows += 1

    invalid_row_numbers = {
        e["source_row_number"]
        for e in row_errors
        if e.get("field") != "headers" and e.get("source_row_number")
    }
    invalid_rows = len(invalid_row_numbers) if invalid_row_numbers else max(
        physical_rows - len(normalized), 0
    )

    completeness = {
        "respondents_with_gross_sales_band": sum(
            1 for r in normalized if r.get("gross_sales_band")
        ),
        "respondents_with_experience_rating": sum(
            1 for r in normalized if r.get("experience_rating")
        ),
        "respondents_with_product_categories": sum(
            1 for r in normalized if r.get("product_categories")
        ),
    }

    valid = len(missing) == 0 and invalid_rows == 0 and physical_rows > 0

    return {
        "schema_name": SCHEMA_NAME,
        "schema_version": SCHEMA_VERSION,
        "valid": valid,
        "total_rows": physical_rows,
        "valid_rows": len(normalized),
        "invalid_rows": max(physical_rows - len(normalized), 0),
        "normalized_records": normalized,
        "row_errors": row_errors,
        "warnings": warnings,
        "observed_unknown_values": unknown_values,
        "data_completeness": completeness,
        "missing_headers": missing,
        "unexpected_headers": unexpected,
    }
