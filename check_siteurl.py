import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
require_once('staging/wp-load.php');
echo "Site URL: " . get_option('siteurl') . "\\n";
echo "Home: " . get_option('home') . "\\n";
?>"""

with open('check_url.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('check_url.php', 'rb') as f:
        ftp.storbinary('STOR check_url.php', f)
        
    res = requests.get('https://bactiveph.com/check_url.php')
    print(res.text)
    
    ftp.delete('check_url.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
