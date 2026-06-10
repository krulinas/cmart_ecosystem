"""
Seed cmart_db with dummy bookings (product_details) and feedbacks (comments)
for Word Cloud analytics development and demos.
"""

from __future__ import annotations

import os
from datetime import date, timedelta
from pathlib import Path

import mysql.connector
from mysql.connector import Error

SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
LARAVEL_ENV_PATH = REPO_ROOT / "backend" / ".env"

PRODUCT_ROWS = [
    ("Food & Beverages", "Ayam gunting pedas, Sos Thai homemade"),
    ("Pre-loved / Thrift", "Bundle t-shirt vintage, Seluar jeans Levi's"),
    ("Food & Beverages", "Nasi lemak daun pisang, Sambal ikan bilis"),
    ("Pre-loved / Thrift", "Sneakers terpakai, Kasut Converse bundle"),
    ("Food & Beverages", "Keropok lekor panas, Sos kuah special"),
    ("Food & Beverages", "Air kelapa muda, Teh tarik kaw"),
    ("Pre-loved / Thrift", "Baju kurung preloved, Tudung labuh bundle"),
    ("Food & Beverages", "Mee rebus kuah kacang, Tauhu goreng"),
    ("Pre-loved / Thrift", "Jacket denim vintage, Beg sandang bundle"),
    ("Food & Beverages", "Pisang goreng cheese, Cakoi garing"),
    ("Pre-loved / Thrift", "Kasut kasual bundle, Stokin bundle campur"),
    ("Food & Beverages", "Laksa utara pedas, Ulam-ulaman segar"),
    ("Pre-loved / Thrift", "Dress floral bundle, Skirt denim bundle"),
    ("Food & Beverages", "Burger ramly special, French fries"),
    ("Pre-loved / Thrift", "Topi baseball bundle, Hoodie bundle murah"),
]

FEEDBACK_ROWS = [
    ("Shopper", "Terbaik sangat meriah, banyak vendor makanan sedap"),
    ("Local Resident", "Harga murah berbaloi, parking senang"),
    ("UUM Student", "Banyak pilihan makanan sedap, suasana best"),
    ("Vendor", "Panas tapi seronok, jualan laris petang"),
    ("Shopper", "Baju bundle kualiti padu, harga berpatutan"),
    ("Local Resident", "Meriah gila hujung minggu, datang lagi"),
    ("UUM Student", "Makanan F&B banyak pilihan, nasi lemak sedap"),
    ("Shopper", "Vendor friendly, barang preloved cantik"),
    ("Vendor", "Tapak selesa, pengunjung ramai dari pagi"),
    ("Local Resident", "Suasana keluarga, anak-anak enjoy"),
    ("Shopper", "Keropok lekor panas, ayam gunting pedas terbaik"),
    ("UUM Student", "Bundle t-shirt vintage murah, kualiti okay"),
    ("Local Resident", "Tempat luas, senang jalan, makanan sedap"),
    ("Shopper", "Harga berbaloi, banyak sneaker terpakai cantik"),
    ("Vendor", "Event teratur, staff CMart sangat membantu"),
]

USER_IDS = (1, 6)
DEFAULT_SPACE_ID = 1


def load_laravel_env(path: Path) -> dict[str, str]:
    """Parse Laravel .env key=value pairs (no external dependency)."""
    values: dict[str, str] = {}
    if not path.is_file():
        return values

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        values[key] = value

    return values


def get_db_config() -> dict:
    env = load_laravel_env(LARAVEL_ENV_PATH)

    return {
        "host": env.get("DB_HOST") or os.getenv("DB_HOST", "127.0.0.1"),
        "port": int(env.get("DB_PORT") or os.getenv("DB_PORT", "3306")),
        "user": env.get("DB_USERNAME") or os.getenv("DB_USERNAME", "root"),
        "password": env.get("DB_PASSWORD") or os.getenv("DB_PASSWORD", ""),
        "database": env.get("DB_DATABASE") or os.getenv("DB_DATABASE", "cmart_db"),
    }


def get_connection():
    config = get_db_config()
    return mysql.connector.connect(
        host=config["host"],
        port=config["port"],
        user=config["user"],
        password=config["password"],
        database=config["database"],
    )


def alternate_user_id(index: int) -> int:
    return USER_IDS[index % len(USER_IDS)]


def seed_bookings(cursor) -> int:
    insert_sql = """
        INSERT INTO bookings (
            user_id, space_id, booking_date, approval_status,
            product_category, product_details, whatsapp_link,
            created_at, updated_at
        ) VALUES (
            %s, %s, %s, 'Approved',
            %s, %s, 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE',
            NOW(), NOW()
        )
    """
    base_date = date(2026, 5, 16)
    inserted = 0

    for index, (category, details) in enumerate(PRODUCT_ROWS):
        cursor.execute(
            insert_sql,
            (
                alternate_user_id(index),
                DEFAULT_SPACE_ID,
                base_date + timedelta(days=index),
                category,
                details,
            ),
        )
        inserted += 1

    return inserted


def seed_feedbacks(cursor) -> int:
    insert_sql = """
        INSERT INTO feedbacks (
            user_id, reviewer_role, comments,
            rating, service_rating, value_rating,
            helpful_count, is_hidden,
            created_at, updated_at
        ) VALUES (
            %s, %s, %s,
            %s, %s, %s,
            0, 0,
            NOW(), NOW()
        )
    """
    inserted = 0

    for index, (role, comment) in enumerate(FEEDBACK_ROWS):
        service_rating = 4 + (index % 2)
        value_rating = 4 + ((index + 1) % 2)
        overall_rating = round((service_rating + value_rating) / 2)

        cursor.execute(
            insert_sql,
            (
                alternate_user_id(index),
                role,
                comment,
                overall_rating,
                service_rating,
                value_rating,
            ),
        )
        inserted += 1

    return inserted


def main() -> None:
    config = get_db_config()
    print(f"Connecting to MySQL at {config['host']}:{config['port']}/{config['database']} ...")

    connection = None
    try:
        connection = get_connection()
        cursor = connection.cursor()

        bookings_count = seed_bookings(cursor)
        feedbacks_count = seed_feedbacks(cursor)

        connection.commit()

        print("Success: Word Cloud seed data injected.")
        print(f"  - {bookings_count} bookings (product_details, approval_status=Approved)")
        print(f"  - {feedbacks_count} feedbacks (comments)")
        print(f"  - user_id alternates between {USER_IDS[0]} and {USER_IDS[1]}, space_id={DEFAULT_SPACE_ID}")

    except Error as exc:
        if connection:
            connection.rollback()
        print(f"Error: seed failed — {exc}")
        raise SystemExit(1) from exc
    finally:
        if connection and connection.is_connected():
            connection.close()


if __name__ == "__main__":
    main()
