import env_loader  # loads .env
import ftplib
import os

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])

with open('root_htaccess.txt', 'wb') as f:
    ftp.retrbinary('RETR .htaccess', f.write)

ftp.quit()
