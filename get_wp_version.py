import ftplib
import os

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
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
