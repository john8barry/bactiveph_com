import env_loader  # loads .env
import ftplib
import os

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    with open('setup_catalog.php', 'rb') as f:
        print("Uploading setup_catalog.php...")
        ftp.storbinary('STOR staging/setup_catalog.php', f)
    print("Uploaded successfully")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
