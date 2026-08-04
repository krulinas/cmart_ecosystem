"""Descriptive event-level aggregations for vendor_post_event_v1 responses."""

from __future__ import annotations

from collections import Counter
from datetime import datetime, timezone
from typing import Any

from survey_schema import (
    CALCULATION_VERSION,
    CIRCULARITY_NEGATIVE_ACTIONS,
    CIRCULARITY_POSITIVE_ACTIONS,
    EVENT_INFO_SOURCE_ORDER,
    GROSS_SALES_BAND_ORDER,
    ITEMS_SOLD_BAND_ORDER,
    PRODUCT_CATEGORY_ORDER,
    SALES_PURPOSE_ORDER,
    SCHEMA_NAME,
    SCHEMA_VERSION,
    SMALL_SAMPLE_THRESHOLD,
)


def _pct(count: int, denominator: int) -> float | None:
    if denominator <= 0:
        return None
    return round((count / denominator) * 100, 1)


def _distribution(counter: Counter, denominator: int, ordered_keys: list[str] | None = None) -> list[dict[str, Any]]:
    keys = ordered_keys if ordered_keys is not None else sorted(counter.keys(), key=lambda k: (-counter[k], str(k)))
    rows = []
    for key in keys:
        count = int(counter.get(key, 0))
        rows.append(
            {
                "key": key,
                "label": key,
                "count": count,
                "denominator": denominator,
                "percent": _pct(count, denominator),
                "display": f"{count} of {denominator} respondents ({_pct(count, denominator)}%)"
                if denominator
                else f"{count} of 0 respondents",
            }
        )
    # Include any unexpected keys not in ordered list
    if ordered_keys is not None:
        for key, count in counter.items():
            if key not in ordered_keys:
                rows.append(
                    {
                        "key": key,
                        "label": key,
                        "count": int(count),
                        "denominator": denominator,
                        "percent": _pct(int(count), denominator),
                        "display": f"{count} of {denominator} respondents ({_pct(int(count), denominator)}%)",
                    }
                )
    return rows


def _multi_select_counter(records: list[dict[str, Any]], field: str) -> Counter:
    counter: Counter = Counter()
    for record in records:
        values = record.get(field) or []
        if isinstance(values, list):
            for value in values:
                if value:
                    counter[str(value)] += 1
    return counter


