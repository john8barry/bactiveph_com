import env_loader  # loads .env
import os
import ftplib
import requests

php_script = """<?php
$htaccess = '/home/waypmvhk/public_html/staging/.htaccess';
$content = "# BEGIN WordPress\\n<IfModule mod_rewrite.c>\\nRewriteEngine On\\nRewriteBase /\\nRewriteRule ^index\\\\.php$ - [L]\\nRewriteCond %{REQUEST_FILENAME} !-f\\nRewriteCond %{REQUEST_FILENAME} !-d\\nRewriteRule . /index.php [L]\\n</IfModule>\\n# END WordPress\\n";

file_put_contents($htaccess, $content);
echo "Updated .htaccess\\n";
?>"""

with open('fix_htaccess.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('fix_htaccess.php', 'rb') as f:
        ftp.storbinary('STOR fix_htaccess.php', f)
    
    response = requests.get('https://bactiveph.com/fix_htaccess.php', verify=False)
    print(response.text)
    
    ftp.delete('fix_htaccess.php')
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove('fix_htaccess.php')
