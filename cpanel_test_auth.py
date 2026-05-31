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

php_code = """<?php
require_once('wp-load.php');

$user = wp_authenticate('bactive_support', 'w4NHlX@ek9JocATz');
if ( is_wp_error( $user ) ) {
    echo "Auth error: " . $user->get_error_message();
} else {
    echo "Auth success for " . $user->user_login;
}
"""

res = requests.post(f"{base_url}/Fileman/save_file_content", headers=headers, data={
    "dir": "bactiveph.com",
    "file": "test_auth.php",
    "content": php_code
})

