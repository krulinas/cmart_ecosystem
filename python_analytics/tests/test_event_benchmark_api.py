"""API-key protection for event-benchmark (without requiring httpx TestClient)."""

from __future__ import annotations

import os
import unittest
from unittest.mock import patch

from fastapi import HTTPException

import main as analytics_main
from event_benchmarking import benchmark_events


class EventBenchmarkApiKeyTest(unittest.TestCase):
    def setUp(self):
        analytics_main.get_analytics_api_key.cache_clear()

    def tearDown(self):
        analytics_main.get_analytics_api_key.cache_clear()

    def test_rejects_missing_key_when_configured(self):
        with patch.dict(os.environ, {"ANALYTICS_API_KEY": "secret-bench-key"}, clear=False):
            analytics_main.get_analytics_api_key.cache_clear()
            with self.assertRaises(HTTPException) as ctx:
                analytics_main.verify_api_key(x_analytics_key=None)
            self.assertEqual(ctx.exception.status_code, 401)

    def test_accepts_valid_key_and_benchmarks(self):
        with patch.dict(os.environ, {"ANALYTICS_API_KEY": "secret-bench-key"}, clear=False):
            analytics_main.get_analytics_api_key.cache_clear()
            analytics_main.verify_api_key(x_analytics_key="secret-bench-key")
            body = benchmark_events(
                {
                    "selected_event_id": 1,
                    "events": [
                        {
                            "event_id": 1,
                            "event_name": "Solo",
                            "event_date": "2026-01-01",
                            "approved_vendors": 3,
                        }
                    ],
                }
            )
            self.assertEqual(body["selected_event_id"], 1)
            self.assertEqual(body["sample_size"], 1)


if __name__ == "__main__":
    unittest.main()