def aggregate_survey_records(
    records: list[dict[str, Any]],
    *,
    carboot_event_id: int | None = None,
    import_batch_id: int | None = None,
    source_fingerprint: str | None = None,
) -> dict[str, Any]:
    n = len(records)
    now = datetime.now(timezone.utc).isoformat()

    product_counter = _multi_select_counter(records, "product_categories")
    condition_counter = _multi_select_counter(records, "item_conditions")
    info_counter = _multi_select_counter(records, "event_info_sources")
    unsold_counter = _multi_select_counter(records, "unsold_item_actions")
    improvement_counter = _multi_select_counter(records, "improvement_areas")
    impact_counter = _multi_select_counter(records, "supporting_activity_impacts")

    difficulty_yes = sum(1 for r in records if r.get("has_difficulty") is True)
    difficulty_no = sum(1 for r in records if r.get("has_difficulty") is False)
    difficulty_answered = difficulty_yes + difficulty_no

    items_sold = Counter(r["items_sold_band"] for r in records if r.get("items_sold_band"))
    gross_sales = Counter(r["gross_sales_band"] for r in records if r.get("gross_sales_band"))
    purpose = Counter(r["sales_purpose"] for r in records if r.get("sales_purpose"))
    experience = Counter(r["experience_rating"] for r in records if r.get("experience_rating"))
    supporting = Counter(
        r["supporting_activity_attracted_visitors"]
        for r in records
        if r.get("supporting_activity_attracted_visitors")
    )

    circularity_positive = sum(
        1
        for r in records
        if any(a in CIRCULARITY_POSITIVE_ACTIONS for a in (r.get("unsold_item_actions") or []))
    )
    circularity_negative = sum(
        1
        for r in records
        if any(a in CIRCULARITY_NEGATIVE_ACTIONS for a in (r.get("unsold_item_actions") or []))
    )
    used_goods_vendors = sum(
        1 for r in records if "terpakai" in (r.get("item_conditions") or [])
    )

    comments = []
    qualitative_groups = {
        "operational_difficulties": [],
        "improvement_suggestions": [],
        "general_comments": [],
        "supporting_activity_impacts": [],
        "other_responses": [],
    }
    NON_SUBSTANTIVE = {
        "tiada",
        "tiada komen",
        "tiada cadangan",
        "tidak ada",
        "n/a",
        "na",
        "none",
        "-",
        "—",
    }

    def _is_substantive(value: Any) -> bool:
        text = (value or "").strip() if isinstance(value, str) else ""
        if not text:
            return False
        if all(ch.isspace() or (not ch.isalnum()) for ch in text):
            return False
        return text.lower() not in NON_SUBSTANTIVE

    def _append_comment(group_key: str, text: str, source_question: str) -> None:
        entry = {
            "text": text[:500],
            "source_question": source_question,
        }
        qualitative_groups[group_key].append(entry)
        if group_key == "general_comments":
            comments.append({"text": text[:500]})

    for r in records:
        if _is_substantive(r.get("difficulty_details")):
            _append_comment(
                "operational_difficulties",
                str(r.get("difficulty_details")).strip(),
                "Operational difficulties",
            )
        if _is_substantive(r.get("improvement_areas_other_text")):
            _append_comment(
                "improvement_suggestions",
                str(r.get("improvement_areas_other_text")).strip(),
                "Improvement suggestions",
            )
        if _is_substantive(r.get("comments_and_suggestions")):
            _append_comment(
                "general_comments",
                str(r.get("comments_and_suggestions")).strip(),
                "General comments",
            )
        if _is_substantive(r.get("supporting_activity_impacts_other_text")):
            _append_comment(
                "supporting_activity_impacts",
                str(r.get("supporting_activity_impacts_other_text")).strip(),
                "Supporting-activity impacts",
            )
        if _is_substantive(r.get("product_categories_other_text")):
            _append_comment(
                "other_responses",
                str(r.get("product_categories_other_text")).strip(),
                "Other product category",
            )
        if _is_substantive(r.get("event_info_sources_other_text")):
            _append_comment(
                "other_responses",
                str(r.get("event_info_sources_other_text")).strip(),
                "Other information source",
            )

    substantive_count = sum(len(v) for v in qualitative_groups.values())
    actionable_count = (
        len(qualitative_groups["improvement_suggestions"])
        + len(qualitative_groups["operational_difficulties"])
        + len(qualitative_groups["supporting_activity_impacts"])
    )

    answered_denominators = {
        "items_sold_band": sum(items_sold.values()),
        "gross_sales_band": sum(gross_sales.values()),
        "sales_purpose": sum(purpose.values()),
        "experience_rating": sum(experience.values()),
        "supporting_activity_attracted_visitors": sum(supporting.values()),
        "has_difficulty": difficulty_answered,
    }

    unavailable = []
    if n == 0:
        unavailable.append("No valid survey responses for this event/import.")

    limitations = [
        "Survey metrics describe responding vendors only; they do not represent all event vendors unless response rate is known.",
        "Gross sales are categorical bands only; exact RM totals are not computed.",
        "Q4 event_info_sources are vendor information sources, not visitor discovery or organizer campaign ROI.",
        "Q5 items_sold_band applies to used goods sell-through estimates.",
    ]
    if n > 0 and n < SMALL_SAMPLE_THRESHOLD:
        limitations.append(
            f"Small sample (n={n} < {SMALL_SAMPLE_THRESHOLD}): interpret percentages cautiously."
        )

    data_completeness = {
        field: {
            "answered": answered_denominators[field],
            "denominator": n,
            "percent": _pct(answered_denominators[field], n),
            "display": f"{answered_denominators[field]} of {n} respondents ({_pct(answered_denominators[field], n)}%)",
        }
        for field in answered_denominators
    }

    return {
        "schema_name": SCHEMA_NAME,
        "schema_version": SCHEMA_VERSION,
        "calculation_version": CALCULATION_VERSION,
        "carboot_event_id": carboot_event_id,
        "import_batch_id": import_batch_id,
        "source_fingerprint": source_fingerprint,
        "respondent_count": n,
        "computed_at": now,
        "small_sample": n > 0 and n < SMALL_SAMPLE_THRESHOLD,
        "small_sample_threshold": SMALL_SAMPLE_THRESHOLD,
        "limitations": limitations,
        "unavailable_metrics": unavailable,
        "data_completeness": data_completeness,
        "sections": {
            "vendors": {
                "respondent_count": n,
                "product_categories": _distribution(product_counter, n, PRODUCT_CATEGORY_ORDER),
                "item_conditions": _distribution(condition_counter, n),
                "sales_purpose": _distribution(purpose, n, SALES_PURPOSE_ORDER),
                "sales_purpose_answered": sum(purpose.values()),
                "sales_purpose_unanswered": max(n - sum(purpose.values()), 0),
            },
            "economics": {
                "respondent_count": n,
                "gross_sales_band": _distribution(gross_sales, n, GROSS_SALES_BAND_ORDER),
                "gross_sales_answered": sum(gross_sales.values()),
                "gross_sales_unanswered": max(n - sum(gross_sales.values()), 0),
                "note": "Categorical bands only — no exact RM conversion.",
            },
            "items": {
                "respondent_count": n,
                "items_sold_band": _distribution(items_sold, n, ITEMS_SOLD_BAND_ORDER),
                "items_sold_answered": sum(items_sold.values()),
                "items_sold_unanswered": max(n - sum(items_sold.values()), 0),
                "unsold_item_actions": _distribution(unsold_counter, n),
                "used_goods_vendor_count": used_goods_vendors,
                "used_goods_vendor_display": f"{used_goods_vendors} of {n} respondents ({_pct(used_goods_vendors, n)}%)",
                "circularity_proxies": {
                    "positive_action_respondents": circularity_positive,
                    "positive_action_display": f"{circularity_positive} of {n} respondents ({_pct(circularity_positive, n)}%)",
                    "discard_action_respondents": circularity_negative,
                    "discard_action_display": f"{circularity_negative} of {n} respondents ({_pct(circularity_negative, n)}%)",
                    "note": "Proxy indicators from self-reported unsold-item actions; not verified diversion tonnes.",
                },
            },
            "experience": {
                "respondent_count": n,
                "experience_rating": _distribution(experience, n),
                "supporting_activity_attracted_visitors": _distribution(supporting, n),
                "supporting_activity_impacts": _distribution(impact_counter, n),
                "comments_and_suggestions": {
                    "substantive_count": len(comments),
                    "items": [c["text"] for c in comments[:50]],
                },
                "qualitative_comments": {
                    "substantive_count": substantive_count,
                    "actionable_suggestion_count": actionable_count,
                    "groups": {
                        key: value[:50] for key, value in qualitative_groups.items()
                    },
                    "source": "Vendor Survey CSV",
                    "theme_summary": [
                        {"label": "Operational difficulties", "count": len(qualitative_groups["operational_difficulties"])},
                        {"label": "Improvement suggestions", "count": len(qualitative_groups["improvement_suggestions"])},
                        {"label": "General comments", "count": len(qualitative_groups["general_comments"])},
                        {"label": "Supporting-activity impacts", "count": len(qualitative_groups["supporting_activity_impacts"])},
                        {"label": "Other responses", "count": len(qualitative_groups["other_responses"])},
                    ],
                },
            },
            "operations": {
                "respondent_count": n,
                "has_difficulty": {
                    "yes": difficulty_yes,
                    "no": difficulty_no,
                    "answered": difficulty_answered,
                    "yes_display": f"{difficulty_yes} of {n} respondents ({_pct(difficulty_yes, n)}%)",
                    "no_display": f"{difficulty_no} of {n} respondents ({_pct(difficulty_no, n)}%)",
                },
                "event_info_sources": _distribution(info_counter, n, EVENT_INFO_SOURCE_ORDER),
                "improvement_areas": _distribution(improvement_counter, n),
            },
            "data_quality": {
                "respondent_count": n,
                "schema_name": SCHEMA_NAME,
                "schema_version": SCHEMA_VERSION,
                "data_completeness": data_completeness,
                "limitations": limitations,
            },
        },
    }
