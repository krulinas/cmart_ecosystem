from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import mysql.connector
import pandas as pd

app = FastAPI(title="Carboot@CMart Analytics API")

# Allow your Vue.js frontend to talk to this Python API without CORS errors
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], 
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Connect to your XAMPP MySQL database
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",       # Default XAMPP user
        password="",       # Default XAMPP password (blank)
        database="cmart_db"
    )

@app.get("/")
def read_root():
    return {"message": "200 OK: Carboot@CMart Analytics API is operational."}

@app.get("/api/analytics/status-summary")
def get_status_summary():
    conn = get_db_connection()
    
    # Retrieve booking statuses from the Laravel application database.
    query = "SELECT approval_status FROM bookings"
    
    # Use Pandas to aggregate booking status counts.
    df = pd.read_sql(query, conn)
    conn.close()
    
    # Calculate the totals for Pending, Approved, and Rejected
    summary = df['approval_status'].value_counts().to_dict()
    
    return {
        "total_bookings": len(df),
        "status_breakdown": summary
    }