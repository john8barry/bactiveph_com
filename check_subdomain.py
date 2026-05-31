import env_loader
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

def cpanel_req(module, function, params=None):
    url = f"{base_url}/{module}/{function}"
    res = requests.get(url, headers=headers, params=params or {})
    print(res.json())

cpanel_req("SubDomain", "listsubdomains")
