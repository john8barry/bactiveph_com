import ftplib
import requests

php_script = """<?php
require_once('staging/wp-load.php');
update_option('siteurl', 'https://bactiveph.com/staging');
update_option('home', 'https://bactiveph.com/staging');
flush_rewrite_rules();
echo "Updated siteurl to https://bactiveph.com/staging and flushed rules!\\n";
?>"""

with open('fix_staging_url.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('fix_staging_url.php', 'rb') as f:
        ftp.storbinary('STOR fix_staging_url.php', f)
        
    res = requests.get('https://bactiveph.com/fix_staging_url.php', verify=False)
    print(res.text)
    
    ftp.delete('fix_staging_url.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
