import os
import env_loader  # auto-loads .env into os.environ
import urllib.request
import re

# Fetch salt keys
response = urllib.request.urlopen('https://api.wordpress.org/secret-key/1.1/salt/')
salts = response.read().decode('utf-8')

# Read sample config
with open('wordpress/wp-config-sample.php', 'r') as f:
    config = f.read()

# Replace DB variables
config = config.replace("database_name_here", "waypmvhk_bactwp")
config = config.replace("username_here", "waypmvhk_bactusr")
config = config.replace("password_here", os.environ['WP_DB_PASSWORD'])

# The sample config has a block of 8 keys.
# We will find the start of AUTH_KEY and the end of NONCE_SALT
start_idx = config.find("define( 'AUTH_KEY'")
end_idx = config.find(");", config.find("define( 'NONCE_SALT'")) + 2

if start_idx != -1 and end_idx != -1:
    config = config[:start_idx] + salts + config[end_idx:]

with open('wp-config.php', 'w') as f:
    f.write(config)

print("wp-config.php generated successfully.")
