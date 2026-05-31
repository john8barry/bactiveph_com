import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== WP CORE VERIFY CHECKSUMS ===\\n";
echo shell_exec('php wp-cli.phar core verify-checksums 2>&1');
?>"""

with open('verify_prod.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('verify_prod.php', 'rb') as f:
        ftp.storbinary('STOR verify_prod.php', f)
        
    res = requests.get('https://bactiveph.com/verify_prod.php', verify=False)
    print(res.text)
    
    ftp.delete('verify_prod.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
