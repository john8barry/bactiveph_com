import env_loader  # loads .env
import os
import ftplib
import requests

php_script = """<?php
require('/home/waypmvhk/public_html/staging/wp-load.php');

update_option('siteurl', 'https://staging.bactiveph.com');
update_option('home', 'https://staging.bactiveph.com');

echo "Site URL is now: " . get_option('siteurl') . "\\n";
echo "Home is now: " . get_option('home') . "\\n";
?>"""

with open('db_fix.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('db_fix.php', 'rb') as f:
        ftp.storbinary('STOR db_fix.php', f)
    
    response = requests.get('https://bactiveph.com/db_fix.php', verify=False)
    print("--- SERVER RESPONSE ---")
    print(response.text)
    
    ftp.delete('db_fix.php')
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove('db_fix.php')
