import os
import env_loader  # loads .env
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    files = ftp.nlst('staging/')
    if 'staging/index.php' in files:
        print("staging/index.php EXISTS")
    else:
        print("staging/index.php IS MISSING!")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
