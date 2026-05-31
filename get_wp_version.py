import env_loader  # auto-loads .env into os.environ
import ftplib
import os

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('version.php', 'wb') as f:
        ftp.retrbinary('RETR wp-includes/version.php', f.write)
        
    print("Downloaded version.php")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()

# Read version.php
with open('version.php', 'r') as f:
    lines = f.readlines()
    for line in lines:
        if '$wp_version =' in line:
            print(line.strip())
            break
