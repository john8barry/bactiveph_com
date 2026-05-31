import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php ../wp-cli.phar litespeed-purge all 2>&1');
echo shell_exec('cd staging && php ../wp-cli.phar litespeed-online 2>&1');
?>"""

with open('purge.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('purge.php', 'rb') as f:
        ftp.storbinary('STOR purge.php', f)
        
    res = requests.get('https://bactiveph.com/purge.php', verify=False)
    print(res.text)
    
    ftp.delete('purge.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
