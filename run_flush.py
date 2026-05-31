import ftplib
import requests

php_script = """<?php
require_once('staging/wp-load.php');
delete_option('rewrite_rules');
flush_rewrite_rules(true);
echo "Flushed rewrite rules!\\n";
?>"""

with open('flush.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('flush.php', 'rb') as f:
        ftp.storbinary('STOR flush.php', f)
        
    res = requests.get('https://bactiveph.com/flush.php', verify=False)
    print(res.text)
    
    ftp.delete('flush.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
