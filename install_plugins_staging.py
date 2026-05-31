import os
import env_loader  # loads .env into os.environ
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php wp-cli.phar plugin install woo-variation-swatches --activate 2>&1');
echo shell_exec('cd staging && php wp-cli.phar plugin install filter-everything --activate 2>&1');
echo "Plugins installed on staging.";
?>"""

with open('install_plugins.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('install_plugins.php', 'rb') as f:
        ftp.storbinary('STOR install_plugins.php', f)
    
    response = requests.get('https://bactiveph.com/install_plugins.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('install_plugins.php')
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
