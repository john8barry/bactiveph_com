import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php get_pdp_data.php 2>&1');
?>"""

with open('get_pdp_data_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('get_pdp_data_root.php', 'rb') as f:
        ftp.storbinary('STOR get_pdp_data_root.php', f)
        
    print("Executing root script...")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()

response = requests.get('https://bactiveph.com/get_pdp_data_root.php', timeout=300)
print("PHP Output:\n", response.text)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    ftp.delete('get_pdp_data_root.php')
except Exception as e:
    pass
finally:
    ftp.quit()
