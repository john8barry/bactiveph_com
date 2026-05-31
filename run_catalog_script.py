import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php setup_catalog.php 2>&1');
echo "Done running setup_catalog via CLI";
?>"""

with open('run_catalog.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('run_catalog.php', 'rb') as f:
        ftp.storbinary('STOR run_catalog.php', f)
    
    # Increase timeout since creating products takes time
    response = requests.get('https://bactiveph.com/run_catalog.php', timeout=300)
    print("PHP Output:\n", response.text)
    
    ftp.delete('run_catalog.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
