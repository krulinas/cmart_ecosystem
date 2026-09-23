"""
Cross-event benchmarking for Organizer Analytics (Phase 1).

Laravel sends authoritative per-event metric rows. This module performs
statistical comparison with Pandas — it does not recompute business formulas.
Null observations are excluded per metric (never coerced to zero).
"""

from __future__ import annotations

from typing import Any

import numpy as np
import pandas as pd

METRIC_KEYS = (
    "approved_vendors",
    "site_utilisation_percent",
    "collection_rate_percent",
    "average_rating",
)

# Minimum valid observations for each capability.
MIN_TREND = 2
MIN_MEDIAN = 3
MIN_OUTLIER = 5


def _to_nullable_float(value: Any) -> float | None:
    if value is None:
        return None
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        if isinstance(value, float) and (np.isnan(value) or np.isinf(value)):
            return None
        return float(value)
    if isinstance(value, str) and value.strip() == "":
        return None
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None
    if np.isnan(number) or np.isinf(number):
        return None
    return number


def _normalize_events(events: list[dict[str, Any]]) -> pd.DataFrame:
    rows: list[dict[str, Any]] = []
    for raw in events or []:
        if not isinstance(raw, dict):
            continue
        event_id = raw.get("event_id")
        if event_id is None:
            continue
        row: dict[str, Any] = {
            "event_id": int(event_id),
            "event_name": str(raw.get("event_name") or f"Event {event_id}"),
            "event_date": raw.get("event_date"),
        }
        for key in METRIC_KEYS:
            row[key] = _to_nullable_float(raw.get(key))
        rows.append(row)

    if not rows:
        return pd.DataFrame(columns=["event_id", "event_name", "event_date", *METRIC_KEYS])

    return pd.DataFrame(rows).sort_values(
        by=["event_date", "event_id"],
        ascending=True,
        na_position="last",
    )


def _status_for(value: float, median: float, sample_size: int) -> str | None:
    if sample_size < MIN_OUTLIER:
        return None
    delta = value - median
    # Soft bands around the median (±10% relative, or absolute 2 pts for percentages-like).
    band = max(abs(median) * 0.1, 2.0)
    if abs(delta) <= band:
        return "typical"
    return "above_typical" if delta > 0 else "below_typical"


def _metric_block(
    series: pd.Series,
    selected_value: float | None,
    sample_size_all: int,
) -> dict[str, Any] | None:
    valid = series.dropna()
    n = int(valid.shape[0])
    if selected_value is None:
        return {
            "value": None,
            "median": None,
            "percentile": None,
            "delta_from_median": None,
            "status": None,
            "valid_n": n,
            "warning": "selected_event_metric_unavailable",
        }

    block: dict[str, Any] = {
        "value": float(selected_value),
        "median": None,
        "percentile": None,
        "delta_from_median": None,
        "status": None,
        "valid_n": n,
        "warning": None,
    }

    if n < MIN_MEDIAN:
        block["warning"] = "insufficient_history_for_median"
        return block

    median = float(valid.median())
    # Percentile of selected among valid observations (inclusive rank).
    percentile = float((valid.le(selected_value).sum() / n) * 100.0)
    delta = float(selected_value - median)
    block["median"] = round(median, 4)
    block["percentile"] = round(percentile, 2)
    block["delta_from_median"] = round(delta, 4)

    status = _status_for(selected_value, median, n)
    if status is None:
        block["warning"] = "insufficient_history_for_outlier_status"
    else:
        block["status"] = status

    return block


def _trend_for_metric(df: pd.DataFrame, metric: str, selected_event_id: int) -> dict[str, Any] | None:
    points: list[dict[str, Any]] = []
    for _, row in df.iterrows():
        value = row.get(metric)
        if value is None or (isinstance(value, float) and np.isnan(value)):
            continue
        event_id = int(row["event_id"])
        points.append(
            {
                "event_id": event_id,
                "event_name": row.get("event_name"),
                "event_date": row.get("event_date"),
                "value": float(value),
                "selected": event_id == selected_event_id,
            }
        )

    if len(points) < MIN_TREND:
        return None

    return {
        "metric": metric,
        "points": points,
    }


def benchmark_events(payload: dict[str, Any]) -> dict[str, Any]:
    """
    Compare the selected event against historical organizer events.

    Expected payload:
      {
        "selected_event_id": int,
        "events": [ { event_id, event_name, event_date, metrics... } ]
      }
    """
    selected_event_id = payload.get("selected_event_id")
    if selected_event_id is None:
        raise ValueError("selected_event_id is required.")

    selected_event_id = int(selected_event_id)
    df = _normalize_events(payload.get("events") or [])
    warnings: list[str] = []

    if df.empty:
        return {
            "selected_event_id": selected_event_id,
            "sample_size": 0,
            "metrics": {},
            "trends": [],
            "warnings": ["no_events_provided"],
        }

    sample_size = int(df.shape[0])
    if selected_event_id not in set(df["event_id"].tolist()):
        warnings.append("selected_event_not_in_payload")

    selected_rows = df[df["event_id"] == selected_event_id]
    selected = selected_rows.iloc[0] if not selected_rows.empty else None

    metrics: dict[str, Any] = {}
    for key in METRIC_KEYS:
        selected_value = None if selected is None else selected.get(key)
        if selected_value is not None and isinstance(selected_value, float) and np.isnan(selected_value):
            selected_value = None
        metrics[key] = _metric_block(df[key], selected_value, sample_size)

    trends: list[dict[str, Any]] = []
    for key in METRIC_KEYS:
        trend = _trend_for_metric(df, key, selected_event_id)
        if trend is not None:
            trends.append(trend)

    if sample_size < MIN_MEDIAN:
        warnings.append("insufficient_history_for_median_comparison")
    if sample_size < MIN_OUTLIER:
        warnings.append("insufficient_history_for_outlier_status")

    return {
        "selected_event_id": selected_event_id,
        "sample_size": sample_size,
        "metrics": metrics,
        "trends": trends,
        "warnings": warnings,
    }
