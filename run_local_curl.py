import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && wp post list --post_type=product --post_status=publish --fields=ID,post_title,post_status --format=table 2>&1');
echo "\\n=== CURL DRESS ===\\n";
echo shell_exec('curl -s -u \\'bactive_team:BActive_Stg_2026!\\' http://127.0.0.1/product/the-court-dress/ -H "Host: staging.bactiveph.com" | grep -i -E "<title>|product_title|404|not found" 2>&1');
echo "\\n=== CURL SKORT ===\\n";
echo shell_exec('curl -s -u \\'bactive_team:BActive_Stg_2026!\\' http://127.0.0.1/product/the-pleated-skort/ -H "Host: staging.bactiveph.com" | grep -i -E "<title>|product_title|404|not found" 2>&1');
echo "\\n=== CURL SET ===\\n";
echo shell_exec('curl -s -u \\'bactive_team:BActive_Stg_2026!\\' http://127.0.0.1/product/the-halter-set/ -H "Host: staging.bactiveph.com" | grep -i -E "<title>|product_title|404|not found" 2>&1');
?>"""

with open('test_curl.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('test_curl.php', 'rb') as f:
        ftp.storbinary('STOR test_curl.php', f)
        
    print("Executing script...")
    res = requests.get('https://bactiveph.com/test_curl.php')
    print(res.text)
    
    ftp.delete('test_curl.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
