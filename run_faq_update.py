import os
import env_loader
import ftplib
import requests

with open('run_update_faq.php', 'w') as f:
    f.write("<?php\necho shell_exec('cd staging && php update_faq.php 2>&1');\n?>")

# Upload and execute
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('update_faq.php', 'rb') as f:
        ftp.storbinary('STOR staging/update_faq.php', f)
    with open('run_update_faq.php', 'rb') as f:
        ftp.storbinary('STOR run_update_faq.php', f)
    print("Files uploaded.")
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()

print("Executing script...")
response = requests.get('https://bactiveph.com/run_update_faq.php', timeout=300)
print("Output:", response.text)

# Cleanup
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    ftp.delete('staging/update_faq.php')
    ftp.delete('run_update_faq.php')
    print("Cleanup done.")
except Exception as e:
    print("Cleanup error:", e)
finally:
    ftp.quit()
