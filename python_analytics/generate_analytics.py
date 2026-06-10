"""
Query cmart_db, run basic NLP on vendor products and community feedback,
and export word-frequency CSV files for Word Cloud analytics.
"""

from __future__ import annotations

import csv
import os
from pathlib import Path

import pymysql
from pymysql import Error

from text_analytics import tokenize_feedback, tokenize_products

SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
LARAVEL_ENV_PATH = REPO_ROOT / "backend" / ".env"

VENDOR_CSV = SCRIPT_DIR / "vendor_word_cloud.csv"
FEEDBACK_CSV = SCRIPT_DIR / "feedback_word_cloud.csv"


def load_laravel_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    if not path.is_file():
        return values

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        values[key.strip()] = value.strip().strip('"').strip("'")

    return values


def get_db_config() -> dict:
    env = load_laravel_env(LARAVEL_ENV_PATH)

    return {
        "host": env.get("DB_HOST") or os.getenv("DB_HOST", "127.0.0.1"),
        "port": int(env.get("DB_PORT") or os.getenv("DB_PORT", "3306")),
        "user": env.get("DB_USERNAME") or os.getenv("DB_USERNAME", "root"),
        "password": env.get("DB_PASSWORD") or os.getenv("DB_PASSWORD", ""),
        "database": env.get("DB_DATABASE") or os.getenv("DB_DATABASE", "cmart_db"),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
    }


def get_connection():
    config = get_db_config()
    return pymysql.connect(
        host=config["host"],
        port=config["port"],
        user=config["user"],
        password=config["password"],
        database=config["database"],
        charset=config["charset"],
        cursorclass=config["cursorclass"],
    )


def fetch_column(cursor, query: str, column: str) -> list[str]:
    cursor.execute(query)
    rows = cursor.fetchall()
    return [
        str(row[column])
        for row in rows
        if row.get(column) and str(row[column]).strip()
    ]


def export_word_frequencies(counter, output_path: Path) -> int:
    with output_path.open("w", newline="", encoding="utf-8") as csvfile:
        writer = csv.writer(csvfile)
        writer.writerow(["Word", "Frequency"])
        for word, frequency in counter.most_common():
            writer.writerow([word, frequency])

    return len(counter)


def main() -> None:
    config = get_db_config()
    print(f"Connecting to MySQL at {config['host']}:{config['port']}/{config['database']} ...")

    connection = None
    try:
        connection = get_connection()
        with connection.cursor() as cursor:
            vendor_texts = fetch_column(
                cursor,
                """
                SELECT product_details
                FROM bookings
                WHERE approval_status = 'Approved'
                  AND product_details IS NOT NULL
                  AND TRIM(product_details) != ''
                """,
                "product_details",
            )

            feedback_texts = fetch_column(
                cursor,
                """
                SELECT comments
                FROM feedbacks
                WHERE comments IS NOT NULL
                  AND TRIM(comments) != ''
                """,
                "comments",
            )

        vendor_counter = tokenize_products(vendor_texts)
        feedback_counter = tokenize_feedback(feedback_texts)

        vendor_terms = export_word_frequencies(vendor_counter, VENDOR_CSV)
        feedback_terms = export_word_frequencies(feedback_counter, FEEDBACK_CSV)

        print("Success: Word frequency analytics exported.")
        print(f"  - {VENDOR_CSV.name}: {vendor_terms} unique words from {len(vendor_texts)} bookings")
        print(f"  - {FEEDBACK_CSV.name}: {feedback_terms} unique words from {len(feedback_texts)} feedbacks")

    except Error as exc:
        print(f"Error: analytics generation failed — {exc}")
        raise SystemExit(1) from exc
    finally:
        if connection:
            connection.close()


if __name__ == "__main__":
    main()
