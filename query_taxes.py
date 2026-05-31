import os
import mysql.connector
from dotenv import load_dotenv

load_dotenv()

conn = mysql.connector.connect(
    host=os.getenv('DB_HOST', 'staging.bactiveph.com'),
    user=os.environ['DB_USER'],
    password=os.environ['DB_PASSWORD'],
    database=os.environ['DB_NAME']
)
cursor = conn.cursor()

cursor.execute("SELECT * FROM wp_woocommerce_tax_rates")
rows = cursor.fetchall()
for row in rows:
    print(row)

cursor.close()
conn.close()
