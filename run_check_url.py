import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php ../wp-cli.phar post list --post_type=product --name=the-court-dress --fields=url 2>&1');
?>"""

with open('check_url.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('check_url.php', 'rb') as f:
        ftp.storbinary('STOR check_url.php', f)
        
    res = requests.get('https://bactiveph.com/check_url.php', verify=False)
    print(res.text)
    
    ftp.delete('check_url.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
