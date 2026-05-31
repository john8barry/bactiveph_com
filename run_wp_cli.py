import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php wp-cli.phar post list --post_type=product --format=json 2>&1');
?>"""

with open('get_products_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('get_products_root.php', 'rb') as f:
        ftp.storbinary('STOR get_products_root.php', f)
    
    response = requests.get('https://bactiveph.com/get_products_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('get_products_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
