import ftplib
import requests
import json
import os
from dotenv import load_dotenv

load_dotenv()

# FTP Audit
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    # Upload and run audit script
    with open('wp_audit.php', 'rb') as f:
        ftp.storbinary('STOR wp_audit.php', f)
        
    response = requests.get('https://bactiveph.com/wp_audit.php')
    print("--- Audit Script Response ---")
    print(response.text)
    
    # Check for leftover scripts
    print("\n--- Remaining PHP Files in Root ---")
    files = ftp.nlst()
    leftovers = [f for f in files if f.endswith('.php') and f not in ['wp-load.php', 'wp-config.php', 'wp-settings.php', 'wp-blog-header.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-login.php', 'wp-mail.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'index.php', 'extract.php', 'recover.php']]
    for f in leftovers:
        print("Found:", f)
        ftp.delete(f)
        print("Deleted:", f)
        
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()

# Cloudflare Cache Purge
API_TOKEN = os.environ.get("CLOUDFLARE_API_TOKEN", "")
zone_id = "71ba04a85c07a74049896f0f1580469f"
headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}
cf_res = requests.post(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache", headers=headers, json={"purge_everything": True})
print("\n--- Cloudflare Cache Purge ---")
print(cf_res.json())
