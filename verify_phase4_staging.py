import os
import env_loader  # loads .env into os.environ
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php wp-cli.phar wc product create --name="Sample Dress" --type="variable" --status="publish" --regular_price="1500" --user="1" 2>&1');
// Creating global attributes via WP-CLI is tricky, doing a quick hack with wc api or just creating simple product to test tabs
echo shell_exec('cd staging && php wp-cli.phar wc product update 1 --short_description="This is the short description that goes in the Features & Fit tab." 2>&1');
echo "Verification setup done on staging.";
?>"""

with open('verify_phase4.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('verify_phase4.php', 'rb') as f:
        ftp.storbinary('STOR verify_phase4.php', f)
    
    response = requests.get('https://bactiveph.com/verify_phase4.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('verify_phase4.php')
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
