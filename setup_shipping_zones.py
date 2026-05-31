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

# Clear existing zones (except 0)
cursor.execute("DELETE FROM wp_woocommerce_shipping_zones WHERE zone_id > 0")
cursor.execute("DELETE FROM wp_woocommerce_shipping_zone_locations")
cursor.execute("DELETE FROM wp_woocommerce_shipping_zone_methods")

zones = [
    (1, 'Davao City', 1),
    (2, 'Mindanao (excl. Davao)', 2),
    (3, 'Luzon & Visayas', 3)
]
cursor.executemany("INSERT INTO wp_woocommerce_shipping_zones (zone_id, zone_name, zone_order) VALUES (%s, %s, %s)", zones)

# Locations
davao_locations = [ (1, 'PH50', 'state') ]

mindanao_states = [
    'PH02', 'PH03', 'PH12', 'PH22', 'PH26', 'PH33', 'PH34', 'PH35', 'PH36', 'PH37', 'PH38', 
    'PH43', 'PH44', 'PH47', 'PH49', 'PH51', 'PH62', 'PH68', 'PH69', 'PH70', 'PH71', 'PH72', 
    'PH74', 'PH82', 'PH83', 'PH84'
]
mindanao_locations = [ (2, state, 'state') for state in mindanao_states ]

# Luzon & Visayas is the rest of PH
luzon_visayas_locations = [ (3, 'PH', 'country') ]

all_locations = davao_locations + mindanao_locations + luzon_visayas_locations
cursor.executemany("INSERT INTO wp_woocommerce_shipping_zone_locations (zone_id, location_code, location_type) VALUES (%s, %s, %s)", all_locations)

# Methods
methods = [
    (1, 1, 'flexible_shipping', 1, 1), # Davao Flexible Shipping
    (2, 1, 'local_pickup', 2, 1),      # Davao Local Pickup
    (3, 2, 'flexible_shipping', 1, 1), # Mindanao Flexible Shipping
    (4, 3, 'flexible_shipping', 1, 1), # Luzon/Visayas Flexible Shipping
]
cursor.executemany("INSERT INTO wp_woocommerce_shipping_zone_methods (instance_id, zone_id, method_id, method_order, is_enabled) VALUES (%s, %s, %s, %s, %s)", methods)

# Flexible Shipping Options in wp_options
# This requires serialized PHP arrays, which is tricky to build in Python manually. 
# But wait! If the flexible shipping methods are already in wp_options, I can just run a PHP script via WP-CLI to configure them.

conn.commit()
cursor.close()
conn.close()
print("Shipping zones and locations reset and inserted.")
