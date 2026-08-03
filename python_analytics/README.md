# Carboot@CMart Python Analytics

FastAPI service for booking analytics and text analytics (word clouds).

## Setup

```bash
cd python_analytics
python -m venv .venv
.venv\Scripts\activate          # Windows
pip install -r requirements.txt
copy .env.example .env            # edit DB_* and ANALYTICS_API_KEY
```

On first run, NLTK stopwords are downloaded automatically.

## Run (port 8001)

```bash
uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

Keep this running alongside `php artisan serve` (Laravel, port 8000) and the Vite dev server.

## Endpoints

| Method | Path | Auth |
|--------|------|------|
| GET | `/` | Public health check |
| GET | `/api/analytics/status-summary` | `X-Analytics-Key` header when `ANALYTICS_API_KEY` is set |
| GET | `/api/analytics/wordcloud/feedback` | Same (`?event_id=` optional when column exists) |
| GET | `/api/analytics/wordcloud/products` | Same |
| GET | `/api/analytics/survey/schema` | Same |
| POST | `/api/analytics/survey/validate` | Same (multipart file; requires `python-multipart`) |
| POST | `/api/analytics/survey/aggregate` | Same (JSON records or event id) |

Boss users reach word-cloud data through the Laravel proxy at `GET /api/boss/analytics/wordcloud/{feedback|products}`.
