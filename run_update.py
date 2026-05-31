import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php setup_catalog_internal.php 2>&1');
?>"""

with open('setup_catalog_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('setup_catalog_internal.php', 'rb') as f:
        ftp.storbinary('STOR staging/setup_catalog_internal.php', f)
    with open('setup_catalog_root.php', 'rb') as f:
        ftp.storbinary('STOR setup_catalog_root.php', f)
        
    print("Executing update script...")
    response = requests.get('https://bactiveph.com/setup_catalog_root.php', timeout=300)
    print("Output:", response.text)
    
    ftp.delete('setup_catalog_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
