import os
from functools import lru_cache
from typing import Annotated

import mysql.connector
import pandas as pd
from dotenv import load_dotenv
from fastapi import Depends, FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from text_analytics import counter_to_terms, tokenize_feedback, tokenize_products

load_dotenv()

app = FastAPI(title="Carboot@CMart Analytics API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@lru_cache
def get_analytics_api_key() -> str:
    return os.getenv("ANALYTICS_API_KEY", "")


def verify_api_key(
    x_analytics_key: Annotated[str | None, Header()] = None,
) -> None:
    expected = get_analytics_api_key()
    if expected and x_analytics_key != expected:
        raise HTTPException(status_code=401, detail="Invalid or missing analytics API key.")


def get_db_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USERNAME", "root"),
        password=os.getenv("DB_PASSWORD", ""),
        database=os.getenv("DB_DATABASE", "cmart_db"),
    )


def fetch_text_column(query: str, column: str) -> list[str]:
    conn = get_db_connection()
    try:
        df = pd.read_sql(query, conn)
    finally:
        conn.close()

    if df.empty or column not in df.columns:
        return []

    return [str(value) for value in df[column].dropna().tolist() if str(value).strip()]


def build_wordcloud_response(texts: list[str], source: str, tokenize_fn) -> dict:
    counter = tokenize_fn(texts)
    return {
        "source": source,
        "total_documents": len(texts),
        "unique_terms": len(counter),
        "terms": counter_to_terms(counter),
    }


@app.get("/")
def read_root():
    return {"message": "200 OK: Carboot@CMart Analytics API is operational."}


@app.get("/api/analytics/status-summary", dependencies=[Depends(verify_api_key)])
def get_status_summary():
    conn = get_db_connection()
    try:
        df = pd.read_sql("SELECT approval_status FROM bookings", conn)
    finally:
        conn.close()

    summary = df["approval_status"].value_counts().to_dict() if not df.empty else {}

    return {
        "total_bookings": len(df),
        "status_breakdown": summary,
    }


@app.get("/api/analytics/wordcloud/feedback", dependencies=[Depends(verify_api_key)])
def get_feedback_wordcloud():
    texts = fetch_text_column(
        """
        SELECT comments
        FROM feedbacks
        WHERE comments IS NOT NULL AND TRIM(comments) != ''
        """,
        "comments",
    )
    return build_wordcloud_response(texts, "feedback", tokenize_feedback)


@app.get("/api/analytics/wordcloud/products", dependencies=[Depends(verify_api_key)])
def get_products_wordcloud():
    texts = fetch_text_column(
        """
        SELECT product_details
        FROM bookings
        WHERE approval_status = 'Approved'
          AND product_details IS NOT NULL
          AND TRIM(product_details) != ''
        """,
        "product_details",
    )
    return build_wordcloud_response(texts, "products", tokenize_products)
