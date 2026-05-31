import ftplib
import requests

php_script = """<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== WP POST LIST ===\\n";
echo shell_exec('php wp-cli.phar post list --post_type=product --post_status=publish --fields=ID,post_title,post_status --format=table 2>&1');
?>"""

with open('prove_staging_cli.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('prove_staging_cli.php', 'rb') as f:
        ftp.storbinary('STOR staging/prove_staging_cli.php', f)
        
    res = requests.get('https://bactiveph.com/staging/prove_staging_cli.php', verify=False)
    print(res.text)
    
    ftp.delete('staging/prove_staging_cli.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
