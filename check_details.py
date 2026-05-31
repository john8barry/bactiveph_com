import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php wp-cli.phar post get 83 --field=post_excerpt 2>&1');
echo "\\n---FEATURES---\\n";
echo shell_exec('cd staging && php wp-cli.phar wc product get 83 --format=json 2>&1');
echo "\\n---TEST PRODUCT---\\n";
echo shell_exec('cd staging && php wp-cli.phar post list --post_type=product --name="Test Product" --format=json 2>&1');
?>"""

with open('check_details_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('check_details_root.php', 'rb') as f:
        ftp.storbinary('STOR check_details_root.php', f)
    
    response = requests.get('https://bactiveph.com/check_details_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('check_details_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
