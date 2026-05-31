import ftplib
import os

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')

with open('root_htaccess.txt', 'wb') as f:
    ftp.retrbinary('RETR .htaccess', f.write)

ftp.quit()
