import os
import pymysql
import pandas as pd
import seaborn as sns
import matplotlib.pyplot as plt
from textblob import TextBlob
from sklearn.metrics import accuracy_score, precision_score, classification_report, confusion_matrix

# 1. Connect to your database
def get_db_connection():
    return pymysql.connect(
        host='127.0.0.1',
        user='root',
        password='',
        database='cmart_db',
        cursorclass=pymysql.cursors.DictCursor
    )

def analyze_data():
    conn = get_db_connection()
    # Fetch feedbacks with their actual ratings
    query = "SELECT comments, ratings FROM feedbacks WHERE comments IS NOT NULL"
    df = pd.read_sql(query, conn)
    conn.close()

    if df.empty:
        print("No feedback data found.")
        return

    print(f"Loaded {len(df)} feedbacks for analysis...")

    # 2. Prepare the Ground Truth (Actual Ratings)
    # Let's assume ratings 4-5 are "Positive" (1) and 1-3 are "Negative" (0)
    df['actual_sentiment'] = df['ratings'].apply(lambda x: 1 if x >= 4 else 0)

    # 3. Predict Sentiment using NLP (TextBlob)
    # TextBlob returns a polarity score from -1.0 (negative) to 1.0 (positive)
    def predict_sentiment(text):
        polarity = TextBlob(str(text)).sentiment.polarity
        return 1 if polarity > 0 else 0

    df['predicted_sentiment'] = df['comments'].apply(predict_sentiment)
    
    # 4. Extract Text Metadata for Heatmap
    df['word_count'] = df['comments'].apply(lambda x: len(str(x).split()))
    df['polarity_score'] = df['comments'].apply(lambda x: TextBlob(str(x)).sentiment.polarity)

    # --- RESULTS: ACCURACY & PRECISION ---
    print("\n" + "="*40)
    print("🎯 NLP CLASSIFICATION METRICS")
    print("="*40)
    
    accuracy = accuracy_score(df['actual_sentiment'], df['predicted_sentiment'])
    precision = precision_score(df['actual_sentiment'], df['predicted_sentiment'], zero_division=0)
    
    print(f"Accuracy:  {accuracy * 100:.2f}% (How often Python's sentiment matched the star rating)")
    print(f"Precision: {precision * 100:.2f}% (When Python guessed 'Positive', how often it was right)")
    print("\nFull Classification Report:")
    print(classification_report(df['actual_sentiment'], df['predicted_sentiment'], target_names=['Negative', 'Positive']))

    # --- RESULTS: CORRELATION HEATMAP ---
    print("\n📊 Generating Correlation Heatmap...")
    
    # Select only numerical columns for the heatmap
    numerical_df = df[['ratings', 'word_count', 'polarity_score', 'actual_sentiment']]
    correlation_matrix = numerical_df.corr()

    # Draw the Heatmap using Seaborn
    plt.figure(figsize=(8, 6))
    sns.heatmap(correlation_matrix, annot=True, cmap='coolwarm', vmin=-1, vmax=1, fmt=".2f")
    plt.title('Correlation Heatmap: Ratings vs Text Features')
    plt.tight_layout()
    
    # Save the heatmap to your folder
    heatmap_path = 'correlation_heatmap.png'
    plt.savefig(heatmap_path)
    print(f"✅ Heatmap saved successfully as '{heatmap_path}'!")

if __name__ == "__main__":
    analyze_data()