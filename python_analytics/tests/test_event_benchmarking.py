"""Unit tests for cross-event benchmarking (null-safe Pandas stats)."""

from __future__ import annotations

import unittest

from event_benchmarking import benchmark_events


class EventBenchmarkingTest(unittest.TestCase):
    def test_null_metrics_excluded_not_zeroed(self):
        result = benchmark_events(
            {
                "selected_event_id": 2,
                "events": [
                    {
                        "event_id": 1,
                        "event_name": "A",
                        "event_date": "2026-01-01",
                        "site_utilisation_percent": 10,
                        "collection_rate_percent": None,
                    },
                    {
                        "event_id": 2,
                        "event_name": "B",
                        "event_date": "2026-02-01",
                        "site_utilisation_percent": 20,
                        "collection_rate_percent": None,
                    },
                    {
                        "event_id": 3,
                        "event_name": "C",
                        "event_date": "2026-03-01",
                        "site_utilisation_percent": 30,
                        "collection_rate_percent": 50,
                    },
                ],
            }
        )
        collection = result["metrics"]["collection_rate_percent"]
        self.assertIsNone(collection["value"])
        self.assertEqual(collection["warning"], "selected_event_metric_unavailable")
        # Only one valid collection observation — insufficient for median.
        self.assertEqual(collection["valid_n"], 1)

    def test_median_percentile_and_delta(self):
        result = benchmark_events(
            {
                "selected_event_id": 2,
                "events": [
                    {"event_id": 1, "event_name": "A", "event_date": "2026-01-01", "site_utilisation_percent": 10},
                    {"event_id": 2, "event_name": "B", "event_date": "2026-02-01", "site_utilisation_percent": 14.1},
                    {"event_id": 3, "event_name": "C", "event_date": "2026-03-01", "site_utilisation_percent": 30},
                ],
            }
        )
        block = result["metrics"]["site_utilisation_percent"]
        self.assertEqual(block["value"], 14.1)
        self.assertEqual(block["median"], 14.1)
        self.assertAlmostEqual(block["delta_from_median"], 0.0, places=4)
        self.assertIsNotNone(block["percentile"])

    def test_insufficient_sample_warnings(self):
        result = benchmark_events(
            {
                "selected_event_id": 1,
                "events": [
                    {"event_id": 1, "event_name": "Only", "event_date": "2026-01-01", "approved_vendors": 5},
                ],
            }
        )
        self.assertIn("insufficient_history_for_median_comparison", result["warnings"])
        self.assertEqual(result["metrics"]["approved_vendors"]["warning"], "insufficient_history_for_median")
        self.assertEqual(result["trends"], [])

    def test_selected_event_highlighted_in_trends(self):
        result = benchmark_events(
            {
                "selected_event_id": 2,
                "events": [
                    {"event_id": 1, "event_name": "A", "event_date": "2026-01-01", "average_rating": 4.0},
                    {"event_id": 2, "event_name": "B", "event_date": "2026-02-01", "average_rating": 5.0},
                    {"event_id": 3, "event_name": "C", "event_date": "2026-03-01", "average_rating": None},
                ],
            }
        )
        trend = next(t for t in result["trends"] if t["metric"] == "average_rating")
        selected = [p for p in trend["points"] if p["selected"]]
        self.assertEqual(len(selected), 1)
        self.assertEqual(selected[0]["event_id"], 2)
        self.assertEqual(len(trend["points"]), 2)


if __name__ == "__main__":
    unittest.main()
