import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php -r "require_once(\\'wp-load.php\\'); flush_rewrite_rules(); echo \\'Rewrite rules flushed!\\';" 2>&1');
?>"""

with open('flush_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('flush_root.php', 'rb') as f:
        ftp.storbinary('STOR flush_root.php', f)
    
    response = requests.get('https://bactiveph.com/flush_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('flush_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
