import os
from functools import lru_cache
from typing import Annotated, Any

import mysql.connector
import pandas as pd
from dotenv import dotenv_values, load_dotenv
from fastapi import Depends, FastAPI, File, Header, HTTPException, Query, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from event_aggregations import aggregate_survey_records
from survey_schema import CALCULATION_VERSION, SCHEMA_NAME, SCHEMA_VERSION
from text_analytics import counter_to_terms, tokenize_feedback, tokenize_products
from validate_survey_csv import validate_survey_csv_text

# Prefer python_analytics/.env over inherited shell DB_* values (e.g. stale cmart_db).
load_dotenv(override=True)
_FILE_ENV = dotenv_values()

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
    host = _FILE_ENV.get("DB_HOST") or os.getenv("DB_HOST") or "127.0.0.1"
    port = int(_FILE_ENV.get("DB_PORT") or os.getenv("DB_PORT") or "3306")
    user = _FILE_ENV.get("DB_USERNAME") or os.getenv("DB_USERNAME") or "root"
    password = _FILE_ENV.get("DB_PASSWORD")
    if password is None:
        password = os.getenv("DB_PASSWORD", "")
    # Prefer file-configured DB; default matches local Laravel rebuild database.
    database = _FILE_ENV.get("DB_DATABASE") or "cmart_db_rebuild"

    return mysql.connector.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
    )


def fetch_text_column(query: str, column: str, params: tuple | None = None) -> list[str]:
    conn = get_db_connection()
    try:
        df = pd.read_sql(query, conn, params=params or ())
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


def _column_exists(table: str, column: str) -> bool:
    conn = get_db_connection()
    try:
        cursor = conn.cursor()
        cursor.execute(
            """
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s
            """,
            (os.getenv("DB_DATABASE", "cmart_db"), table, column),
        )
        row = cursor.fetchone()
        return bool(row and row[0] > 0)
    finally:
        conn.close()


def _load_survey_records_for_event(event_id: int, import_batch_id: int | None = None) -> list[dict[str, Any]]:
    if not _column_exists("survey_responses", "id"):
        raise HTTPException(
            status_code=503,
            detail="survey_responses table is not available. Apply analytics migrations first.",
        )

    query = """
        SELECT *
        FROM survey_responses
        WHERE carboot_event_id = %s
          AND validation_status = 'valid'
    """
    params: list[Any] = [event_id]
    if import_batch_id is not None:
        query += " AND import_batch_id = %s"
        params.append(import_batch_id)

    conn = get_db_connection()
    try:
        df = pd.read_sql(query, conn, params=tuple(params))
    finally:
        conn.close()

    if df.empty:
        return []

    import json

    records = []
    for _, row in df.iterrows():
        record = row.to_dict()
        for field in (
            "product_categories",
            "item_conditions",
            "event_info_sources",
            "unsold_item_actions",
            "improvement_areas",
            "supporting_activity_impacts",
        ):
            value = record.get(field)
            if isinstance(value, str):
                try:
                    record[field] = json.loads(value)
                except json.JSONDecodeError:
                    record[field] = []
        if "has_difficulty" in record and record["has_difficulty"] is not None:
            record["has_difficulty"] = bool(record["has_difficulty"])
        records.append(record)
    return records


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
def get_feedback_wordcloud(event_id: int | None = Query(default=None)):
    clauses = [
        "comments IS NOT NULL",
        "TRIM(comments) != ''",
        "(is_hidden IS NULL OR is_hidden = 0)",
    ]
    params: list[Any] = []
    if event_id is not None:
        if not _column_exists("feedbacks", "carboot_event_id"):
            raise HTTPException(
                status_code=422,
                detail="feedbacks.carboot_event_id is not available for event-scoped word clouds.",
            )
        clauses.append("carboot_event_id = %s")
        params.append(event_id)

    sql = f"SELECT comments FROM feedbacks WHERE {' AND '.join(clauses)}"
    texts = fetch_text_column(sql, "comments", tuple(params) if params else None)
    return build_wordcloud_response(texts, "feedback", tokenize_feedback)


@app.get("/api/analytics/wordcloud/products", dependencies=[Depends(verify_api_key)])
def get_products_wordcloud(event_id: int | None = Query(default=None)):
    clauses = [
        "approval_status = 'Approved'",
        "product_details IS NOT NULL",
        "TRIM(product_details) != ''",
    ]
    params: list[Any] = []
    if event_id is not None:
        clauses.append("carboot_event_id = %s")
        params.append(event_id)

    sql = f"SELECT product_details FROM bookings WHERE {' AND '.join(clauses)}"
    texts = fetch_text_column(sql, "product_details", tuple(params) if params else None)
    return build_wordcloud_response(texts, "products", tokenize_products)


@app.post("/api/analytics/survey/validate", dependencies=[Depends(verify_api_key)])
async def validate_survey_upload(file: UploadFile = File(...)):
    raw = await file.read()
    try:
        text = raw.decode("utf-8-sig")
    except UnicodeDecodeError as exc:
        raise HTTPException(status_code=422, detail="CSV must be UTF-8 encoded.") from exc

    result = validate_survey_csv_text(text)
    result["original_filename"] = file.filename
    return result


class SurveyAggregateRequest(BaseModel):
    carboot_event_id: int | None = None
    import_batch_id: int | None = None
    source_fingerprint: str | None = None
    records: list[dict[str, Any]] = Field(default_factory=list)


@app.post("/api/analytics/survey/aggregate", dependencies=[Depends(verify_api_key)])
def aggregate_survey(payload: SurveyAggregateRequest):
    records = payload.records
    if not records:
        if payload.carboot_event_id is None:
            raise HTTPException(
                status_code=422,
                detail="Provide records or carboot_event_id for aggregation.",
            )
        records = _load_survey_records_for_event(
            payload.carboot_event_id,
            payload.import_batch_id,
        )

    return aggregate_survey_records(
        records,
        carboot_event_id=payload.carboot_event_id,
        import_batch_id=payload.import_batch_id,
        source_fingerprint=payload.source_fingerprint,
    )


@app.get("/api/analytics/survey/schema", dependencies=[Depends(verify_api_key)])
def survey_schema_meta():
    return {
        "schema_name": SCHEMA_NAME,
        "schema_version": SCHEMA_VERSION,
        "calculation_version": CALCULATION_VERSION,
    }
