import env_loader  # loads .env
import os
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('wordpress/wp-config-sample.php', 'rb') as f:
        ftp.storbinary('STOR wp-config-sample.php', f)
    print("Upload successful")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
