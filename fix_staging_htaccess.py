import os
import env_loader  # loads .env
import ftplib
import re
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('staging_htaccess', 'wb') as f:
        ftp.retrbinary('RETR staging/.htaccess', f.write)
        
    with open('staging_htaccess', 'r') as f:
        content = f.read()
        
    # Fix RewriteBase
    content = re.sub(r"RewriteBase /[\r\n]", "RewriteBase /staging/\n", content)
    content = re.sub(r"RewriteRule \. /index\.php \[L\]", "RewriteRule . /staging/index.php [L]", content)
    
    # Remove Wordfence block
    content = re.sub(r"# Wordfence WAF.*?# END Wordfence WAF\n?", "", content, flags=re.DOTALL)
    
    with open('staging_htaccess', 'w') as f:
        f.write(content)
        
    with open('staging_htaccess', 'rb') as f:
        ftp.storbinary('STOR staging/.htaccess', f)
    
    print("Fixed staging .htaccess!")
    
    # Now run wp post list again by uploading a script
    php_script = """<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== WP POST LIST ===\\n";
echo shell_exec('php wp-cli.phar post list --post_type=product --post_status=publish --fields=ID,post_title,post_status --format=table 2>&1');
?>"""

    with open('prove_staging_cli.php', 'w') as f:
        f.write(php_script)
        
    with open('prove_staging_cli.php', 'rb') as f:
        ftp.storbinary('STOR staging/prove_staging_cli.php', f)
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()

res = requests.get('https://bactiveph.com/staging/prove_staging_cli.php', verify=False)
print(res.text)

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
ftp.delete('staging/prove_staging_cli.php')
ftp.quit()
