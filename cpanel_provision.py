import env_loader  # auto-loads .env into os.environ
import os
import requests
from dotenv import load_dotenv

load_dotenv()

CPANEL_HOST = "premium343.web-hosting.com"
CPANEL_USER = os.environ.get('CPANEL_USER')
CPANEL_TOKEN = os.environ.get('CPANEL_API_TOKEN')

headers = {
    'Authorization': f'cpanel {CPANEL_USER}:{CPANEL_TOKEN}'
}
base_url = f"https://{CPANEL_HOST}:2083/execute"

def cpanel_req(module, function, params):
    url = f"{base_url}/{module}/{function}"
    res = requests.get(url, headers=headers, params=params)
    print(f"--- {module}::{function} ---")
    print(res.json())
    return res.json()

# 1. Create Subdomain
cpanel_req("SubDomain", "addsubdomain", {
    "domain": "staging",
    "rootdomain": "bactiveph.com",
    "dir": "public_html/staging"
})

# 2. Create Database
cpanel_req("Mysql", "create_database", {
    "name": f"{CPANEL_USER}_stg"
})

# 3. Create Database User
db_pass = os.environ.get('STAGING_DB_PASSWORD','')
cpanel_req("Mysql", "create_user", {
    "name": f"{CPANEL_USER}_stg",
    "password": db_pass
})

# 4. Grant Privileges
cpanel_req("Mysql", "set_privileges_on_database", {
    "user": f"{CPANEL_USER}_stg",
    "database": f"{CPANEL_USER}_stg",
    "privileges": "ALL PRIVILEGES"
})

# Save DB info to a file we can use in PHP later
with open('stg_db_info.txt', 'w') as f:
    f.write(f"DB_NAME={CPANEL_USER}_stg\n")
    f.write(f"DB_USER={CPANEL_USER}_stg\n")
    f.write(f"DB_PASS={db_pass}\n")

print("cPanel Provisioning Complete.")
