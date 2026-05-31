import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php ../wp-cli.phar post meta get 36 _visibility 2>&1');
echo shell_exec('cd staging && php ../wp-cli.phar post get 36 --fields=post_name,post_status,post_password 2>&1');
?>"""

with open('check_visibility.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('check_visibility.php', 'rb') as f:
        ftp.storbinary('STOR check_visibility.php', f)
        
    res = requests.get('https://bactiveph.com/check_visibility.php', verify=False)
    print(res.text)
    
    ftp.delete('check_visibility.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
