import ftplib
import requests

php_script = """<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== PERMALINK STRUCTURE ===\\n";
echo shell_exec('cd staging && php ../wp-cli.phar option get permalink_structure 2>&1');
echo "\\n=== WOOCOMMERCE PERMALINKS ===\\n";
echo shell_exec('cd staging && php ../wp-cli.phar option get woocommerce_permalinks 2>&1');
?>"""

with open('check_permalinks.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('check_permalinks.php', 'rb') as f:
        ftp.storbinary('STOR check_permalinks.php', f)
        
    res = requests.get('https://bactiveph.com/check_permalinks.php', verify=False)
    print(res.text)
    
    ftp.delete('check_permalinks.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
